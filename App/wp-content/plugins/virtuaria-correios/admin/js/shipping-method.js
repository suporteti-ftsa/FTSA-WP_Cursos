jQuery(document).ready(function ($) {
    let html_field;
    if ( shipping.authorized == true ) {
        html_field = '<tr id="big-format" valign="top"><th class="titledesc" scope="row"><label for="woocommerce_virtuaria-correios-grand_formats">Grandes Formatos (057)</label></th>';
        html_field += '<td class="forminp"><fieldset><legend class="screen-reader-text"><span>Grandes Formatos (057)</span></legend>';
        html_field += '<label for="woocommerce_virtuaria-correios-grand_formats">';
        html_field += '<input disabled type="checkbox" name="woocommerce_virtuaria-correios-grand_formats" id="woocommerce_virtuaria-correios-grand_formats"';
        html_field += ' checked value="1"> Serviço adicionado automaticamente. Requerido para entregas de GRANDES FORMATOS.</label><br></fieldset></td></tr>';
    }

    let service_cod = $("#woocommerce_virtuaria-correios-sedex_service_cod").val();
    if ( shipping.authorized == true
        && ( service_cod == '03328' || service_cod == '03212' ) ) {
        $('#woocommerce_virtuaria-correios-sedex_optional_services + p + .form-table tbody').append(html_field);
    }

    if ( service_cod == '20117' ) {
        $('label[for="woocommerce_virtuaria-correios-sedex_register_type"]').parent().parent().show();
    } else {
        $('label[for="woocommerce_virtuaria-correios-sedex_register_type"]').parent().parent().hide();
    }

    $("#woocommerce_virtuaria-correios-sedex_service_cod").on('change', function(){
        if ( shipping.authorized == true ) {
            $('#big-format').remove();
            if ( $(this).val() == '03328'
                || $(this).val() == '03212' ) {
                $('#woocommerce_virtuaria-correios-sedex_optional_services + p + .form-table tbody').append(html_field);
            }
        }

        const internactional = [ '45128', '45195', '45209', '45110' ];
        $('.internacional-warn').remove();
        if ( internactional.includes( $(this).val() ) ) {
            $('.forminp-cod_service').append(
                '<span class="internacional-warn"><b>Atenção:</b> a utilização deste serviço está condicionada a disponibilidade de entrega para o país de destino. Consulte a <a href="https://www.correios.com.br/enviar/encomendas/arquivo/internacional/disponibilidade-de-servico-por-pais-de-destino" target="_blank">lista de países</a> e serviços adicionais aceitos por eles.</span>'
            );

            $('.forminp-cod_service').append(
                '<span style="display:block" class="description internacional-warn">Em caso de dúvidas quanto aos serviços de Exportação, clique <a href="https://forms.office.com/pages/responsepage.aspx?id=9Z0GAC-sp0y5pdmf6jerAivNEGPT0jxHiY5UqhUhyENURU81SlhER0ZLQVpFQzhWS1ZPNzlQRk1VVS4u" target="_blank">aqui</a> para receber atendimento personalizado.</span>'
            );
        }

        if ( $(this).val() == '20117' ) {
            $('label[for="woocommerce_virtuaria-correios-sedex_register_type"]').parent().parent().show();
        } else {
            $('label[for="woocommerce_virtuaria-correios-sedex_register_type"]').parent().parent().hide();
        }
    });

    if ( $('#woocommerce_virtuaria-correios-sedex_declare_value').val() == '' ) {
        $('#woocommerce_virtuaria-correios-sedex_min_value_declared').parent().parent().parent().hide();
    }

    $("#woocommerce_virtuaria-correios-sedex_declare_value").on('change', function(){
        if ( $(this).val() == '' ) {
            $('#woocommerce_virtuaria-correios-sedex_min_value_declared').parent().parent().parent().hide();
        } else {
            $('#woocommerce_virtuaria-correios-sedex_min_value_declared').parent().parent().parent().show();
        }
    });

    $('#woocommerce_virtuaria-correios-sedex_minimum_weight, #woocommerce_virtuaria-correios-sedex_extra_weight + .extra_weight').on('change', function(e){
        const MAX_ALLOWED_WEIGHT = 25;

        let warning = '';
        if ( $(this).val() >= MAX_ALLOWED_WEIGHT ) {
            warning = 'Você informou um peso (';
            warning += $(this).val();
            warning += 'kg)  muito alto que pode não ser compatível com alguns servicos dos Correios.';
            $(this).css('border-color', 'orange');
            alert(warning);
        }
    });
});

(function($) {
    $('label[for="woocommerce_virtuaria-correios-sedex_register_type"]').parent().parent().hide();
})(jQuery);