/* global wp, wc */ /* CTStripe v1.8.0 — WooCommerce Blocks integration */
( function () {
    'use strict';

    var registry = wc.wcBlocksRegistry;
    var settings = wc.wcSettings;
    var element  = wp.element;

    if ( ! registry || ! settings || ! element ) {
        return;
    }

    var registerPaymentMethod = registry.registerPaymentMethod;
    var getSetting            = settings.getSetting;
    var createElement         = element.createElement;
    var useState              = element.useState;
    var useEffect             = element.useEffect;
    var useRef                = element.useRef;

    var cfg = getSetting( 'ctstripe_data', {} );

    if ( ! cfg.publishable_key ) {
        return;
    }

    function buildAppearance() {
        var a   = cfg.appearance || {};
        var app = { theme: a.theme || 'stripe', variables: {} };
        [ 'colorPrimary', 'colorBackground', 'colorText', 'colorDanger',
          'fontFamily', 'fontSizeBase', 'borderRadius', 'spacingUnit' ]
            .forEach( function ( k ) {
                if ( a[k] ) { app.variables[k] = a[k]; }
            } );
        if ( a.rules ) {
            try { app.rules = JSON.parse( a.rules ); } catch ( e ) {}
        }
        return app;
    }

    function getStripeInstance() {
        return window.Stripe( cfg.publishable_key );
    }

    // ── Payment Element component ─────────────────────────────────────────────

    function Content( props ) {
        var eventRegistration = props.eventRegistration;
        var emitResponse      = props.emitResponse;
        var billing           = props.billing;

        var stripeRef   = useRef( null );
        var elemsRef    = useRef( null );
        var mountRef    = useRef( null );
        var initedRef   = useRef( false );

        var errorState = useState( '' );
        var error      = errorState[0];
        var setError   = errorState[1];

        // ── Mount Stripe Payment Element once ─────────────────────────────────
        useEffect( function () {
            if ( initedRef.current || ! mountRef.current ) {
                return;
            }
            initedRef.current = true;

            var stripe = getStripeInstance();
            stripeRef.current = stripe;

            var cartTotal  = billing && billing.cartTotal;
            var currency   = ( ( cartTotal && cartTotal.currency_code ) || 'EUR' ).toLowerCase();
            var amount     = parseInt( ( cartTotal && cartTotal.value ) || '100', 10 );

            var elemParams = {
                mode:       'payment',
                amount:     amount || 100,
                currency:   currency,
                locale:     cfg.locale || 'auto',
                appearance: buildAppearance(),
            };
            if ( cfg.pmc_id ) {
                elemParams.paymentMethodConfiguration = cfg.pmc_id;
            }

            var elems = stripe.elements( elemParams );
            elemsRef.current = elems;

            var peLayout = cfg.pe_layout === 'tabs'
                ? { type: 'tabs' }
                : { type: 'accordion', defaultCollapsed: false, radios: true, spacedAccordionItems: true };

            var pe = elems.create( 'payment', { layout: peLayout } );
            pe.mount( mountRef.current );
        }, [] );

        // ── Update amount if cart total changes ───────────────────────────────
        useEffect( function () {
            if ( ! elemsRef.current ) { return; }
            var cartTotal = billing && billing.cartTotal;
            var amount    = parseInt( ( cartTotal && cartTotal.value ) || '0', 10 );
            if ( amount > 0 ) {
                elemsRef.current.update( { amount: amount } );
            }
        }, [ billing && billing.cartTotal && billing.cartTotal.value ] );

        // ── onPaymentSetup: validate + create confirmation token ──────────────
        useEffect( function () {
            var onPaymentSetup = eventRegistration.onPaymentSetup;
            var unsubscribe = onPaymentSetup( function () {
                if ( ! stripeRef.current || ! elemsRef.current ) {
                    return Promise.resolve( {
                        type:    emitResponse.responseTypes.ERROR,
                        message: 'Stripe not initialised.',
                    } );
                }

                return elemsRef.current.submit()
                    .then( function ( submitResult ) {
                        if ( submitResult.error ) {
                            setError( submitResult.error.message );
                            return {
                                type:    emitResponse.responseTypes.ERROR,
                                message: submitResult.error.message,
                            };
                        }

                        return stripeRef.current.createConfirmationToken( {
                            elements: elemsRef.current,
                        } );
                    } )
                    .then( function ( tokenResult ) {
                        if ( ! tokenResult ) { return; } // already returned error above
                        if ( tokenResult.error ) {
                            setError( tokenResult.error.message );
                            return {
                                type:    emitResponse.responseTypes.ERROR,
                                message: tokenResult.error.message,
                            };
                        }
                        setError( '' );
                        return {
                            type: emitResponse.responseTypes.SUCCESS,
                            meta: {
                                paymentMethodData: {
                                    ctstripe_confirmation_token: tokenResult.confirmationToken.id,
                                },
                            },
                        };
                    } )
                    .catch( function ( err ) {
                        var msg = ( err && err.message ) ? err.message : 'Payment error.';
                        setError( msg );
                        return { type: emitResponse.responseTypes.ERROR, message: msg };
                    } );
            } );

            return unsubscribe;
        }, [ eventRegistration.onPaymentSetup ] );

        // ── Render ────────────────────────────────────────────────────────────
        return createElement(
            'div',
            { className: 'ctstripe-blocks-wrapper' },
            cfg.description
                ? createElement( 'p', { className: 'ctstripe-blocks-description' }, cfg.description )
                : null,
            createElement( 'div', {
                ref:   mountRef,
                style: { minHeight: '40px' },
            } ),
            error
                ? createElement( 'p', {
                    role:  'alert',
                    style: { color: '#cc0000', margin: '8px 0 0', fontSize: '0.9em' },
                }, error )
                : null
        );
    }

    // ── Label ─────────────────────────────────────────────────────────────────

    function Label() {
        return createElement( 'span', { className: 'wc-block-components-payment-method-label' },
            cfg.title || 'Pay with card or other method'
        );
    }

    // ── Register ──────────────────────────────────────────────────────────────

    registerPaymentMethod( {
        name:           'ctstripe',
        label:          createElement( Label, null ),
        content:        createElement( Content, null ),
        edit:           createElement( 'div', {
            style: { padding: '12px', color: '#666', fontSize: '0.9em' },
        }, cfg.title || 'CartTrigger Stripe (Payment Element)' ),
        canMakePayment: function () { return !! cfg.publishable_key; },
        ariaLabel:      cfg.title || 'Pay with card or other method',
        supports: {
            features: cfg.supports || [ 'products' ],
        },
    } );

} )();
