jQuery(document).ready(function($) {
    $('#virt-postcode').keydown(function(){
        var cep = $(this).val();
        cep = cep.replace(/\D/g, '');
        if(cep.length > 5){
            cep = cep.substr(0, 5) + '-' + cep.substr(5);
        }
        $(this).val(cep);
    });

    $('#virt-button').on('click', function() {
        let cep = $('#virt-postcode').val();
        if ( cep.length != 9 ) {
            $('#virt-calc-response')
                .css('color', 'red')
                .html('O CEP informado é inválido! Por favor, informe um CEP válido e tente novamente.');
            return false;
        } else {
            $('#virt-calc-response').css('color', 'inherit');
        }

        let variation_id;

        let normal_variation_not_found = ( $('#product-' + virtCorreios.product_id + ' .woocommerce-variation-add-to-cart').length > 0
            && ( ! $('#product-' + virtCorreios.product_id + ' .woocommerce-variation-add-to-cart .variation_id').val()
                || $('#product-' + virtCorreios.product_id + ' .woocommerce-variation-add-to-cart .variation_id').val() == 0 ) );
        
        let block_variation_not_found = ( $('.wp-block-add-to-cart-form .variation_id').length > 0
            && ( ! $('.wp-block-add-to-cart-form .variation_id').val()
                || $('.wp-block-add-to-cart-form .variation_id').val() == 0 ) );

        if ( normal_variation_not_found || block_variation_not_found ) {
            alert( 'Por favor, selecione uma variação antes de calcular o frete!' );
            return false;
        } else if ( $('.wp-block-add-to-cart-form .variation_id').val() > 0 ) {
            variation_id = $('.wp-block-add-to-cart-form .variation_id').val();
        } else {
            variation_id = $('#product-' + virtCorreios.product_id + ' .woocommerce-variation-add-to-cart .variation_id').val();
        }

        $(this).prop('disabled', true);

        $(".summary, .wp-block-columns").addClass( 'processing' ).block({
            message: null,
            overlayCSS: {
                background: '#fff',
                opacity: 0.6
            }
        });

        $('#virt-calc-response').fadeOut();

        $.ajax({
            type: 'POST',
            url: virtCorreios.ajaxUrl,
            data: {
                'action': 'product_calc_shipping',
                'postcode': cep,
                'nonce' : virtCorreios.nonce,
                'product_id': $('.cart .single_add_to_cart_button').val(),
                'blog_id': $('#shipping-calc #virt-blog-id').val(),
                'variation_id': variation_id,
                'virt_post_id': $('#shipping-calc #virt-post-id').val()
            },
            success: function(response) {
                $('#virt-calc-response')
                    .html(response);
            },
            error: function(error) {
                $('#virt-calc-response')
                    .css('color', 'red')
                    .html('Falha ao cálcular frete. Por favor, tente novamente.');
            },
            complete: function() {
                $('#virt-calc-response').fadeIn();
                $(".summary, .wp-block-columns").removeClass( 'processing' ).unblock();
                $('#virt-button').prop('disabled', false);
            }
        });
    });
});