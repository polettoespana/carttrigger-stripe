/* global ctstripe, Stripe */
( function ( $ ) {
    'use strict';

    if ( typeof Stripe === 'undefined' || typeof ctstripe === 'undefined' ) {
        return;
    }

    var stripe          = Stripe( ctstripe.publishable_key );
    var elements        = null;
    var paymentElement  = null;
    var clientSecret    = null;
    var intentId        = null;
    var mounted         = false;

    function isOurGatewaySelected() {
        return $( 'input[name="payment_method"]:checked' ).val() === ctstripe.gateway_id;
    }

    function mountPaymentElement( secret ) {
        clientSecret = secret;

        if ( mounted ) {
            return;
        }

        var container = document.getElementById( 'ctstripe-payment-element' );
        if ( ! container ) {
            return;
        }

        elements = stripe.elements( {
            clientSecret: clientSecret,
            locale:       ctstripe.locale || 'auto',
            appearance:   {
                theme:     'stripe',
                variables: { borderRadius: '4px' },
            },
        } );

        paymentElement = elements.create( 'payment', {
            layout: {
                type:                 'accordion',
                defaultCollapsed:     false,
                radios:               true,
                spacedAccordionItems: true,
            },
        } );
        paymentElement.mount( '#ctstripe-payment-element' );
        mounted = true;
    }

    function fetchIntent( orderId ) {
        return $.ajax( {
            url:    ctstripe.ajax_url,
            method: 'POST',
            data:   {
                nonce:    ctstripe.nonce,
                order_id: orderId || 0,
            },
        } );
    }

    function initElement() {
        if ( ! isOurGatewaySelected() ) {
            return;
        }

        fetchIntent( 0 ).done( function ( response ) {
            if ( ! response.success ) {
                showError( response.data.message || 'Errore Stripe.' );
                return;
            }
            intentId = response.data.intent_id;
            mountPaymentElement( response.data.client_secret );
        } ).fail( function () {
            showError( 'Impossibile connettersi a Stripe.' );
        } );
    }

    function showError( message ) {
        $( '#ctstripe-errors' ).text( message );
    }

    function clearError() {
        $( '#ctstripe-errors' ).text( '' );
    }

    // Init on gateway selection.
    $( document.body ).on( 'payment_method_selected', function () {
        if ( isOurGatewaySelected() && ! mounted ) {
            initElement();
        }
    } );

    // Init if already selected on load.
    $( function () {
        if ( isOurGatewaySelected() ) {
            initElement();
        }
    } );

    // Intercept WC checkout submission.
    $( document.body ).on( 'checkout_place_order_' + ctstripe.gateway_id, function () {
        if ( ! elements || ! clientSecret ) {
            showError( 'Stripe non ancora inizializzato. Riprova.' );
            return false;
        }
        return true;
    } );

    // After WC creates the order and returns success, confirm payment via Stripe.
    $( document.body ).on( 'checkout_error', function () {
        // Re-enable submit button on WC error.
        $( '#place_order' ).prop( 'disabled', false );
    } );

    // Hook into WC's AJAX checkout response.
    var originalProcessResponse = window.wc_checkout_params;

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

        if (
            data.result === 'success' &&
            data.redirect === false &&
            isOurGatewaySelected()
        ) {
            // Extract order id from messages or re-fetch intent with order.
            clearError();
            confirmPayment();
        }
    } );

    function confirmPayment() {
        var returnUrl = ctstripe.return_url +
            '?payment_intent_client_secret=' + encodeURIComponent( clientSecret );

        elements.submit().then( function ( result ) {
            if ( result.error ) {
                showError( result.error.message );
                $( '#place_order' ).prop( 'disabled', false );
                return;
            }

            stripe.confirmPayment( {
                elements:       elements,
                clientSecret:   clientSecret,
                confirmParams: {
                    return_url: returnUrl,
                },
            } ).then( function ( result ) {
                if ( result.error ) {
                    showError( result.error.message );
                    $( '#place_order' ).prop( 'disabled', false );
                }
                // On success Stripe redirects automatically.
            } );
        } );
    }

} )( jQuery );
