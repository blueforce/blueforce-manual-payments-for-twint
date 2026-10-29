<?php
/**
 * Admin-Übersicht «TWINT-Zahlungen»: alle offenen (unbezahlten) TWINT-Bestellungen
 * auf einer Seite – mit Betrag, Referenz und Freigabe-Button direkt in der Zeile.
 *
 * Verkürzt den manuellen Abgleich: Statt jede Bestellung einzeln zu öffnen, sitzt
 * man mit der TWINT-App vor dieser Liste und hakt Eingänge ab. Der Button nutzt
 * den bestehenden «Zahlung erhalten»-Handler (admin_post_bf_twint_mark_paid).
 *
 * @package Blueforce_Manual_Payments_For_TWINT
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registriert die Unterseite im WooCommerce-Menü und rendert die Liste.
 */
final class BF_TWINT_Payments_Page {

	/**
	 * Slug der Admin-Seite.
	 */
	const PAGE_SLUG = 'bf-twint-payments';

	/**
	 * Bestellungen pro Seite (defensiv; die Zielgruppe sind kleine Shops, mehr
	 * als das deutet auf ein anderes Problem hin). Was darüber hinausgeht, ist
	 * über die Blätterfunktion erreichbar und wird nicht mehr verschwiegen.
	 */
	const PER_PAGE = 250;

	/**
	 * Hooks registrieren.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 60 );
	}

	/**
	 * Unterseite unter WooCommerce anlegen.
	 *
	 * @return void
	 */
	public static function register_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'TWINT payments', 'blueforce-manual-payments-for-twint' ),
			__( 'TWINT payments', 'blueforce-manual-payments-for-twint' ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Eine Seite offener TWINT-Bestellungen laden, älteste zuerst, die warten am
	 * längsten.
	 *
	 * Mit «paginate» liefert WooCommerce zusätzlich die echte Gesamtzahl. Vorher
	 * endete die Seite stumm bei 250 Treffern, ohne Hinweis und ohne Weg zum Rest.
	 *
	 * @param int $page Seitenzahl, ab 1.
	 * @return object Mit den Feldern orders, total und max_num_pages.
	 */
	private static function get_open_orders( $page = 1 ) {
		return wc_get_orders(
			array(
				'payment_method' => BF_TWINT_GATEWAY_ID,
				'status'         => array( 'on-hold', 'pending' ),
				'limit'          => self::PER_PAGE,
				'page'           => max( 1, (int) $page ),
				'paginate'       => true,
				'orderby'        => 'date',
				'order'          => 'ASC',
			)
		);
	}

	/**
	 * Gateway-Instanz holen, für die bestellbezogenen Einstellungen.
	 *
	 * @return WC_Payment_Gateway|null
	 */
	private static function get_gateway() {
		$gateways = WC()->payment_gateways() ? WC()->payment_gateways->payment_gateways() : array();
		return isset( $gateways[ BF_TWINT_GATEWAY_ID ] ) ? $gateways[ BF_TWINT_GATEWAY_ID ] : null;
	}

	/**
	 * Seite rendern.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nur die Seitenzahl einer lesenden Admin-Ansicht.
		$paged  = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
		$result = self::get_open_orders( $paged );

		// Seitenzahl aus einem alten Lesezeichen zeigt ins Leere, sobald der Shop
		// aufgeräumt hat. Dann auf die erste Seite zurück, statt «alles erledigt»
		// zu melden, obwohl noch Zahlungen offen sind. Die Gesamtzahl taugt dafür
		// nicht als Prüfung: Liegt die Seite ausserhalb, liefert WooCommerce auch
		// total und max_num_pages als 0.
		if ( empty( $result->orders ) && $paged > 1 ) {
			$paged  = 1;
			$result = self::get_open_orders( $paged );
		}

		$orders    = $result->orders;
		$total     = (int) $result->total;
		$max_pages = (int) $result->max_num_pages;
		$can_edit  = current_user_can( 'edit_shop_orders' );
		$gateway   = self::get_gateway();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Open TWINT payments', 'blueforce-manual-payments-for-twint' ) . '</h1>';
		echo '<p>' . esc_html__( 'All unpaid TWINT orders (on hold or pending). Release an order once you have confirmed the payment in your TWINT app.', 'blueforce-manual-payments-for-twint' ) . '</p>';

		// Dezenter Bewertungshinweis, nur auf dieser Plugin-eigenen Seite und
		// erst nach genügend freigegebenen Zahlungen (siehe Klasse).
		if ( class_exists( 'BF_TWINT_Review_Prompt' ) ) {
			BF_TWINT_Review_Prompt::maybe_render();
		}

		if ( empty( $orders ) ) {
			echo '<p><strong>' . esc_html__( 'No open TWINT payments – everything is settled.', 'blueforce-manual-payments-for-twint' ) . '</strong></p></div>';
			return;
		}

		if ( $max_pages > 1 ) {
			echo '<p>' . esc_html(
				sprintf(
					/* translators: 1: number of payments on this page, 2: total number of open payments. */
					__( 'Showing %1$s of %2$s open payments, oldest first.', 'blueforce-manual-payments-for-twint' ),
					number_format_i18n( count( $orders ) ),
					number_format_i18n( $total )
				)
			) . '</p>';
		}

		echo '<table class="widefat striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Order', 'blueforce-manual-payments-for-twint' ) . '</th>';
		echo '<th>' . esc_html__( 'Date', 'blueforce-manual-payments-for-twint' ) . '</th>';
		echo '<th>' . esc_html__( 'Customer', 'blueforce-manual-payments-for-twint' ) . '</th>';
		echo '<th>' . esc_html__( 'Total', 'blueforce-manual-payments-for-twint' ) . '</th>';
		echo '<th>' . esc_html__( 'TWINT', 'blueforce-manual-payments-for-twint' ) . '</th>';
		echo '<th>' . esc_html__( 'Action', 'blueforce-manual-payments-for-twint' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $orders as $order ) {
			self::render_row( $order, $can_edit, $gateway );
		}

		echo '</tbody></table>';

		// Blätterfunktion. Die Beschriftungen kommen aus WordPress selbst, dafür
		// braucht es keine eigenen Texte.
		if ( $max_pages > 1 ) {
			$links = paginate_links(
				array(
					'base'    => add_query_arg(
						array(
							'page'  => self::PAGE_SLUG,
							'paged' => '%#%',
						),
						admin_url( 'admin.php' )
					),
					'format'  => '',
					'total'   => $max_pages,
					'current' => $paged,
					'type'    => 'plain',
				)
			);
			if ( $links ) {
				echo '<div class="tablenav bottom"><div class="tablenav-pages">' . wp_kses_post( $links ) . '</div></div>';
			}
		}

		echo '</div>';
	}

	/**
	 * Eine Bestell-Zeile rendern.
	 *
	 * @param WC_Order                $order    Bestellung.
	 * @param bool                    $can_edit Ob der Nutzer Bestellungen freigeben darf.
	 * @param WC_Payment_Gateway|null $gateway  Gateway für die bestellbezogenen Einstellungen.
	 * @return void
	 */
	private static function render_row( $order, $can_edit, $gateway ) {
		$order_id = $order->get_id();
		$number   = $order->get_order_number();

		// Kunde: Rechnungsname, sonst «Gast».
		$customer = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
		if ( '' === $customer ) {
			$customer = __( 'Guest', 'blueforce-manual-payments-for-twint' );
		}

		// Alter der Bestellung («vor 3 Tagen»).
		$created = $order->get_date_created();
		$age     = $created
			? sprintf(
				/* translators: %s: human-readable time difference (e.g. "3 days"). */
				__( '%s ago', 'blueforce-manual-payments-for-twint' ),
				human_time_diff( $created->getTimestamp() )
			)
			: '';

		// Modus-gerechte Abgleich-Anweisung, über denselben Weg wie Danke-Seite und
		// E-Mail: bevorzugt aus dem Order-Snapshot, bei Altbestellungen ohne
		// Snapshot aus den aktuellen Einstellungen. Das rohe Meta allein hätte
		// dort immer «Kunde sendet» ergeben, samt fehlender Kundennummer.
		$mode = $gateway
			? (string) $gateway->order_setting( $order, 'mode' )
			: (string) $order->get_meta( '_bf_twint_mode' );
		if ( 'request' === $mode ) {
			$phone = (string) $order->get_meta( '_bf_twint_customer_phone' );
			$twint = $phone
				? sprintf(
					/* translators: %s: customer TWINT mobile number. */
					__( 'Request from %s', 'blueforce-manual-payments-for-twint' ),
					'<strong>' . esc_html( $phone ) . '</strong>'
				)
				: esc_html__( 'Request payment (no number on file)', 'blueforce-manual-payments-for-twint' );
		} else {
			$twint = sprintf(
				/* translators: %s: order number used as the payment message. */
				__( 'Check for incoming payment with message %s', 'blueforce-manual-payments-for-twint' ),
				'<strong>#' . esc_html( $number ) . '</strong>'
			);
		}

		// Kundenmeldung «Zahlung gesendet» – diese Bestellungen zuerst prüfen.
		$claimed = absint( $order->get_meta( '_bf_twint_paid_claimed' ) );
		if ( $claimed > 0 ) {
			$twint .= '<br><span class="description">✓ ' . esc_html(
				sprintf(
					/* translators: %s: date and time the customer reported the payment. */
					__( 'Unverified customer report: payment sent (%s).', 'blueforce-manual-payments-for-twint' ),
					wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $claimed )
				)
			) . '</span>';
		}

		echo '<tr>';
		echo '<td><a href="' . esc_url( $order->get_edit_order_url() ) . '"><strong>#' . esc_html( $number ) . '</strong></a></td>';
		echo '<td>' . esc_html( $created ? $created->date_i18n( get_option( 'date_format' ) ) : '' ) . ( $age ? '<br><span class="description">' . esc_html( $age ) . '</span>' : '' ) . '</td>';
		echo '<td>' . esc_html( $customer ) . '</td>';
		echo '<td>' . wp_kses_post( $order->get_formatted_order_total() ) . '</td>';
		echo '<td>' . wp_kses_post( $twint ) . '</td>';
		echo '<td>';
		if ( $can_edit ) {
			// Bestehender Handler aus der Bestellansicht; leitet per Referer hierher zurück.
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			echo '<input type="hidden" name="action" value="bf_twint_mark_paid" />';
			echo '<input type="hidden" name="order_id" value="' . esc_attr( $order_id ) . '" />';
			wp_nonce_field( 'bf_twint_mark_paid_' . $order_id );
			echo '<button type="submit" class="button button-primary">' . esc_html__( 'Payment received – release order', 'blueforce-manual-payments-for-twint' ) . '</button>';
			echo '</form>';
		}
		echo '</td>';
		echo '</tr>';
	}
}
