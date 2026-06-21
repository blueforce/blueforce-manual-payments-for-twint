<?php
/**
 * Integration in den Block-Checkout (WooCommerce Cart/Checkout-Blocks, Store-API).
 *
 * @package TWINT_For_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Registriert die Bezahlmethode für den Block-Checkout und reicht die
 * Einstellungen an das Frontend-Script weiter.
 */
final class BF_TWINT_Blocks_Support extends AbstractPaymentMethodType {

	/**
	 * Eindeutiger Name der Bezahlmethode.
	 *
	 * @var string
	 */
	protected $name = BF_TWINT_GATEWAY_ID;

	/**
	 * Einstellungen laden und serverseitige Verarbeitung der Block-Daten anhängen.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_' . BF_TWINT_GATEWAY_ID . '_settings', array() );

		// Die im Block-Checkout übergebene TWINT-Handynummer in die Bestellung schreiben
		// und im «request»-Ablauf serverseitig validieren.
		add_action( 'woocommerce_rest_checkout_process_payment_with_context', array( $this, 'process_block_payment' ), 10, 2 );
	}

	/**
	 * Ist die Methode aktiv?
	 *
	 * @return bool
	 */
	public function is_active() {
		return ! empty( $this->settings['enabled'] ) && 'yes' === $this->settings['enabled'];
	}

	/**
	 * Script-Handles für den Block-Checkout registrieren.
	 *
	 * @return string[]
	 */
	public function get_payment_method_script_handles() {
		$file = BF_TWINT_PATH . 'assets/js/blocks.js';
		$ver  = file_exists( $file ) ? (string) filemtime( $file ) : BF_TWINT_VERSION;

		wp_register_script(
			'bf-twint-blocks',
			BF_TWINT_URL . 'assets/js/blocks.js',
			array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities', 'wp-i18n' ),
			$ver,
			true
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'bf-twint-blocks', 'twint-for-woocommerce', BF_TWINT_PATH . 'languages' );
		}

		return array( 'bf-twint-blocks' );
	}

	/**
	 * Daten, die ans Frontend-Script übergeben werden.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		return array(
			'title'        => isset( $this->settings['title'] ) ? $this->settings['title'] : __( 'TWINT', 'twint-for-woocommerce' ),
			'description'  => isset( $this->settings['description'] ) ? $this->settings['description'] : '',
			'mode'         => isset( $this->settings['mode'] ) ? $this->settings['mode'] : 'send',
			'phone'        => isset( $this->settings['phone'] ) ? $this->settings['phone'] : '',
			'supports'     => array( 'products' ),
		);
	}

	/**
	 * Verarbeitet die im Block-Checkout übergebene TWINT-Handynummer.
	 *
	 * @param \Automattic\WooCommerce\StoreApi\Payments\PaymentContext $context Kontext.
	 * @param \Automattic\WooCommerce\StoreApi\Payments\PaymentResult  $result  Ergebnis (Referenz).
	 * @return void
	 *
	 * @throws \Exception Wenn im «request»-Ablauf keine gültige Nummer übergeben wird.
	 */
	public function process_block_payment( $context, $result ) {
		if ( BF_TWINT_GATEWAY_ID !== $context->payment_method ) {
			return;
		}

		$data  = $context->payment_data;
		$phone = isset( $data['bf_twint_phone'] ) ? sanitize_text_field( wp_unslash( $data['bf_twint_phone'] ) ) : '';
		$mode  = isset( $this->settings['mode'] ) ? $this->settings['mode'] : 'send';

		if ( 'request' === $mode ) {
			$digits = preg_replace( '/\D+/', '', $phone );
			if ( strlen( (string) $digits ) < 6 ) {
				throw new \Exception( esc_html__( 'Bitte gib eine gültige TWINT-Handynummer an, damit wir die Zahlung anfordern können.', 'twint-for-woocommerce' ) );
			}
		}

		if ( '' !== $phone ) {
			$context->order->update_meta_data( '_bf_twint_customer_phone', $phone );
			$context->order->save();
		}
	}
}
