<?php
/**
 * Datenschutz: bindet die im Ablauf «Ich fordere an» gespeicherte
 * TWINT-Handynummer des Kunden in die WooCommerce-/WordPress-Mechanik für
 * Datenexport, Löschung und Datenschutzerklärung ein.
 *
 * @package Blueforce_Manual_Payments_For_TWINT
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registriert die nötigen WooCommerce-Privacy-Filter.
 *
 * Die Kundennummer liegt als Order-Meta «_bf_twint_customer_phone» vor und ist
 * personenbezogen. WooCommerce exportiert/anonymisiert eigene Order-Meta nicht
 * automatisch – diese Klasse hängt das Feld sauber in beide Vorgänge ein.
 */
final class BF_TWINT_Privacy {

	/**
	 * Meta-Key der personenbezogenen Kundennummer.
	 */
	const META_KEY = '_bf_twint_customer_phone';

	/**
	 * Hooks registrieren.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'woocommerce_privacy_export_order_personal_data_props', array( __CLASS__, 'export_props' ), 10, 2 );
		add_filter( 'woocommerce_privacy_export_order_personal_data_prop', array( __CLASS__, 'export_prop' ), 10, 3 );
		add_filter( 'woocommerce_privacy_remove_order_personal_data_meta', array( __CLASS__, 'erase_meta' ) );
		add_action( 'admin_init', array( __CLASS__, 'add_privacy_policy_content' ) );
	}

	/**
	 * Fügt die TWINT-Kundennummer als exportierbare Eigenschaft hinzu.
	 *
	 * @param array    $props Bisherige Eigenschaften (prop => Label).
	 * @param WC_Order $order Bestellung.
	 * @return array
	 */
	public static function export_props( $props, $order ) {
		// Nur für TWINT-Bestellungen, bei denen tatsächlich eine Nummer vorliegt –
		// sonst entstünden leere/irrelevante Felder im Datenexport.
		if ( BF_TWINT_GATEWAY_ID === $order->get_payment_method()
			&& '' !== (string) $order->get_meta( self::META_KEY )
		) {
			$props['bf_twint_customer_phone'] = __( 'TWINT mobile number (for payment request)', 'blueforce-manual-payments-for-twint' );
		}
		return $props;
	}

	/**
	 * Liefert den Wert der TWINT-Kundennummer für den Datenexport.
	 *
	 * @param string   $value Bisheriger Wert.
	 * @param string   $prop  Eigenschaft.
	 * @param WC_Order $order Bestellung.
	 * @return string
	 */
	public static function export_prop( $value, $prop, $order ) {
		if ( 'bf_twint_customer_phone' === $prop ) {
			$value = (string) $order->get_meta( self::META_KEY );
		}
		return $value;
	}

	/**
	 * Nimmt die TWINT-Kundennummer in die Order-Anonymisierung auf.
	 *
	 * @param array $meta Zu entfernende Meta-Keys (key => Typ).
	 * @return array
	 */
	public static function erase_meta( $meta ) {
		$meta[ self::META_KEY ] = 'text';
		return $meta;
	}

	/**
	 * Ergänzt einen Vorschlagstext für die Datenschutzerklärung.
	 *
	 * @return void
	 */
	public static function add_privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = wpautop(
			__( 'If you choose TWINT with the "I request the amount" flow at checkout, we store the TWINT mobile number you provide with your order. We use it solely to request payment via the TWINT app. The number is included in the export and erasure of your personal data.', 'blueforce-manual-payments-for-twint' )
		);

		wp_add_privacy_policy_content(
			__( 'Blueforce Manual Payments for TWINT', 'blueforce-manual-payments-for-twint' ),
			wp_kses_post( $content )
		);
	}
}
