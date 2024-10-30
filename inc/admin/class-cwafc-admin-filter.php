<?php
/**
 * CWAFC_Admin_Filter Class
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

if ( ! class_exists( 'CWAFC_Admin_Filter' ) ) {

	/**
	 *  The CWAFC_Admin_Filter Class
	 */
	class CWAFC_Admin_Filter {

		/**
		 * Construct.
		 */
		public function __construct() {
			// Filter to add plugin links.
			add_filter( 'plugin_action_links_' . CWAFC_PLUGIN_BASENAME, array( $this, 'filter__cwafc_plugin_action_links' ) );

			// Add a tab section to WooCommerce settings.
			add_filter( 'woocommerce_settings_tabs_array', array( $this, 'filter__cwafc_tax_add_settings_tab' ), 50 );
		}

		/**
		 * Function to add extra plugins link
		 *
		 * @param string $links Url add to settings.
		 * @return $links
		 */
		public function filter__cwafc_plugin_action_links( $links ) {
			$settings_url            = add_query_arg( array( 'page' => 'wc-settings&tab=cwafc_ceretax' ), admin_url( 'admin.php' ) );
			$links['cwafc-settings'] = '<a href="' . esc_url( $settings_url ) . '" title="' . esc_attr( __( 'Plugin Settings', 'ceretax' ) ) . '">' . __( 'Settings', 'ceretax' ) . '</a>';
			return $links;
		}

		/**
		 * Function to add extra tabs in WooCommerce settings.
		 *
		 * @param string $tabs tab add to settings.
		 * @return $tabs
		 */
		public function filter__cwafc_tax_add_settings_tab( $tabs ) {
			$tabs['cwafc_ceretax'] = __( 'CereTax', 'ceretax' );
			return $tabs;
		}
	}

	add_action(
		'plugins_loaded',
		function () {
			cwafc()->admin->filter = new CWAFC_Admin_Filter();
		}
	);
}
