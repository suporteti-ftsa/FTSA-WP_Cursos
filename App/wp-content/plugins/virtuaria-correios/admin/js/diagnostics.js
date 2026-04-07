jQuery(document).ready(function($){
    let errorMsg = 'Falha ao carregar os diagnósticos, por favor, atualize a página para tentar novamente.';

    $.ajax({
        url: diagnostic.ajax_url,
        type: 'POST',
        data: {
            action: 'make_diagnostics',
            nonce: diagnostic.nonce
        },
        success: function(response) {
            if ( response.data != '' ) {
                let html = '';
                for (let key in response.data) {
                    console.log(response.data[key].valid);
                    let status_icon = response.data[key].valid == true
                        ? '<span class="dashicons dashicons-yes-alt"></span> Válido'
                        : '<span class="dashicons dashicons-dismiss"></span> Inválido';

                    html += '<div class="checked-item"><h4 class="status-bar">'
                        + '<span class="title">' + key + '</span>'
                        + '<div class="status-wrapper"><span class="status">' + status_icon + '</span>'
                        + '<span class="dashicons dashicons-arrow-down-alt2"></span></div></h4>'
                        + '<div class="desc">' + response.data[key].description + '</div></div>';
                }
                $('#diagnostics').addClass('done').html(html);
            } else {
                alert( errorMsg );
            }
        },
        error: function(error) {
            console.log(error);
            alert( errorMsg );
        }
    });

    $('#diagnostics').on('click', '.status-bar', function(){
        $(this).parent().toggleClass('open');
    });
});