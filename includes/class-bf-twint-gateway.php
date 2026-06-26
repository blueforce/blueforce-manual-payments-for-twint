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
class BF_TWINT_Gateway extends WC_Payment_Gateway {

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
		$this->method_title       = __( 'TWINT', 'blueforce-manual-payments-for-twint' );
		$this->method_description = __( 'TWINT als Offline-Zahlung – ohne API, ohne Vertrag mit TWINT. Ablauf «Kunde sendet» (deine TWINT-Nummer/QR wird angezeigt) oder «Ich fordere an» (Kunde gibt seine TWINT-Nummer an). Der Zahlungseingang wird von Hand bestätigt.', 'blueforce-manual-payments-for-twint' );
		$this->has_fields         = true;
		$this->supports           = array( 'products' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title        = $this->get_option( 'title', __( 'TWINT', 'blueforce-manual-payments-for-twint' ) );
		$this->description  = $this->get_option( 'description' );
		$this->mode         = $this->get_option( 'mode', 'send' );
		$this->phone        = $this->get_option( 'phone' );
		$this->account_name = $this->get_option( 'account_name' );
		$this->qr_image     = $this->get_option( 'qr_image' );
		$this->instructions = $this->get_option( 'instructions' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
		add_action( 'admin_notices', array( $this, 'maybe_show_config_notice' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_assets' ) );
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
		// WooCommerce-Core-Filter (kein eigener Hook) – bewusst ohne Plugin-Prefix.
		return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
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
			wp_kses_post( __( '<strong>Blueforce Manual Payments for TWINT:</strong> Der Ablauf «Kunde sendet» ist aktiv, aber es ist weder eine TWINT-Handynummer noch ein QR-Code hinterlegt. Die Bezahlmethode wird im Checkout ausgeblendet, bis du die <a href="%s">Angaben ergänzt</a>.', 'blueforce-manual-payments-for-twint' ) ),
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
				'title'   => __( 'Aktivieren', 'blueforce-manual-payments-for-twint' ),
				'type'    => 'checkbox',
				'label'   => __( 'TWINT als Bezahlmethode aktivieren', 'blueforce-manual-payments-for-twint' ),
				'default' => 'no',
			),
			'title'        => array(
				'title'       => __( 'Titel', 'blueforce-manual-payments-for-twint' ),
				'type'        => 'text',
				'description' => __( 'Im Checkout angezeigter Name.', 'blueforce-manual-payments-for-twint' ),
				'default'     => __( 'TWINT', 'blueforce-manual-payments-for-twint' ),
				'desc_tip'    => true,
			),
			'description'  => array(
				'title'       => __( 'Beschreibung', 'blueforce-manual-payments-for-twint' ),
				'type'        => 'textarea',
				'description' => __( 'Kurztext unter dem Methodennamen im Checkout.', 'blueforce-manual-payments-for-twint' ),
				'default'     => __( 'Bezahle einfach und sicher mit TWINT.', 'blueforce-manual-payments-for-twint' ),
			),
			'mode'         => array(
				'title'       => __( 'Ablauf', 'blueforce-manual-payments-for-twint' ),
				'type'        => 'select',
				'description' => __( '«Kunde sendet»: deine TWINT-Nummer wird angezeigt. «Ich fordere an»: Kunde gibt seine TWINT-Nummer an, du forderst den Betrag.', 'blueforce-manual-payments-for-twint' ),
				'default'     => 'send',
				'options'     => array(
					'send'    => __( 'Kunde sendet an meine TWINT-Nummer', 'blueforce-manual-payments-for-twint' ),
					'request' => __( 'Ich fordere den Betrag an (Kunde gibt Nummer an)', 'blueforce-manual-payments-for-twint' ),
				),
				'desc_tip'    => true,
			),
			'phone'        => array(
				'title'       => __( 'Deine TWINT-Handynummer', 'blueforce-manual-payments-for-twint' ),
				'type'        => 'text',
				'description' => __( 'Nur bei «Kunde sendet»: Nummer, an die der Betrag gesendet wird (z. B. +41 79 123 45 67).', 'blueforce-manual-payments-for-twint' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'account_name' => array(
				'title'       => __( 'Name des Kontoinhabers', 'blueforce-manual-payments-for-twint' ),
				'type'        => 'text',
				'description' => __( 'Optional. Wird dem Kunden zur Kontrolle angezeigt (z. B. Firmen- oder Personenname).', 'blueforce-manual-payments-for-twint' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'qr_image'     => array(
				'title'       => __( 'TWINT-QR-Bild', 'blueforce-manual-payments-for-twint' ),
				'type'        => 'qr_image',
				'description' => __( 'Optional, nur bei «Kunde sendet». Lade in der TWINT-App unter «Geld empfangen» deinen QR-Code, speichere ihn als Bild und wähle ihn hier aus der Mediathek. Der Kunde kann ihn dann direkt scannen.', 'blueforce-manual-payments-for-twint' ),
				'default'     => '',
			),
			'instructions' => array(
				'title'       => __( 'Zusätzliche Hinweise', 'blueforce-manual-payments-for-twint' ),
				'type'        => 'textarea',
				'description' => __( 'Erscheint auf der Danke-Seite und in der Bestell-E-Mail.', 'blueforce-manual-payments-for-twint' ),
				'default'     => __( 'Wir bearbeiten deine Bestellung, sobald die Zahlung eingegangen ist.', 'blueforce-manual-payments-for-twint' ),
			),
		);
	}

	/**
	 * Lädt das Admin-CSS auf der TWINT-Einstellungsseite und in der Bestellansicht;
	 * den Media-Uploader (QR-Auswahl) nur auf der Einstellungsseite.
	 *
	 * @param string $hook Aktuelle Admin-Seite.
	 */
	public function admin_assets( $hook ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$section    = isset( $_GET['section'] ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$is_setting = 'woocommerce_page_wc-settings' === $hook && $this->id === $section;

		$screen   = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$is_order = 'woocommerce_page_wc-orders' === $hook || ( $screen && 'shop_order' === $screen->post_type );

		if ( ! $is_setting && ! $is_order ) {
			return;
		}

		wp_enqueue_style( 'bf-twint-admin', BF_TWINT_URL . 'assets/css/admin.css', array(), BF_TWINT_VERSION );

		if ( ! $is_setting ) {
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
				'title'  => __( 'TWINT-QR-Bild wählen', 'blueforce-manual-payments-for-twint' ),
				'button' => __( 'Dieses Bild verwenden', 'blueforce-manual-payments-for-twint' ),
			)
		);
	}

	/**
	 * Lädt das Frontend-CSS auf Checkout- und Danke-Seite.
	 *
	 * @return void
	 */
	public function frontend_assets() {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return;
		}
		wp_enqueue_style( 'bf-twint-frontend', BF_TWINT_URL . 'assets/css/frontend.css', array(), BF_TWINT_VERSION );
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
		$value     = $this->get_option( $key );

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?> <?php echo wp_kses_post( $this->get_tooltip_html( $data ) ); ?></label>
			</th>
			<td class="forminp">
				<fieldset>
					<legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
					<input class="input-text regular-input <?php echo esc_attr( $data['class'] ); ?>" type="url" name="<?php echo esc_attr( $field_key ); ?>" id="<?php echo esc_attr( $field_key ); ?>" style="<?php echo esc_attr( $data['css'] ); ?>" value="<?php echo esc_attr( $value ); ?>" placeholder="<?php echo esc_attr( $data['placeholder'] ); ?>" />
					<button type="button" class="button bf-twint-qr-upload" data-target="<?php echo esc_attr( $field_key ); ?>"><?php esc_html_e( 'Bild auswählen', 'blueforce-manual-payments-for-twint' ); ?></button>
					<button type="button" class="button bf-twint-qr-remove" data-target="<?php echo esc_attr( $field_key ); ?>" style="<?php echo $value ? '' : 'display:none'; ?>"><?php esc_html_e( 'Entfernen', 'blueforce-manual-payments-for-twint' ); ?></button>
					<?php echo wp_kses_post( $this->get_description_html( $data ) ); ?>
					<div class="bf-twint-qr-preview">
						<?php if ( $value ) : ?>
							<img src="<?php echo esc_url( $value ); ?>" alt="" />
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
			echo wp_kses_post( wpautop( $this->description ) );
		}

		if ( 'request' === $this->mode ) {
			echo '<p class="form-row form-row-wide bf-twint-field">';
			echo '<label for="bf_twint_phone">' . esc_html__( 'TWINT-Handynummer', 'blueforce-manual-payments-for-twint' ) . ' <abbr class="required" title="' . esc_attr__( 'Pflichtfeld', 'blueforce-manual-payments-for-twint' ) . '">*</abbr></label>';
			echo '<input type="tel" id="bf_twint_phone" name="bf_twint_phone" autocomplete="tel" placeholder="+41 79 123 45 67" required aria-required="true" aria-describedby="bf_twint_phone_hint" />';
			echo '<span id="bf_twint_phone_hint" class="bf-twint-hint">' . esc_html__( 'An diese Nummer senden wir dir eine TWINT-Zahlungsanforderung.', 'blueforce-manual-payments-for-twint' ) . '</span>';
			echo '</p>';
		} elseif ( $this->phone ) {
			printf(
				'<p style="margin:.5em 0 0">%s</p>',
				sprintf(
					/* translators: %s: TWINT phone number of the shop. */
					esc_html__( 'Sende den Betrag via TWINT an %s – die genauen Angaben erhältst du nach der Bestellung.', 'blueforce-manual-payments-for-twint' ),
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
			$phone = isset( $_POST['bf_twint_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['bf_twint_phone'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the checkout nonce.
			if ( ! $this->is_valid_phone( $phone ) ) {
				wc_add_notice( __( 'Bitte gib eine gültige TWINT-Handynummer an, damit wir die Zahlung anfordern können.', 'blueforce-manual-payments-for-twint' ), 'error' );
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
				wc_add_notice( __( 'Bitte gib eine gültige TWINT-Handynummer an, damit wir die Zahlung anfordern können.', 'blueforce-manual-payments-for-twint' ), 'error' );
				return array( 'result' => 'failure' );
			}

			$phone = $this->normalize_phone( $phone );
			$order->update_meta_data( '_bf_twint_customer_phone', $phone );

			$order->update_status(
				'on-hold',
				sprintf(
					/* translators: %s: customer TWINT phone number. */
					__( 'TWINT: Zahlungsanforderung an %s senden.', 'blueforce-manual-payments-for-twint' ),
					$phone ? $phone : __( '(keine Nummer angegeben)', 'blueforce-manual-payments-for-twint' )
				)
			);
		} else {
			// Defensive Nachprüfung: ohne Zahlungsziel (Nummer/QR) keine Bestellung
			// anlegen – normalerweise blendet is_available() TWINT bereits aus.
			if ( ! $this->has_send_target() ) {
				wc_add_notice( __( 'TWINT ist derzeit nicht vollständig konfiguriert. Bitte wähle eine andere Zahlungsart oder versuche es später erneut.', 'blueforce-manual-payments-for-twint' ), 'error' );
				return array( 'result' => 'failure' );
			}
			$order->update_status( 'on-hold', __( 'TWINT: Warten auf Zahlungseingang (Kunde sendet).', 'blueforce-manual-payments-for-twint' ) );
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
				wp_kses_post( __( 'Wir senden dir in Kürze eine <strong>TWINT-Zahlungsanforderung</strong>%s. Bitte bestätige die Zahlung in deiner TWINT-App.', 'blueforce-manual-payments-for-twint' ) ),
				$phone ? ' ' . sprintf(
					/* translators: %s: phone number. */
					esc_html__( 'an %s', 'blueforce-manual-payments-for-twint' ),
					'<strong>' . esc_html( $phone ) . '</strong>'
				) : ''
			) . '</p>';
		} else {
			$out .= '<p>' . sprintf(
				/* translators: 1: order total, 2: shop TWINT phone (may be empty). */
				wp_kses_post( __( 'Bitte sende %1$s via <strong>TWINT</strong>%2$s.', 'blueforce-manual-payments-for-twint' ) ),
				'<strong>' . wp_kses_post( $order->get_formatted_order_total() ) . '</strong>',
				$shop_phone ? ' ' . sprintf(
					/* translators: %s: phone number. */
					esc_html__( 'an %s', 'blueforce-manual-payments-for-twint' ),
					'<strong>' . esc_html( $shop_phone ) . '</strong>'
				) : ''
			) . '</p>';

			if ( $account_name ) {
				$out .= '<p>' . sprintf(
					/* translators: %s: account holder name. */
					esc_html__( 'Kontoinhaber: %s', 'blueforce-manual-payments-for-twint' ),
					'<strong>' . esc_html( $account_name ) . '</strong>'
				) . '</p>';
			}

			$copy_button = '';
			if ( 'thankyou' === $context ) {
				$copy_button = ' <button type="button" class="button bf-twint-copy"'
					. ' data-bf-twint-copy="' . esc_attr( $order->get_order_number() ) . '"'
					. ' data-copied="' . esc_attr__( 'Kopiert!', 'blueforce-manual-payments-for-twint' ) . '"'
					. ' aria-live="polite"'
					. ' aria-label="' . esc_attr__( 'Bestellnummer kopieren', 'blueforce-manual-payments-for-twint' ) . '">'
					. esc_html__( 'Kopieren', 'blueforce-manual-payments-for-twint' ) . '</button>';
			}

			$out .= '<p>' . sprintf(
				/* translators: %s: order number. */
				wp_kses_post( __( 'Verwende deine Bestellnummer %s als Mitteilung.', 'blueforce-manual-payments-for-twint' ) ),
				'<strong>#' . esc_html( $order->get_order_number() ) . '</strong>'
			) . $copy_button . '</p>';

			if ( $qr_image ) {
				$out .= '<p><img src="' . esc_url( $qr_image ) . '" alt="' . esc_attr__( 'TWINT-QR-Code', 'blueforce-manual-payments-for-twint' ) . '" class="bf-twint-qr" /></p>';
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
					__( 'Wir senden dir in Kürze eine TWINT-Zahlungsanforderung an %s. Bitte bestätige die Zahlung in deiner TWINT-App.', 'blueforce-manual-payments-for-twint' ),
					$phone
				)
				: __( 'Wir senden dir in Kürze eine TWINT-Zahlungsanforderung. Bitte bestätige die Zahlung in deiner TWINT-App.', 'blueforce-manual-payments-for-twint' );
		} else {
			$total   = wp_strip_all_tags( $order->get_formatted_order_total() );
			$lines[] = $shop_phone
				? sprintf(
					/* translators: 1: order total, 2: shop TWINT phone number. */
					__( 'Bitte sende %1$s via TWINT an %2$s.', 'blueforce-manual-payments-for-twint' ),
					$total,
					$shop_phone
				)
				: sprintf(
					/* translators: %s: order total. */
					__( 'Bitte sende %s via TWINT.', 'blueforce-manual-payments-for-twint' ),
					$total
				);

			if ( $account_name ) {
				$lines[] = sprintf(
					/* translators: %s: account holder name. */
					__( 'Kontoinhaber: %s', 'blueforce-manual-payments-for-twint' ),
					$account_name
				);
			}

			$lines[] = sprintf(
				/* translators: %s: order number. */
				__( 'Verwende deine Bestellnummer %s als Mitteilung.', 'blueforce-manual-payments-for-twint' ),
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
		$allowed           = wp_kses_allowed_html( 'post' );
		$allowed['button'] = array(
			'type'               => true,
			'class'              => true,
			'aria-label'         => true,
			'aria-live'          => true,
			'data-bf-twint-copy' => true,
			'data-copied'        => true,
		);

		echo '<section class="woocommerce-bf-twint">' . wp_kses( $this->details_html( $order, 'thankyou' ), $allowed ) . '</section>';
	}

	/**
	 * Anweisungen in der Bestell-E-Mail an den Kunden.
	 *
	 * @param WC_Order $order          Bestellung.
	 * @param bool     $sent_to_admin  An den Admin gerichtet.
	 * @param bool     $plain_text     Klartext-Variante.
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

		echo '<div class="bf-twint-admin-box">';

		if ( 'request' === $mode ) {
			$phone = $order->get_meta( '_bf_twint_customer_phone' );
			echo '<p><strong>' . esc_html__( 'TWINT-Handynummer (Kunde):', 'blueforce-manual-payments-for-twint' ) . '</strong> ' . ( $phone ? esc_html( $phone ) : '<em>' . esc_html__( 'keine', 'blueforce-manual-payments-for-twint' ) . '</em>' ) . '</p>';
			echo '<p class="bf-twint-steps"><strong>' . esc_html__( 'Vorgehen:', 'blueforce-manual-payments-for-twint' ) . '</strong><br>';
			echo sprintf(
				/* translators: 1: amount, 2: order number. */
				esc_html__( '1. In der TWINT-App den Betrag (%1$s) an diese Nummer anfordern – Bestellnummer %2$s als Mitteilung.', 'blueforce-manual-payments-for-twint' ),
				'<strong>' . esc_html( $total ) . '</strong>',
				'<strong>#' . esc_html( $order->get_order_number() ) . '</strong>'
			) . '<br>';
			echo esc_html__( '2. Nach Zahlungseingang: Status auf «In Bearbeitung» setzen.', 'blueforce-manual-payments-for-twint' ) . '<br>';
			echo esc_html__( '3. Nach Versand: «Abgeschlossen».', 'blueforce-manual-payments-for-twint' ) . '</p>';
		} else {
			echo '<p><strong>' . esc_html__( 'TWINT-Ablauf:', 'blueforce-manual-payments-for-twint' ) . '</strong> ' . esc_html__( 'Kunde sendet den Betrag selbst.', 'blueforce-manual-payments-for-twint' ) . '</p>';
			echo '<p class="bf-twint-steps"><strong>' . esc_html__( 'Vorgehen:', 'blueforce-manual-payments-for-twint' ) . '</strong><br>';
			echo sprintf(
				/* translators: 1: amount, 2: order number. */
				esc_html__( '1. Prüfe in der TWINT-App den Eingang von %1$s mit Mitteilung %2$s.', 'blueforce-manual-payments-for-twint' ),
				'<strong>' . esc_html( $total ) . '</strong>',
				'<strong>#' . esc_html( $order->get_order_number() ) . '</strong>'
			) . '<br>';
			echo esc_html__( '2. Nach Zahlungseingang: Status auf «In Bearbeitung» setzen.', 'blueforce-manual-payments-for-twint' ) . '<br>';
			echo esc_html__( '3. Nach Versand: «Abgeschlossen».', 'blueforce-manual-payments-for-twint' ) . '</p>';
		}

		// Ein-Klick-Bestätigung: Bestellung als bezahlt freigeben (nur solange offen
		// und nur für Nutzer, die Bestellungen bearbeiten dürfen).
		$can_mark_paid = current_user_can( 'edit_shop_orders' ) || current_user_can( 'edit_shop_order', $order->get_id() );
		if ( $can_mark_paid && $order->has_status( array( 'on-hold', 'pending' ) ) ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			echo '<input type="hidden" name="action" value="bf_twint_mark_paid" />';
			echo '<input type="hidden" name="order_id" value="' . esc_attr( $order->get_id() ) . '" />';
			wp_nonce_field( 'bf_twint_mark_paid_' . $order->get_id() );
			echo '<button type="submit" class="button button-primary">' . esc_html__( 'Zahlung erhalten – Bestellung freigeben', 'blueforce-manual-payments-for-twint' ) . '</button>';
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
			wp_die( esc_html__( 'Du hast keine Berechtigung, diese Bestellung zu bearbeiten.', 'blueforce-manual-payments-for-twint' ) );
		}

		$order = $order_id ? wc_get_order( $order_id ) : false;

		if ( $order
			&& BF_TWINT_GATEWAY_ID === $order->get_payment_method()
			&& $order->has_status( array( 'on-hold', 'pending' ) )
		) {
			$user = wp_get_current_user();
			$by   = ( $user && $user->exists() ) ? $user->display_name : __( 'unbekannt', 'blueforce-manual-payments-for-twint' );
			$order->add_order_note(
				sprintf(
					/* translators: %s: display name of the admin who confirmed the payment. */
					__( 'TWINT-Zahlung von Hand als erhalten bestätigt (durch %s).', 'blueforce-manual-payments-for-twint' ),
					$by
				),
				false
			);
			$order->payment_complete();
		}

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=wc-orders' ) );
		exit;
	}
}
