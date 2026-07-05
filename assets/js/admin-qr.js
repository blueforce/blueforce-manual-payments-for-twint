/**
 * Blueforce Manual Payments for TWINT — Admin-JS für den Gateway-Einstellungen-Screen.
 *
 * Zwei Aufgaben:
 * 1. Media-Uploader für das QR-Bild-Feld: öffnet die WordPress-Mediathek über den
 *    Button neben dem Feld «TWINT-QR-Bild» und schreibt die gewählte Bild-URL ins
 *    Feld (inkl. Vorschau).
 * 2. Modus-abhängige Felder: blendet die nur für «Kunde sendet» relevanten Felder
 *    (TWINT-Nummer, Kontoinhaber, QR-Bild) aus, wenn der Ablauf «Ich fordere an»
 *    gewählt ist. Rein visuell – die Werte bleiben erhalten und werden serverseitig
 *    je nach Modus ohnehin ignoriert.
 *
 * @package Blueforce_Manual_Payments_For_TWINT
 */
( function ( $ ) {
	'use strict';

	var l10n = window.bfTwintQr || { title: 'Bild wählen', button: 'Verwenden' };

	// ── Modus-abhängige Sichtbarkeit der «Kunde sendet»-Felder ──────────────
	var SEND_ONLY_FIELDS = [
		'woocommerce_bf_twint_phone',
		'woocommerce_bf_twint_account_name',
		'woocommerce_bf_twint_qr_image',
	];

	function toggleModeFields() {
		var $mode = $( '#woocommerce_bf_twint_mode' );
		if ( ! $mode.length ) {
			return;
		}
		var showSend = 'send' === $mode.val();
		$.each( SEND_ONLY_FIELDS, function ( i, id ) {
			$( '#' + id ).closest( 'tr' ).toggle( showSend );
		} );
	}

	$( document ).on( 'change', '#woocommerce_bf_twint_mode', toggleModeFields );
	$( toggleModeFields );

	function previewFor( $input ) {
		return $input.closest( 'fieldset' ).find( '.bf-twint-qr-preview' );
	}

	function removeBtnFor( $input ) {
		return $input.closest( 'fieldset' ).find( '.bf-twint-qr-remove' );
	}

	$( document ).on( 'click', '.bf-twint-qr-upload', function ( e ) {
		e.preventDefault();

		var $input = $( '#' + $( this ).data( 'target' ) );

		var frame = window.wp.media( {
			title: l10n.title,
			button: { text: l10n.button },
			library: { type: 'image' },
			multiple: false,
		} );

		frame.on( 'select', function () {
			var att = frame.state().get( 'selection' ).first().toJSON();
			var url = ( att.sizes && att.sizes.medium ) ? att.sizes.medium.url : att.url;

			$input.val( url ).trigger( 'change' );
			previewFor( $input ).html(
				$( '<img>', {
					src: url,
					alt: '',
					css: { maxWidth: '160px', height: 'auto', border: '1px solid #ddd', padding: '4px', background: '#fff' },
				} )
			);
			removeBtnFor( $input ).show();
		} );

		frame.open();
	} );

	$( document ).on( 'click', '.bf-twint-qr-remove', function ( e ) {
		e.preventDefault();
		var $input = $( '#' + $( this ).data( 'target' ) );
		$input.val( '' ).trigger( 'change' );
		previewFor( $input ).empty();
		$( this ).hide();
	} );
} )( jQuery );
