<?php
/**
 * Einmalige Zahlungserinnerung für unbezahlte TWINT-Bestellungen.
 *
 * Löst das häufigste Alltagsproblem des manuellen Verfahrens: Der Kunde
 * bestellt und vergisst zu zahlen. Nach der konfigurierten Frist
 * (Gateway-Einstellung reminder_days; leer/0 = deaktiviert) erhält er einmalig
 * eine E-Mail mit denselben Zahlungsangaben wie in der Bestellbestätigung
 * (Nummer/QR/Referenz). Kunden, die bereits «Ich habe bezahlt» gemeldet haben,
 * werden nicht erinnert.
 *
 * @package Blueforce_Manual_Payments_For_TWINT
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plant das Cron-Event selbstheilend und versendet fällige Erinnerungen.
 */
final class BF_TWINT_Payment_Reminder {

	/**
	 * Hook-Name des Cron-Events.
	 */
	const CRON_HOOK = 'bf_twint_send_payment_reminders';

	/**
	 * Nachlauf-Fenster in Tagen: Bestellungen, die älter als Frist + Fenster
	 * sind, werden nicht mehr erinnert (hält die Kandidatenmenge klein und
	 * verhindert Erinnerungen an längst aufgegebene Bestellungen).
	 */
	const WINDOW_DAYS = 30;

	/**
	 * Obergrenze der Mail-Übergaben pro Cron-Lauf, gelungen oder nicht.
	 *
	 * Zählt jeden Versuch, nicht nur die gelungenen: Verweigert der Mailversand
	 * wiederholt, liefe ein Lauf sonst durch alle Kandidaten, bei langsamer
	 * Mailverbindung bis zum Abbruch.
	 */
	const BATCH_SIZE = 20;

	/**
	 * Grösse einer Kandidaten-Seite.
	 *
	 * Bewusst getrennt von BATCH_SIZE: Bereits erinnerte Bestellungen bleiben bis
	 * zum Ende des Fensters Treffer der Abfrage. Sie dürfen den Stapel nicht
	 * belegen, darum blättert send_due_reminders() weiter.
	 */
	const PAGE_SIZE = 100;

	/**
	 * Obergrenze der Seiten pro Lauf (verhindert Timeouts bei grossen Rückständen).
	 */
	const MAX_PAGES = 10;

	/**
	 * Hooks registrieren.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_schedule' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'send_due_reminders' ) );
	}

	/**
	 * Konfigurierte Frist in Tagen (0 = deaktiviert).
	 *
	 * @return int
	 */
	public static function get_days() {
		$settings = get_option( 'woocommerce_' . BF_TWINT_GATEWAY_ID . '_settings', array() );
		return isset( $settings['reminder_days'] ) ? absint( $settings['reminder_days'] ) : 0;
	}

	/**
	 * Cron-Event selbstheilend planen bzw. entfernen (analog Auto-Cancel).
	 *
	 * @return void
	 */
	public static function maybe_schedule() {
		$scheduled = wp_next_scheduled( self::CRON_HOOK );

		if ( self::get_days() > 0 ) {
			if ( ! $scheduled ) {
				wp_schedule_event( time(), 'twicedaily', self::CRON_HOOK );
			}
		} elseif ( $scheduled ) {
			wp_unschedule_event( $scheduled, self::CRON_HOOK );
		}
	}

	/**
	 * Fällige Erinnerungen versenden (Cron-Callback).
	 *
	 * @return void
	 */
	public static function send_due_reminders() {
		$days = self::get_days();
		if ( $days < 1 ) {
			return;
		}

		// Kandidaten im Fenster [Frist + Nachlauf, Frist], ältere fallen bewusst raus.
		//
		// Gefiltert wird in PHP, nicht per «meta_query»: Die klassische Bestelltabelle
		// (Shops ohne HPOS) unterstützt das Argument in wc_get_orders() nicht.
		// WooCommerce verwirft es dort stillschweigend und meldet zusätzlich
		// «doing it wrong», und das bei jedem Cron-Lauf.
		//
		// Neueste zuerst: Frisch fällig gewordene Bestellungen stehen so immer auf
		// der ersten Seite. Bereits erinnerte sind älter und bleiben bis zum Ende
		// des Fensters Treffer. Aufsteigend sortiert konnten mehr als
		// MAX_PAGES × PAGE_SIZE davon die neuen bei jedem Lauf verdecken.
		$args = array(
			'payment_method' => BF_TWINT_GATEWAY_ID,
			'status'         => array( 'on-hold', 'pending' ),
			'date_created'   => ( time() - ( $days + self::WINDOW_DAYS ) * DAY_IN_SECONDS ) . '...' . ( time() - $days * DAY_IN_SECONDS ),
			'limit'          => self::PAGE_SIZE,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		// Gateway einmal pro Lauf holen. Fehlt es, kann keine Erinnerung die
		// Zahlungsangaben enthalten, dann gar nicht erst durch die Kandidaten gehen.
		$gateways = WC()->payment_gateways() ? WC()->payment_gateways->payment_gateways() : array();
		$gateway  = isset( $gateways[ BF_TWINT_GATEWAY_ID ] ) ? $gateways[ BF_TWINT_GATEWAY_ID ] : null;
		if ( ! $gateway ) {
			if ( function_exists( 'wc_get_logger' ) ) {
				wc_get_logger()->warning( 'Reminder run skipped: the TWINT payment method is not available.', array( 'source' => 'bf-twint' ) );
			}
			return;
		}

		$sent       = 0;
		$failed     = 0;
		$attempts   = 0;
		$candidates = 0;

		for ( $page = 1; $page <= self::MAX_PAGES; $page++ ) {
			// Blättern über «page» ist hier gefahrlos: Eine versendete Erinnerung
			// setzt nur Meta, die Treffermenge der Abfrage verschiebt sich dadurch
			// nicht. Beide Bestelltabellen kennen «page».
			$orders = wc_get_orders( array_merge( $args, array( 'page' => $page ) ) );
			if ( empty( $orders ) ) {
				break;
			}
			$candidates += count( $orders );

			foreach ( $orders as $order ) {
				// Nur einmal erinnern, und nie, wenn der Kunde bereits
				// «bezahlt» gemeldet hat.
				if ( self::is_done( $order ) ) {
					continue;
				}

				// Unmittelbar vor dem Versand frisch lesen: Hat der Shop den
				// Zahlungseingang inzwischen bestätigt oder der Kunde «bezahlt»
				// gemeldet, geht keine Erinnerung mehr raus.
				$current = BF_TWINT_Gateway::fresh_order( $order->get_id() );
				if ( ! $current
					|| ! $current->has_status( array( 'on-hold', 'pending' ) )
					|| BF_TWINT_GATEWAY_ID !== $current->get_payment_method()
					|| self::is_done( $current )
				) {
					continue;
				}

				$result = self::send_reminder( $current, $gateway );
				if ( 'sent' === $result ) {
					++$sent;
				} else {
					++$failed;
				}
				// Eine Bestellung ohne Adresse kostet keinen Versuch beim Mailversand.
				if ( 'no_address' !== $result ) {
					++$attempts;
				}
				if ( $attempts >= self::BATCH_SIZE ) {
					break 2;
				}
			}

			// Letzte Seite erreicht.
			if ( count( $orders ) < self::PAGE_SIZE ) {
				break;
			}
		}

		// Lauf-Protokoll unter WooCommerce → Status → Protokolle (Quelle bf-twint).
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->info(
				sprintf( 'Reminder run: %d candidates, %d reminders sent, %d not sent (no e-mail address or mailer refused).', $candidates, $sent, $failed ),
				array( 'source' => 'bf-twint' )
			);
		}
	}

	/**
	 * Ob eine Bestellung keine Erinnerung (mehr) braucht.
	 *
	 * Nur einmal erinnern, und nie, wenn der Kunde bereits «bezahlt» gemeldet hat.
	 *
	 * @param WC_Order $order Bestellung.
	 * @return bool
	 */
	private static function is_done( $order ) {
		return absint( $order->get_meta( '_bf_twint_reminder_sent' ) ) || absint( $order->get_meta( '_bf_twint_paid_claimed' ) );
	}

	/**
	 * Erinnerungs-Mail für eine Bestellung versenden und protokollieren.
	 *
	 * @param WC_Order           $order   Bestellung.
	 * @param WC_Payment_Gateway $gateway TWINT-Gateway.
	 * @return string «sent», «failed» (Mailversand hat abgelehnt) oder «no_address».
	 */
	private static function send_reminder( $order, $gateway ) {
		$to = $order->get_billing_email();
		if ( ! $to ) {
			return 'no_address';
		}

		$mailer  = WC()->mailer();
		$heading = __( 'Payment reminder', 'blueforce-manual-payments-for-twint' );
		$subject = sprintf(
			/* translators: %s: order number. */
			__( 'Payment reminder for your order %s', 'blueforce-manual-payments-for-twint' ),
			'#' . $order->get_order_number()
		);

		$intro = '<p>' . sprintf(
			/* translators: %s: order number. */
			esc_html__( 'We have not yet received the TWINT payment for your order %s. Here are the payment details again:', 'blueforce-manual-payments-for-twint' ),
			'<strong>#' . esc_html( $order->get_order_number() ) . '</strong>'
		) . '</p>';

		$message = $intro . $gateway->get_payment_details_html( $order );
		$sent    = $mailer->send( $to, $subject, $mailer->wrap_message( $heading, $message ) );

		// Nur bei erfolgreicher Übergabe als «erinnert» markieren – sonst bekommt
		// die Bestellung beim nächsten Cron-Lauf einen neuen Versuch (innerhalb
		// des Kandidaten-Fensters), statt fälschlich übersprungen zu werden.
		if ( ! $sent ) {
			return 'failed';
		}

		// Adresse in der Notiz nur maskiert: Notizen überleben die
		// Datenschutz-Löschung, die Rechnungsadresse der Bestellung nicht.
		$order->update_meta_data( '_bf_twint_reminder_sent', time() );
		$order->add_order_note(
			sprintf(
				/* translators: %s: customer email address. */
				__( 'TWINT: payment reminder sent to %s.', 'blueforce-manual-payments-for-twint' ),
				BF_TWINT_Gateway::mask_email( $to )
			),
			false
		);
		$order->save();

		return 'sent';
	}
}
