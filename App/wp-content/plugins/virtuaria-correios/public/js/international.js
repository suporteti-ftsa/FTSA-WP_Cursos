jQuery(document).ready(function($) {
    let url = window.location.href;
    let urlObj = new URL(url);
    let searchParams = urlObj.searchParams;

    if ( searchParams.has('correios-int') ) {
        console.log('Pais selecionado: ' + searchParams.get('correios-int'));
        $('#billing_country, #shipping_country').val(searchParams.get('correios-int'));
        $('#select2-billing_country-container').text(
            jQuery('#billing_country option:selected').text()
        );
        $('#select2-shipping_country-container').text(
            jQuery('#shipping_country option:selected').text()
        );
    }

    $('#billing_country, #shipping_country').on('change', function() {
        if ( $(this).val() != '' ) {
            
            if (searchParams.has('correios-int')) {
                searchParams.delete('correios-int');
            }

            searchParams.set('correios-int', $(this).val());

            console.log('Novo país selecionado: ' + urlObj.toString()); 
            window.location.href = urlObj.toString();
        }
    });

    // Variáveis para armazenar os campos brasileiros removidos (um para cada seção)
    var brazilianBillingFields = [];
    var brazilianShippingFields = [];

    // Função para tratar a mudança de país
    function handleCountryChange(country, addressForm, storageVariable) {
        if (country === 'BR') {
            // Se for Brasil, restaura os campos salvos se existirem
            if (Array.isArray(storageVariable) && storageVariable.length > 0) {
                addressForm.append(storageVariable);
                storageVariable.length = 0; // Limpa o array após restaurar
            }
        } else {
            // Se não for Brasil, remove os campos específicos e armazena
            storageVariable.length = 0; // Limpa o array antes de recolher novos campos
            
            // Encontra e remove todos os elementos que contêm 'virtuaria-correios' nas classes
            addressForm.find('div').each(function() {
                var element = $(this);
                if (element.attr('class') && element.attr('class').includes('virtuaria-correios')) {
                    storageVariable.push(element.clone(true)); // Armazena uma cópia do elemento
                    element.remove(); // Remove o elemento original
                }
            });
        }
    }

    // Eventos separados para cada país (Cobrança e Envio)
    $(document).on('change', '#billing-country', function() {
        handleCountryChange(
            $(this).val(),
            $('#billing.wc-block-components-address-form'), // Formulário de cobrança
            brazilianBillingFields
        );
    });

    $(document).on('change', '#shipping-country', function() {
        handleCountryChange(
            $(this).val(),
            $('#shipping.wc-block-components-address-form'), // Formulário de envio
            brazilianShippingFields
        );
    });
});