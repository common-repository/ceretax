<?php
/**
 * CWAFC_Front_Filter Class
 *
 * Handles the Frontend Filters.
 *
 * @package Custom Woo Addon For Ceretax
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit;
}

if ( ! class_exists( 'CWAFC_Front_Filter' ) ) {

	/**
	 *  The CWAFC_Front_Filter Class
	 */
	class CWAFC_Front_Filter {

		/**
		 * Construct.
		 */
		public function __construct() {
		}
	}

	add_action(
		'plugins_loaded',
		function () {
			cwafc()->front->filter = new CWAFC_Front_Filter();
		}
	);
}
