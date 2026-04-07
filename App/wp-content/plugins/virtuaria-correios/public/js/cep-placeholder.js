jQuery(document).ready(function($) {
    change_cep_placeholder();

    $('#billing_country, #shipping_country, #calc_shipping_country').on('change', function() {
        setTimeout(change_cep_placeholder, 100);
    });

    $(document).on('updated_checkout', function() {
        change_cep_placeholder();
    });
});

function change_cep_placeholder() {
    let country = '';
    if ( jQuery('.woocommerce-checkout').length !== 0 ) {
        country = jQuery('#billing_country').val()
            ? jQuery('#billing_country').val()
            : jQuery('#shipping_country').val();
    } else {
        country = jQuery('#calc_shipping_country').val();
    }

    let selector = jQuery('#calc_shipping_postcode');
    let checkout = jQuery('label[for="billing_postcode"], label[for="shipping_postcode"]');

    if ( '' !== country && 'BR' !== country ) {
        selector.attr('placeholder', 'COD');
        if ( typeof checkout.html() !== 'undefined' ) {
            checkout.html( checkout.html().replace('CEP', 'COD') );
        }
    } else {
        selector.attr('placeholder', 'CEP');
        if ( typeof checkout.html() !== 'undefined' ) {
            checkout.html( checkout.html().replace('COD', 'CEP') );
        }
    }
}