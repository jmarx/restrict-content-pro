var rcp_validating_discount = false;
var rcp_validating_gateway  = false;
var rcp_validating_level    = false;
var rcp_processing          = false;
var rcp_calculating_total   = false;

jQuery(document).ready(function($) {

	// Initial validation of subscription level and gateway options
	rcp_validate_form( true );
	rcp_calc_total();

	// Trigger gateway change event when gateway option changes
	$('#rcp_payment_gateways select, #rcp_payment_gateways input').change( function() {

		$('body').trigger( 'rcp_gateway_change' );

	});

	// Trigger subscription level change event when level selection changes
	$('.rcp_level').change(function() {

		$('body').trigger( 'rcp_level_change' );

	});

	$('body').on( 'rcp_gateway_change', function() {

		rcp_validate_form( true );

	}).on( 'rcp_level_change', function() {

		rcp_validate_form( true );

	});

	// Validate discount code
	$('#rcp_apply_discount').on( 'click', function(e) {
		
		e.preventDefault();

		$('body').trigger( 'rcp_discount_change' );

		rcp_validate_discount();

	});

	$(document.getElementById('rcp_auto_renew')).on('change', rcp_calc_total);
	$('body').on( 'rcp_discount_change rcp_level_change rcp_gateway_change', rcp_calc_total);

	$(document).on('click', '#rcp_registration_form #rcp_submit', function(e) {

		var submission_form = document.getElementById('rcp_registration_form');
		var form = $('#rcp_registration_form');

		if( typeof submission_form.checkValidity === "function" && false === submission_form.checkValidity() ) {
			return;
		}

		e.preventDefault();

		var submit_register_text = $(this).val();

		form.block({
			message: rcp_script_options.pleasewait,
			css: {
				border: 'none',
				padding: '15px',
				backgroundColor: '#000',
				'-webkit-border-radius': '10px',
				'-moz-border-radius': '10px',
				opacity: .5,
				color: '#fff'
			}
		});

		$('#rcp_submit', form).val( rcp_script_options.pleasewait );

		// Don't allow form to be submitted multiple times simultaneously
		if( rcp_processing ) {
			return;
		}

		rcp_processing = true;

		$.post( rcp_script_options.ajaxurl, form.serialize() + '&action=rcp_process_register_form&rcp_ajax=true', function(response) {

			$('.rcp-submit-ajax', form).remove();
			$('.rcp_message.error', form).remove();
			if ( response.success ) {
				$('body').trigger( 'rcp_register_form_submission' );
				$(submission_form).submit();
			} else {
				$('#rcp_submit', form).val( submit_register_text );
				$('#rcp_submit', form).before( response.data.errors );
				$('#rcp_register_nonce', form).val( response.data.nonce );
				form.unblock();
				rcp_processing = false;
			}
		}).done(function( response ) {
		}).fail(function( response ) {
			console.log( response );
		}).always(function( response ) {
		});

	});

});

function rcp_validate_form( validate_gateways ) {

	// Validate the subscription level
	rcp_validate_subscription_level();

	if( validate_gateways ) {
		// Validate the discount selected gateway
		rcp_validate_gateways();
	}

	rcp_validate_discount();

}

function rcp_validate_subscription_level() {

	if( rcp_validating_level ) {
		return;
	}

	var $        = jQuery;
	var is_free  = false;
	var options  = [];
	var level    = $( '#rcp_subscription_levels input:checked' );
	var full     = $('.rcp_gateway_fields').hasClass( 'rcp_discounted_100' );
	var lifetime = level.data( 'duration' ) == 'forever';

	rcp_validating_level = true;

	if( level.attr('rel') == 0 ) {
		is_free = true;
	}

	if( is_free ) {

		$('.rcp_gateway_fields,#rcp_auto_renew_wrap,#rcp_discount_code_wrap').hide();
		$('.rcp_gateway_fields').removeClass( 'rcp_discounted_100' );
		$('#rcp_discount_code_wrap input').val('');
		$('.rcp_discount_amount,#rcp_gateway_extra_fields').remove();
		$('.rcp_discount_valid, .rcp_discount_invalid').hide();
		$('#rcp_auto_renew_wrap input').attr('checked', false);

	} else {

		if( full ) {
			$('#rcp_gateway_extra_fields').remove();
		} else if( lifetime ) {
			$('#rcp_auto_renew_wrap input').attr('checked', false);
			$('#rcp_auto_renew_wrap').hide();
		} else {
			$('.rcp_gateway_fields,#rcp_auto_renew_wrap').show();
		}

		$('#rcp_discount_code_wrap').show();

	}

	rcp_validating_level = false;

}

function rcp_get_gateway() {
	var gateway;
	var $ = jQuery;

	if( $('#rcp_payment_gateways').length > 0 ) {

		gateway = $( '#rcp_payment_gateways select option:selected' );

		if( gateway.length < 1 ) {

			// Support radio input fields
			gateway = $( 'input[name="rcp_gateway"]:checked' );

		}

	} else {

		gateway = $( 'input[name="rcp_gateway"]' );

	}

	return gateway;
}

function rcp_validate_gateways() {

	if( rcp_validating_gateway ) {
		return;
	}

	var $        = jQuery;
	var form     = $('#rcp_registration_form');
	var is_free  = false;
	var options  = [];
	var level    = $( '#rcp_subscription_levels input:checked' );
	var full     = $('.rcp_gateway_fields').hasClass( 'rcp_discounted_100' );
	var lifetime = level.data( 'duration' ) == 'forever';
	var gateway  = rcp_get_gateway();

	rcp_validating_gateway = true;

	if( level.attr('rel') == 0 ) {
		is_free = true;
	}

	$('.rcp_message.error', form).remove();

	if( is_free ) {

		$('.rcp_gateway_fields').hide();
		$('#rcp_auto_renew_wrap').hide();
		$('#rcp_auto_renew_wrap input').attr('checked', false);
		$('#rcp_gateway_extra_fields').remove();

	} else {

		if( full ) {

			$('#rcp_gateway_extra_fields').remove();

		} else {

			form.block({
				message: rcp_script_options.pleasewait,
				css: {
					border: 'none', 
					padding: '15px', 
					backgroundColor: '#000', 
					'-webkit-border-radius': '10px', 
					'-moz-border-radius': '10px', 
					opacity: .5, 
					color: '#fff' 
				}
			});

			$('.rcp_gateway_fields').show();
			var data = { action: 'rcp_load_gateway_fields', rcp_gateway: gateway.val() };

			$.post( rcp_script_options.ajaxurl, data, function(response) {
				$('#rcp_gateway_extra_fields').remove();
				if( response.success && response.data.fields ) {
					if( $('.rcp_gateway_fields' ).length ) {

						$( '<div class="rcp_gateway_' + gateway.val() + '_fields" id="rcp_gateway_extra_fields">' + response.data.fields + '</div>' ).insertAfter('.rcp_gateway_fields');
					
					} else {

						// Pre 2.1 template files
						$( '<div class="rcp_gateway_' + gateway.val() + '_fields" id="rcp_gateway_extra_fields">' + response.data.fields + '</div>' ).insertAfter('.rcp_gateways_fieldset');

					}
				}
				form.unblock();
			});
		}

		if( 'yes' == gateway.data( 'supports-recurring' ) && ! full && ! lifetime ) {

			$('#rcp_auto_renew_wrap').show();
		
		} else {
		
			$('#rcp_auto_renew_wrap').hide();
			$('#rcp_auto_renew_wrap input').attr('checked', false);
		
		}

		$('#rcp_discount_code_wrap').show();

	}

	rcp_validating_gateway = false;

}

function rcp_validate_discount() {

	if( rcp_validating_discount ) {
		return;
	}

	var $ = jQuery;
	var gateway_fields = $('.rcp_gateway_fields');
	var discount = $('#rcp_discount_code').val();

	if( $('#rcp_subscription_levels input:checked').length ) {

		var subscription = $('#rcp_subscription_levels input:checked').val();

	} else {

		var subscription = $('input[name="rcp_level"]').val();

	}

	if( ! discount ) {
		return;
	}

	var data = {
		action: 'validate_discount',
		code: discount,
		subscription_id: subscription
	};

	rcp_validating_discount = true;

	$.post(rcp_script_options.ajaxurl, data, function(response) {

		$('.rcp_discount_amount').remove();
		$('.rcp_discount_valid, .rcp_discount_invalid').hide();

		if( ! response.valid ) {

			// code is invalid
			$('.rcp_discount_invalid').show();
			gateway_fields.removeClass('rcp_discounted_100');
			$('.rcp_gateway_fields,#rcp_auto_renew_wrap').show();

		} else if( response.valid ) {

			// code is valid
			$('.rcp_discount_valid').show();
			$('#rcp_discount_code_wrap label').append( '<span class="rcp_discount_amount"> - ' + response.amount + '</span>' );

			if( response.full ) {

				$('#rcp_auto_renew_wrap').hide();
				gateway_fields.hide().addClass('rcp_discounted_100');
				$('#rcp_gateway_extra_fields').remove();

			} else {

				$('#rcp_auto_renew_wrap').show();
				gateway_fields.show().removeClass('rcp_discounted_100');
				rcp_validate_gateways();

			}

		}

		rcp_validating_discount = false;
		$('body').trigger('rcp_discount_applied', [ response ] );

	});
}

function rcp_calc_total() {

	var $               = jQuery;
	var $total          = $('.rcp_registration_total');
	var discount        = $('#rcp_discount_code').val();
	var subscription    = ( $('#rcp_subscription_levels input:checked').length ) ? $('#rcp_subscription_levels input:checked').val() : $('input[name="rcp_level"]').val();
	var $gateway        = rcp_get_gateway();

	if ( ! $total.length ) {
		return;
	}

	rcp_calculating_total = true;

	var data = {
		action: 'rcp_calc_discount',
		rcp_level: subscription,
		rcp_discount: discount,
		rcp_gateway: $gateway.val()
	};

	if ( $('#rcp_auto_renew').is(':checked') ) {
		data.rcp_auto_renew = true;
	}

	$.post(rcp_script_options.ajaxurl, data, function(response) {
		rcp_calculating_total = false;

		if (undefined !== response.total ) {
			$total.html(response.total);
		}

	});

}