/* global ctstripe, Stripe */
( function ( $ ) {
    'use strict';

    if ( typeof Stripe === 'undefined' || typeof ctstripe === 'undefined' ) {
        return;
    }

    var stripe      = Stripe( ctstripe.publishable_key );
    var eceMounted  = {}; // containerId → stripe.elements() instance
    var eceElems    = null; // elements instance that triggered ECE confirm
    var peElems     = null;
    var peInstance  = null;
    var peMounted   = false;
    var eceActive   = false;

    var appearanceCfg = ctstripe.appearance || {};
    var appearance = {
        theme:     appearanceCfg.theme || 'stripe',
        variables: {},
    };
    var varKeys = [ 'colorPrimary', 'colorBackground', 'colorText', 'colorDanger', 'fontFamily', 'fontSizeBase', 'borderRadius', 'spacingUnit' ];
    varKeys.forEach( function ( key ) {
        if ( appearanceCfg[ key ] ) {
            appearance.variables[ key ] = appearanceCfg[ key ];
        }
    } );
    if ( appearanceCfg.rules ) {
        try {
            appearance.rules = JSON.parse( appearanceCfg.rules );
        } catch ( e ) {
            // invalid JSON — ignore
        }
    }

    function cartAmount() {
        return parseInt( ctstripe.cart_amount, 10 );
    }

    function elementsParams() {
        var params = {
            mode:       'payment',
            amount:     cartAmount() || 100,
            currency:   ctstripe.cart_currency || 'eur',
            locale:     ctstripe.locale || 'auto',
            appearance: appearance,
        };
        if ( ctstripe.pmc_id ) {
            params.paymentMethodConfiguration = ctstripe.pmc_id;
        }
        return params;
    }

    function isOurGateway() {
        return $( 'input[name="payment_method"]:checked' ).val() === ctstripe.gateway_id;
    }

    function showError( msg ) {
        $( '#ctstripe-errors' ).text( msg );
    }

    function clearError() {
        $( '#ctstripe-errors' ).text( '' );
    }

    // ── Express Checkout Element ──────────────────────────────────────────────

    function initECE( containerId ) {
        var container = document.getElementById( containerId );
        if ( ! container ) {
            return;
        }

        var elems = stripe.elements( elementsParams() );
        var el    = elems.create( 'expressCheckout', {
            buttonType:   { applePay: 'buy', googlePay: 'buy' },
            buttonHeight: parseInt( ctstripe.ece_height, 10 ) || 44,
            layout:       { maxColumns: parseInt( ctstripe.ece_columns, 10 ) || 2, maxRows: 1, overflow: 'auto' },
        } );

        el.on( 'ready', function ( event ) {
            var hasButtons = event.availablePaymentMethods &&
                Object.values( event.availablePaymentMethods ).some( Boolean );
            container.style.display = hasButtons ? '' : 'none';

            if ( hasButtons ) {
                $( '#ctstripe-separator' ).show();
            }
        } );

        el.on( 'confirm', function () {
            // Capture which elements instance triggered the confirmation.
            eceElems = elems;

            if ( ! isOurGateway() ) {
                $( 'input[name="payment_method"][value="' + ctstripe.gateway_id + '"]' )
                    .prop( 'checked', true )
                    .trigger( 'change' );
            }
            eceActive = true;
            $( 'form.checkout' ).submit();
        } );

        el.mount( '#' + containerId );
        eceMounted[ containerId ] = elems;
    }

    // ── Payment Element ───────────────────────────────────────────────────────

    function initPE() {
        if ( peMounted ) {
            return;
        }
        var container = document.getElementById( 'ctstripe-payment-element' );
        if ( ! container ) {
            return;
        }

        peElems = stripe.elements( elementsParams() );

        var peLayout = ctstripe.pe_layout === 'tabs'
            ? { type: 'tabs' }
            : { type: 'accordion', defaultCollapsed: false, radios: true, spacedAccordionItems: true };

        peInstance = peElems.create( 'payment', { layout: peLayout } );

        peInstance.mount( '#ctstripe-payment-element' );
        peMounted = true;
    }

    // ── WC checkout AJAX intercept ────────────────────────────────────────────

    $( document ).ajaxComplete( function ( event, xhr, settings ) {
        if ( ! settings.url || settings.url.indexOf( 'wc-ajax=checkout' ) === -1 ) {
            return;
        }

        var data;
        try {
            data = JSON.parse( xhr.responseText );
        } catch ( e ) {
            return;
        }

        if ( data.result !== 'success' ) {
            eceActive = false;
            $( '#place_order' ).prop( 'disabled', false );
            return;
        }

        if ( ! data.redirect || data.redirect.indexOf( '#ctstripe-' ) === -1 ) {
            return;
        }

        var clientSecret = data.ctstripe_client_secret;
        if ( ! clientSecret ) {
            return;
        }

        clearError();

        if ( eceActive ) {
            stripe.confirmPayment( {
                elements:      eceElems,
                clientSecret:  clientSecret,
                confirmParams: { return_url: ctstripe.return_url },
            } ).then( function ( result ) {
                if ( result.error ) {
                    showError( result.error.message );
                    $( '#place_order' ).prop( 'disabled', false );
                }
                eceActive = false;
            } );
        } else {
            peElems.submit().then( function ( result ) {
                if ( result.error ) {
                    showError( result.error.message );
                    $( '#place_order' ).prop( 'disabled', false );
                    return;
                }
                stripe.confirmPayment( {
                    elements:      peElems,
                    clientSecret:  clientSecret,
                    confirmParams: { return_url: ctstripe.return_url },
                } ).then( function ( result ) {
                    if ( result.error ) {
                        showError( result.error.message );
                        $( '#place_order' ).prop( 'disabled', false );
                    }
                } );
            } );
        }
    } );

    // ── Keep amount in sync + re-mount after WC refreshes checkout HTML ───────

    $( document.body ).on( 'updated_checkout', function ( event, data ) {
        var newAmount = data && data.ctstripe_cart_amount
            ? parseInt( data.ctstripe_cart_amount, 10 )
            : null;

        if ( newAmount ) {
            ctstripe.cart_amount = newAmount;
        }

        // WC replaces the payment-fields HTML on every update — reset PE so it
        // gets re-mounted into the new (empty) container.
        peMounted  = false;
        peElems    = null;
        peInstance = null;
        if ( isOurGateway() ) {
            initPE();
        }

        // Re-mount ECE containers that were emptied by WC (no iframe inside).
        // Shortcode containers outside the payment box survive the refresh.
        document.querySelectorAll( '[data-ctstripe-ece]' ).forEach( function ( el ) {
            if ( ! el.querySelector( 'iframe' ) ) {
                delete eceMounted[ el.id ];
                initECE( el.id );
            } else if ( newAmount && eceMounted[ el.id ] ) {
                eceMounted[ el.id ].update( { amount: newAmount } );
            }
        } );
    } );

    // ── Gateway selection ─────────────────────────────────────────────────────

    $( document.body ).on( 'payment_method_selected', function () {
        if ( isOurGateway() && ! peMounted ) {
            initPE();
        }
    } );

    // ── Init on load ──────────────────────────────────────────────────────────

    $( function () {
        document.querySelectorAll( '[data-ctstripe-ece]' ).forEach( function ( el ) {
            initECE( el.id );
        } );

        if ( isOurGateway() ) {
            initPE();
        }

        // Apply title_class to the WC-injected gateway label.
        if ( ctstripe.title_class ) {
            $( '.wc_payment_method.payment_method_' + ctstripe.gateway_id + ' > label' ).addClass( ctstripe.title_class );
        }
    } );

} )( jQuery );
