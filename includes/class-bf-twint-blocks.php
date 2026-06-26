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
	 * Delegiert an die Verfügbarkeitsprüfung des Gateways, damit der Block-Checkout
	 * dieselben Regeln wie der klassische Checkout anwendet – insbesondere den
	 * CHF-Guard und den Filter «bf_twint_is_available». Ohne diese Delegation bliebe
	 * TWINT im Block-Checkout auch bei Fremdwährung sichtbar.
	 *
	 * @return bool
	 */
	public function is_active() {
		if ( empty( $this->settings['enabled'] ) || 'yes' !== $this->settings['enabled'] ) {
			return false;
		}

		$gateway = $this->get_gateway();
		if ( $gateway ) {
			return $gateway->is_available();
		}

		// Fallback ohne Gateway-Objekt: dieselben Regeln aus den Einstellungen
		// nachbilden (CHF-Guard, Send-Target, Filter), damit die Parität gewahrt
		// bleibt, falls die Gateway-Instanz ausnahmsweise nicht verfügbar ist.
		$available = 'CHF' === get_woocommerce_currency();

		if ( $available ) {
			$mode = isset( $this->settings['mode'] ) ? $this->settings['mode'] : 'send';
			if ( 'send' === $mode ) {
				$phone = isset( $this->settings['phone'] ) ? trim( (string) $this->settings['phone'] ) : '';
				$qr    = isset( $this->settings['qr_image'] ) ? trim( (string) $this->settings['qr_image'] ) : '';
				if ( '' === $phone && '' === $qr ) {
					$available = false;
				}
			}
		}

		return (bool) apply_filters( 'bf_twint_is_available', $available, null );
	}

	/**
	 * Holt die registrierte Gateway-Instanz (oder null, falls nicht verfügbar).
	 *
	 * @return BF_TWINT_Gateway|null
	 */
	private function get_gateway() {
		if ( ! function_exists( 'WC' ) || ! WC()->payment_gateways() ) {
			return null;
		}
		$gateways = WC()->payment_gateways()->payment_gateways();
		return isset( $gateways[ BF_TWINT_GATEWAY_ID ] ) ? $gateways[ BF_TWINT_GATEWAY_ID ] : null;
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
			wp_set_script_translations( 'bf-twint-blocks', 'blueforce-manual-payments-for-twint', BF_TWINT_PATH . 'languages' );
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
			'title'       => isset( $this->settings['title'] ) ? $this->settings['title'] : __( 'TWINT', 'blueforce-manual-payments-for-twint' ),
			'description' => isset( $this->settings['description'] ) ? $this->settings['description'] : '',
			'mode'        => isset( $this->settings['mode'] ) ? $this->settings['mode'] : 'send',
			'phone'       => isset( $this->settings['phone'] ) ? $this->settings['phone'] : '',
			'supports'    => array( 'products' ),
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

		$gateway = $this->get_gateway();
		$data    = $context->payment_data;
		$phone   = isset( $data['bf_twint_phone'] ) ? sanitize_text_field( wp_unslash( $data['bf_twint_phone'] ) ) : '';
		$mode    = isset( $this->settings['mode'] ) ? $this->settings['mode'] : 'send';

		if ( 'request' === $mode ) {
			if ( $gateway ) {
				$valid = $gateway->is_valid_phone( $phone );
			} else {
				$digits = strlen( (string) preg_replace( '/\D+/', '', $phone ) );
				$valid  = $digits >= 6 && $digits <= 15;
			}
			if ( ! $valid ) {
				throw new \Exception( esc_html__( 'Bitte gib eine gültige TWINT-Handynummer an, damit wir die Zahlung anfordern können.', 'blueforce-manual-payments-for-twint' ) );
			}
			if ( $gateway ) {
				$phone = $gateway->normalize_phone( $phone );
			}
		}

		$dirty = false;

		// Bestellrelevante Einstellungen einfrieren (analog zum klassischen Checkout).
		if ( $gateway ) {
			$gateway->store_settings_snapshot( $context->order );
			$dirty = true;
		}

		if ( '' !== $phone ) {
			$context->order->update_meta_data( '_bf_twint_customer_phone', $phone );
			$dirty = true;
		}

		if ( $dirty ) {
			$context->order->save();
		}
	}
}
