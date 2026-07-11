<?php
/**
 * Plugin Name:       Blueforce Manual Payments for TWINT
 * Plugin URI:        https://blueforce.ch/twint
 * Description:       Manual TWINT payment method for WooCommerce without the TWINT API – payments are reconciled and confirmed by hand.
 * Version:           1.6.2
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            Blueforce Digital Solutions
 * Author URI:        https://blueforce.ch
 * Text Domain:       blueforce-manual-payments-for-twint
 * Domain Path:       /languages
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * WC requires at least: 7.0
 * WC tested up to:       10.8
 *
 * Note: This plugin is an independent community project by Blueforce Digital
 * Solutions and is not affiliated with TWINT AG. "TWINT" is a registered trademark
 * of TWINT AG and is used here only to describe compatibility.
 *
 * @package Blueforce_Manual_Payments_For_TWINT
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BF_TWINT_VERSION', '1.6.2' );
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
		echo esc_html__( 'Blueforce Manual Payments for TWINT requires WooCommerce to be active.', 'blueforce-manual-payments-for-twint' );
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

		require_once BF_TWINT_PATH . 'includes/class-bf-twint-gateway.php';

		add_filter(
			'woocommerce_payment_gateways',
			static function ( $gateways ) {
				$gateways[] = 'BF_TWINT_Gateway';
				return $gateways;
			}
		);

		// «Zahlung erhalten»-Button aus der Bestellansicht (Form-POST).
		add_action( 'admin_post_bf_twint_mark_paid', array( 'BF_TWINT_Gateway', 'handle_mark_paid' ) );

		// «Ich habe bezahlt»-Meldung des Kunden (Danke-Seite/«Mein Konto», auch als Gast).
		add_action( 'admin_post_bf_twint_claim_paid', array( 'BF_TWINT_Gateway', 'handle_claim_paid' ) );
		add_action( 'admin_post_nopriv_bf_twint_claim_paid', array( 'BF_TWINT_Gateway', 'handle_claim_paid' ) );

		// Datenschutz: Kundennummer in Export/Löschung/Datenschutzerklärung einbinden.
		require_once BF_TWINT_PATH . 'includes/class-bf-twint-privacy.php';
		BF_TWINT_Privacy::init();

		// Admin-Übersicht «TWINT-Zahlungen» (offene Zahlungen abgleichen/freigeben)
		// und Dashboard-Widget mit dem Handlungsbedarf auf einen Blick.
		if ( is_admin() ) {
			require_once BF_TWINT_PATH . 'includes/class-bf-twint-payments-page.php';
			BF_TWINT_Payments_Page::init();

			require_once BF_TWINT_PATH . 'includes/class-bf-twint-dashboard-widget.php';
			BF_TWINT_Dashboard_Widget::init();
		}

		// Auto-Cancel: unbezahlte TWINT-Bestellungen nach konfigurierter Frist stornieren.
		require_once BF_TWINT_PATH . 'includes/class-bf-twint-auto-cancel.php';
		BF_TWINT_Auto_Cancel::init();

		// Zahlungserinnerung: einmalige Mail für unbezahlte TWINT-Bestellungen.
		require_once BF_TWINT_PATH . 'includes/class-bf-twint-payment-reminder.php';
		BF_TWINT_Payment_Reminder::init();
	},
	11
);

/**
 * Beim Deaktivieren die Cron-Events des Plugins entfernen.
 */
register_deactivation_hook(
	__FILE__,
	static function () {
		if ( class_exists( 'BF_TWINT_Auto_Cancel' ) ) {
			BF_TWINT_Auto_Cancel::unschedule();
		}
		if ( class_exists( 'BF_TWINT_Payment_Reminder' ) ) {
			BF_TWINT_Payment_Reminder::unschedule();
		}
	}
);

/**
 * Einstellungen-Link in der Plugin-Liste.
 */
add_filter(
	'plugin_action_links_' . plugin_basename( BF_TWINT_FILE ),
	static function ( $links ) {
		$url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . BF_TWINT_GATEWAY_ID );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'blueforce-manual-payments-for-twint' ) . '</a>' );
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
