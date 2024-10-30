<?php
/**
 * Plugin Name: CereTax
 * Plugin URI: https://woocommerce.com/products/woocommerce-ceretax/
 * Description: Simplify sales tax complexity with CereTax for WooCommerce.
 * Version: 1.2.0
 * Author: CereTax, Inc.
 * Author URI: https://www.ceretax.com/
 * Developer: CereTax, Inc.
 * Developer URI: https://www.ceretax.com/
 * Text Domain: ceretax
 * Domain Path: /languages
 * Requires Plugins: woocommerce
 *
 * Woo: 12345:342928dfsfhsf8429842374wdf4234sfd
 * WC requires at least: 6.0
 * WC tested up to: 8.0
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package Custom Woo Addon For Ceretax
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit;
}

/**
 * Basic plugin definitions
 *
 * @package Custom Woo Addon For Ceretax
 * @since 1.0
 */

// Version of plugin.
if ( ! defined( 'CWAFC_VERSION' ) ) {
	define( 'CWAFC_VERSION', '1.0.0' );
}

// Plugin File.
if ( ! defined( 'CWAFC_FILE' ) ) {
	define( 'CWAFC_FILE', __FILE__ );
}

// Plugin dir.
if ( ! defined( 'CWAFC_DIR' ) ) {
	define( 'CWAFC_DIR', __DIR__ );
}

// Plugin url.
if ( ! defined( 'CWAFC_URL' ) ) {
	define( 'CWAFC_URL', plugin_dir_url( __FILE__ ) );
}

// Plugin path.
if ( ! defined( 'CWAFC_DIR_PATH' ) ) {
	define( 'CWAFC_DIR_PATH', plugin_dir_path( __FILE__ ) );
}

// Plugin base name.
if ( ! defined( 'CWAFC_PLUGIN_BASENAME' ) ) {
	define( 'CWAFC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
}

// Plugin metabox prefix.
if ( ! defined( 'CWAFC_META_PREFIX' ) ) {
	define( 'CWAFC_META_PREFIX', 'cwafc_' );
}

// Plugin prefix.
if ( ! defined( 'CWAFC_PREFIX' ) ) {
	define( 'CWAFC_PREFIX', 'cwafc' );
}

/**
 * Initialize the main class
 */
if ( ! function_exists( 'cwafc' ) ) {

	if ( is_admin() ) {
		include CWAFC_DIR . '/inc/admin/class-' . CWAFC_PREFIX . '-admin.php';
		include CWAFC_DIR . '/inc/admin/class-' . CWAFC_PREFIX . '-admin-action.php';
		include CWAFC_DIR . '/inc/admin/class-' . CWAFC_PREFIX . '-admin-filter.php';
	} else {
		include CWAFC_DIR . '/inc/front/class-' . CWAFC_PREFIX . '-front.php';
		include CWAFC_DIR . '/inc/front/class-' . CWAFC_PREFIX . '-front-action.php';
		include CWAFC_DIR . '/inc/front/class-' . CWAFC_PREFIX . '-front-filter.php';
	}

	// Initialize all the things.
	include CWAFC_DIR . '/inc/class-' . CWAFC_PREFIX . '.php';
}
