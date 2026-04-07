jQuery(document).ready(function($) {
    handle_person_type();
    $(document).on('change', '#_billing_persontype', function() {
        handle_person_type();
    });

    function handle_person_type() {
        let person = $('#_billing_persontype').val();

        if (person === 'pf') {
            $('._billing_cnpj_field, ._billing_ie_field, ._billing_company_field').hide();
            $('._billing_cpf_field, ._billing_rg_field').show();
        } else if (person === 'pj') {
            $('._billing_cnpj_field, ._billing_ie_field, ._billing_company_field').show();
            $('._billing_cpf_field, ._billing_rg_field').hide();
        }
    }

    $('.data-extra-fields-title > .dashicons-edit').on('click', function(){
        $('.customer-info.block-mode').toggleClass('virtuaria-correios-hide-fields');
        $('.customer-info.only-read').toggleClass('virtuaria-correios-hide-fields');
    });
});