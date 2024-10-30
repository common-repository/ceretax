<?php
/**
 * CWAFC_Front Class
 *
 * Handles the Frontend functionality.
 *
 * @package Custom Woo Addon For Ceretax
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit;
}

if ( ! class_exists( 'CWAFC_Front' ) ) {

	/**
	 * The CWAFC_Front Class
	 */
	class CWAFC_Front {

		/**
		 * $action var for instance.
		 *
		 * @var null
		 */
		public $action = null;

		/**
		 * $filter var for instance.
		 *
		 * @var null
		 */
		public $filter = null;

		/**
		 * Construct.
		 */
		public function __construct() {
		}
	}

	add_action(
		'plugins_loaded',
		function () {
			cwafc()->front = new CWAFC_Front();
		}
	);
}
