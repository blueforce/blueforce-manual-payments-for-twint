<?php
/**
 * Plugin Name:       Blueforce Manual Payments for TWINT
 * Plugin URI:        https://github.com/blueforce/twint-for-woocommerce
 * Description:       Manuelle TWINT-Bezahlmethode für WooCommerce – ohne API, ohne Vertrag mit TWINT. Zwei Abläufe: «Kunde sendet» (deine TWINT-Nummer/QR wird angezeigt) oder «Ich fordere an» (Kunde gibt seine TWINT-Nummer an). Der Zahlungseingang wird von Hand bestätigt.
 * Version:           1.4.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Blueforce Digital Solutions
 * Author URI:        https://blueforce.ch
 * Text Domain:       blueforce-manual-payments-for-twint
 * Domain Path:       /languages
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * WC requires at least: 7.0
 * WC tested up to:       9.9
 *
 * Hinweis: Dieses Plugin ist ein unabhängiges Community-Projekt von Blueforce Digital
 * Solutions und steht in keiner Verbindung zur TWINT AG. «TWINT» ist eine eingetragene
 * Marke der TWINT AG und wird hier nur zur Beschreibung der Kompatibilität verwendet.
 *
 * @package TWINT_For_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BF_TWINT_VERSION', '1.4.0' );
define( 'BF_TWINT_FILE', __FILE__ );
define( 'BF_TWINT_PATH', plugin_dir_path( __FILE__ ) );
define( 'BF_TWINT_URL', plugin_dir_url( __FILE__ ) );
define( 'BF_TWINT_GATEWAY_ID', 'bf_twint' );

/**
 * Admin-Hinweis, falls WooCommerce fehlt.
 */
add_action(
	'admin_notices',
	static function () {
		if ( class_exists( 'WooCommerce' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'Blueforce Manual Payments for TWINT benötigt ein aktives WooCommerce.', 'blueforce-manual-payments-for-twint' );
		echo '</p></div>';
	}
);

/**
 * HPOS (High-Performance Order Storage) und Cart/Checkout-Blocks als kompatibel deklarieren.
 */
add_action(
	'before_woocommerce_init',
	static function () {
		if ( ! class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			return;
		}
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', BF_TWINT_FILE, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', BF_TWINT_FILE, true );
	}
);

/**
 * Gateway-Klasse laden und registrieren (klassischer Checkout).
 */
add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
			return;
		}

		require_once BF_TWINT_PATH . 'includes/class-wc-gateway-bf-twint.php';

		add_filter(
			'woocommerce_payment_gateways',
			static function ( $gateways ) {
				$gateways[] = 'WC_Gateway_BF_TWINT';
				return $gateways;
			}
		);

		// «Zahlung erhalten»-Button aus der Bestellansicht (Form-POST).
		add_action( 'admin_post_bf_twint_mark_paid', array( 'WC_Gateway_BF_TWINT', 'handle_mark_paid' ) );

		// Datenschutz: Kundennummer in Export/Löschung/Datenschutzerklärung einbinden.
		require_once BF_TWINT_PATH . 'includes/class-bf-twint-privacy.php';
		BF_TWINT_Privacy::init();
	},
	11
);

/**
 * Einstellungen-Link in der Plugin-Liste.
 */
add_filter(
	'plugin_action_links_' . plugin_basename( BF_TWINT_FILE ),
	static function ( $links ) {
		$url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . BF_TWINT_GATEWAY_ID );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Einstellungen', 'blueforce-manual-payments-for-twint' ) . '</a>' );
		return $links;
	}
);

/**
 * Block-Checkout-Integration registrieren.
 */
add_action(
	'woocommerce_blocks_loaded',
	static function () {
		if ( ! class_exists( 'Automattic\\WooCommerce\\Blocks\\Payments\\Integrations\\AbstractPaymentMethodType' ) ) {
			return;
		}

		require_once BF_TWINT_PATH . 'includes/class-bf-twint-blocks.php';

		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			static function ( $registry ) {
				$registry->register( new BF_TWINT_Blocks_Support() );
			}
		);
	}
);
