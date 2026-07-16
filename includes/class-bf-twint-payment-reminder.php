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
	 * Obergrenze versendeter Mails pro Cron-Lauf.
	 */
	const BATCH_SIZE = 20;

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
	 * Beim Deaktivieren des Plugins das Cron-Event entfernen.
	 *
	 * @return void
	 */
	public static function unschedule() {
		$scheduled = wp_next_scheduled( self::CRON_HOOK );
		if ( $scheduled ) {
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

		// Kandidaten im Fenster [Frist + Nachlauf, Frist] – ältere fallen bewusst raus.
		// Bereits erinnerte Bestellungen und Kundenmeldungen «Ich habe bezahlt»
		// werden gar nicht erst geladen: Würden sie erst in der Schleife
		// übersprungen, könnten sie den Stapel belegen und neuere Bestellungen
		// blieben unerinnert, bis sie aus dem Fenster fallen.
		$orders = wc_get_orders(
			array(
				'payment_method' => BF_TWINT_GATEWAY_ID,
				'status'         => array( 'on-hold', 'pending' ),
				'date_created'   => ( time() - ( $days + self::WINDOW_DAYS ) * DAY_IN_SECONDS ) . '...' . ( time() - $days * DAY_IN_SECONDS ),
				'limit'          => 100,
				'orderby'        => 'date',
				'order'          => 'ASC',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'AND',
					array(
						'key'     => '_bf_twint_reminder_sent',
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => '_bf_twint_paid_claimed',
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		$sent = 0;
		foreach ( $orders as $order ) {
			if ( $sent >= self::BATCH_SIZE ) {
				break;
			}
			// Sicherheitsnetz: nur einmal erinnern, und nie, wenn der Kunde
			// bereits «bezahlt» gemeldet hat.
			if ( absint( $order->get_meta( '_bf_twint_reminder_sent' ) ) || absint( $order->get_meta( '_bf_twint_paid_claimed' ) ) ) {
				continue;
			}
			if ( self::send_reminder( $order ) ) {
				++$sent;
			}
		}
	}

	/**
	 * Erinnerungs-Mail für eine Bestellung versenden und protokollieren.
	 *
	 * @param WC_Order $order Bestellung.
	 * @return bool Ob die Mail übergeben wurde.
	 */
	private static function send_reminder( $order ) {
		$to = $order->get_billing_email();
		if ( ! $to ) {
			return false;
		}

		$gateways = WC()->payment_gateways() ? WC()->payment_gateways->payment_gateways() : array();
		$gateway  = isset( $gateways[ BF_TWINT_GATEWAY_ID ] ) ? $gateways[ BF_TWINT_GATEWAY_ID ] : null;
		if ( ! $gateway ) {
			return false;
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
			return false;
		}

		$order->update_meta_data( '_bf_twint_reminder_sent', time() );
		$order->add_order_note(
			sprintf(
				/* translators: %s: customer email address. */
				__( 'TWINT: payment reminder sent to %s.', 'blueforce-manual-payments-for-twint' ),
				$to
			),
			false
		);
		$order->save();

		return true;
	}
}
