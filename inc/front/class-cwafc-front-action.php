<?php
/**
 * CWAFC_Front_Action Class
 *
 * Handles the Frontend Actions.
 *
 * @package Custom Woo Addon For Ceretax
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit;
}

if ( ! class_exists( 'CWAFC_Front_Action' ) ) {

	/**
	 *  The CWAFC_Front_Action Class
	 */
	class CWAFC_Front_Action {

		/**
		 * Construct.
		 */
		public function __construct() {
			add_action( 'wp_enqueue_scripts', array( $this, 'action__cwafc_enqueue_scripts' ) );
		}

		/**
		 * Enqueue script in front side
		 *
		 * @return void
		 */
		public function action__cwafc_enqueue_scripts() {

			$enable_address_change = 1;
			if ( is_user_logged_in() ) {
				$user_id         = get_current_user_id();
				$billing_address = get_user_meta( $user_id, 'billing_address_1', true );
				$billing_city    = get_user_meta( $user_id, 'billing_city', true );
				$billing_state   = get_user_meta( $user_id, 'billing_state', true );
				if ( ! empty( $billing_address ) && ! empty( $billing_city ) && ! empty( $billing_state ) ) {
					$enable_address_change = 0;
				} else {
					$enable_address_change = 1;
				}
			} else {
				$enable_address_change = 1;
			}

			wp_enqueue_script( CWAFC_PREFIX . '_front_js', CWAFC_URL . 'assets/js/front.js', array( 'jquery-core' ), CWAFC_VERSION, true );
			wp_localize_script(
				CWAFC_PREFIX . '_front_js',
				'cwa_front_ajax_object',
				array(
					'ajax_url'                             => admin_url( 'admin-ajax.php' ),
					'nonce'                                => wp_create_nonce( 'cwa-nonce' ),
					'is_ceretax_enable'                    => ( function_exists( 'cwafc' ) ) ? cwafc()->cwafc_is_ceretax_enable() : false,
					'is_ceretax_enable_validate_addresses' => ( function_exists( 'cwafc' ) ) ? cwafc()->cwafc_is_ceretax_enable_validate_addresses() : false,
					'ship_to_destination'                  => get_option( 'woocommerce_ship_to_destination' ),
					'enable_address_change'                => $enable_address_change,
				)
			);
		}
	}

	add_action(
		'plugins_loaded',
		function () {
			cwafc()->front->action = new CWAFC_Front_Action();
		}
	);
}
