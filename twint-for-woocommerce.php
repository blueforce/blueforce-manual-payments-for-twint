<?php
/**
 * Plugin Name:       TWINT for WooCommerce
 * Plugin URI:        https://github.com/blueforce/twint-for-woocommerce
 * Description:       TWINT als Bezahlmethode für WooCommerce – ohne API, ohne Vertrag mit TWINT. Zwei Abläufe: «Kunde sendet» (deine TWINT-Nummer/QR wird angezeigt) oder «Ich fordere an» (Kunde gibt seine TWINT-Nummer an). Der Zahlungseingang wird von Hand bestätigt.
 * Version:           1.1.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Blueforce Digital Solutions
 * Author URI:        https://blueforce.ch
 * Text Domain:       twint-for-woocommerce
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

define( 'BF_TWINT_VERSION', '1.1.1' );
define( 'BF_TWINT_FILE', __FILE__ );
define( 'BF_TWINT_PATH', plugin_dir_path( __FILE__ ) );
define( 'BF_TWINT_URL', plugin_dir_url( __FILE__ ) );
define( 'BF_TWINT_GATEWAY_ID', 'bf_twint' );

/**
 * Übersetzungen laden.
 */
add_action( 'init', static function () {
	load_plugin_textdomain( 'twint-for-woocommerce', false, dirname( plugin_basename( BF_TWINT_FILE ) ) . '/languages' );
} );

/**
 * Admin-Hinweis, falls WooCommerce fehlt.
 */
add_action( 'admin_notices', static function () {
	if ( class_exists( 'WooCommerce' ) ) {
		return;
	}
	echo '<div class="notice notice-error"><p>';
	echo esc_html__( 'TWINT for WooCommerce benötigt ein aktives WooCommerce.', 'twint-for-woocommerce' );
	echo '</p></div>';
} );

/**
 * HPOS (High-Performance Order Storage) und Cart/Checkout-Blocks als kompatibel deklarieren.
 */
add_action( 'before_woocommerce_init', static function () {
	if ( ! class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		return;
	}
	\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', BF_TWINT_FILE, true );
	\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', BF_TWINT_FILE, true );
} );

/**
 * Automatische Updates aus den GitHub-Releases.
 *
 * Zeigt neue Versionen (z. B. 1.0.1) direkt im WordPress-Backend an und erlaubt die
 * 1-Klick-Aktualisierung – wie bei Plugins aus dem wordpress.org-Verzeichnis. Quelle
 * sind die Release-Assets (sauberes ZIP) des Repos.
 */
require_once BF_TWINT_PATH . 'includes/plugin-update-checker/plugin-update-checker.php';
$bf_twint_update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
	'https://github.com/blueforce/twint-for-woocommerce/',
	BF_TWINT_FILE,
	'twint-for-woocommerce'
);
$bf_twint_update_checker->setBranch( 'main' );
$bf_twint_update_checker->getVcsApi()->enableReleaseAssets();

// TWINT-Logo als Plugin-Icon in der Update-/Plugin-Ansicht (GitHub liefert kein Icon mit).
$bf_twint_update_checker->addResultFilter(
	static function ( $info ) {
		$info->icons = array(
			'svg'     => BF_TWINT_URL . 'assets/img/twint-logo.svg',
			'1x'      => BF_TWINT_URL . 'assets/img/icon-128x128.png',
			'2x'      => BF_TWINT_URL . 'assets/img/icon-256x256.png',
			'default' => BF_TWINT_URL . 'assets/img/icon-256x256.png',
		);
		return $info;
	}
);

/**
 * Gateway-Klasse laden und registrieren (klassischer Checkout).
 */
add_action( 'plugins_loaded', static function () {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	require_once BF_TWINT_PATH . 'includes/class-wc-gateway-bf-twint.php';

	add_filter( 'woocommerce_payment_gateways', static function ( $gateways ) {
		$gateways[] = 'WC_Gateway_BF_TWINT';
		return $gateways;
	} );
}, 11 );

/**
 * Einstellungen-Link in der Plugin-Liste.
 */
add_filter( 'plugin_action_links_' . plugin_basename( BF_TWINT_FILE ), static function ( $links ) {
	$url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . BF_TWINT_GATEWAY_ID );
	array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Einstellungen', 'twint-for-woocommerce' ) . '</a>' );
	return $links;
} );

/**
 * Block-Checkout-Integration registrieren.
 */
add_action( 'woocommerce_blocks_loaded', static function () {
	if ( ! class_exists( 'Automattic\\WooCommerce\\Blocks\\Payments\\Integrations\\AbstractPaymentMethodType' ) ) {
		return;
	}

	require_once BF_TWINT_PATH . 'includes/class-bf-twint-blocks.php';

	add_action( 'woocommerce_blocks_payment_method_type_registration', static function ( $registry ) {
		$registry->register( new BF_TWINT_Blocks_Support() );
	} );
} );
