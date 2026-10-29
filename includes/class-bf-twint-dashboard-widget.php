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
	 *
	 * Die Anzahl stammt nicht mehr aus dieser Teilmenge: Sie wird über «paginate»
	 * vollständig gezählt. Summe und Kundenmeldungen beziehen sich weiter auf die
	 * geladenen Bestellungen und werden entsprechend ausgewiesen, sobald die
	 * Grenze greift.
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
		$result = wc_get_orders(
			array(
				'payment_method' => BF_TWINT_GATEWAY_ID,
				'status'         => array( 'on-hold', 'pending' ),
				'limit'          => self::MAX_ORDERS,
				'paginate'       => true,
				'orderby'        => 'date',
				'order'          => 'ASC',
			)
		);

		$open       = $result->orders;
		$open_count = (int) $result->total;
		// Greift die Grenze, deckt alles Gerechnete nur die geladenen Bestellungen ab.
		$capped = $open_count > count( $open );

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
				$capped
					? sprintf(
						/* translators: 1: total number of open payments, 2: formatted amount, 3: number of orders that amount covers. */
						__( 'Open payments: %1$s (%2$s in total for the %3$s oldest)', 'blueforce-manual-payments-for-twint' ),
						number_format_i18n( $open_count ),
						wc_price( $total ),
						number_format_i18n( count( $open ) )
					)
					: sprintf(
						/* translators: 1: number of open payments, 2: formatted total amount. */
						__( 'Open payments: %1$s (%2$s in total)', 'blueforce-manual-payments-for-twint' ),
						number_format_i18n( $open_count ),
						wc_price( $total )
					)
			) . '</strong></p>';

			if ( $claimed > 0 ) {
				echo '<p>' . esc_html(
					$capped
						? sprintf(
							/* translators: 1: number of orders reported as paid, 2: number of orders that were checked. */
							__( 'Reported as paid by the customer: %1$s (of the %2$s oldest checked)', 'blueforce-manual-payments-for-twint' ),
							number_format_i18n( $claimed ),
							number_format_i18n( count( $open ) )
						)
						: sprintf(
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
		// Gezählt, nicht geladen: «paginate» liefert die Gesamtzahl, vorher holte
		// die Abfrage mit «limit => -1» sämtliche IDs der letzten 30 Tage.
		$received = wc_get_orders(
			array(
				'payment_method' => BF_TWINT_GATEWAY_ID,
				'status'         => array( 'processing', 'completed' ),
				'date_paid'      => '>' . ( time() - 30 * DAY_IN_SECONDS ),
				'return'         => 'ids',
				'limit'          => 1,
				'paginate'       => true,
			)
		);
		echo '<p class="description">' . esc_html(
			sprintf(
				/* translators: %s: number of payments received in the last 30 days. */
				__( 'Payments received in the last 30 days: %s', 'blueforce-manual-payments-for-twint' ),
				number_format_i18n( (int) $received->total )
			)
		) . '</p>';

		echo '<p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=' . BF_TWINT_Payments_Page::PAGE_SLUG ) ) . '">' . esc_html__( 'Go to the payments overview', 'blueforce-manual-payments-for-twint' ) . '</a></p>';
	}
}
