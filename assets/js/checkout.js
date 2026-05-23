/* global ctstripe, Stripe */
( function ( $ ) {
    'use strict';

    if ( typeof Stripe === 'undefined' || typeof ctstripe === 'undefined' ) {
        return;
    }

    var stripe      = Stripe( ctstripe.publishable_key );
    var eceMounted  = {}; // containerId → { elems, el }
    var eceElems    = null; // elements instance that triggered ECE confirm
    var peElems     = null;
    var peInstance  = null;
    var peMounted   = false;
    var eceActive   = false;
    var eceEvent    = null; // ECE confirm event — holds paymentFailed() for error handling

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

    function redirectAfterPayment( paymentIntent ) {
        window.location.href = ctstripe.return_url +
            '?payment_intent=' + paymentIntent.id +
            '&payment_intent_client_secret=' + paymentIntent.client_secret;
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
            buttonType:     { applePay: 'buy', googlePay: 'buy' },
            buttonHeight:   parseInt( ctstripe.ece_height, 10 ) || 44,
            layout:         { maxColumns: parseInt( ctstripe.ece_columns, 10 ) || 2, maxRows: parseInt( ctstripe.ece_max_rows, 10 ), overflow: 'auto' },
            paymentMethods: ctstripe.ece_payment_methods || {},
        } );

        el.on( 'ready', function ( event ) {
            var hasButtons = event.availablePaymentMethods &&
                Object.values( event.availablePaymentMethods ).some( Boolean );

            // Hide/show the wrapper (includes notice) or the container itself.
            var wrapper = container.closest( '[data-ctstripe-ece-wrapper]' );
            if ( wrapper ) {
                wrapper.style.display = hasButtons ? '' : 'none';
            } else {
                container.style.display = hasButtons ? '' : 'none';
            }

            if ( hasButtons ) {
                $( '#ctstripe-separator' ).show();
            }
        } );

        el.on( 'confirm', function ( event ) {
            eceElems = elems;
            eceEvent = event;
            eceActive = true;

            if ( $( 'form.checkout' ).length ) {
                // Validate T&C before opening the payment sheet.
                var $terms = $( '#terms' );
                if ( $terms.length && ! $terms.is( ':checked' ) ) {
                    eceEvent.paymentFailed( { reason: 'fail' } );
                    eceActive = false;
                    $terms[0].scrollIntoView( { behavior: 'smooth', block: 'center' } );
                    return;
                }

                // Populate WC billing fields from Apple Pay billingDetails so WC
                // validation doesn't reject the form for empty required fields.
                var eceBilling  = event.billingDetails || {};
                var eceAddress  = eceBilling.address || {};
                var eceNameParts = ( eceBilling.name || '' ).split( ' ' );
                var eceFirst    = eceNameParts.shift() || '';
                var eceLast     = eceNameParts.join( ' ' );
                if ( eceFirst )                 { $( '#billing_first_name' ).val( eceFirst ); }
                if ( eceLast )                  { $( '#billing_last_name' ).val( eceLast ); }
                if ( eceBilling.email )         { $( '#billing_email' ).val( eceBilling.email ); }
                if ( eceBilling.phone )         { $( '#billing_phone' ).val( eceBilling.phone ); }
                if ( eceAddress.line1 )         { $( '#billing_address_1' ).val( eceAddress.line1 ); }
                if ( eceAddress.line2 )         { $( '#billing_address_2' ).val( eceAddress.line2 ); }
                if ( eceAddress.city )          { $( '#billing_city' ).val( eceAddress.city ); }
                if ( eceAddress.postal_code )   { $( '#billing_postcode' ).val( eceAddress.postal_code ); }
                if ( eceAddress.country )       { $( '#billing_country' ).val( eceAddress.country ).trigger( 'change' ); }
                if ( eceAddress.state )         { $( '#billing_state' ).val( eceAddress.state ); }

                // Checkout page: submit WC form; payment confirmed in ajaxComplete.
                if ( ! isOurGateway() ) {
                    $( 'input[name="payment_method"][value="' + ctstripe.gateway_id + '"]' )
                        .prop( 'checked', true )
                        .trigger( 'change' );
                }
                $( 'form.checkout' ).submit();
            } else {
                // Outside checkout: create order via AJAX with Apple Pay billing details.
                $.ajax( {
                    url:      ctstripe.ajax_url,
                    type:     'POST',
                    dataType: 'json',
                    data: {
                        action:  'ctstripe_create_order',
                        nonce:   ctstripe.nonce,
                        billing: JSON.stringify( event.billingDetails || {} ),
                    },
                    success: function ( response ) {
                        if ( ! response.success ) {
                            if ( eceEvent ) { eceEvent.paymentFailed( { reason: 'fail' } ); }
                            eceActive = false;
                            return;
                        }
                        clearError();
                        stripe.confirmPayment( {
                            elements:      eceElems,
                            clientSecret:  response.data.client_secret,
                            confirmParams: { return_url: ctstripe.return_url },
                        } ).then( function ( result ) {
                            if ( result.error ) {
                                if ( eceEvent ) { eceEvent.paymentFailed( { reason: 'fail' } ); }
                                showError( result.error.message );
                                eceActive = false;
                                return;
                            }
                            // Wallet payments (Apple Pay, Google Pay) resolve here instead of
                            // redirecting — we redirect manually to the return handler.
                            if ( result.paymentIntent ) {
                                redirectAfterPayment( result.paymentIntent );
                                return;
                            }
                            eceActive = false;
                        } );
                    },
                    error: function () {
                        if ( eceEvent ) { eceEvent.paymentFailed( { reason: 'fail' } ); }
                        eceActive = false;
                    },
                } );
            }
        } );

        el.mount( '#' + containerId );
        eceMounted[ containerId ] = { elems: elems, el: el };
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
            if ( eceEvent ) { eceEvent.paymentFailed( { reason: 'fail' } ); }
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
                    if ( eceEvent ) { eceEvent.paymentFailed( { reason: 'fail' } ); }
                    showError( result.error.message );
                    $( '#place_order' ).prop( 'disabled', false );
                    eceActive = false;
                    return;
                }
                // Wallet payments (Apple Pay, Google Pay) resolve here instead of
                // redirecting — we redirect manually to the return handler.
                if ( result.paymentIntent ) {
                    redirectAfterPayment( result.paymentIntent );
                    return;
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
        var newAmount = data && data.fragments && data.fragments['ctstripe_cart_amount']
            ? parseInt( data.fragments['ctstripe_cart_amount'], 10 )
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

        // Update amount and re-attach ECE elements to their containers.
        // Reuse the same stripe.elements() instance — Stripe does not support multiple instances.
        // el.mount() after el.unmount() re-attaches the same element without re-evaluating availability.
        document.querySelectorAll( '[data-ctstripe-ece]' ).forEach( function ( domEl ) {
            var mounted = eceMounted[ domEl.id ];
            if ( ! mounted ) {
                initECE( domEl.id );
                return;
            }
            if ( newAmount ) {
                mounted.elems.update( { amount: newAmount } );
            }
            if ( ! domEl.querySelector( 'iframe' ) ) {
                mounted.el.mount( '#' + domEl.id );
            }
        } );
    } );

    // ── Cart page: re-init ECE after WC cart fragment refresh ────────────────

    $( document.body ).on( 'updated_cart wc_fragments_refreshed', function () {
        document.querySelectorAll( '[data-ctstripe-ece]' ).forEach( function ( domEl ) {
            if ( domEl.querySelector( 'iframe' ) ) {
                return; // still mounted, nothing to do
            }
            if ( eceMounted[ domEl.id ] ) {
                try { eceMounted[ domEl.id ].el.unmount(); } catch ( e ) {}
                delete eceMounted[ domEl.id ];
            }
            initECE( domEl.id );
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
