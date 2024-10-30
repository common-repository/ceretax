( function($) {
	"use strict";
		$('.cere_tax_checkbox').each(function() {
			var $checkbox = $(this);
			$checkbox.wrap('<label class="toggle-button"></label>');
			$checkbox.after('<span class="slider"></span>');
		});

} )( jQuery );
