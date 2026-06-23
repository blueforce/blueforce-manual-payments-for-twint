<?php
/**
 * TWINT-Gateway (klassischer Checkout + gemeinsame Logik für Block-Checkout).
 *
 * @package TWINT_For_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Offline-TWINT-Gateway mit zwei Abläufen.
 *
 * - «send»    : Der Shop zeigt seine TWINT-Handynummer (und optional einen QR-Code).
 *               Der Kunde sendet den Betrag mit der Bestellnummer als Mitteilung.
 * - «request» : Der Kunde gibt seine TWINT-Handynummer an; der Shop fordert den
 *               Betrag in der TWINT-App an.
 *
 * In beiden Fällen wird die Bestellung auf «In Wartestellung» gesetzt und der
 * Zahlungseingang von Hand bestätigt – es findet kein API-Call statt.
 */
class WC_Gateway_BF_TWINT extends WC_Payment_Gateway {

	/**
	 * Gewählter Ablauf: «send» oder «request».
	 *
	 * @var string
	 */
	public $mode;

	/**
	 * TWINT-Handynummer des Shops (Ablauf «send»).
	 *
	 * @var string
	 */
	public $phone;

	/**
	 * Name des Kontoinhabers (Ablauf «send», optional).
	 *
	 * @var string
	 */
	public $account_name;

	/**
	 * URL eines hochgeladenen TWINT-QR-Bildes (Ablauf «send», optional).
	 *
	 * @var string
	 */
	public $qr_image;

	/**
	 * Zusätzliche Hinweise für Danke-Seite und E-Mail.
	 *
	 * @var string
	 */
	public $instructions;

	/**
	 * Konstruktor.
	 */
	public function __construct() {
		$this->id                 = BF_TWINT_GATEWAY_ID;
		$this->method_title       = __( 'TWINT', 'twint-for-woocommerce' );
		$this->method_description = __( 'TWINT als Offline-Zahlung – ohne API, ohne Vertrag mit TWINT. Ablauf «Kunde sendet» (deine TWINT-Nummer/QR wird angezeigt) oder «Ich fordere an» (Kunde gibt seine TWINT-Nummer an). Der Zahlungseingang wird von Hand bestätigt.', 'twint-for-woocommerce' );
		$this->icon               = BF_TWINT_URL . 'assets/img/twint-logo.svg';
		$this->has_fields         = true;
		$this->supports           = array( 'products' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title        = $this->get_option( 'title', __( 'TWINT', 'twint-for-woocommerce' ) );
		$this->description  = $this->get_option( 'description' );
		$this->mode         = $this->get_option( 'mode', 'send' );
		$this->phone        = $this->get_option( 'phone' );
		$this->account_name = $this->get_option( 'account_name' );
		$this->qr_image     = $this->get_option( 'qr_image' );
		$this->instructions = $this->get_option( 'instructions' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
		add_action( 'admin_notices', array( $this, 'maybe_show_config_notice' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
		add_action( 'woocommerce_email_after_order_table', array( $this, 'email_instructions' ), 10, 3 );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'admin_order_details' ) );
	}

	/**
	 * Icon in sinnvoller Grösse rendern (Admin-Liste + klassischer Checkout).
	 *
	 * @return string
	 */
	public function get_icon() {
		$icon = $this->icon
			? '<img src="' . esc_url( $this->icon ) . '" alt="TWINT" style="max-height:24px;width:auto;vertical-align:middle" />'
			: '';
		return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );
	}

	/**
	 * Verfügbarkeit: TWINT funktioniert nur in Schweizer Franken.
	 *
	 * Blendet die Methode im Checkout aus, wenn die Shop-Währung nicht CHF ist
	 * (verhindert Fehlbestellungen in Fremdwährung). Über den Filter
	 * «bf_twint_is_available» lässt sich das bei Bedarf übersteuern (z. B. in
	 * Multi-Währungs-Setups, die TWINT bewusst auch anders anbieten).
	 *
	 * @return bool
	 */
	public function is_available() {
		$available = parent::is_available();

		if ( $available && 'CHF' !== get_woocommerce_currency() ) {
			$available = false;
		}

		// Ablauf «Kunde sendet» ist nur sinnvoll, wenn der Kunde auch weiss, wohin –
		// also eine TWINT-Nummer oder ein QR-Code hinterlegt ist. Fehlt beides, würde
		// eine Bestellung entstehen, bei der niemand weiss, wohin gezahlt werden soll.
		if ( $available && 'send' === $this->mode && ! $this->has_send_target() ) {
			$available = false;
		}

		return (bool) apply_filters( 'bf_twint_is_available', $available, $this );
	}

	/**
	 * Hat der Shop für den Ablauf «Kunde sendet» ein Zahlungsziel hinterlegt?
	 *
	 * @return bool
	 */
	public function has_send_target() {
		return '' !== trim( (string) $this->phone ) || '' !== trim( (string) $this->qr_image );
	}

	/**
	 * Warnt im Backend, wenn TWINT aktiv ist, aber Pflichtangaben fehlen.
	 *
	 * Greift im Ablauf «Kunde sendet», wenn weder Nummer noch QR-Code hinterlegt
	 * ist – dann blendet is_available() die Methode aus, und ohne Hinweis wäre für
	 * den Shop nicht ersichtlich, warum TWINT im Checkout nicht erscheint.
	 *
	 * @return void
	 */
	public function maybe_show_config_notice() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		if ( 'yes' !== $this->enabled ) {
			return;
		}
		if ( 'send' !== $this->mode || $this->has_send_target() ) {
			return;
		}

		$url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . $this->id );
		echo '<div class="notice notice-warning"><p>';
		printf(
			/* translators: %s: URL to the TWINT settings page. */
			wp_kses_post( __( '<strong>TWINT for WooCommerce:</strong> Der Ablauf «Kunde sendet» ist aktiv, aber es ist weder eine TWINT-Handynummer noch ein QR-Code hinterlegt. Die Bezahlmethode wird im Checkout ausgeblendet, bis du die <a href="%s">Angaben ergänzt</a>.', 'twint-for-woocommerce' ) ),
			esc_url( $url )
		);
		echo '</p></div>';
	}

	/**
	 * Einstellungsfelder im Admin.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'      => array(
				'title'   => __( 'Aktivieren', 'twint-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'TWINT als Bezahlmethode aktivieren', 'twint-for-woocommerce' ),
				'default' => 'no',
			),
			'title'        => array(
				'title'       => __( 'Titel', 'twint-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Im Checkout angezeigter Name.', 'twint-for-woocommerce' ),
				'default'     => __( 'TWINT', 'twint-for-woocommerce' ),
				'desc_tip'    => true,
			),
			'description'  => array(
				'title'       => __( 'Beschreibung', 'twint-for-woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'Kurztext unter dem Methodennamen im Checkout.', 'twint-for-woocommerce' ),
				'default'     => __( 'Bezahle einfach und sicher mit TWINT.', 'twint-for-woocommerce' ),
			),
			'mode'         => array(
				'title'       => __( 'Ablauf', 'twint-for-woocommerce' ),
				'type'        => 'select',
				'description' => __( '«Kunde sendet»: deine TWINT-Nummer wird angezeigt. «Ich fordere an»: Kunde gibt seine TWINT-Nummer an, du forderst den Betrag.', 'twint-for-woocommerce' ),
				'default'     => 'send',
				'options'     => array(
					'send'    => __( 'Kunde sendet an meine TWINT-Nummer', 'twint-for-woocommerce' ),
					'request' => __( 'Ich fordere den Betrag an (Kunde gibt Nummer an)', 'twint-for-woocommerce' ),
				),
				'desc_tip'    => true,
			),
			'phone'        => array(
				'title'       => __( 'Deine TWINT-Handynummer', 'twint-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Nur bei «Kunde sendet»: Nummer, an die der Betrag gesendet wird (z. B. +41 79 123 45 67).', 'twint-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'account_name' => array(
				'title'       => __( 'Name des Kontoinhabers', 'twint-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Optional. Wird dem Kunden zur Kontrolle angezeigt (z. B. Firmen- oder Personenname).', 'twint-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'qr_image'     => array(
				'title'       => __( 'TWINT-QR-Bild', 'twint-for-woocommerce' ),
				'type'        => 'qr_image',
				'description' => __( 'Optional, nur bei «Kunde sendet». Lade in der TWINT-App unter «Geld empfangen» deinen QR-Code, speichere ihn als Bild und wähle ihn hier aus der Mediathek. Der Kunde kann ihn dann direkt scannen.', 'twint-for-woocommerce' ),
				'default'     => '',
			),
			'instructions' => array(
				'title'       => __( 'Zusätzliche Hinweise', 'twint-for-woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'Erscheint auf der Danke-Seite und in der Bestell-E-Mail.', 'twint-for-woocommerce' ),
				'default'     => __( 'Wir bearbeiten deine Bestellung, sobald die Zahlung eingegangen ist.', 'twint-for-woocommerce' ),
			),
		);
	}

	/**
	 * Lädt den WordPress-Media-Uploader nur auf der TWINT-Einstellungsseite.
	 *
	 * @param string $hook Aktuelle Admin-Seite.
	 */
	public function admin_assets( $hook ) {
		if ( 'woocommerce_page_wc-settings' !== $hook ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$section = isset( $_GET['section'] ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $this->id !== $section ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script(
			'bf-twint-admin-qr',
			BF_TWINT_URL . 'assets/js/admin-qr.js',
			array( 'jquery' ),
			BF_TWINT_VERSION,
			true
		);
		wp_localize_script(
			'bf-twint-admin-qr',
			'bfTwintQr',
			array(
				'title'  => __( 'TWINT-QR-Bild wählen', 'twint-for-woocommerce' ),
				'button' => __( 'Dieses Bild verwenden', 'twint-for-woocommerce' ),
			)
		);
	}

	/**
	 * Eigener Feldtyp «qr_image»: URL-Feld + Button zur Auswahl aus der Mediathek + Vorschau.
	 *
	 * @param string $key  Feld-Key.
	 * @param array  $data Feld-Definition.
	 * @return string
	 */
	public function generate_qr_image_html( $key, $data ) {
		$field_key = $this->get_field_key( $key );
		$data      = wp_parse_args(
			$data,
			array(
				'title'       => '',
				'class'       => '',
				'css'         => '',
				'placeholder' => '',
				'desc_tip'    => false,
				'description' => '',
			)
		);
		$value = $this->get_option( $key );

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?> <?php echo $this->get_tooltip_html( $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
			</th>
			<td class="forminp">
				<fieldset>
					<legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
					<input class="input-text regular-input <?php echo esc_attr( $data['class'] ); ?>" type="url" name="<?php echo esc_attr( $field_key ); ?>" id="<?php echo esc_attr( $field_key ); ?>" style="<?php echo esc_attr( $data['css'] ); ?>" value="<?php echo esc_attr( $value ); ?>" placeholder="<?php echo esc_attr( $data['placeholder'] ); ?>" />
					<button type="button" class="button bf-twint-qr-upload" data-target="<?php echo esc_attr( $field_key ); ?>"><?php esc_html_e( 'Bild auswählen', 'twint-for-woocommerce' ); ?></button>
					<button type="button" class="button bf-twint-qr-remove" data-target="<?php echo esc_attr( $field_key ); ?>" style="<?php echo $value ? '' : 'display:none'; ?>"><?php esc_html_e( 'Entfernen', 'twint-for-woocommerce' ); ?></button>
					<?php echo $this->get_description_html( $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<div class="bf-twint-qr-preview" style="margin-top:8px">
						<?php if ( $value ) : ?>
							<img src="<?php echo esc_url( $value ); ?>" alt="" style="max-width:160px;height:auto;border:1px solid #ddd;padding:4px;background:#fff" />
						<?php endif; ?>
					</div>
				</fieldset>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/**
	 * Sanitisiert den Wert des QR-Bild-Feldes als URL.
	 *
	 * @param string $key   Feld-Key.
	 * @param string $value Rohwert.
	 * @return string
	 */
	public function validate_qr_image_field( $key, $value ) {
		return esc_url_raw( trim( (string) $value ) );
	}

	/**
	 * Checkout-Felder / Anweisungen je nach Ablauf (klassischer Checkout).
	 */
	public function payment_fields() {
		if ( $this->description ) {
			echo wpautop( wp_kses_post( $this->description ) );
		}

		if ( 'request' === $this->mode ) {
			echo '<p class="form-row form-row-wide">';
			echo '<label for="bf_twint_phone">' . esc_html__( 'TWINT-Handynummer', 'twint-for-woocommerce' ) . ' <abbr class="required" title="' . esc_attr__( 'Pflichtfeld', 'twint-for-woocommerce' ) . '">*</abbr></label>';
			echo '<input type="tel" id="bf_twint_phone" name="bf_twint_phone" autocomplete="tel" placeholder="+41 79 123 45 67" />';
			echo '<span style="display:block;font-size:.9em;color:#666">' . esc_html__( 'An diese Nummer senden wir dir eine TWINT-Zahlungsanforderung.', 'twint-for-woocommerce' ) . '</span>';
			echo '</p>';
		} elseif ( $this->phone ) {
			printf(
				'<p style="margin:.5em 0 0">%s</p>',
				sprintf(
					/* translators: %s: TWINT phone number of the shop. */
					esc_html__( 'Sende den Betrag via TWINT an %s – die genauen Angaben erhältst du nach der Bestellung.', 'twint-for-woocommerce' ),
					'<strong>' . esc_html( $this->phone ) . '</strong>'
				)
			);
		}
	}

	/**
	 * Im «request»-Ablauf ist die Kundennummer Pflicht (klassischer Checkout).
	 *
	 * @return bool
	 */
	public function validate_fields() {
		if ( 'request' === $this->mode ) {
			$phone = isset( $_POST['bf_twint_phone'] ) ? trim( wc_clean( wp_unslash( $_POST['bf_twint_phone'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the checkout nonce.
			if ( ! $this->is_valid_phone( $phone ) ) {
				wc_add_notice( __( 'Bitte gib eine gültige TWINT-Handynummer an, damit wir die Zahlung anfordern können.', 'twint-for-woocommerce' ), 'error' );
				return false;
			}
		}
		return true;
	}

	/**
	 * Bringt eine Handynummer in eine einheitliche Anzeigeform.
	 *
	 * Entfernt unzulässige Zeichen, reduziert Mehrfach-Leerzeichen und trimmt.
	 * Bewusst konservativ (keine Ländervorwahl-Logik), damit international
	 * eingegebene Nummern nicht verfälscht werden.
	 *
	 * @param string $phone Rohwert.
	 * @return string
	 */
	public function normalize_phone( $phone ) {
		$phone = preg_replace( '/[^\d+\s()\/.-]/', '', (string) $phone );
		$phone = preg_replace( '/\s+/', ' ', (string) $phone );
		return trim( (string) $phone );
	}

	/**
	 * Prüft, ob eine Handynummer plausibel ist (6–15 Ziffern, E.164-Rahmen).
	 *
	 * @param string $phone Rohwert.
	 * @return bool
	 */
	public function is_valid_phone( $phone ) {
		$digits = preg_replace( '/\D+/', '', (string) $phone );
		$len    = strlen( (string) $digits );
		return $len >= 6 && $len <= 15;
	}

	/**
	 * Speichert die bestellrelevanten Einstellungen als Snapshot in der Bestellung.
	 *
	 * So bleiben Danke-Seite, E-Mail und Admin-Ansicht einer Bestellung korrekt,
	 * auch wenn der Shop den Ablauf, die Nummer oder den QR-Code später ändert.
	 * Der Marker «_bf_twint_snapshot» (= Plugin-Version) zeigt an, dass ein Snapshot
	 * vorliegt; nur dann werden die gespeicherten – auch leere – Werte verwendet.
	 *
	 * Setzt die Meta nur in den Speicher; das eigentliche $order->save() erfolgt
	 * durch den Aufrufer.
	 *
	 * @param WC_Order $order Bestellung.
	 * @return void
	 */
	public function store_settings_snapshot( $order ) {
		$order->update_meta_data( '_bf_twint_snapshot', BF_TWINT_VERSION );
		$order->update_meta_data( '_bf_twint_mode', $this->mode );
		$order->update_meta_data( '_bf_twint_shop_phone', $this->phone );
		$order->update_meta_data( '_bf_twint_account_name', $this->account_name );
		$order->update_meta_data( '_bf_twint_qr_image', $this->qr_image );
		$order->update_meta_data( '_bf_twint_instructions', $this->instructions );
	}

	/**
	 * Liest eine bestellrelevante Einstellung – bevorzugt aus dem Order-Snapshot,
	 * sonst aus den aktuellen Plugin-Einstellungen (Altbestellungen ohne Snapshot).
	 *
	 * @param WC_Order $order Bestellung.
	 * @param string   $key   Einer von: mode, shop_phone, account_name, qr_image, instructions.
	 * @return string
	 */
	private function order_setting( $order, $key ) {
		if ( '' !== (string) $order->get_meta( '_bf_twint_snapshot' ) ) {
			return (string) $order->get_meta( '_bf_twint_' . $key );
		}

		switch ( $key ) {
			case 'mode':
				return (string) $this->mode;
			case 'shop_phone':
				return (string) $this->phone;
			case 'account_name':
				return (string) $this->account_name;
			case 'qr_image':
				return (string) $this->qr_image;
			case 'instructions':
				return (string) $this->instructions;
		}
		return '';
	}

	/**
	 * Zahlung verarbeiten: Bestellung auf «In Wartestellung», kein API-Call.
	 *
	 * @param int $order_id Bestell-ID.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return array( 'result' => 'failure' );
		}

		// Bestellrelevante Einstellungen einfrieren (vor dem späteren save()).
		$this->store_settings_snapshot( $order );

		if ( 'request' === $this->mode ) {
			$phone = isset( $_POST['bf_twint_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['bf_twint_phone'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the checkout nonce.

			// Block-Checkout liefert die Nummer als paymentMethodData (siehe Blocks-Klasse).
			if ( '' === $phone ) {
				$phone = (string) $order->get_meta( '_bf_twint_customer_phone' );
			}

			// Defensive Nachprüfung: ohne gültige Nummer keine Bestellung anlegen.
			if ( ! $this->is_valid_phone( $phone ) ) {
				wc_add_notice( __( 'Bitte gib eine gültige TWINT-Handynummer an, damit wir die Zahlung anfordern können.', 'twint-for-woocommerce' ), 'error' );
				return array( 'result' => 'failure' );
			}

			$phone = $this->normalize_phone( $phone );
			$order->update_meta_data( '_bf_twint_customer_phone', $phone );

			$order->update_status(
				'on-hold',
				sprintf(
					/* translators: %s: customer TWINT phone number. */
					__( 'TWINT: Zahlungsanforderung an %s senden.', 'twint-for-woocommerce' ),
					$phone ? $phone : __( '(keine Nummer angegeben)', 'twint-for-woocommerce' )
				)
			);
		} else {
			$order->update_status( 'on-hold', __( 'TWINT: Warten auf Zahlungseingang (Kunde sendet).', 'twint-for-woocommerce' ) );
		}

		$order->save();

		wc_reduce_stock_levels( $order_id );

		if ( WC()->cart ) {
			WC()->cart->empty_cart();
		}

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}

	/**
	 * Detail-Text für Danke-Seite und E-Mail (HTML).
	 *
	 * @param WC_Order $order   Bestellung.
	 * @param string   $context «thankyou» (mit Kopier-Button) oder «email» (reiner Text).
	 * @return string
	 */
	private function details_html( $order, $context = 'email' ) {
		$out = '';

		$mode         = $this->order_setting( $order, 'mode' );
		$shop_phone   = $this->order_setting( $order, 'shop_phone' );
		$account_name = $this->order_setting( $order, 'account_name' );
		$qr_image     = $this->order_setting( $order, 'qr_image' );
		$instructions = $this->order_setting( $order, 'instructions' );

		if ( 'request' === $mode ) {
			$phone = $order->get_meta( '_bf_twint_customer_phone' );
			$out  .= '<p>' . sprintf(
				/* translators: %s: customer phone number (may be empty). */
				wp_kses_post( __( 'Wir senden dir in Kürze eine <strong>TWINT-Zahlungsanforderung</strong>%s. Bitte bestätige die Zahlung in deiner TWINT-App.', 'twint-for-woocommerce' ) ),
				$phone ? ' ' . sprintf(
					/* translators: %s: phone number. */
					esc_html__( 'an %s', 'twint-for-woocommerce' ),
					'<strong>' . esc_html( $phone ) . '</strong>'
				) : ''
			) . '</p>';
		} else {
			$out .= '<p>' . sprintf(
				/* translators: 1: order total, 2: shop TWINT phone (may be empty). */
				wp_kses_post( __( 'Bitte sende %1$s via <strong>TWINT</strong>%2$s.', 'twint-for-woocommerce' ) ),
				'<strong>' . wp_kses_post( $order->get_formatted_order_total() ) . '</strong>',
				$shop_phone ? ' ' . sprintf(
					/* translators: %s: phone number. */
					esc_html__( 'an %s', 'twint-for-woocommerce' ),
					'<strong>' . esc_html( $shop_phone ) . '</strong>'
				) : ''
			) . '</p>';

			if ( $account_name ) {
				$out .= '<p>' . sprintf(
					/* translators: %s: account holder name. */
					esc_html__( 'Kontoinhaber: %s', 'twint-for-woocommerce' ),
					'<strong>' . esc_html( $account_name ) . '</strong>'
				) . '</p>';
			}

			$copy_button = '';
			if ( 'thankyou' === $context ) {
				$copy_button = ' <button type="button" class="button bf-twint-copy"'
					. ' data-bf-twint-copy="' . esc_attr( $order->get_order_number() ) . '"'
					. ' data-copied="' . esc_attr__( 'Kopiert!', 'twint-for-woocommerce' ) . '"'
					. ' aria-label="' . esc_attr__( 'Bestellnummer kopieren', 'twint-for-woocommerce' ) . '">'
					. esc_html__( 'Kopieren', 'twint-for-woocommerce' ) . '</button>';
			}

			$out .= '<p>' . sprintf(
				/* translators: %s: order number. */
				wp_kses_post( __( 'Verwende deine Bestellnummer %s als Mitteilung.', 'twint-for-woocommerce' ) ),
				'<strong>#' . esc_html( $order->get_order_number() ) . '</strong>'
			) . $copy_button . '</p>';

			if ( $qr_image ) {
				$out .= '<p><img src="' . esc_url( $qr_image ) . '" alt="' . esc_attr__( 'TWINT-QR-Code', 'twint-for-woocommerce' ) . '" style="max-width:220px;height:auto;border:1px solid #eee;padding:8px;background:#fff" /></p>';
			}
		}

		if ( $instructions ) {
			$out .= wpautop( wp_kses_post( $instructions ) );
		}

		return $out;
	}

	/**
	 * Detail-Text für die Plain-Text-Bestell-E-Mail (reiner Text, kein HTML).
	 *
	 * @param WC_Order $order Bestellung.
	 * @return string
	 */
	private function details_text( $order ) {
		$lines = array();

		$mode         = $this->order_setting( $order, 'mode' );
		$shop_phone   = $this->order_setting( $order, 'shop_phone' );
		$account_name = $this->order_setting( $order, 'account_name' );
		$instructions = $this->order_setting( $order, 'instructions' );

		if ( 'request' === $mode ) {
			$phone   = $order->get_meta( '_bf_twint_customer_phone' );
			$lines[] = $phone
				? sprintf(
					/* translators: %s: customer TWINT phone number. */
					__( 'Wir senden dir in Kürze eine TWINT-Zahlungsanforderung an %s. Bitte bestätige die Zahlung in deiner TWINT-App.', 'twint-for-woocommerce' ),
					$phone
				)
				: __( 'Wir senden dir in Kürze eine TWINT-Zahlungsanforderung. Bitte bestätige die Zahlung in deiner TWINT-App.', 'twint-for-woocommerce' );
		} else {
			$total   = wp_strip_all_tags( $order->get_formatted_order_total() );
			$lines[] = $shop_phone
				? sprintf(
					/* translators: 1: order total, 2: shop TWINT phone number. */
					__( 'Bitte sende %1$s via TWINT an %2$s.', 'twint-for-woocommerce' ),
					$total,
					$shop_phone
				)
				: sprintf(
					/* translators: %s: order total. */
					__( 'Bitte sende %s via TWINT.', 'twint-for-woocommerce' ),
					$total
				);

			if ( $account_name ) {
				$lines[] = sprintf(
					/* translators: %s: account holder name. */
					__( 'Kontoinhaber: %s', 'twint-for-woocommerce' ),
					$account_name
				);
			}

			$lines[] = sprintf(
				/* translators: %s: order number. */
				__( 'Verwende deine Bestellnummer %s als Mitteilung.', 'twint-for-woocommerce' ),
				'#' . $order->get_order_number()
			);
			// Der QR-Code wird in der Plain-Text-Mail bewusst weggelassen (nicht darstellbar).
		}

		if ( $instructions ) {
			$lines[] = wp_strip_all_tags( $instructions );
		}

		return implode( "\n\n", $lines );
	}

	/**
	 * Danke-Seite.
	 *
	 * @param int $order_id Bestell-ID.
	 */
	public function thankyou_page( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		wp_enqueue_script(
			'bf-twint-frontend',
			BF_TWINT_URL . 'assets/js/frontend.js',
			array(),
			BF_TWINT_VERSION,
			true
		);

		// Kopier-Button (data-Attribute) zusätzlich zu wp_kses_post erlauben.
		$allowed                       = wp_kses_allowed_html( 'post' );
		$allowed['button']             = array(
			'type'              => true,
			'class'             => true,
			'aria-label'        => true,
			'data-bf-twint-copy' => true,
			'data-copied'       => true,
		);

		echo '<section class="woocommerce-bf-twint">' . wp_kses( $this->details_html( $order, 'thankyou' ), $allowed ) . '</section>';
	}

	/**
	 * Anweisungen in der Bestell-E-Mail an den Kunden.
	 *
	 * @param WC_Order $order          Bestellung.
	 * @param bool     $sent_to_admin  An Admin?
	 * @param bool     $plain_text     Klartext?
	 */
	public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
		if ( $sent_to_admin || $order->get_payment_method() !== $this->id ) {
			return;
		}
		if ( ! $order->has_status( array( 'on-hold', 'pending' ) ) ) {
			return;
		}

		if ( $plain_text ) {
			echo esc_html( $this->details_text( $order ) ) . "\n\n";
			return;
		}

		echo wp_kses_post( $this->details_html( $order ) );
	}

	/**
	 * Admin-Bestellansicht: Kundennummer / QR-Hinweis + Vorgehen.
	 *
	 * @param WC_Order $order Bestellung.
	 */
	public function admin_order_details( $order ) {
		if ( $order->get_payment_method() !== $this->id ) {
			return;
		}

		$total = $order->get_currency() . ' ' . number_format( (float) $order->get_total(), 2, '.', "'" );
		$mode  = $this->order_setting( $order, 'mode' );

		echo '<div style="margin-top:10px;padding:10px 12px;background:#f6efe2;border:1px solid #d9c9a3;border-radius:4px">';

		if ( 'request' === $mode ) {
			$phone = $order->get_meta( '_bf_twint_customer_phone' );
			echo '<p style="margin:0 0 6px"><strong>' . esc_html__( 'TWINT-Handynummer (Kunde):', 'twint-for-woocommerce' ) . '</strong> ' . ( $phone ? esc_html( $phone ) : '<em>' . esc_html__( 'keine', 'twint-for-woocommerce' ) . '</em>' ) . '</p>';
			echo '<p style="margin:0;font-size:12px;color:#555"><strong>' . esc_html__( 'Vorgehen:', 'twint-for-woocommerce' ) . '</strong><br>';
			echo sprintf(
				/* translators: 1: amount, 2: order number. */
				esc_html__( '1. In der TWINT-App den Betrag (%1$s) an diese Nummer anfordern – Bestellnummer %2$s als Mitteilung.', 'twint-for-woocommerce' ),
				'<strong>' . esc_html( $total ) . '</strong>',
				'<strong>#' . esc_html( $order->get_order_number() ) . '</strong>'
			) . '<br>';
			echo esc_html__( '2. Nach Zahlungseingang: Status auf «In Bearbeitung» setzen.', 'twint-for-woocommerce' ) . '<br>';
			echo esc_html__( '3. Nach Versand: «Abgeschlossen».', 'twint-for-woocommerce' ) . '</p>';
		} else {
			echo '<p style="margin:0 0 6px"><strong>' . esc_html__( 'TWINT-Ablauf:', 'twint-for-woocommerce' ) . '</strong> ' . esc_html__( 'Kunde sendet den Betrag selbst.', 'twint-for-woocommerce' ) . '</p>';
			echo '<p style="margin:0;font-size:12px;color:#555"><strong>' . esc_html__( 'Vorgehen:', 'twint-for-woocommerce' ) . '</strong><br>';
			echo sprintf(
				/* translators: 1: amount, 2: order number. */
				esc_html__( '1. Prüfe in der TWINT-App den Eingang von %1$s mit Mitteilung %2$s.', 'twint-for-woocommerce' ),
				'<strong>' . esc_html( $total ) . '</strong>',
				'<strong>#' . esc_html( $order->get_order_number() ) . '</strong>'
			) . '<br>';
			echo esc_html__( '2. Nach Zahlungseingang: Status auf «In Bearbeitung» setzen.', 'twint-for-woocommerce' ) . '<br>';
			echo esc_html__( '3. Nach Versand: «Abgeschlossen».', 'twint-for-woocommerce' ) . '</p>';
		}

		// Ein-Klick-Bestätigung: Bestellung als bezahlt freigeben (nur solange offen).
		if ( $order->has_status( array( 'on-hold', 'pending' ) ) ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:10px">';
			echo '<input type="hidden" name="action" value="bf_twint_mark_paid" />';
			echo '<input type="hidden" name="order_id" value="' . esc_attr( $order->get_id() ) . '" />';
			wp_nonce_field( 'bf_twint_mark_paid_' . $order->get_id() );
			echo '<button type="submit" class="button button-primary">' . esc_html__( 'Zahlung erhalten – Bestellung freigeben', 'twint-for-woocommerce' ) . '</button>';
			echo '</form>';
		}

		echo '</div>';
	}

	/**
	 * Verarbeitet den «Zahlung erhalten»-Button aus der Bestellansicht.
	 *
	 * Setzt die Bestellung per WooCommerce-Standard auf bezahlt (payment_complete →
	 * Status «In Bearbeitung» bzw. «Abgeschlossen» bei rein virtuellen Bestellungen)
	 * und hinterlegt eine Notiz. Reiner Form-POST mit Nonce, kein JavaScript.
	 *
	 * @return void
	 */
	public static function handle_mark_paid() {
		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;

		check_admin_referer( 'bf_twint_mark_paid_' . $order_id );

		if ( ! current_user_can( 'edit_shop_orders' ) && ! current_user_can( 'edit_shop_order', $order_id ) ) {
			wp_die( esc_html__( 'Du hast keine Berechtigung, diese Bestellung zu bearbeiten.', 'twint-for-woocommerce' ) );
		}

		$order = $order_id ? wc_get_order( $order_id ) : false;

		if ( $order
			&& BF_TWINT_GATEWAY_ID === $order->get_payment_method()
			&& $order->has_status( array( 'on-hold', 'pending' ) )
		) {
			$order->add_order_note( __( 'TWINT-Zahlung von Hand als erhalten bestätigt.', 'twint-for-woocommerce' ), false );
			$order->payment_complete();
		}

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=wc-orders' ) );
		exit;
	}
}
