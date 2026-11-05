<?php
/**
 * Automatisches Stornieren unbezahlter TWINT-Bestellungen nach Frist.
 *
 * WooCommerce räumt nur «pending»-Bestellungen automatisch ab; manuelle
 * TWINT-Bestellungen stehen aber auf «on-hold» – ohne diese Klasse bliebe
 * reservierte Ware bei Nichtzahlern unbegrenzt blockiert. Die Frist ist eine
 * Gateway-Einstellung (auto_cancel_days); leer/0 = deaktiviert (Standard).
 * Beim Statuswechsel auf «cancelled» bucht WooCommerce den Lagerbestand
 * selbst zurück (wc_maybe_increase_stock_levels).
 *
 * @package Blueforce_Manual_Payments_For_TWINT
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plant das Cron-Event selbstheilend und storniert überfällige Bestellungen.
 */
final class BF_TWINT_Auto_Cancel {

	/**
	 * Hook-Name des Cron-Events.
	 */
	const CRON_HOOK = 'bf_twint_cancel_unpaid_orders';

	/**
	 * Obergrenze pro Cron-Lauf (verhindert Timeouts bei grossen Rückständen).
	 */
	const BATCH_SIZE = 50;

	/**
	 * Grösse einer Kandidaten-Seite.
	 */
	const PAGE_SIZE = 50;

	/**
	 * Obergrenze der Seiten pro Lauf.
	 *
	 * Bestellungen mit frischer Kundenmeldung «Ich habe bezahlt» bleiben Treffer
	 * der Abfrage, bis ihre Kulanzfrist abgelaufen ist. Damit sie den Stapel nicht
	 * belegen, wird über sie hinweggeblättert.
	 */
	const MAX_PAGES = 10;

	/**
	 * Kulanzfrist in Tagen ab der Kundenmeldung «Ich habe bezahlt».
	 *
	 * Meldet der Kunde die Zahlung, wird die Stornierung nicht dauerhaft
	 * ausgesetzt, sondern nur um diese Frist verschoben, so hat der Shop Zeit
	 * zum Abgleich, ein bloss behaupteter Zahlungseingang blockiert den
	 * Lagerbestand aber nicht unbegrenzt. Über den Filter
	 * «bf_twint_claim_grace_days» anpassbar.
	 */
	const CLAIM_GRACE_DAYS = 3;

	/**
	 * Kulanzfrist ab Kundenmeldung in Tagen (mind. 0).
	 *
	 * @return int
	 */
	public static function get_claim_grace_days() {
		return max( 0, absint( apply_filters( 'bf_twint_claim_grace_days', self::CLAIM_GRACE_DAYS ) ) );
	}

	/**
	 * Hooks registrieren.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_schedule' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'cancel_overdue_orders' ) );
	}

	/**
	 * Konfigurierte Frist in Tagen (0 = deaktiviert).
	 *
	 * @return int
	 */
	public static function get_days() {
		$settings = get_option( 'woocommerce_' . BF_TWINT_GATEWAY_ID . '_settings', array() );
		return isset( $settings['auto_cancel_days'] ) ? absint( $settings['auto_cancel_days'] ) : 0;
	}

	/**
	 * Cron-Event selbstheilend planen bzw. entfernen.
	 *
	 * Läuft auf «init» und gleicht den Plan mit der Einstellung ab – so braucht
	 * es keinen Aktivierungs-Hook, und eine geänderte Frist greift von selbst.
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
	 * Überfällige unbezahlte TWINT-Bestellungen stornieren (Cron-Callback).
	 *
	 * @return void
	 */
	public static function cancel_overdue_orders() {
		$days = self::get_days();
		if ( $days < 1 ) {
			return;
		}

		$grace_cutoff = time() - self::get_claim_grace_days() * DAY_IN_SECONDS;

		// Gefiltert wird in PHP, nicht per «meta_query»: Die klassische Bestelltabelle
		// (Shops ohne HPOS) unterstützt das Argument in wc_get_orders() nicht.
		// WooCommerce verwirft es dort stillschweigend und meldet zusätzlich
		// «doing it wrong», und das bei jedem Cron-Lauf.
		$args = array(
			'payment_method' => BF_TWINT_GATEWAY_ID,
			'status'         => array( 'on-hold', 'pending' ),
			'date_created'   => '<' . ( time() - $days * DAY_IN_SECONDS ),
			'limit'          => self::PAGE_SIZE,
			'orderby'        => 'date',
			'order'          => 'ASC',
		);

		$cancelled  = array();
		$candidates = 0;
		$skipped    = 0;
		$changed    = 0;

		for ( $page = 1; $page <= self::MAX_PAGES; $page++ ) {
			// Geblättert wird über «offset» in Höhe der übersprungenen Bestellungen,
			// nicht über «page»: Eine stornierte Bestellung verlässt den Status
			// «on-hold/pending» und damit die Treffermenge. Mit Seitenzahlen würde
			// die Abfrage um genau diese Zahl weiterrutschen und Bestellungen
			// überspringen. Übrig bleiben vorne nur die übersprungenen.
			$orders = wc_get_orders( array_merge( $args, array( 'offset' => $skipped ) ) );
			if ( empty( $orders ) ) {
				break;
			}
			$candidates += count( $orders );

			foreach ( $orders as $order ) {
				// Unmittelbar vor dem Stornieren frisch lesen. Zwischen der Abfrage und
				// diesem Punkt können Minuten liegen, denn jede Stornierung verschickt
				// Mails und bucht Lager zurück. Hat der Shop inzwischen den
				// Zahlungseingang bestätigt, darf der Lauf den neueren Status nicht mit
				// «cancelled» überschreiben.
				$current = BF_TWINT_Gateway::fresh_order( $order->get_id() );
				if ( ! $current
					|| ! $current->has_status( array( 'on-hold', 'pending' ) )
					|| BF_TWINT_GATEWAY_ID !== $current->get_payment_method()
				) {
					// Hat die Treffermenge verlassen, «offset» bleibt darum unverändert.
					++$changed;
					continue;
				}

				// Sicherheitsnetz: Hat der Kunde «Ich habe bezahlt» gemeldet und liegt
				// die Meldung noch innerhalb der Kulanzfrist, nicht automatisch
				// stornieren, der Shop soll erst abgleichen können.
				$claimed = absint( $current->get_meta( '_bf_twint_paid_claimed' ) );
				if ( $claimed > 0 && $claimed > $grace_cutoff ) {
					++$skipped;
					continue;
				}

				$current->update_status(
					'cancelled',
					sprintf(
						/* translators: %d: number of days without payment. */
						__( 'TWINT: automatically cancelled – no payment was received within %d days.', 'blueforce-manual-payments-for-twint' ),
						$days
					)
				);
				$cancelled[] = '#' . $current->get_order_number();

				if ( count( $cancelled ) >= self::BATCH_SIZE ) {
					break 2;
				}
			}

			// Letzte Seite erreicht.
			if ( count( $orders ) < self::PAGE_SIZE ) {
				break;
			}
		}

		// Lauf-Protokoll unter WooCommerce → Status → Protokolle (Quelle bf-twint).
		// Beantwortet «läuft der Cron?» und «warum wurde Bestellung X storniert?»,
		// ohne dass jemand in die Datenbank muss.
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->info(
				sprintf(
					'Auto-cancel run: %d candidates older than %d days, %d cancelled%s, %d left alone because they changed during the run.',
					$candidates,
					$days,
					count( $cancelled ),
					$cancelled ? ' (' . implode( ', ', $cancelled ) . ')' : '',
					$changed
				),
				array( 'source' => 'bf-twint' )
			);
		}
	}
}
