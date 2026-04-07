jQuery(document).ready(function($) {
	$('.generate-ticket, .generate-declaration-content').on('click', function(e) {
		e.preventDefault();
		let prepostContent = '.ticket';
		if ( $(this).hasClass('generate-declaration-content') ) {
			prepostContent = '.declare';
		}
		$(this).parent().find('.shipping-prepost' + prepostContent).fadeIn().prepend('<div class="close">X</div>');
	});

	$('.add-tracking-code').on('click', function(e) {
		e.preventDefault();
		$(this).parent().find('.new-trakking-code').fadeIn().prepend('<div class="close">X</div>');
	});

	$('.generate-declaration').on('click', function(e) {
		e.preventDefault();
		$(this).parent().find('.shipping-declaration').fadeIn().prepend('<div class="close">X</div>');
	});

	$(document).on('click', '.close', function() {
		$(this).parent().fadeOut();
		$('.trakking-bg').fadeOut();
	});

	$(document).mouseup(function(e) {
		var ticket = $(".shipping-prepost");

		// if the target of the click isn't the container nor a descendant of the container
		if ( ! ticket.is(e.target) && ticket.has(e.target).length === 0 && ticket.is(':visible') ) 
		{
			ticket.fadeOut();
			$('.close').remove();
		}

		var trakking = $(".new-trakking-code");

		// if the target of the click isn't the container nor a descendant of the container
		if ( ! trakking.is(e.target) && trakking.has(e.target).length === 0 && trakking.is(':visible') ) 
		{
			trakking.fadeOut();
			$('.close').remove();
		}

		var products = $(".product-list");

		// if the target of the click isn't the container nor a descendant of the container
		if ( ! products.is(e.target) && products.has(e.target).length === 0 && products.is(':visible') ) 
		{
			products.fadeOut();
			$('.close').remove();
		}

		var declaration = $(".shipping-declaration");

		// if the target of the click isn't the container nor a descendant of the container
		if ( ! declaration.is(e.target) && declaration.has(e.target).length === 0 && declaration.is(':visible') ) 
		{
			declaration.fadeOut();
			$('.close').remove();
		}

		var trakking = $(".trakking-order");

		// if the target of the click isn't the container nor a descendant of the container
		if ( ! trakking.is(e.target) && trakking.has(e.target).length === 0 && trakking.is(':visible') ) 
		{
			trakking.fadeOut();
			$('.close').remove();
			$('.trakking-bg').fadeOut();
		}
	});

	$(document).on('click', 'a.print-ticket, a.print-declaration', function(e) {
		e.preventDefault();
		window.open($(this).attr('href'), '_blank');
	});

	$(document).on('click', 'a.print-dace', function(e) {
		const href = $(this).attr('href');
		if ( href && href !== '#' ) {
			e.preventDefault();
			window.open(href, '_blank');
			return;
		}
	});

	$('.prepost.ticket').off().on('click', function(e) {
		let button_prepost = $(this);
		let shipping_prepost = $(this).parent();

		let nf_number = shipping_prepost.find('#nf-number').val();
		let nf_key = shipping_prepost.find('#nf-key').val();

		if ( nf_key || nf_number ) {
			$(this).parent().find('#correios_order_id').val($(this).data('orderid'));

			shipping_prepost.addClass('loading').append('<div class="spinner"></div>');
			shipping_prepost.find('.spinner').show();

			ajax_prepost(button_prepost, shipping_prepost);            
		} else {
			alert('Preencha pelo menos um dos campos com dados da NFe.');
			$('#nf-key,#nf-number').css('border', '1px solid red');
			e.preventDefault();
		}
		return false;
	});

	$('.prepost.declare').off().on('click', function(e) {
		let button_prepost = $(this);
		let shipping_prepost = $(this).parent();

		$(this).parent().find('#correios_order_id').val($(this).data('orderid'));

		shipping_prepost.addClass('loading').append('<div class="spinner"></div>');
		shipping_prepost.find('.spinner').show();

		ajax_prepost(button_prepost, shipping_prepost);
		return false;
	});

	$(document).on('click', '.print-prepost-label', function(e) {
		e.preventDefault();
		let button = $(this);

		button.addClass('loading');
		let spinner = $('<span class="spinner is-active"></span>');
		button.after(spinner);

		$.ajax({
			url: setting.ajaxurl,
			type: 'POST',
			data: {
				action: 'print_prepost_label',
				correios_order_id: button.data('orderid'),
				correios_instance_id: button.data('instanceid'),
				print_prepost_label_nonce: button.data('nonce')
			},
			success: function(response) {
				if ( response.success === true && response.data.ticket_url != '' ) {
					button
						.attr('target', '_blank')
						.html('Imprimir etiqueta')
						.attr('href', response.data.ticket_url)
						.addClass('print-ticket')
						.removeClass('print-prepost-label');
					window.open(response.data.ticket_url, '_blank');
				} else if ( response.success === true && response.data.pending_label ) {
					let error = '';

					if ( response.data.errors ) {
						error = '\\n- ' + response.data.errors.join('\\n- ') + '\\n';
					}

					alert(
						'Etiqueta ainda não liberada. '
						+ error
						+ 'Tente novamente em alguns minutos.'
					);
				} else {
					let error = '';

					if ( response.data ) {
						error = '\\n- ' + response.data.join('\\n- ') + '\\n';
					}

					alert(
						'Falha ao imprimir a etiqueta. '
						+ error
						+ 'Para mais informações consulte o log do plugin. '
					);
				}
			},
			error: function(data) {
				console.log(data);
			},
			complete: function() {
				button.removeClass('loading');
				spinner.remove();
			}
		});
	});

	$(document).on('click', '.print-dace', function(e) {
		let button = $(this);

		if ( button.attr('href') && button.attr('href') !== '#' ) {
			return;
		}

		e.preventDefault();
		button.addClass('loading');
		let spinner = $('<span class="spinner is-active"></span>');
		button.after(spinner);

		$.ajax({
			url: setting.ajaxurl,
			type: 'POST',
			data: {
				action: 'print_dace',
				correios_dace_order_id: button.data('orderid'),
				correios_dace_instance_id: button.data('instanceid'),
				print_dace_nonce: button.data('nonce')
			},
			success: function(response) {
				if ( response.success === true && response.data.dace_url != '') {
					button
						.attr('target', '_blank')
						.html('Imprimir DACE')
						.attr('href', response.data.dace_url);
					window.open(response.data.dace_url, '_blank');
				} else {
					let error = '';

					if ( response.data ) {
						error = '\\n- ' + response.data.join('\\n- ') + '\\n';
					}

					alert(
						'Falha ao imprimir a DACE. '
						+ error
						+ 'Para mais informações consulte o log do plugin. '
					);
				}
			},
			error: function(data) {
				console.log(data);
			},
			complete: function() {
				button.removeClass('loading');
				spinner.remove();
			}
		});
	});

	$('.submit-trakking-code').off().on('click', function(e) {
		e.preventDefault();
		let button = $(this);
		let trakking = $(this).parent();

		trakking.addClass('loading').append('<div class="spinner"></div>');
		trakking.find('.spinner').show();

		$.ajax({
			url: setting.ajaxurl,
			type: 'POST',
			data: {
				action: 'add_trakking_code',
				order_id: button.parent().find('.trakking-order-id').val(),
				trakking_nonce: button.parent().find('input[name="trakking-nonce"]').val(),
				trakking_code: trakking.find('.input-trakking-code').val()
			},
			success: function(data) {
				if ( 'Fail' !== data ) {
					trakking.parent().parent().append(data);
					trakking.parent().remove();
				} else {
					alert('Falha ao adicionar o Código de Rastreamento. Tente novamente mais tarde.');
				}
			},
			error: function(data) {
				console.log(data);
			},
			complete: function() {
				trakking.fadeOut();
				trakking.find('.spinner').remove();
				trakking.removeClass('loading');
			}
		});
	});

	$('.see-trakking, .tracking-code').on('click', function(e) {
		e.preventDefault();
		let icon = $(this);
		let trakking = $(this).parent().find('.trakking-order');

		trakking.addClass('loading').html('<div class="spinner"></div>');
		trakking.find('.spinner').show();

		trakking.parent().find('.trakking-bg').fadeIn();

		trakking.fadeIn();
		if ( trakking.find('.close').length == 0 ) {
			trakking.prepend('<div class="close">X</div>');
		}

		$.ajax({
			url: setting.ajaxurl,
			type: 'POST',
			data: {
				action: 'trakking_order',
				order_id: icon.data('order-id'),
				trakking_nonce: icon.parent().find('input[name="trakking-order-nonce"]').val(),
			},
			success: function(data) {
				if ( 'Fail' !== data ) {
					trakking.append(data);
				} else {
					alert('Falha ao rastrear entrega do pedido. Tente novamente mais tarde.');
				}
			},
			error: function(data) {
				console.log(data);
			},
			complete: function() {
				trakking.find('.spinner').remove();
				trakking.removeClass('loading');
			}
		});
	});

	$('.see-products').on('click', function(e) {
		e.preventDefault();
		let products = $(this).parent().find('.product-list').fadeIn();
		if ( products.find('.close').length == 0 ) {
			products.append('<div class="close">X</div>');
		}
	});

	$('.select-method').on('change', function() {
		let select = $(this);
		if ( select.val() !== '' ) {
			select.prop('disabled', true);
			select.parent().find('.spinner').css('visibility', 'visible');
	 
			$.ajax({
				url: setting.ajaxurl,
				type: 'POST',
				data: {
					action: 'change_shipping_method',
					order_id: select.data('order'),
					shipping_method: select.val(),
					shipping_title: select.find('option:selected').text(),
					shipping_method_nonce: select.data('nonce')
				},
				success: function(data) {
					if ( data === 'success' ) {
						alert('Alteração efetuada com sucesso!');
						window.location.reload();
					} 
				},
				error: function(data) {
					console.log('Erro na alteração do método de envio: ' + data);
				},
				complete: function() {
					select.prop('disabled', false);
					select.parent().find('.spinner').css('visibility', 'hidden');
				}
			});
		}
	});

	$('.declaration.button').on('click', function(e) {
		e.preventDefault();

		let button_declaration = $(this);
		let shipping_declaration = $(this).parent();

		$(this).parent().find('#correios_order_id').val($(this).data('orderid'));

		shipping_declaration.addClass('loading').append('<div class="spinner"></div>');
		shipping_declaration.find('.spinner').show();

		$.ajax({
			url: setting.ajaxurl,
			type: 'POST',
			data: {
				action: 'create_declaration',
				correios_declaration_order_id: button_declaration.data('orderid'),
				correios_declaration_instance_id: shipping_declaration.find('#correios_declaration_instance_id').val(),
				create_declaration_nonce: shipping_declaration.parent().find('#create_declaration_nonce').val(),
			},
			success: function(response) {
				if ( response.success === true && response.data.declaration_url != '') {
					shipping_declaration.parent().find('.generate-declaration')
						.attr('target', '_blank')
						.html('Imprimir Declaração')
						.attr('href', response.data.declaration_url)
						.addClass('print-declaration')
						.removeClass('generate-declaration');
	
					shipping_declaration.remove();
				} else {
					let error = '';
	
					if ( response.data ) {
						error = '\n- ' + response.data.join('\n- ') + '\n';
					}
	
					alert(
						'Falha ao gerar a etiqueta. '
						+ error
						+ 'Para mais informações consulte o log do plugin. '
					);
				}
			},
			error: function(data) {
				console.log(data);
			},
			complete: function() {
				shipping_declaration.find('.spinner').remove();
				shipping_declaration.removeClass('loading');
			}
		});
	});

	$(document).on('click', '#send-email-trakking', function(e) {
		e.preventDefault();
		let button = $(this);
		let trakking = $(this).parent().parent();


		trakking.addClass('loading').append('<div class="spinner"></div>');
		trakking.find('.spinner').show();

		$.ajax({
			url: setting.ajaxurl,
			type: 'POST',
			data: {
				action: 'send_trakking_email',
				order_id: trakking.data('orderid'),
				trakking_nonce: trakking.parent().find('input[name="trakking-order-nonce"]').val(),
			},
			success: function(data) {
				if ( data ) {
					alert('Email enviado com sucesso!');
				} else {
					alert('Falha ao enviar email de rastreio. Tente novamente mais tarde.');
				}
			},
			error: function(data) {
				console.log(data);
			},
			complete: function() {
				trakking.find('.spinner').remove();
				trakking.removeClass('loading');
			}
		});
	});
});

function ajax_prepost(button_prepost, shipping_prepost) {
	jQuery.ajax({
		url: setting.ajaxurl,
		type: 'POST',
		data: {
			action: 'create_prepost',
			correios_order_id: button_prepost.data('orderid'),
			correios_instance_id: shipping_prepost.find('#correios_instance_id').val(),
			create_prepost_nonce: shipping_prepost.parent().find('#create_prepost_nonce').val(),
			nf_number: shipping_prepost.find('#nf-number').val(),
			nf_key: shipping_prepost.find('#nf-key').val()
		},
		success: function(response) {
			console.log(response);
			if ( response.success === true && response.data.ticket_url != '') {
				if ( response.data.trakking_code != '' ) {
					let column_status = shipping_prepost.parent().parent()
					.find('.add-tracking-code, .tracking-code')
					.parent();

					shipping_prepost.parent().parent()
						.find('.add-tracking-code, .tracking-code')
						.remove();

					column_status
						.append('<a href="https://rastreamento.correios.com.br/app/index.php?objetos=' + response.data.trakking_code + '" target="_blank" class="tracking-code">' + response.data.trakking_code + '</a>');
					
					shipping_prepost.parent().parent()
						.find('.status-a-enviar')
						.addClass('status-enviado')
						.removeClass('status-a-enviar')
						.html('✓ ENVIADO');
				}

				shipping_prepost.parent().find('.generate-ticket,.generate-declaration-content')
					.attr('target', '_blank')
					.html('Imprimir etiqueta')
					.attr('href', response.data.ticket_url)
					.addClass('print-ticket')
					.removeClass('generate-ticket');

				if (shipping_prepost.parent().find('.print-ticket').length > 1) {
					shipping_prepost.parent().find('.generate-declaration-content').remove();
				}

				shipping_prepost.parent().find('.shipping-prepost').remove();
				shipping_prepost.remove();
			} else if ( response.success === true && response.data.pending_label ) {
				if ( response.data.trakking_code != '' ) {
					let column_status = shipping_prepost.parent().parent()
					.find('.add-tracking-code, .tracking-code')
					.parent();

					shipping_prepost.parent().parent()
						.find('.add-tracking-code, .tracking-code')
						.remove();

					column_status
						.append('<a href="https://rastreamento.correios.com.br/app/index.php?objetos=' + response.data.trakking_code + '" target="_blank" class="tracking-code">' + response.data.trakking_code + '</a>');
					
					shipping_prepost.parent().parent()
						.find('.status-a-enviar')
						.addClass('status-enviado')
						.removeClass('status-a-enviar')
						.html('✓ ENVIADO');
				}

				let label_link = shipping_prepost.parent().find('.generate-declaration-content');
				if ( label_link.length === 0 ) {
					label_link = shipping_prepost.parent().find('.generate-ticket');
				}

				label_link
					.html('Imprimir etiqueta')
					.attr('href', '#')
					.addClass('print-prepost-label')
					.removeClass('generate-declaration-content')
					.removeClass('generate-ticket')
					.removeAttr('target')
					.attr('data-orderid', button_prepost.data('orderid'))
					.attr('data-instanceid', shipping_prepost.find('#correios_instance_id').val())
					.attr('data-nonce', response.data.print_label_nonce);

				if ( response.data.dace_available && shipping_prepost.parent().find('.print-dace').length === 0 ) {
					shipping_prepost.parent().append(
						'<a href="#" class="print-dace" data-orderid="' + button_prepost.data('orderid') + '" data-instanceid="' + shipping_prepost.find('#correios_instance_id').val() + '" data-nonce="' + response.data.print_dace_nonce + '">Imprimir DACE</a>'
					);
				}

				shipping_prepost.parent().find('.shipping-prepost').remove();
				shipping_prepost.remove();
			} else {
				let error = '';

				if ( response.data ) {
					error = '\n- ' + response.data.join('\n- ') + '\n';
				}

				alert(
					'Falha ao gerar a etiqueta. '
					+ error
					+ 'Para mais informações consulte o log do plugin. '
				);
			}
		},
		error: function(data) {
			console.log(data);
		},
		complete: function() {
			shipping_prepost.find('.spinner').remove();
			shipping_prepost.removeClass('loading');
		}
	});
}

(function($){
	if ( $('.multi-ticket').length > 0 ) {
		window.open( $('.multi-ticket').attr('href') );
		$('.multi-ticket').remove();
	}
})(jQuery);
