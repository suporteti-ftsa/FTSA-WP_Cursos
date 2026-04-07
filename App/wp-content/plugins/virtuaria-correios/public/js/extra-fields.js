jQuery(window).on('load', function() {
    let $j = jQuery.noConflict();

    let person_type = '';
    if (options.is_block) {
        person_type = $j('.wc-block-components-select-input-virtuaria-correios-person_type select');
    } else {
        person_type = $j('#virtuaria-correios\\/person_type').length > 0
            ? $j('#virtuaria-correios\\/person_type')
            : $j('#billing_person_type');
    }

    if (person_type.length) {
        $j('#billing_cnpj_field, #billing_ie_field, #billing_company_field').hide();
        $j('#virtuaria-correios\\/cnpj_field, #virtuaria-correios\\/ie_field, #virtuaria-correios\\/company_field').hide();
        $j('.wc-block-components-address-form__virtuaria-correios-cnpj, .wc-block-components-address-form__virtuaria-correios-ie').hide();
    }

    handle_person_type( person_type );

    if (options.is_block) {
        $j(document).on('change', '.wc-block-components-select-input-virtuaria-correios-person_type select', function() {
            handle_person_type( $j(this) );
        });
    } else {
        $j('#billing_person_type,#virtuaria-correios\\/person_type').change(function() {
           handle_person_type( $j(this) );
        });
    }

    function handle_person_type( elem ) {
        var selectedOption = elem.val();

        if (selectedOption === 'pf') {
            $j('#billing_cpf_field, #billing_rg_field').show();
            $j('#virtuaria-correios\\/cpf_field, #virtuaria-correios\\/rg_field').show();
            $j('.wc-block-components-address-form__virtuaria-correios-cpf,.wc-block-components-address-form__virtuaria-correios-rg').show();

            $j('#billing_cnpj_field, #billing_ie_field, #billing_company_field').hide();
            $j('#virtuaria-correios\\/cnpj_field, #virtuaria-correios\\/ie_field, #virtuaria-correios\\/company_field').hide();
            $j('.wc-block-components-address-form__virtuaria-correios-cnpj,.wc-block-components-address-form__virtuaria-correios-ie').hide();
        } else if (selectedOption === 'pj') {
            $j('#billing_cpf_field, #billing_rg_field').hide();
            $j('#virtuaria-correios\\/cpf_field, #virtuaria-correios\\/rg_field').hide();
            $j('.wc-block-components-address-form__virtuaria-correios-cpf,.wc-block-components-address-form__virtuaria-correios-rg').hide();

            $j('#billing_cnpj_field, #billing_ie_field, #billing_company_field').show();
            $j('#virtuaria-correios\\/cnpj_field, #virtuaria-correios\\/ie_field, #virtuaria-correios\\/company_field').show();
            $j('.wc-block-components-address-form__virtuaria-correios-cnpj,.wc-block-components-address-form__virtuaria-correios-ie').show();
        }
    }

    function applyMask(input, maskFunction, maxLength) {
        input.on('input', function() {
            var value = $j(this).val();
    
            if (value !== '') {
                var maskedValue = maskFunction(value).substr(0, maxLength);
                if (maskedValue !== value) {
                    $j(this).val(maskedValue);
                }
            }
        });
    }

    //TODO: erro XXX.XXX.XXXX-X
    function maskCPF(cpf) {
        cpf = cpf.replace(/\D/g, '');
        cpf = cpf.replace(/(\d{3})(\d)/, '$1.$2');
        cpf = cpf.replace(/(\d{3})(\d)/, '$1.$2');
        cpf = cpf.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
        return cpf;
    }

    function maskCNPJ(cnpj) {
        cnpj = cnpj.replace(/\D/g, '');
        cnpj = cnpj.replace(/^(\d{2})(\d)/, '$1.$2');
        cnpj = cnpj.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
        cnpj = cnpj.replace(/\.(\d{3})(\d)/, '.$1/$2');
        cnpj = cnpj.replace(/(\d{4})(\d)/, '$1-$2');
        return cnpj;
    }

    function maskRG(rg) {
        rg = rg.replace(/\D/g, '');
        rg = rg.replace(/(\d{2})(\d)/, '$1.$2');
        rg = rg.replace(/(\d{3})(\d)/, '$1.$2');
        rg = rg.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
        return rg;
    }

    function maskCellphone(cellphone) {
        cellphone = cellphone.replace(/\D/g, '');
        cellphone = cellphone.replace(/(\d{2})(\d)/, '($1) $2');
        cellphone = cellphone.replace(/(\d{5})(\d)/, '$1-$2');
        return cellphone;
    }

    function maskDate(date) {
        date = date.replace(/\D/g, '');
        date = date.replace(/(\d{2})(\d)/, '$1/$2');
        date = date.replace(/(\d{2})(\d)/, '$1/$2');
        date = date.replace(/(\d{4})$/, '$1');
        return date;
    }

    function maskZipCode(zipcode) {
        zipcode = zipcode.replace(/\D/g, '');
        zipcode = zipcode.replace(/(\d{5})(\d)/, '$1-$2');
        return zipcode;
    }

    function maskNumber(number) {
        number = number.replace(/\D/g, '');
        return number;
    }

    if (options.mask) {
        if (options.is_block) {
            applyMask($j('#shipping .wc-block-components-address-form__virtuaria-correios-cnpj > input'), maskCNPJ, 18);
            applyMask($j('#shipping .wc-block-components-address-form__virtuaria-correios-cpf > input'), maskCPF, 14);
            applyMask($j('#shipping .wc-block-components-address-form__virtuaria-correios-rg > input'), maskRG, 12);
            applyMask($j('#shipping .wc-block-components-address-form__virtuaria-correios-cellphone > input'), maskCellphone, 15);
            applyMask($j('#shipping .wc-block-components-address-form__virtuaria-correios-phone > input'), maskCellphone, 15);
            applyMask($j('#shipping .wc-block-components-address-form__virtuaria-correios-birthdate > input'), maskDate, 10);
            applyMask($j('#shipping .wc-block-components-address-form__virtuaria-correios-number > input'), maskNumber, 30);
        } else {
            applyMask($j('#virtuaria-correios\\/cpf, #billing_cpf'), maskCPF, 14); 
            applyMask($j('#virtuaria-correios\\/cnpj, #billing_cnpj'), maskCNPJ, 18); 
            applyMask($j('#virtuaria-correios\\/rg, #billing_rg'), maskRG, 12); 
            applyMask($j('#virtuaria-correios\\/cellphone, #billing_cellphone'), maskCellphone, 15);
            applyMask($j('#virtuaria-correios\\/phone, #billing_phone'), maskCellphone, 15);
            applyMask($j('#virtuaria-correios\\/birthdate, #billing_birthdate'), maskDate, 10); 
            applyMask($j('#virtuaria-correios\\/postcode, #billing_postcode'), maskZipCode, 9);
            applyMask($j('#virtuaria-correios\\/postcode, #shipping_postcode'), maskZipCode, 9);
            applyMask($j('#virtuaria-correios\\/number, #shipping_number'), maskNumber, 30);
            applyMask($j('#virtuaria-correios\\/number, #billing_number'), maskNumber, 30);
        }
    }

    function makeFieldRequired(fieldId, checkout = false) {
        var label = $j(fieldId).find('label');
        
        if (checkout && typeof label.html() !== 'undefined') {
            label.html(label.html().replace('(optional)', '').replace('(opcional)', '') );
            if ($j(fieldId).find('option').length > 0) {
                $j(fieldId).find('option').each(function(i, v) {
                    $j(v).html($j(v).html().replace('(optional)', '').replace('(opcional)', ''));
                });
            }
        } else {
            label.find('span.optional').remove();
    
            if (!label.find('.required').length) {
                label.append('<abbr class="required" title="obrigatório">*</abbr>');
            }
        }
    }

    if (options.is_block) {
        //Blocks checkout
        makeFieldRequired('.wc-block-components-address-form__virtuaria-correios-cnpj', true );
        makeFieldRequired('.wc-block-components-address-form__virtuaria-correios-cpf', true);
        makeFieldRequired('.wc-block-components-address-form__virtuaria-correios-rg', true);
        makeFieldRequired('.wc-block-components-address-form__virtuaria-correios-birthdate', true);
        makeFieldRequired('.wc-block-components-address-form__virtuaria-correios-ie', true);
        makeFieldRequired('.wc-block-components-select-input-virtuaria-correios-gender', true);
        makeFieldRequired('.wc-block-components-address-form__virtuaria-correios-number', true);
    } else {
        // TODO: numero é required mas neighborhood vai depender da config
        makeFieldRequired('#virtuaria-correios\\/cpf_field, #billing_cpf_field');
        makeFieldRequired('#virtuaria-correios\\/cnpj_field, #billing_cnpj_field');
        makeFieldRequired('#virtuaria-correios\\/rg_field,#billing_rg_field');
        makeFieldRequired('#virtuaria-correios\\/ie_field, #billing_ie_field');
        makeFieldRequired('#virtuaria-correios\\/neighborhood_field, #billing_neighborhood_field');
        makeFieldRequired('#shipping_neighborhood_field');
        makeFieldRequired('#virtuaria-correios\\/number_field, #billing_number_field');
        makeFieldRequired('#shipping_number_field');
        makeFieldRequired('#virtuaria-correios\\/birthdate_field, #billing_birthdate_field');
        makeFieldRequired('#virtuaria-correios\\/gender_field, #billing_gender_field');
    }

    if ( options.phone_required ) {
        if (options.is_block) {
            makeFieldRequired('.wc-block-components-address-form__virtuaria-correios-cellphone', true);
        } else {
            makeFieldRequired('#billing_cellphone_field, #virtuaria-correios\\/cellphone_field');
            makeFieldRequired('#billing_cellphone_field, #virtuaria-correios\\/cellphone_field');
        }
    }
});
