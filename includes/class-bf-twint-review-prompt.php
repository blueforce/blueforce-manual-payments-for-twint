<?php
/**
 * Dezenter Hinweis auf eine Bewertung im WordPress.org-Verzeichnis.
 *
 * Erscheint ausschliesslich auf der Plugin-eigenen Seite «TWINT-Zahlungen» und
 * erst, wenn der Shop eine Mindestzahl an Zahlungen über das Plugin freigegeben
 * hat. Der Zeitpunkt ist bewusst an den Nutzen gekoppelt statt an die
 * Installationsdauer: Wer bereits zehn Zahlungen abgeglichen hat, weiss, ob das
 * Plugin taugt; ein Hinweis kurz nach der Aktivierung träfe dagegen jemanden,
 * der noch gar nichts damit gemacht hat.
 *
 * Die Entscheidung wird pro Benutzer gespeichert (nicht pro Shop), damit ein
 * Wegklicken nicht für Kolleginnen und Kollegen mitgilt.
 *
 * @package Blueforce_Manual_Payments_For_TWINT
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Zeigt und verwaltet den Bewertungshinweis.
 */
final class BF_TWINT_Review_Prompt {

	/**
	 * Option mit der Anzahl freigegebener Zahlungen (shop-weit).
	 */
	const COUNT_OPTION = 'bf_twint_released_count';

	/**
	 * User-Meta mit der Entscheidung: «done» = nie wieder, sonst Zeitstempel.
	 */
	const USER_META = '_bf_twint_review_prompt';

	/**
	 * Ab wie vielen freigegebenen Zahlungen der Hinweis erscheint.
	 */
	const MIN_RELEASES = 10;

	/**
	 * Wie lange «Später» den Hinweis ausblendet (Sekunden).
	 */
	const SNOOZE = 90 * DAY_IN_SECONDS;

	/**
	 * Bewertungsseite im Verzeichnis.
	 */
	const REVIEW_URL = 'https://wordpress.org/support/plugin/blueforce-manual-payments-for-twint/reviews/#new-post';

	/**
	 * Hooks registrieren.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_post_bf_twint_review_prompt', array( __CLASS__, 'handle_action' ) );
	}

	/**
	 * Zählt eine freigegebene Zahlung.
	 *
	 * Wird aus dem «Zahlung erhalten»-Handler aufgerufen. Reiner Zähler, kein
	 * Personenbezug und keine Bestelldaten.
	 *
	 * @return void
	 */
	public static function count_release() {
		$count = absint( get_option( self::COUNT_OPTION, 0 ) );
		update_option( self::COUNT_OPTION, $count + 1, false );
	}

	/**
	 * Soll der Hinweis für die aktuelle Person erscheinen?
	 *
	 * @return bool
	 */
	private static function should_show() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return false;
		}
		if ( absint( get_option( self::COUNT_OPTION, 0 ) ) < self::MIN_RELEASES ) {
			return false;
		}

		$state = get_user_meta( get_current_user_id(), self::USER_META, true );
		if ( 'done' === $state ) {
			return false;
		}
		if ( $state && absint( $state ) + self::SNOOZE > time() ) {
			return false;
		}

		return true;
	}

	/**
	 * Hinweis ausgeben (nur auf der Zahlungsübersicht aufgerufen).
	 *
	 * @return void
	 */
	public static function maybe_render() {
		if ( ! self::should_show() ) {
			return;
		}

		$count = absint( get_option( self::COUNT_OPTION, 0 ) );

		echo '<div class="notice notice-info bf-twint-review">';
		echo '<p><strong>' . esc_html__( 'Enjoying Blueforce Manual Payments for TWINT?', 'blueforce-manual-payments-for-twint' ) . '</strong></p>';
		echo '<p>' . esc_html(
			sprintf(
				/* translators: %s: number of payments released with the plugin. */
				_n(
					'You have released %s payment with this plugin so far. A short review helps other small shops find it.',
					'You have released %s payments with this plugin so far. A short review helps other small shops find it.',
					$count,
					'blueforce-manual-payments-for-twint'
				),
				number_format_i18n( $count )
			)
		) . '</p>';

		echo '<p>';
		self::button( 'rate', __( 'Write a review', 'blueforce-manual-payments-for-twint' ), 'button-primary' );
		echo ' ';
		self::button( 'done', __( 'I already did', 'blueforce-manual-payments-for-twint' ), 'button' );
		echo ' ';
		self::button( 'later', __( 'Maybe later', 'blueforce-manual-payments-for-twint' ), 'button-link' );
		echo '</p>';
		echo '</div>';
	}

	/**
	 * Einen der drei Buttons als eigenes Formular ausgeben.
	 *
	 * Bewusst Form-POST statt Link: Die Entscheidung ändert einen Zustand und
	 * gehört darum hinter eine Nonce, nicht in eine aufrufbare URL.
	 *
	 * @param string $action Eine von: rate, done, later.
	 * @param string $label  Beschriftung.
	 * @param string $css    CSS-Klasse des Buttons.
	 * @return void
	 */
	private static function button( $action, $label, $css ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">';
		echo '<input type="hidden" name="action" value="bf_twint_review_prompt" />';
		echo '<input type="hidden" name="choice" value="' . esc_attr( $action ) . '" />';
		wp_nonce_field( 'bf_twint_review_prompt' );
		echo '<button type="submit" class="button ' . esc_attr( $css ) . '">' . esc_html( $label ) . '</button>';
		echo '</form>';
	}

	/**
	 * Verarbeitet die Entscheidung und leitet zurück.
	 *
	 * @return void
	 */
	public static function handle_action() {
		check_admin_referer( 'bf_twint_review_prompt' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'blueforce-manual-payments-for-twint' ), '', array( 'response' => 403 ) );
		}

		$choice = isset( $_POST['choice'] ) ? sanitize_key( wp_unslash( $_POST['choice'] ) ) : '';
		$user   = get_current_user_id();

		if ( 'later' === $choice ) {
			update_user_meta( $user, self::USER_META, (string) time() );
		} elseif ( in_array( $choice, array( 'rate', 'done' ), true ) ) {
			// «Bewerten» blendet ebenfalls dauerhaft aus: Wer den Weg zur
			// Bewertungsseite geht, soll nicht erneut gefragt werden.
			update_user_meta( $user, self::USER_META, 'done' );
		}

		if ( 'rate' === $choice ) {
			// wp_redirect statt wp_safe_redirect: Ziel ist bewusst extern
			// (wordpress.org) und hier fest im Code hinterlegt, nicht aus einer
			// Eingabe übernommen.
			wp_redirect( self::REVIEW_URL ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Fixed external URL (plugin directory), not user input.
			exit;
		}

		$back = admin_url( 'admin.php?page=' . BF_TWINT_Payments_Page::PAGE_SLUG );
		wp_safe_redirect( $back );
		exit;
	}
}
