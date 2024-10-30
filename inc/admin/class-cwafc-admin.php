<?php
/**
 * CWAFC_Admin Class
 *
 * Handles the admin functionality.
 *
 * @package Custom Woo Addon For Ceretax
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit;
}

if ( ! class_exists( 'CWAFC_Admin' ) ) {

	/**
	 * The CWAFC_Admin Class
	 */
	class CWAFC_Admin {

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
			cwafc()->admin = new CWAFC_Admin();
		}
	);
}
