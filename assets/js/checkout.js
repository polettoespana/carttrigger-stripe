/* global ctstripe, Stripe */
( function ( $ ) {
    'use strict';

    if ( typeof Stripe === 'undefined' || typeof ctstripe === 'undefined' ) {
        return;
    }

    var stripe     = Stripe( ctstripe.publishable_key );
    var eceEl      = null;  // Express Checkout Element instance
    var eceElems   = null;  // stripe.elements() for ECE (mode-based)
    var peElems    = null;  // stripe.elements() for Payment Element (mode-based)
    var peInstance = null;
    var peMounted  = false;
    var eceActive  = false; // true when ECE triggered the form submission

    var appearance = {
        theme:     'stripe',
        variables: { borderRadius: '4px' },
    };

    function cartAmount() {
        return parseInt( ctstripe.cart_amount, 10 );
    }

    function elementsParams() {
        return {
            mode:       'payment',
            amount:     cartAmount() || 100, // fallback to avoid Stripe rejecting amount=0
            currency:   ctstripe.cart_currency || 'eur',
            locale:     ctstripe.locale || 'auto',
            appearance: appearance,
        };
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

        eceElems = stripe.elements( elementsParams() );

        eceEl = eceElems.create( 'expressCheckout', {
            buttonType: { applePay: 'buy', googlePay: 'buy' },
        } );

        eceEl.on( 'ready', function ( event ) {
            var hasButtons = event.availablePaymentMethods &&
                Object.values( event.availablePaymentMethods ).some( Boolean );
            container.style.display = hasButtons ? '' : 'none';

            // Show the separator inside the payment box only if gateway is selected.
            if ( hasButtons ) {
                $( '#ctstripe-separator' ).show();
            }
        } );

        eceEl.on( 'confirm', function () {
            if ( ! isOurGateway() ) {
                // Force-select our gateway and submit.
                $( 'input[name="payment_method"][value="' + ctstripe.gateway_id + '"]' )
                    .prop( 'checked', true )
                    .trigger( 'change' );
            }
            eceActive = true;
            $( 'form.checkout' ).submit();
        } );

        eceEl.mount( '#' + containerId );
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

        peInstance = peElems.create( 'payment', {
            layout: {
                type:                 'accordion',
                defaultCollapsed:     false,
                radios:               true,
                spacedAccordionItems: true,
            },
        } );

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

    // ── Keep amount in sync when cart updates ─────────────────────────────────

    $( document.body ).on( 'updated_checkout', function ( event, data ) {
        var newAmount = data && data.ctstripe_cart_amount
            ? parseInt( data.ctstripe_cart_amount, 10 )
            : null;

        if ( newAmount ) {
            ctstripe.cart_amount = newAmount;
            if ( eceElems ) {
                eceElems.update( { amount: newAmount } );
            }
            if ( peElems ) {
                peElems.update( { amount: newAmount } );
            }
        }
    } );

    // ── Gateway selection ─────────────────────────────────────────────────────

    $( document.body ).on( 'payment_method_selected', function () {
        if ( isOurGateway() && ! peMounted ) {
            initPE();
        }
    } );

    // ── Init on load ──────────────────────────────────────────────────────────

    $( function () {
        // Mount ECE in every container marked with data-ctstripe-ece.
        document.querySelectorAll( '[data-ctstripe-ece]' ).forEach( function ( el ) {
            initECE( el.id );
        } );

        if ( isOurGateway() ) {
            initPE();
        }
    } );

} )( jQuery );
