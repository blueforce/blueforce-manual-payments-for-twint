<?php
/**
 * Prüfungen für «Werkzeuge → Website-Zustand» (Site Health).
 *
 * Das Plugin hat zwei stille Abhängigkeiten: Auto-Stornierung und
 * Zahlungserinnerung laufen über WP-Cron, und TWINT erscheint nur bei
 * Shop-Währung CHF. Fällt eines davon weg, passiert einfach nichts, und im
 * Backend weist nichts darauf hin. Diese Tests machen das an der Stelle
 * sichtbar, an der WordPress solche Befunde ohnehin sammelt, ohne eigene
 * Diagnose-Seite.
 *
 * @package Blueforce_Manual_Payments_For_TWINT
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registriert die Site-Health-Tests des Plugins.
 */
final class BF_TWINT_Site_Health {

	/**
	 * Ab wann ein geplantes Cron-Event als überfällig gilt (Sekunden nach dem
	 * Solltermin). WP-Cron läuft nur bei Seitenaufrufen, ein wenig Verzug ist
	 * normal; mehr als eine Stunde deutet auf einen blockierten Cron hin.
	 */
	const OVERDUE_AFTER = HOUR_IN_SECONDS;

	/**
	 * Hooks registrieren.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'site_status_tests', array( __CLASS__, 'register_tests' ) );
	}

	/**
	 * Tests anmelden (alle «direct», also beim Seitenaufbau berechnet).
	 *
	 * @param array $tests Bisherige Tests.
	 * @return array
	 */
	public static function register_tests( $tests ) {
		$tests['direct']['bf_twint_currency']    = array(
			'label' => __( 'TWINT: shop currency', 'blueforce-manual-payments-for-twint' ),
			'test'  => array( __CLASS__, 'test_currency' ),
		);
		$tests['direct']['bf_twint_send_target'] = array(
			'label' => __( 'TWINT: payment details', 'blueforce-manual-payments-for-twint' ),
			'test'  => array( __CLASS__, 'test_send_target' ),
		);
		$tests['direct']['bf_twint_cron']        = array(
			'label' => __( 'TWINT: scheduled tasks', 'blueforce-manual-payments-for-twint' ),
			'test'  => array( __CLASS__, 'test_cron' ),
		);
		return $tests;
	}

	/**
	 * Gateway-Einstellungen (roh, ohne Gateway-Instanz).
	 *
	 * @return array
	 */
	private static function settings() {
		$settings = get_option( 'woocommerce_' . BF_TWINT_GATEWAY_ID . '_settings', array() );
		return is_array( $settings ) ? $settings : array();
	}

	/**
	 * Ist das Gateway aktiviert?
	 *
	 * @return bool
	 */
	private static function is_enabled() {
		$settings = self::settings();
		return isset( $settings['enabled'] ) && 'yes' === $settings['enabled'];
	}

	/**
	 * Grundgerüst eines Testergebnisses.
	 *
	 * @param string $slug        Test-Slug.
	 * @param string $label       Überschrift.
	 * @param string $status      good, recommended oder critical.
	 * @param string $description Beschreibung (ein Absatz, Klartext).
	 * @param string $actions     Optionaler Link (HTML).
	 * @return array
	 */
	private static function result( $slug, $label, $status, $description, $actions = '' ) {
		return array(
			'label'       => $label,
			'status'      => $status,
			'badge'       => array(
				'label' => __( 'Payments', 'blueforce-manual-payments-for-twint' ),
				'color' => 'blue',
			),
			'description' => '<p>' . esc_html( $description ) . '</p>',
			'actions'     => $actions,
			'test'        => $slug,
		);
	}

	/**
	 * Link auf die Gateway-Einstellungen.
	 *
	 * @return string
	 */
	private static function settings_link() {
		$url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . BF_TWINT_GATEWAY_ID );
		return '<p><a href="' . esc_url( $url ) . '">' . esc_html__( 'Open the TWINT settings', 'blueforce-manual-payments-for-twint' ) . '</a></p>';
	}

	/**
	 * Test 1: Shop-Währung muss CHF sein, sonst erscheint TWINT nirgends.
	 *
	 * @return array
	 */
	public static function test_currency() {
		$label = __( 'TWINT is available at checkout', 'blueforce-manual-payments-for-twint' );

		if ( ! self::is_enabled() ) {
			return self::result( 'bf_twint_currency', __( 'TWINT is not enabled', 'blueforce-manual-payments-for-twint' ), 'good', __( 'The TWINT payment method is switched off, so no further checks apply.', 'blueforce-manual-payments-for-twint' ) );
		}

		$currency = get_woocommerce_currency();
		if ( 'CHF' !== $currency ) {
			$url = admin_url( 'admin.php?page=wc-settings&tab=general' );
			return self::result(
				'bf_twint_currency',
				__( 'TWINT is hidden because the shop currency is not CHF', 'blueforce-manual-payments-for-twint' ),
				'critical',
				sprintf(
					/* translators: %s: current shop currency code. */
					__( 'TWINT only works in Swiss francs. Your shop currency is %s, so the payment method is enabled but never shown to customers.', 'blueforce-manual-payments-for-twint' ),
					$currency
				),
				'<p><a href="' . esc_url( $url ) . '">' . esc_html__( 'Change the shop currency', 'blueforce-manual-payments-for-twint' ) . '</a></p>'
			);
		}

		return self::result( 'bf_twint_currency', $label, 'good', __( 'The shop currency is CHF.', 'blueforce-manual-payments-for-twint' ) );
	}

	/**
	 * Test 2: Ablauf «Kunde sendet» braucht Nummer oder QR-Code.
	 *
	 * @return array
	 */
	public static function test_send_target() {
		$settings = self::settings();
		$mode     = isset( $settings['mode'] ) && 'request' === $settings['mode'] ? 'request' : 'send';

		if ( ! self::is_enabled() || 'request' === $mode ) {
			return self::result( 'bf_twint_send_target', __( 'TWINT payment details are complete', 'blueforce-manual-payments-for-twint' ), 'good', __( 'Nothing to check for the current flow.', 'blueforce-manual-payments-for-twint' ) );
		}

		$phone = isset( $settings['phone'] ) ? trim( (string) $settings['phone'] ) : '';
		$qr    = isset( $settings['qr_image'] ) ? trim( (string) $settings['qr_image'] ) : '';
		if ( '' === $phone && '' === $qr ) {
			return self::result(
				'bf_twint_send_target',
				__( 'TWINT is hidden because no payment details are set', 'blueforce-manual-payments-for-twint' ),
				'critical',
				__( 'The "Customer sends" flow is active, but neither a TWINT mobile number nor a QR code is set. Customers would not know where to send the money, so the payment method stays hidden.', 'blueforce-manual-payments-for-twint' ),
				self::settings_link()
			);
		}

		return self::result( 'bf_twint_send_target', __( 'TWINT payment details are complete', 'blueforce-manual-payments-for-twint' ), 'good', __( 'A TWINT mobile number or QR code is set for the "Customer sends" flow.', 'blueforce-manual-payments-for-twint' ) );
	}

	/**
	 * Test 3: Cron-Events geplant und nicht überfällig.
	 *
	 * Prüft nur Funktionen, die der Shop eingeschaltet hat. Ein überfälliges
	 * Event bedeutet: WP-Cron feuert nicht (typisch bei DISABLE_WP_CRON ohne
	 * Server-Cron oder bei Shops ohne Besucher).
	 *
	 * @return array
	 */
	public static function test_cron() {
		$jobs = array();
		if ( class_exists( 'BF_TWINT_Auto_Cancel' ) && BF_TWINT_Auto_Cancel::get_days() > 0 ) {
			$jobs[ BF_TWINT_Auto_Cancel::CRON_HOOK ] = __( 'auto-cancelling unpaid orders', 'blueforce-manual-payments-for-twint' );
		}
		if ( class_exists( 'BF_TWINT_Payment_Reminder' ) && BF_TWINT_Payment_Reminder::get_days() > 0 ) {
			$jobs[ BF_TWINT_Payment_Reminder::CRON_HOOK ] = __( 'payment reminders', 'blueforce-manual-payments-for-twint' );
		}

		if ( ! self::is_enabled() || empty( $jobs ) ) {
			return self::result( 'bf_twint_cron', __( 'TWINT scheduled tasks are not in use', 'blueforce-manual-payments-for-twint' ), 'good', __( 'Neither auto-cancelling nor payment reminders are enabled, so no scheduled task is needed.', 'blueforce-manual-payments-for-twint' ) );
		}

		$problems = array();
		foreach ( $jobs as $hook => $name ) {
			$next = wp_next_scheduled( $hook );
			if ( ! $next ) {
				$problems[] = $name;
			} elseif ( $next + self::OVERDUE_AFTER < time() ) {
				$problems[] = $name;
			}
		}

		if ( ! empty( $problems ) ) {
			$description = sprintf(
				/* translators: %s: comma-separated list of affected features. */
				__( 'The scheduled task for %s is missing or overdue. WP-Cron seems not to be running, so these features are silently inactive. Make sure WP-Cron works or set up a real server cron that calls wp-cron.php.', 'blueforce-manual-payments-for-twint' ),
				implode( ', ', $problems )
			);
			if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
				$description .= ' ' . __( 'DISABLE_WP_CRON is set on this site, so a server cron is required.', 'blueforce-manual-payments-for-twint' );
			}
			return self::result( 'bf_twint_cron', __( 'TWINT scheduled tasks are not running', 'blueforce-manual-payments-for-twint' ), 'critical', $description );
		}

		return self::result( 'bf_twint_cron', __( 'TWINT scheduled tasks are running', 'blueforce-manual-payments-for-twint' ), 'good', __( 'The scheduled tasks for auto-cancelling and payment reminders are planned and on time.', 'blueforce-manual-payments-for-twint' ) );
	}
}
