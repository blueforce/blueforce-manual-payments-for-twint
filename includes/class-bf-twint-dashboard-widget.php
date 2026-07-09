<?php
/**
 * Dashboard-Widget «TWINT-Zahlungen»: Handlungsbedarf auf einen Blick.
 *
 * Zeigt nach dem Login, ob Abgleich ansteht – offene Zahlungen (Anzahl, Summe,
 * davon vom Kunden als bezahlt gemeldet, Alter der ältesten) mit Link auf die
 * Zahlungsübersicht, plus eine Kontext-Zeile zu den erhaltenen Zahlungen der
 * letzten 30 Tage. Erscheint nur, wenn das Gateway aktiviert ist und der
 * Nutzer Bestellungen verwalten darf.
 *
 * @package Blueforce_Manual_Payments_For_TWINT
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registriert und rendert das Dashboard-Widget.
 */
final class BF_TWINT_Dashboard_Widget {

	/**
	 * Obergrenze der betrachteten offenen Bestellungen (wie Zahlungsübersicht).
	 */
	const MAX_ORDERS = 250;

	/**
	 * Hooks registrieren.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'register_widget' ) );
	}

	/**
	 * Widget anlegen – nur bei aktiviertem Gateway und passender Berechtigung.
	 *
	 * @return void
	 */
	public static function register_widget() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$settings = get_option( 'woocommerce_' . BF_TWINT_GATEWAY_ID . '_settings', array() );
		if ( empty( $settings['enabled'] ) || 'yes' !== $settings['enabled'] ) {
			return;
		}

		wp_add_dashboard_widget(
			'bf_twint_dashboard',
			__( 'TWINT payments', 'blueforce-manual-payments-for-twint' ),
			array( __CLASS__, 'render_widget' )
		);
	}

	/**
	 * Widget-Inhalt rendern.
	 *
	 * @return void
	 */
	public static function render_widget() {
		$open = wc_get_orders(
			array(
				'payment_method' => BF_TWINT_GATEWAY_ID,
				'status'         => array( 'on-hold', 'pending' ),
				'limit'          => self::MAX_ORDERS,
				'orderby'        => 'date',
				'order'          => 'ASC',
			)
		);

		if ( empty( $open ) ) {
			echo '<p>' . esc_html__( 'No open TWINT payments – everything is settled.', 'blueforce-manual-payments-for-twint' ) . '</p>';
		} else {
			$total   = 0.0;
			$claimed = 0;
			foreach ( $open as $order ) {
				$total += (float) $order->get_total();
				if ( absint( $order->get_meta( '_bf_twint_paid_claimed' ) ) ) {
					++$claimed;
				}
			}

			echo '<p><strong>' . wp_kses_post(
				sprintf(
					/* translators: 1: number of open payments, 2: formatted total amount. */
					__( 'Open payments: %1$s (%2$s in total)', 'blueforce-manual-payments-for-twint' ),
					number_format_i18n( count( $open ) ),
					wc_price( $total )
				)
			) . '</strong></p>';

			if ( $claimed > 0 ) {
				echo '<p>' . esc_html(
					sprintf(
						/* translators: %s: number of orders the customer reported as paid. */
						__( 'Reported as paid by the customer: %s', 'blueforce-manual-payments-for-twint' ),
						number_format_i18n( $claimed )
					)
				) . '</p>';
			}

			$created = $open[0]->get_date_created();
			if ( $created ) {
				echo '<p>' . esc_html(
					sprintf(
						/* translators: %s: human-readable age of the oldest open payment (e.g. "3 days ago"). */
						__( 'Oldest open payment: %s', 'blueforce-manual-payments-for-twint' ),
						sprintf(
							/* translators: %s: human-readable time difference (e.g. "3 days"). */
							__( '%s ago', 'blueforce-manual-payments-for-twint' ),
							human_time_diff( $created->getTimestamp() )
						)
					)
				) . '</p>';
			}
		}

		// Kontext: erhaltene TWINT-Zahlungen der letzten 30 Tage.
		$received = wc_get_orders(
			array(
				'payment_method' => BF_TWINT_GATEWAY_ID,
				'status'         => array( 'processing', 'completed' ),
				'date_paid'      => '>' . ( time() - 30 * DAY_IN_SECONDS ),
				'return'         => 'ids',
				'limit'          => -1,
			)
		);
		echo '<p class="description">' . esc_html(
			sprintf(
				/* translators: %s: number of payments received in the last 30 days. */
				__( 'Payments received in the last 30 days: %s', 'blueforce-manual-payments-for-twint' ),
				number_format_i18n( count( $received ) )
			)
		) . '</p>';

		echo '<p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=' . BF_TWINT_Payments_Page::PAGE_SLUG ) ) . '">' . esc_html__( 'Go to the payments overview', 'blueforce-manual-payments-for-twint' ) . '</a></p>';
	}
}
