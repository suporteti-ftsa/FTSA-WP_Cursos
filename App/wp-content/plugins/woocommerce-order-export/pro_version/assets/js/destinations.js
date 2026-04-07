jQuery( document ).ready( function ( $ ) {

	$( '.output_destination' ).click( function () {
		var input = $( this ).find( 'input' );
		var target = input.val();
		$( '.set-destination:not(#' + target + ')' ).hide();
		$( '.my-icon-triangle' ).removeClass( 'ui-icon-triangle-1-n' );
		$( '.my-icon-triangle' ).addClass( 'ui-icon-triangle-1-s' );
		if ( ! jQuery( '#' + target ).is( ':hidden' ) ) {
			jQuery( '#' + target ).hide();
		}
		else {
			if ( jQuery( '#' + target ).is( ':hidden' ) ) {
				jQuery( '#' + target ).show();
				$( '#test_reply_div' ).hide();
				$( input ).next().removeClass( 'ui-icon-triangle-1-s' );
				$( input ).next().addClass( 'ui-icon-triangle-1-n' );
			}
		}
	} );

	$( '.wc_oe_test' ).click( function () {

		var test = $( this ).attr( 'data-test' );
		var data = 'json=' + woe_make_json_var( $( '#export_job_settings' ) )

		data = data + "&action=order_exporter&method=test_destination&mode=" + mode + "&id=" + job_id + "&destination=" + test + '&woe_nonce=' + woe_nonce + '&tab=' + woe_active_tab;

		$( '#test_reply_div' ).hide();

		$.post( ajaxurl, data, function ( data ) {
			$( '#test_reply' ).val( data );
			$( '#test_reply_div' ).show();
		} )
	} )

	$( '#destination_separate' ).change( function() {
		$el = $( '#separate-files__wrapper' );
		$( this ).prop( 'checked' ) ? $el.removeClass( 'hidden' ) : $el.addClass( 'hidden' );
	} )

	var text_area = $( '#destination-email-body' );

	$( '#show-email-body' ).click( function () {
		text_area.toggle();
	} );

	setTimeout( function () {
		if ( ! $( '#destination-email-body textarea' ).val() ) {
			text_area.hide();
		}
	}, 0 );

	$( '#test_reply_div' ).hide();

	$( '#email_use_wc_template' ).change( function() {
		if( $(this).is(':checked') ) {
			jQuery( "#wc_email_templates" ).css( 'display', 'inline-block' ).attr( 'disabled', false );
			jQuery( "#wc_email_templates--select2" ).css( 'display', 'inline' );
			jQuery( "#email_wc_template_notice" ).show();
		} else {
			jQuery( "#wc_email_templates" ).css( 'display', 'none' ).attr( 'disabled', 'disabled' );
			jQuery( "#wc_email_templates--select2" ).hide();
			jQuery( "#email_wc_template_notice" ).hide();
		}
	} );

	$( '#email_use_wc_template' ).trigger('change');
} );