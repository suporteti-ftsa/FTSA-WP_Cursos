jQuery(document).ready(function($){
   $('.welcome-actions .checkbox-wrapper').on('click', function(){
      if ( $(this).hasClass('checked') ) {
        $(this).removeClass('checked');
        $('#inactive_imported_methods').prop('checked', false);
      } else {
        $(this).addClass('checked');
        $('#inactive_imported_methods').prop('checked', true);
      }
   });

   $('.welcome-modal-content .close-modal, .jump-step').on('click', function() {
      $.ajax({
        url: install.ajax_url,
        type: 'POST',
        data: {
          action: 'disable_wizard_install',
          nonce: install.nonce
        },
        success: function (response) {
          if (response) {
            location.href = response;
          }
        },
        error: function (error) {
          console.log(error);
        }
      });
   });

   $('.allow-import').on('click', function(){
        if ( $(this).hasClass('checked') ) {
        $(this).removeClass('checked');
        } else {
        $(this).addClass('checked');
        }
    });

    $('.import-button').on('click', function(){
        let elem = $(this);
        elem.prop('disabled', true);
        elem.html('Processando <span class="dashicons dashicons-update"></span>');
        let allow_import = $('.allow-import').hasClass('checked');
        
        $.ajax({
            url: install.ajax_url,
            type: 'POST',
            data: {
              action: 'import_settings',
              nonce: install.nonce,
              allow_import: {
                woocorreios: $('.woocommerce-correios').find('.allow-import').hasClass('checked'),
                menvios: $('.melhor-envios').find('.allow-import').hasClass('checked'),
              },
              inactive_methods: $('#inactive_imported_methods').prop('checked')
            },
            success: function (response) {
              if (response) {
                elem.html('Importação concluída');
                setTimeout(function(){
                    location.href = response;
                }, 2000);
              } else {
                alert( 'Falha ao importar as configurações, por favor, tente novamente' );
                elem.html('Iniciar importação');
              }
            },
            error: function (error) {
              console.log(error);
            },
            complete: function () {
              setTimeout(function(){
                elem.prop('disabled', false);
                elem.html('Iniciar importação');
              },1500);
            }
        });
    });
});