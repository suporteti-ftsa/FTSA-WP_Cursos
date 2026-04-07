jQuery(document).ready(function($) {
    let deactivateLink = $('#deactivate-virtuaria-correios');
    if (deactivateLink.length) {
        deactivateLink.on('click', function(event) {
            event.preventDefault();

            let modal = $("#virt-correios-modal");
            modal.show();

            let span = $(".close").first();
            span.on('click', function() {
                modal.hide();
            });
            $(window).on('click', function(event) {
                if ($(event.target).is(modal)) {
                    modal.hide();
                }
            });

            let form = $("#uninstallForm");
            form.on('submit', function(event) {
                event.preventDefault();
                $.ajax({
                    url: virt_correios_uninstall.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'virtuaria_correios_submit_feedback',
                        reason: $('#motivo').val(),
                        email: $('#email').val(),
                        comment: $('#comentarios').val(),
                        nonce: virt_correios_uninstall.nonce,
                    },
                    beforeSend: function() {
                        let button = $('#enviar-feedback');
                        button.prop('disabled', true);
                        button.html('Enviando...');
                    },
                    complete: function() {
                        window.location.href = deactivateLink.attr('href');
                    }
                });
            });
        });
    }
});
