jQuery( document ).ready( function( $ ) {

	// Caclulate tax if option enabled
	if ( cwa_front_ajax_object && cwa_front_ajax_object.is_ceretax_enable && cwa_front_ajax_object.enable_address_change == 1 ) {
		
		function debounce(func, wait) {
			let timeout;
			return function(...args) {
				const context = this;
				clearTimeout(timeout);
				timeout = setTimeout(() => func.apply(context, args), wait);
			};
		}

		function setupChangeEvents() {
			// Clear previous bindings
			$( document ).off( 'change', '#shipping_country, #shipping_state, #shipping_city, #shipping_postcode, #shipping_address_1, #shipping-country, #shipping-state, #shipping-city, #shipping-postcode' );
			$( document ).off( 'change', '#billing_country, #billing_state, #billing_city, #billing_postcode, #billing_address_1' );

			// Determine which fields to bind based on checkbox state
			var ship_to_different_address = $( '#ship-to-different-address-checkbox' ).is(':checked');

			var fields = ship_to_different_address ? 
				'#shipping_country, #shipping_state, #shipping_city, #shipping_postcode, #shipping_address_1, #shipping-country, #shipping-state, #shipping-city, #shipping-postcode' :
				'#billing_country, #billing_state, #billing_city, #billing_postcode, #billing_address_1';

			// Bind event handler to the selected fields
			$( document ).on( 'change', fields, debounce(function() {

				triggerTaxCalculation(ship_to_different_address);

				// var data = {
				// 	action: 'update_tax_rate',
				// 	address_1: ship_to_different_address ? ( $('#shipping-address_1').val() || $('#shipping_address_1').val() ) : $('#billing_address_1').val(),
				// 	address_2: ship_to_different_address ? ( $('#shipping-address_2').val() || $('#shipping_address_2').val() ) : $('#billing_address_2').val(),
				// 	city: ship_to_different_address ? ( $('#shipping-city').val() || $('#shipping_city').val() ) : $('#billing_city').val(),
				// 	country: ship_to_different_address ? ( $('#shipping-country input').val() || $('#shipping_country').val() ) : $('#billing_country').val(),
				// 	state: ship_to_different_address ? ( $('#shipping-state input').val() || $('#shipping_state').val() ) : $('#billing_state').val(),
				// 	postcode: ship_to_different_address ? ( $('#shipping-postcode').val() || $('#shipping_postcode').val() ) : $('#billing_postcode').val(),
				// };
				// calculate_ceretax( data, ship_to_different_address ? 'shipping' : 'billing' );
			}, 500));
		}

		// Function to trigger tax calculation
        function triggerTaxCalculation(ship_to_different_address) {
            var data = {
				action: 'update_tax_rate',
				address_1: ship_to_different_address ? ( $('#shipping-address_1').val() || $('#shipping_address_1').val() ) : $('#billing_address_1').val(),
				address_2: ship_to_different_address ? ( $('#shipping-address_2').val() || $('#shipping_address_2').val() ) : $('#billing_address_2').val(),
				city: ship_to_different_address ? ( $('#shipping-city').val() || $('#shipping_city').val() ) : $('#billing_city').val(),
				country: ship_to_different_address ? ( $('#shipping-country input').val() || $('#shipping_country').val() ) : $('#billing_country').val(),
				state: ship_to_different_address ? ( $('#shipping-state input').val() || $('#shipping_state').val() ) : $('#billing_state').val(),
				postcode: ship_to_different_address ? ( $('#shipping-postcode').val() || $('#shipping_postcode').val() ) : $('#billing_postcode').val(),
				nonce: cwa_front_ajax_object.nonce,
			};
			calculate_ceretax( data, ship_to_different_address ? 'shipping' : 'billing' );
        }

		// Initial setup
		setupChangeEvents();

		// Re-setup events when the checkbox changes
		$( document ).on('change', '#ship-to-different-address-checkbox', function() {
			setupChangeEvents();
			triggerTaxCalculation($(this).is(':checked'));
		} );

	}


	// Validate address if option enabled
	if ( cwa_front_ajax_object && cwa_front_ajax_object.is_ceretax_enable_validate_addresses ) {
	
		$(document).on( 'click', '#cwa-address-validate-button', function(){

			// Validate address if option enabled
			if ( cwa_front_ajax_object && ! cwa_front_ajax_object.is_ceretax_enable_validate_addresses ) {
				return false;
			}

			var ship_to_different_address = jQuery( '#ship-to-different-address-checkbox' ).is( ':checked' );			

			
			if ( ship_to_different_address ) {

				// Validate shipping address
				var data = {
					action: 'validate_address',
					address_1: $('#shipping-address_1').val() || $('#shipping_address_1').val(),
					address_2: $('#shipping-address_2').val() || $('#shipping_address_2').val(),
					city: $('#shipping-city').val() || $('#shipping_city').val(),
					country: $('#shipping-country input').val() || $('#shipping_country').val(),
					state: $('#shipping-state input').val() || $('#shipping_state').val(),
					postcode: $('#shipping-postcode').val() || $('#shipping_postcode').val(),
					address_validation: 'shipping',
					nonce: cwa_front_ajax_object.nonce,
				};

			} else {

				// Validate billing address
				var data = {
					action: 'validate_address',
					address_1: $('#billing_address_1').val(),
					address_2: $('#billing_address_2').val(),
					city: $('#billing_city').val(),
					country: $('#billing_country').val(),
					state: $('#billing_state').val(),
					postcode: $('#billing_postcode').val(),
					address_validation: 'billing',
					nonce: cwa_front_ajax_object.nonce,
				};

			}

			

			$.ajax({
				type: 'POST',
				url: cwa_front_ajax_object.ajax_url,
				data: data,
				success: function(response) {

					if (response.success) {

						if ( response.data.validatedAddress != null ) {

							if ( 'shipping' == data.address_validation ) {
								if ( response.data.validatedAddress.addressLine1 != '' && response.data.validatedAddress.addressLine1 != null ) {
									$('#shipping_address_1').val( response.data.validatedAddress.addressLine1 );
								}
								if ( response.data.validatedAddress.addressLine2 != '' && response.data.validatedAddress.addressLine2 != null ) {
									$('#shipping_address_2').val( response.data.validatedAddress.addressLine2 );
								}
								if ( response.data.validatedAddress.city != '' && response.data.validatedAddress.city != null ) {
									$('#shipping_city').val( response.data.validatedAddress.city );
								}
								if ( response.data.validatedAddress.state != '' && response.data.validatedAddress.state != null ) {
									$('#shipping_state').val( response.data.validatedAddress.state );
								}
								if ( response.data.validatedAddress.country != '' && response.data.validatedAddress.country != null ) {
									$('#shipping_country').val( response.data.validatedAddress.country );
								}
								if ( response.data.validatedAddress.postalCode != '' && response.data.validatedAddress.postalCode != null ) {
									$('#shipping_postcode').val( response.data.validatedAddress.postalCode );
								}
							} else {
								if ( response.data.validatedAddress.addressLine1 != '' && response.data.validatedAddress.addressLine1 != null ) {
									$('#billing_address_1').val( response.data.validatedAddress.addressLine1 );
								}
								if ( response.data.validatedAddress.addressLine2 != '' && response.data.validatedAddress.addressLine2 != null ) {
									$('#billing_address_2').val( response.data.validatedAddress.addressLine2 );
								}
								if ( response.data.validatedAddress.city != '' && response.data.validatedAddress.city != null ) {
									$('#billing_city').val( response.data.validatedAddress.city );
								}
								if ( response.data.validatedAddress.state != '' && response.data.validatedAddress.state != null ) {
									$('#billing_state').val( response.data.validatedAddress.state );
								}
								if ( response.data.validatedAddress.country != '' && response.data.validatedAddress.country != null ) {
									$('#billing_country').val( response.data.validatedAddress.country );
								}
								if ( response.data.validatedAddress.postalCode != '' && response.data.validatedAddress.postalCode != null ) {
									$('#billing_postcode').val( response.data.validatedAddress.postalCode );
								}
							}

						}

						$('.woocommerce .woocommerce-notices-wrapper:first-child').html(response.data.notice);
						$(document.body).trigger('update_checkout');
						if ( $('.woocommerce-notices-wrapper').length) {
							$('html, body').animate({
								scrollTop: $('.woocommerce-notices-wrapper').offset().top
							}, 1000);
						}
					}
					jQuery(document.body).trigger('update_checkout');
				}
			});
		});

	}

});


function calculate_ceretax( data, type ) {

	// Caclulate tax if only CereTax calculation option is not enabled
	if ( cwa_front_ajax_object && ! cwa_front_ajax_object.is_ceretax_enable ) {
		return false;
	}

	jQuery.ajax({
		type: 'POST',
		url: cwa_front_ajax_object.ajax_url,
		data: data,
		success: function(response) {
			if (response.success) {
				// Refresh the checkout to reflect the updated tax rate
				// $('body').trigger('update_checkout');
				jQuery(document.body).trigger('update_checkout');
			} else {
				jQuery(document.body).trigger('update_checkout');
			}
		}
	});

}
