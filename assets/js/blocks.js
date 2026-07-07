/**
 * Blueforce Manual Payments for TWINT — Block-Checkout-Integration (ohne Build-Step, via globale wc/wp-Objekte).
 *
 * Registriert die Bezahlmethode "bf_twint". Im Ablauf "request" erscheint ein Pflichtfeld
 * für die TWINT-Handynummer und wird als paymentMethodData (bf_twint_phone) übergeben.
 *
 * @package Blueforce_Manual_Payments_For_TWINT
 */
( function () {
	if ( ! window.wc || ! window.wc.wcBlocksRegistry || ! window.wp ) {
		return;
	}

	var registerPaymentMethod = window.wc.wcBlocksRegistry.registerPaymentMethod;
	var getSetting = ( window.wc.wcSettings && window.wc.wcSettings.getSetting ) || function ( k, d ) { return d; };
	var el = window.wp.element.createElement;
	var useState = window.wp.element.useState;
	var useEffect = window.wp.element.useEffect;
	var decode = ( window.wp.htmlEntities && window.wp.htmlEntities.decodeEntities ) || function ( s ) { return s; };
	var __ = ( window.wp.i18n && window.wp.i18n.__ ) || function ( s ) { return s; };
	var sprintf = ( window.wp.i18n && window.wp.i18n.sprintf ) || function ( s ) { return s; };

	var settings = getSetting( 'bf_twint_data', {} );
	var label = decode( settings.title || __( 'TWINT', 'blueforce-manual-payments-for-twint' ) );
	var description = decode( settings.description || '' );
	var mode = settings.mode || 'send';
	var phone = decode( settings.phone || '' );

	// Methodenname im Block-Checkout.
	var Label = function () {
		return el( 'span', null, label );
	};

	var Content = function ( props ) {
		var onPaymentSetup = props.eventRegistration.onPaymentSetup;
		var responseTypes = props.emitResponse.responseTypes;
		var phoneState = useState( '' );
		var value = phoneState[ 0 ];
		var setValue = phoneState[ 1 ];

		useEffect( function () {
			var unsubscribe = onPaymentSetup( function () {
				if ( 'request' === mode ) {
					var digits = ( value || '' ).replace( /[^0-9]/g, '' );
					if ( digits.length < 6 || digits.length > 15 ) {
						return {
							type: responseTypes.ERROR,
							message: __( 'Please enter your TWINT mobile number so we can request the payment.', 'blueforce-manual-payments-for-twint' ),
						};
					}
					return {
						type: responseTypes.SUCCESS,
						meta: { paymentMethodData: { bf_twint_phone: value } },
					};
				}
				return { type: responseTypes.SUCCESS };
			} );
			return unsubscribe;
		}, [ value, onPaymentSetup, responseTypes ] );

		var children = [];
		if ( description ) {
			children.push( el( 'p', { key: 'desc' }, description ) );
		}

		if ( 'request' === mode ) {
			children.push( el( 'label', { key: 'lbl', htmlFor: 'bf_twint_phone', style: { display: 'block', marginTop: '8px', fontWeight: 600 } }, [
				__( 'TWINT mobile number', 'blueforce-manual-payments-for-twint' ),
				el( 'abbr', { key: 'req', className: 'required', title: __( 'Required field', 'blueforce-manual-payments-for-twint' ), style: { color: '#b32d2e', textDecoration: 'none', marginLeft: '2px' } }, '*' ),
			] ) );
			children.push( el( 'input', {
				key: 'inp',
				id: 'bf_twint_phone',
				type: 'tel',
				value: value,
				placeholder: '+41 79 123 45 67',
				autoComplete: 'tel',
				required: true,
				'aria-required': 'true',
				'aria-describedby': 'bf_twint_phone_hint',
				onChange: function ( e ) { setValue( e.target.value ); },
				style: { width: '100%', padding: '10px', marginTop: '4px', boxSizing: 'border-box' },
			} ) );
			children.push( el( 'span', { key: 'hint', id: 'bf_twint_phone_hint', style: { display: 'block', fontSize: '.9em', color: '#666', marginTop: '4px' } }, __( 'We will send you a TWINT payment request to this number.', 'blueforce-manual-payments-for-twint' ) ) );
		} else if ( phone ) {
			children.push( el( 'p', { key: 'mphone', style: { marginTop: '8px' } }, sprintf( __( 'Send the amount via TWINT to %s – you will receive the details after ordering.', 'blueforce-manual-payments-for-twint' ), phone ) ) );
		}

		return el( 'div', { className: 'bf-twint-fields' }, children );
	};

	registerPaymentMethod( {
		name: 'bf_twint',
		label: el( Label ),
		content: el( Content ),
		edit: el( Content ),
		canMakePayment: function () { return true; },
		ariaLabel: label,
		supports: { features: ( settings.supports || [ 'products' ] ) },
	} );
} )();
