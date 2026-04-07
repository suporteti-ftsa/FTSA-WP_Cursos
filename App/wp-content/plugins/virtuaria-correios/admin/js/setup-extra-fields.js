jQuery(document).ready(function ($) {
    function checkPersonType() {
        var selectedValue = $('#woocommerce_virt_correios_person_type').val();
        $('.conditional-field').hide();

        if (selectedValue === 'both') {
            $('.conditional-field.only-both').show();
        } else if (selectedValue === 'pf') {
            $('.conditional-field.only-pf').show();
        } else if (selectedValue === 'pj') {
            $('.conditional-field.only-pj').show();
        }
    }
 
    checkPersonType();
 
    $('#woocommerce_virt_correios_person_type').on('change', function() {
       checkPersonType();
    });
 });