<?php
/**
 * CWAFC_Admin_Action Class
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

if ( ! class_exists( 'CWAFC_Admin_Action' ) ) {

	/**
	 *  The CWAFC_Admin_Action Class
	 */
	class CWAFC_Admin_Action {

		/**
		 * Construct.
		 */
		public function __construct() {

			add_action( 'admin_enqueue_scripts', array( $this, 'action__cwafc_admin_init' ) );
			// Add settings fields to the new tab.
			add_action( 'woocommerce_settings_tabs_cwafc_ceretax', array( $this, 'action__cwafc_settings_tab' ) );
			// Save settings.
			add_action( 'woocommerce_update_options_cwafc_ceretax', array( $this, 'action__cwafc_update_settings' ) );
			// Display custom fields in the "General" tab of the product edit page.
			add_action( 'woocommerce_product_options_general_product_data', array( $this, 'action__cwafc_add_custom_product_fields' ) );
			// Save custom fields when the product is saved.
			add_action( 'woocommerce_process_product_meta', array( $this, 'action__cwafc_save_custom_product_fields' ) );
			// Add custom field to product category.
			add_action( 'product_cat_add_form_fields', array( $this, 'action__cwafc_add_custom_field_to_product_category' ), 10, 2 );
			add_action( 'product_cat_edit_form_fields', array( $this, 'action__cwafc_edit_custom_field_in_product_category' ), 10, 2 );
			// Save custom field to product category.
			add_action( 'created_product_cat', array( $this, 'action__cwafc_save_custom_field_in_product_category' ), 10, 2 );
			add_action( 'edited_product_cat', array( $this, 'action__cwafc_save_custom_field_in_product_category' ), 10, 2 );
		}

		/**
		 * Register admin min js and admin min css.
		 *
		 * @param string $hook get the settings page name.
		 * @return void
		 */
		public function action__cwafc_admin_init( $hook ) {
			if ( 'woocommerce_page_wc-settings' !== $hook ) {
				return;
			}
			wp_enqueue_script( CWAFC_PREFIX . '_admin_js', CWAFC_URL . 'assets/js/admin.js', array( 'jquery-core' ), CWAFC_VERSION, true );
			wp_enqueue_style( CWAFC_PREFIX . '_admin_css', CWAFC_URL . 'assets/css/admin.css', array(), CWAFC_VERSION );
		}

		/**
		 * Woocommerce setting tabs callback
		 *
		 * @return void
		 */
		public function action__cwafc_settings_tab() {
			woocommerce_admin_fields( $this->cwafc_get_settings() );
		}

		/**
		 * Woocommerce setting tabs save fields
		 *
		 * @return mix
		 */
		public function action__cwafc_update_settings() {

			// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verification already handled in WC_Admin_Settings::save()
			$cere_tax_api_key = ( null !== CWAFC_PREFIX && isset( $_POST[ CWAFC_PREFIX . '_cere_tax_api_key' ] ) ) ? sanitize_text_field( wp_unslash( $_POST[ CWAFC_PREFIX . '_cere_tax_api_key' ] ) ) : '';
			$cere_tax_tax_env = ( null !== CWAFC_PREFIX && isset( $_POST[ CWAFC_PREFIX . '_cere_tax_environment' ] ) ) ? sanitize_text_field( wp_unslash( $_POST[ CWAFC_PREFIX . '_cere_tax_environment' ] ) ) : '';
			// phpcs:enable
			$valid_api_key = $this->cwafc_is_valid_cere_tax_api_key( $cere_tax_api_key, $cere_tax_tax_env );

			if ( ! $valid_api_key ) {
				WC_Admin_Settings::add_error( __( 'API key is not valid. Please add valid API key.', 'ceretax' ) );
				return false;
			} else {
				woocommerce_update_options( $this->cwafc_get_settings() );
				WC_Admin_Settings::add_message( __( 'API key validated successfully.', 'ceretax' ) );
			}
		}

		/**
		 * Define the settings fields
		 *
		 * @return $settings
		 */
		public function cwafc_get_settings() {
			$settings = array(
				array(
					'name' => __( 'Connect to CereTax', 'ceretax' ),
					'type' => 'title',
				),

				array(
					'name'     => __( 'API Key', 'ceretax' ),
					'type'     => 'text',
					'desc'     => __( 'Enter your API key for CereTax', 'ceretax' ),
					'id'       => CWAFC_PREFIX . '_cere_tax_api_key',
					'desc_tip' => true,
					'autoload' => false,
				),

				array(
					'name'     => __( 'Environment', 'ceretax' ),
					'type'     => 'select',
					'desc'     => __( 'Select the environment', 'ceretax' ),
					'id'       => CWAFC_PREFIX . '_cere_tax_environment',
					'desc_tip' => true,
					'autoload' => false,
					'options'  => array(
						'cert'       => __( 'Cert', 'ceretax' ),
						'production' => __( 'Production', 'ceretax' ),
					),
				),

				array(
					'name'     => __( 'Profile', 'ceretax' ),
					'type'     => 'text',
					'desc'     => __( 'Enter your profile for CereTax', 'ceretax' ),
					'id'       => CWAFC_PREFIX . '_cere_tax_profile',
					'desc_tip' => true,
					'autoload' => false,
				),

				array(
					'type' => 'sectionend',
					'id'   => 'cere_tax_section_end',
				),

				array(
					'name' => __( 'General Settings', 'ceretax' ),
					'type' => 'title',
				),

				array(
					'name'    => __( 'Enable CereTax', 'ceretax' ),
					'type'    => 'checkbox',
					'id'      => CWAFC_PREFIX . '_cere_tax_enable',
					'class'   => 'cere_tax_checkbox',
					'default' => 'no',
				),

				array(
					'name'    => __( 'Post finalized transactions to CereTax', 'ceretax' ),
					'type'    => 'checkbox',
					'id'      => CWAFC_PREFIX . '_cere_tax_post_transactions',
					'class'   => 'cere_tax_checkbox',
					'default' => 'no',
				),

				array(
					'name'    => __( 'Enable Logging', 'ceretax' ),
					'type'    => 'checkbox',
					'id'      => CWAFC_PREFIX . '_cere_tax_enable_logging',
					'class'   => 'cere_tax_checkbox',
					'default' => 'no',
				),

				array(
					'type' => 'sectionend',
					'id'   => 'cere_tax_section_end',
				),

				array(
					'name' => __( 'Address Validation Settings', 'ceretax' ),
					'type' => 'title',
				),

				array(
					'name'    => __( 'Validate customer and vendor addresses', 'ceretax' ),
					'type'    => 'checkbox',
					'id'      => CWAFC_PREFIX . '_cere_tax_validate_customer_addresses',
					'class'   => 'cere_tax_checkbox',
					'default' => 'no',
				),

				array(
					'name'    => __( 'Validate addresses on every transaction', 'ceretax' ),
					'type'    => 'checkbox',
					'id'      => CWAFC_PREFIX . '_cere_tax_validate_on_transaction',
					'class'   => 'cere_tax_checkbox',
					'default' => 'no',
				),

				array(
					'type' => 'sectionend',
					'id'   => 'cere_tax_section_end',
				),

				array(
					'name' => __( 'Default Settings', 'ceretax' ),
					'type' => 'title',
				),

				array(
					'name'     => __( 'Business Type', 'ceretax' ),
					'type'     => 'text',
					'desc'     => __( 'Enter your business type', 'ceretax' ),
					'id'       => CWAFC_PREFIX . '_cere_tax_business_type',
					'desc_tip' => true,
					'autoload' => false,
				),

				array(
					'name'     => __( 'Customer Type', 'ceretax' ),
					'type'     => 'text',
					'desc'     => __( 'Enter your customer type', 'ceretax' ),
					'id'       => CWAFC_PREFIX . '_cere_tax_customer_type',
					'desc_tip' => true,
					'autoload' => false,
				),

				array(
					'name'     => __( 'PS Code', 'ceretax' ),
					'type'     => 'text',
					'desc'     => __( 'Enter your PS code', 'ceretax' ),
					'id'       => CWAFC_PREFIX . '_cere_tax_ps_code',
					'desc_tip' => true,
					'autoload' => false,
				),

				array(
					'name'    => __( 'Tax Included', 'ceretax' ),
					'type'    => 'checkbox',
					'id'      => CWAFC_PREFIX . '_cere_tax_tax_included',
					'class'   => 'cere_tax_checkbox',
					'default' => 'no',
				),

				array(
					'type' => 'sectionend',
					'id'   => 'cere_tax_section_end',
				),
			);

			return $settings;
		}

		/**
		 * Check API key entered is valid or not
		 *
		 * @param string $api_key ceratax api key.
		 * @param string $tax_env ceratax api env.
		 * @return boolean
		 */
		public function cwafc_is_valid_cere_tax_api_key( $api_key, $tax_env ) {

			if ( 'production' === $tax_env ) {
				$api_url = 'https://calc.prod.ceretax.net/test';
			} else {
				$api_url = 'https://calc.cert.ceretax.net/test';
			}

			$response = wp_remote_post(
				$api_url,
				array(
					'method'  => 'POST',
					'headers' => array(
						'x-api-key'    => $api_key,
						'Content-Type' => 'application/json',
					),
					'timeout' => 60,
				)
			);

			if ( is_wp_error( $response ) ) {
				return false;
			} else {
				$response_code = wp_remote_retrieve_response_code( $response );
			}
			if ( 200 === $response_code ) {
				return true;
			}
			return false;
		}

		/**
		 * Add custom fields to products
		 *
		 * @return void
		 */
		public function action__cwafc_add_custom_product_fields() {

			woocommerce_wp_text_input(
				array(
					'id'          => CWAFC_PREFIX . '_cere_tax_product_ps_code',
					'label'       => __( 'CereTax PS Code', 'ceretax' ),
					'placeholder' => __( 'Enter PS Code', 'ceretax' ),
					'desc_tip'    => 'true',
					'description' => __( 'PS Codes help identify the appropriate taxes, taxability, and rates that are applicable to a transaction.', 'ceretax' ),
				)
			);
		}

		/**
		 * Save custom fields to products
		 *
		 * @param string $product_id Product ID.
		 * @return void
		 */
		public function action__cwafc_save_custom_product_fields( $product_id ) {

			$product_ps_code_key = CWAFC_PREFIX . '_cere_tax_product_ps_code';
			$product             = wc_get_product( $product_id );
			// phpcs:disable WordPress.Security.NonceVerification.Missing
			$product_ps_code = isset( $_POST[ $product_ps_code_key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $product_ps_code_key ] ) ) : '';
			// phpcs:enable WordPress.Security.NonceVerification.Missing
			$product->update_meta_data( $product_ps_code_key, sanitize_text_field( $product_ps_code ) );
			$product->save();
		}

		/**
		 * Add custom fields to products category
		 *
		 * @return void
		 */
		public function action__cwafc_add_custom_field_to_product_category() {
			?>
				<div class="form-field">
					<label for="custom_field"><?php esc_html_e( 'CereTax PS Code', 'ceretax' ); ?></label>
					<input type="text" name="<?php echo esc_attr( CWAFC_PREFIX . '_cere_tax_product_cat_ps_code' ); ?>" id="cere_tax_product_cat_ps_code" value="">
					<p class="description"><?php esc_html_e( 'Enter product category PS code.', 'ceretax' ); ?></p>
				</div>
			<?php
		}

		/**
		 * Add custom fields in products edit category
		 *
		 * @param string $term ceratax term object.
		 *
		 * @return void
		 */
		public function action__cwafc_edit_custom_field_in_product_category( $term ) {
			$cere_tax_product_cat_ps_code = get_term_meta( $term->term_id, CWAFC_PREFIX . '_cere_tax_product_cat_ps_code', true );
			?>
			<tr class="form-field">
				<th scope="row" valign="top">
					<label for="cere_tax_product_cat_ps_code"><?php esc_html_e( 'CereTax PS Code', 'ceretax' ); ?></label>
				</th>
				<td>
					<input type="text" name="<?php echo esc_attr( CWAFC_PREFIX . '_cere_tax_product_cat_ps_code' ); ?>" id="cere_tax_product_cat_ps_code" value="<?php echo esc_attr( $cere_tax_product_cat_ps_code ) ? esc_attr( $cere_tax_product_cat_ps_code ) : ''; ?>">
					<p class="description"><?php esc_html_e( 'Enter product category PS code.', 'ceretax' ); ?></p>
				</td>
			</tr>
			<?php
		}

		/**
		 * Add custom fields in products edit category
		 *
		 * @param string $term_id ceratax term ID.
		 *
		 * @return void
		 */
		public function action__cwafc_save_custom_field_in_product_category( $term_id ) {
			$term_field_meta_key = CWAFC_PREFIX . '_cere_tax_product_cat_ps_code';
			// phpcs:disable WordPress.Security.NonceVerification.Missing
			if ( isset( $_POST[ $term_field_meta_key ] ) ) {
				update_term_meta( $term_id, $term_field_meta_key, sanitize_text_field( wp_unslash( $_POST[ $term_field_meta_key ] ) ) );
			}
			// phpcs:enable WordPress.Security.NonceVerification.Missing
		}
	}

	add_action(
		'plugins_loaded',
		function () {
			cwafc()->admin->action = new CWAFC_Admin_Action();
		}
	);
}
