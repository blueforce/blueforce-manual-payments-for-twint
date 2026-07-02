/**
 * Blueforce Manual Payments for TWINT — Media-Uploader für das QR-Bild-Feld (Admin).
 *
 * Öffnet die WordPress-Mediathek über den Button neben dem Feld «TWINT-QR-Bild»
 * und schreibt die gewählte Bild-URL ins Feld (inkl. Vorschau).
 *
 * @package Blueforce_Manual_Payments_For_TWINT
 */
( function ( $ ) {
	'use strict';

	var l10n = window.bfTwintQr || { title: 'Bild wählen', button: 'Verwenden' };

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
