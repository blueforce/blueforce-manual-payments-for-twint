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
	 * Überfällige unbezahlte TWINT-Bestellungen stornieren (Cron-Callback).
	 *
	 * @return void
	 */
	public static function cancel_overdue_orders() {
		$days = self::get_days();
		if ( $days < 1 ) {
			return;
		}

		$orders = wc_get_orders(
			array(
				'payment_method' => BF_TWINT_GATEWAY_ID,
				'status'         => array( 'on-hold', 'pending' ),
				'date_created'   => '<' . ( time() - $days * DAY_IN_SECONDS ),
				'limit'          => self::BATCH_SIZE,
				'orderby'        => 'date',
				'order'          => 'ASC',
				// Bestellungen mit Kundenmeldung «Ich habe bezahlt» gar nicht
				// erst laden. Würden sie erst in der Schleife übersprungen,
				// könnten sie als älteste Bestellungen den ganzen Stapel
				// belegen und die Stornierung dauerhaft blockieren.
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => '_bf_twint_paid_claimed',
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		foreach ( $orders as $order ) {
			// Sicherheitsnetz: Hat der Kunde «Ich habe bezahlt» gemeldet, ist
			// manuelles Prüfen gefragt, nie automatisch stornieren. Sonst wird
			// eine womöglich bezahlte Bestellung storniert, bevor der Shop
			// abgleichen konnte.
			if ( absint( $order->get_meta( '_bf_twint_paid_claimed' ) ) ) {
				continue;
			}

			$order->update_status(
				'cancelled',
				sprintf(
					/* translators: %d: number of days without payment. */
					__( 'TWINT: automatically cancelled – no payment was received within %d days.', 'blueforce-manual-payments-for-twint' ),
					$days
				)
			);
		}
	}
}
