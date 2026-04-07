jQuery(document).ready( function($){
	$('.devolution-item-request').on('click', function(){
		var confirm_txt = '';
		if ( $('.woocommerce-view-order').length > 0 ) {
			confirm_txt = 'Tem certeza que deseja solicitar a devolução deste produto?';
		} else {
			confirm_txt = 'Tem certeza que deseja solicitar a devolução de todos os produtos deste pedido?';
		}

		if ( confirm( confirm_txt ) ) {
			$(this).addClass('processing');
			$.ajax(
				{
					url: info.admin_url,
					type: 'POST',
					data: {
						action: 'send_devolution_request',
						order_id: $(this).attr('data-order_id'),
						product_id: $(this).attr('data-product_id'),
						customer_id: info.customer,
						nonce: info.nonce
					},
					success: function( response ) {
						if ( 'sended' === response ) {
							alert( 'Solicitação enviada com sucesso!');
							$('.devolution-item-request.processing').removeClass('processing').addClass('devolution-sended');
						} else {
							alert( 'Falha ao processar sua solicitação, tente novamente.' );
							$('.devolution-item-request').removeClass('processing').removeClass('devolution-sended');
						}
					},
					error: function() {
						$('.devolution-item-request').removeClass('processing');
					}
				}
			);
		}
	});
});