jQuery(document).ready(function($) {
    $(document).on('keyup', '#calc_shipping_postcode, .wc-block-components-address-form__postcode.is-active > input', function(){
        var cep = $(this).val();
        cep = cep.replace(/\D/g, '');

        if (cep.length > 5) {
            cep = cep.substr(0, 5) + '-' + cep.substr(5, 3);

            if ( typeof mask !== 'undefined' && typeof mask.stateHidden !== 'undefined') {
                var state = getStateFromCep( cep );
                if ( state ) {
                    $('#calc_shipping_state').val( state );
                    $('#calc_shipping_city').val('');
                    // $('#calc_shipping_state,.woocommerce-cart .wc-block-components-address-form__state .wc-blocks-components-select__select').val( state );
                    // $('#calc_shipping_city,.woocommerce-cart .wc-block-components-address-form__city > input').val('cidade');

                    // if ( cep.length == 9
                    //     && typeof wp !== 'undefined' && wp.data && wp.data.dispatch) {
                    //     const store = wp.data.dispatch('wc/store/cart');
                    //     console.log('dispatch');
                    //     if ( store && typeof store.setShippingAddress === 'function' ) {
                    //         console.log('state', state);
                    //         if ( state ) {
                    //             store.setShippingAddress( { state: state, city: '' } );
                    //         }
                    //     }
                    // }
                } else {
                    $('.woocommerce-cart p#calc_shipping_state_field,.woocommerce-cart .wc-block-components-address-form__state,'
                        + '.woocommerce-cart #calc_shipping_city_field,.woocommerce-cart .wc-block-components-address-form__city.is-active,'
                        + '.woocommerce-cart #calc_shipping_country_field,.woocommerce-cart .wc-block-components-address-form__country').attr('style', 'display: block!important');
                }
            }
        }

        if (cep.length > 9) {
            cep = cep.substr(0, 9);
        }
        $(this).val(cep);
    });

    // $(document).on('click', '.wp-element-button.wc-block-components-shipping-calculator-address__button', function(){
    //     console.log('click');
    //     if ( typeof mask.stateHidden !== 'undefined'
    //         && typeof wp !== 'undefined' && wp.data && wp.data.dispatch) {

    //         const store = wp.data.dispatch('wc/store/cart');

    //         if ( store && typeof store.setShippingAddress === 'function' ) {
    //             let state = $('.woocommerce-cart .wc-block-components-address-form__state .wc-blocks-components-select__select').val();
    //             if ( state ) {
    //                 store.setShippingAddress( { state: state, city: 'cidade' } );
    //                 store.setBillingAddress( { state: state, city: 'cidade' } );
    //             }
    //         }
    //     }
    // });
});

function getStateFromCep(cep) {
    const ranges = [
        { start: 69900000, end: 69999999, state: 'AC' },
        { start: 57000000, end: 57999999, state: 'AL' },
        { start: 69000000, end: 69299999, state: 'AM' },
        { start: 69400000, end: 69899999, state: 'AM' },
        { start: 68900000, end: 68999999, state: 'AP' },
        { start: 40000000, end: 48999999, state: 'BA' },
        { start: 60000000, end: 63999999, state: 'CE' },
        { start: 70000000, end: 72799999, state: 'DF' },
        { start: 73000000, end: 73699999, state: 'DF' },
        { start: 29000000, end: 29999999, state: 'ES' },
        { start: 72800000, end: 72999999, state: 'GO' },
        { start: 73700000, end: 76799999, state: 'GO' },
        { start: 65000000, end: 65999999, state: 'MA' },
        { start: 30000000, end: 39999999, state: 'MG' },
        { start: 79000000, end: 79999999, state: 'MS' },
        { start: 78000000, end: 78899999, state: 'MT' },
        { start: 66000000, end: 68899999, state: 'PA' },
        { start: 58000000, end: 58999999, state: 'PB' },
        { start: 50000000, end: 56999999, state: 'PE' },
        { start: 64000000, end: 64999999, state: 'PI' },
        { start: 80000000, end: 87999999, state: 'PR' },
        { start: 20000000, end: 28999999, state: 'RJ' },
        { start: 59000000, end: 59999999, state: 'RN' },
        { start: 76800000, end: 76999999, state: 'RO' },
        { start: 69300000, end: 69399999, state: 'RR' },
        { start: 90000000, end: 99999999, state: 'RS' },
        { start: 88000000, end: 89999999, state: 'SC' },
        { start: 49000000, end: 49999999, state: 'SE' },
        { start: 01000000, end: 19999999, state: 'SP' },
        { start: 77000000, end: 77999999, state: 'TO' },
    ];

    cep = parseInt(cep.replace(/\D/g, ''), 10);

    for (const range of ranges) {
        if (cep >= range.start && cep <= range.end) {
            return range.state;
        }
    }

    return false;
}
