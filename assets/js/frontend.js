/**
 * Blueforce Manual Payments for TWINT — Frontend-Helfer für die Danke-Seite.
 *
 * Kopiert die Bestellnummer (TWINT-Mitteilung) per Klick in die Zwischenablage.
 * Die Beschriftung («Kopieren» / «Kopiert!») kommt übersetzt aus den data-Attributen,
 * daher braucht dieses Script keine eigene Übersetzungsdatei.
 *
 * @package Blueforce_Manual_Payments_For_TWINT
 */
( function () {
	function flash( btn, text ) {
		var original = btn.textContent;
		btn.textContent = text;
		setTimeout( function () {
			btn.textContent = original;
		}, 1500 );
	}

	document.addEventListener( 'click', function ( e ) {
		var btn = e.target && e.target.closest ? e.target.closest( '.bf-twint-copy' ) : null;
		if ( ! btn ) {
			return;
		}
		e.preventDefault();

		var value = btn.getAttribute( 'data-bf-twint-copy' ) || '';
		var done  = btn.getAttribute( 'data-copied' ) || 'OK';

		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( value ).then(
				function () { flash( btn, done ); },
				function () { /* still: keine Berechtigung – nichts tun */ }
			);
			return;
		}

		// Fallback für ältere Browser ohne Clipboard-API.
		var ta = document.createElement( 'textarea' );
		ta.value = value;
		ta.setAttribute( 'readonly', '' );
		ta.style.position = 'absolute';
		ta.style.left = '-9999px';
		document.body.appendChild( ta );
		ta.select();
		try {
			document.execCommand( 'copy' );
			flash( btn, done );
		} catch ( err ) {
			/* nichts */
		}
		document.body.removeChild( ta );
	} );
} )();
