<?php
/**
 * CWAFC Class
 *
 * Handles the theme functionality.
 *
 * @package Custom Woo Addon For Ceretax
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit;
}

if ( ! class_exists( 'CWAFC' ) ) {

	/**
	 * The main CWAFC class
	 */
	class CWAFC {

		/**
		 * $admin var for instance.
		 *
		 * @var null
		 */
		public $admin = null;

		/**
		 * $front var for instance.
		 *
		 * @var null
		 */
		public $front = null;

		/**
		 * Instance create for class.
		 *
		 * @var object
		 */
		public static function get_instance() {
			static $instance = null;
			if ( is_null( $instance ) ) {
				$instance = new self();
			}
			return $instance;
		}

		/**
		 * Create array for static data
		 *
		 * @var array  static data => value
		 */
		protected $static_data = array(
			'id' => 123,
		);

		/**
		 * Construct.
		 */
		public function __construct() {
			// Plugin loaded.
			add_action( 'plugins_loaded', array( $this, 'action__cwafc_plugins_loaded' ), 1 );
			// Plugin activation hook.
			register_activation_hook( CWAFC_FILE, array( $this, 'action__cwafc_plugin_activation' ) );
			// Init hook.
			add_action( 'init', array( $this, 'action__cwafc_init' ) );
			// Make plugin HPOS compatible.
			add_action( 'before_woocommerce_init', array( $this, 'action__cwafc_before_woocommerce_init' ) );
			// Update tax rate based on billing/shipping addresses.
			add_action( 'wp_ajax_update_tax_rate', array( $this, 'action__cwafc_update_tax_rate' ) );
			add_action( 'wp_ajax_nopriv_update_tax_rate', array( $this, 'action__cwafc_update_tax_rate' ) );
			// Validate billing/shipping address on checkout page.
			add_action( 'wp_ajax_validate_address', array( $this, 'action__cwafc_validate_address' ) );
			add_action( 'wp_ajax_nopriv_validate_address', array( $this, 'action__cwafc_validate_address' ) );
			// Add default zero tax to cart.
			add_action( 'woocommerce_cart_totals_before_order_total', array( $this, 'action__cwafc_woocommerce_cart_totals_before_shipping' ) );
			// Apply the tax/fee.
			add_action( 'woocommerce_cart_calculate_fees', array( $this, 'action__cwafc_apply_custom_tax_rate' ) );
			// Add address validation button on checkout page.
			add_action( 'woocommerce_before_order_notes', array( $this, 'action__cwafc_add_address_validate_button' ) );
			// Woocommerce overide the plugin template.
			add_filter( 'woocommerce_locate_template', array( $this, 'filter__cwafc_woo_adon_plugin_template' ), 10, 3 );
			// Add/Update order meta.
			add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'action__cwafc_checkout_field_update_order_meta' ) );
			// Add tax caculation table on admin order edit page.
			add_action( 'woocommerce_admin_order_items_after_shipping', array( $this, 'action__cwafc_add_tax_calculation_table_after_shipping' ), 99 );
			// Add tax calulation on order place.
			add_action( 'woocommerce_new_order', array( $this, 'action__cwafc_add_tax_calulation_when_order_placed' ), 10, 2 );
			// Add tax calulation on subscription renewal order place.
			add_filter( 'wcs_renewal_order_created', array( $this, 'filter__cwafc_add_tax_calulation_when_renewal_order_placed' ), 10 );
			// Update tax calulation on order update.
			add_action( 'woocommerce_order_after_calculate_totals', array( $this, 'action__cwafc_order_after_calculate_totals' ), 10, 2 );
			// Allow woocommerce subscriptions to add recurring fee.
			add_filter( 'woocommerce_subscriptions_is_recurring_fee', '__return_true' );
		}

		/**
		 * Action: plugins_loaded
		 *
		 * @return void
		 */
		public function action__cwafc_plugins_loaded() {

			global $wp_version;

			// Set filter for plugin's languages directory.
			$pb_lang_dir = dirname( CWAFC_PLUGIN_BASENAME ) . '/languages/';
			$pb_lang_dir = apply_filters( 'pb_languages_directory', $pb_lang_dir );

			// Traditional WordPress plugin locale filter.
			$get_locale = get_locale();

			if ( $wp_version >= 4.7 ) {
				$get_locale = get_user_locale();
			}

			// Traditional WordPress plugin locale filter.
			$locale = apply_filters( 'plugin_locale', $get_locale, 'ceretax' );
			$mofile = sprintf( '%1$s-%2$s.mo', 'ceretax', $locale );

			// Setup paths to current locale file.
			$mofile_global = WP_LANG_DIR . '/plugins/' . basename( CWAFC_DIR ) . '/' . $mofile;

			if ( file_exists( $mofile_global ) ) {
				// Look in global /wp-content/languages/plugin-name folder.
				load_textdomain( 'ceretax', $mofile_global );
			} else {
				// Load the default language files.
				load_plugin_textdomain( 'ceretax', false, $pb_lang_dir );
			}
		}

		/**
		 * Register activation hook.
		 *
		 * @return void
		 */
		public function action__cwafc_plugin_activation() {}

		/**
		 * Disable the wooCommerce tax.
		 *
		 * @return void
		 */
		public function action__cwafc_init() {
			// If CereTax calculation option is enabled, then disable the wooCommerce tax disable.
			if ( $this->cwafc_is_ceretax_enable() ) {
				// Disable tax calculations.
				update_option( 'woocommerce_calc_taxes', 'no' );
				// Clear the transients to make sure the changes take effect immediately.
				WC_Cache_Helper::get_transient_version( 'shipping', true );
				WC_Cache_Helper::get_transient_version( 'shipping_taxes', true );
				WC_Cache_Helper::get_transient_version( 'cart', true );
				WC_Cache_Helper::get_transient_version( 'cart_taxes', true );
			}
		}

		/**
		 * Add compatibility for HPOS and add incompatibility for blocks
		 *
		 * @return void
		 */
		public function action__cwafc_before_woocommerce_init() {
			// Remove High-Performance Order Storage (HPOS) warning message from admin.
			if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', CWAFC_FILE, true );
			}

			// Set incompatibility of plugin for Cart and Checkout Blocks.
			if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', CWAFC_FILE, false );
			}
		}

		/**
		 * Update tax rate from ceta tax API
		 *
		 * @return void
		 */
		public function action__cwafc_update_tax_rate() {

			// Check for nonce security early exit if nonce is not set.
			if ( ! isset( $_POST['nonce'] ) ) {
				wp_send_json_error( array( 'message' => __( 'Nonce is missing. Please refresh the page and try again.', 'ceretax' ) ) );
				wp_die();
			}

			// Sanitize and verify nonce.
			$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );
			if ( ! wp_verify_nonce( $nonce, 'cwa-nonce' ) ) {
				wp_send_json_error( array( 'message' => __( 'Security check failed. Please refresh the page and try again.', 'ceretax' ) ) );
				wp_die();
			}

			$address_1 = isset( $_POST['address_1'] ) ? sanitize_text_field( wp_unslash( $_POST['address_1'] ) ) : '';
			$address_2 = isset( $_POST['address_2'] ) ? sanitize_text_field( wp_unslash( $_POST['address_2'] ) ) : '';
			$city      = isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '';
			$country   = isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : '';
			$state     = isset( $_POST['state'] ) ? sanitize_text_field( wp_unslash( $_POST['state'] ) ) : '';
			$postcode  = isset( $_POST['postcode'] ) ? sanitize_text_field( wp_unslash( $_POST['postcode'] ) ) : '';

			// Call your third-party API here.
			$tax_rate = $this->cwafc_get_tax_rate_from_api( $address_1, $address_2, $country, $city, $state, $postcode );

			if ( ! empty( $tax_rate ) ) {
				WC()->session->set( 'ceretax_rate', $tax_rate );
				wp_send_json_success();
			} else {
				WC()->session->set( 'ceretax_rate', 0 );
				wp_send_json_error();
			}
			wp_die();
		}

		/**
		 * Validate billing/shipping address.
		 *
		 * @return mix
		 */
		public function action__cwafc_validate_address() {

			// Check for nonce security early exit if nonce is not set.
			if ( ! isset( $_POST['nonce'] ) ) {
				wp_send_json_error( array( 'message' => __( 'Nonce is missing. Please refresh the page and try again.', 'ceretax' ) ) );
				wp_die();
			}

			// Sanitize and verify nonce.
			$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );
			if ( ! wp_verify_nonce( $nonce, 'cwa-nonce' ) ) {
				wp_send_json_error( array( 'message' => __( 'Security check failed. Please refresh the page and try again.', 'ceretax' ) ) );
				wp_die();
			}

			// Validate address only if option enable.
			if ( ! $this->cwafc_is_ceretax_enable_validate_addresses() ) {
				return false;
			}

			$address_1          = isset( $_POST['address_1'] ) ? sanitize_text_field( wp_unslash( $_POST['address_1'] ) ) : '';
			$address_2          = isset( $_POST['address_2'] ) ? sanitize_text_field( wp_unslash( $_POST['address_2'] ) ) : '';
			$city               = isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '';
			$country            = isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : '';
			$state              = isset( $_POST['state'] ) ? sanitize_text_field( wp_unslash( $_POST['state'] ) ) : '';
			$postcode           = isset( $_POST['postcode'] ) ? sanitize_text_field( wp_unslash( $_POST['postcode'] ) ) : '';
			$address_validation = isset( $_POST['address_validation'] ) ? sanitize_text_field( wp_unslash( $_POST['address_validation'] ) ) : '';

			$cere_tax_tax_env = get_option( CWAFC_PREFIX . '_cere_tax_environment' );
			$cere_tax_api_key = get_option( CWAFC_PREFIX . '_cere_tax_api_key' );

			if ( 'production' === $cere_tax_tax_env ) {
				$api_url = 'https://av.prod.ceretax.net/validate';
			} else {
				$api_url = 'https://av.cert.ceretax.net/validate';
			}

			$request_body = array(
				array(
					'addressLine1' => $address_1,
					'city'         => $city,
					'state'        => $state,
				),
			);

			$body = wp_json_encode( $request_body );

			// add api request data in wc logs if log option enabled.
			$this->cwafc_wc_add_custom_logs( $request_body, 'ceretax-address-validation-request' );

			$response = wp_remote_post(
				$api_url,
				array(
					'method'      => 'POST',
					'data_format' => 'body',
					'body'        => $body,
					'headers'     => array(
						'Accept'       => 'application/json',
						'x-api-key'    => $cere_tax_api_key,
						'Content-Type' => 'application/json',
					),
					'timeout'     => 60,
				)
			);

			// add api response data in wc logs if log option enabled.
			$this->cwafc_wc_add_custom_logs( $response, 'ceretax-address-validation-response' );

			if ( is_wp_error( $response ) ) {
				return 'Request failed: ' . $response->get_error_message();
			} else {
				$data = wp_remote_retrieve_body( $response );
				$data = json_decode( $data, true );
			}

			if ( is_wp_error( $response ) ) {
				return false;
			}

			if ( isset( $data['results'][0]['errorMessages'] ) ) {
				$notice_msg  = __( 'Address not validate : ', 'ceretax' );
				$notice_msg .= $data['results'][0]['errorMessages'][0]['message'];
				$notice_type = 'error';
			} else {
				$validated_address_details = $data['results'][0]['validatedAddressDetails'];
				if ( ! empty( $validated_address_details ) ) {

					$validated_address_line_1 = $data['results'][0]['validatedAddressDetails']['addressLine1'];
					$validated_address_line_2 = $data['results'][0]['validatedAddressDetails']['addressLine2'];
					$validated_city           = $data['results'][0]['validatedAddressDetails']['city'];
					$validated_state          = $data['results'][0]['validatedAddressDetails']['state'];
					$validated_country        = $data['results'][0]['validatedAddressDetails']['country'];
					$validated_postal_code    = $data['results'][0]['validatedAddressDetails']['postalCode'];

					if ( 'shipping' === $address_validation ) {

						WC()->customer->set_shipping_address_1( $validated_address_line_1 );
						WC()->customer->set_shipping_address_2( $validated_address_line_2 );
						WC()->customer->set_shipping_city( $validated_city );
						WC()->customer->set_shipping_state( $validated_state );
						WC()->customer->set_shipping_country( $validated_country );
						WC()->customer->set_shipping_postcode( $validated_postal_code );

					} else {

						WC()->customer->set_billing_address_1( $validated_address_line_1 );
						WC()->customer->set_billing_address_2( $validated_address_line_2 );
						WC()->customer->set_billing_city( $validated_city );
						WC()->customer->set_billing_state( $validated_state );
						WC()->customer->set_billing_country( $validated_country );
						WC()->customer->set_billing_postcode( $validated_postal_code );

					}

					$tax_rate = $this->cwafc_get_tax_rate_from_api(
						$validated_address_line_1,
						$validated_address_line_2,
						$validated_country,
						$validated_city,
						$validated_state,
						$validated_postal_code
					);

					if ( isset( $tax_rate ) && '' !== $tax_rate ) {
						WC()->session->set( 'ceretax_rate', $tax_rate );
					}

					$notice_msg  = __( 'Address validate successfully !', 'ceretax' );
					$notice_type = 'success';

				} else {
					$notice_msg  = $data['results'][0]['errorMessages'][0]['message'];
					$notice_type = 'error';
				}
			}

			ob_start();
			wc_print_notice( $notice_msg, $notice_type );
			$notice = ob_get_clean();
			wp_send_json_success(
				array(
					'notice'           => $notice,
					'validatedAddress' => $validated_address_details,
				)
			);
		}

		/**
		 * If the tax is not found then we will be hide the defualt value in cart.
		 *
		 * @return mix
		 */
		public function action__cwafc_woocommerce_cart_totals_before_shipping() {

			// Caclulate tax if only CereTax calculation option is not enabled.
			if ( ! $this->cwafc_is_ceretax_enable() ) {
				return false;
			}

			$custom_tax_rate = WC()->session->get( 'ceretax_rate', 0 );
			if ( empty( $custom_tax_rate ) ) {
				$tax_str = '<tr>
					<th>' . __( 'Tax', 'ceretax' ) . '</th>
					<td data-title="Tax">
						<span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol">' . esc_html( get_woocommerce_currency_symbol() ) . '</span>00.00</bdi></span>';
				if ( is_cart() ) {
					$tax_str .= '<p>' . __( 'Final Tax will be calculated on checkout page.', 'ceretax' ) . '</p>';
				}
				$tax_str .= '</td>
				</tr>';
				echo wp_kses_post( $tax_str );
			}
		}

		/**
		 * Calculate cart tax
		 *
		 * @return mix
		 */
		public function action__cwafc_apply_custom_tax_rate() {

			// Caclulate tax if only CereTax calculation option is not enabled.
			if ( ! $this->cwafc_is_ceretax_enable() ) {
				return false;
			}
			if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
				return;
			}

			$checkout_data = WC()->session->get( 'checkout_data' );

			$ceretax_stau = WC()->session->get( 'ceretax_stau' );

			// phpcs:disable WordPress.Security.NonceVerification.Missing

			if ( is_cart() && isset( $_POST['calc_shipping'] ) ) {

				if ( isset( $_POST['calc_shipping_address_line_1'] ) && ! empty( $_POST['calc_shipping_address_line_1'] ) ) {
					WC()->customer->set_shipping_address_1( sanitize_text_field( wp_unslash( $_POST['calc_shipping_address_line_1'] ) ) );
					WC()->customer->set_billing_address_1( sanitize_text_field( wp_unslash( $_POST['calc_shipping_address_line_1'] ) ) );
				}

				if ( isset( $_POST['calc_shipping_address_line_2'] ) && ! empty( $_POST['calc_shipping_address_line_2'] ) ) {
					WC()->customer->set_shipping_address_2( sanitize_text_field( wp_unslash( $_POST['calc_shipping_address_line_2'] ) ) );
					WC()->customer->set_billing_address_2( sanitize_text_field( wp_unslash( $_POST['calc_shipping_address_line_2'] ) ) );
				}

				$calc_shipping_address_1 = isset( $_POST['calc_shipping_address_line_1'] ) ? sanitize_text_field( wp_unslash( $_POST['calc_shipping_address_line_1'] ) ) : '';
				$calc_shipping_address_2 = isset( $_POST['calc_shipping_address_line_2'] ) ? sanitize_text_field( wp_unslash( $_POST['calc_shipping_address_line_2'] ) ) : '';
				$calc_shipping_country   = isset( $_POST['calc_shipping_country'] ) ? sanitize_text_field( wp_unslash( $_POST['calc_shipping_country'] ) ) : '';
				$calc_shipping_city      = isset( $_POST['calc_shipping_city'] ) ? sanitize_text_field( wp_unslash( $_POST['calc_shipping_city'] ) ) : '';
				$calc_shipping_state     = isset( $_POST['calc_shipping_state'] ) ? sanitize_text_field( wp_unslash( $_POST['calc_shipping_state'] ) ) : '';
				$calc_shipping_postcode  = isset( $_POST['calc_shipping_postcode'] ) ? sanitize_text_field( wp_unslash( $_POST['calc_shipping_postcode'] ) ) : '';

				$tax_rate = $this->cwafc_get_tax_rate_from_api(
					$calc_shipping_address_1,
					$calc_shipping_address_2,
					$calc_shipping_country,
					$calc_shipping_city,
					$calc_shipping_state,
					$calc_shipping_postcode,
				);
				if ( isset( $tax_rate ) && '' !== $tax_rate ) {
					WC()->session->set( 'ceretax_rate', $tax_rate );
				}
			} elseif ( is_cart() && ! WC()->cart->is_empty() && ! empty( $checkout_data ) ) {
				$tax_rate = $this->cwafc_get_tax_rate_from_api(
					$checkout_data['shipping_address_1'],
					$checkout_data['shipping_address_2'],
					$checkout_data['shipping_country'],
					$checkout_data['shipping_city'],
					$checkout_data['shipping_state'],
					$checkout_data['shipping_postcode']
				);
				if ( isset( $tax_rate ) && '' !== $tax_rate ) {
					WC()->session->set( 'ceretax_rate', $tax_rate );
				}
			} elseif ( is_checkout() && ! WC()->cart->is_empty() && isset( $_POST['post_data'] ) ) {

				if ( strpos( sanitize_text_field( wp_unslash( $_POST['post_data'] ) ), 'ship_to_different_address=1' ) !== false ) {
					$address_1 = isset( $_POST['s_address'] ) ? sanitize_text_field( wp_unslash( $_POST['s_address'] ) ) : '';
					$address_2 = isset( $_POST['s_address_2'] ) ? sanitize_text_field( wp_unslash( $_POST['s_address_2'] ) ) : '';
					$country   = isset( $_POST['s_country'] ) ? sanitize_text_field( wp_unslash( $_POST['s_country'] ) ) : '';
					$city      = isset( $_POST['s_city'] ) ? sanitize_text_field( wp_unslash( $_POST['s_city'] ) ) : '';
					$state     = isset( $_POST['s_state'] ) ? sanitize_text_field( wp_unslash( $_POST['s_state'] ) ) : '';
					$postcode  = isset( $_POST['s_postcode'] ) ? sanitize_text_field( wp_unslash( $_POST['s_postcode'] ) ) : '';
				} else {
					$address_1 = isset( $_POST['address'] ) ? sanitize_text_field( wp_unslash( $_POST['address'] ) ) : '';
					$address_2 = isset( $_POST['address_2'] ) ? sanitize_text_field( wp_unslash( $_POST['address_2'] ) ) : '';
					$country   = isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : '';
					$city      = isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '';
					$state     = isset( $_POST['state'] ) ? sanitize_text_field( wp_unslash( $_POST['state'] ) ) : '';
					$postcode  = isset( $_POST['postcode'] ) ? sanitize_text_field( wp_unslash( $_POST['postcode'] ) ) : '';
				}

				$tax_rate = $this->cwafc_get_tax_rate_from_api(
					$address_1,
					$address_2,
					$country,
					$city,
					$state,
					$postcode
				);

				if ( isset( $tax_rate ) && '' !== $tax_rate ) {
					WC()->session->set( 'ceretax_rate', $tax_rate );
				}
			}

			// phpcs:enable WordPress.Security.NonceVerification.Missing
			$custom_tax_rate = WC()->session->get( 'ceretax_rate', 0 );
			if ( ! empty( $custom_tax_rate ) ) {
				$tax = $custom_tax_rate;
				// Ensure the fee is added even if the tax is 0.00.
				WC()->cart->add_fee( __( 'Tax', 'ceretax' ), $tax, true, '' );
			} elseif ( is_checkout() ) {
				$tax = 0;
				WC()->cart->add_fee( __( 'Tax', 'ceretax' ), $tax, true, '' );
			}
		}

		/**
		 * Tax item display on order pay page
		 *
		 * @param array  $total_rows Order total rows.
		 * @param object $order      Order Object.
		 * @return $total_rows
		 */
		public function cwafc_show_custom_fee_to_order_pay_page( $total_rows, $order ) {
			if ( is_wc_endpoint_url( 'order-pay' ) ) {
				$cere_tax_post_transactions = get_option( CWAFC_PREFIX . '_cere_tax_post_transactions' );
				if ( 'yes' !== $cere_tax_post_transactions ) {
					return false;
				}

				$order_id           = $order->get_id();
				$shipping_address_1 = $order->get_shipping_address_1();
				if ( ! empty( $shipping_address_1 ) ) {
					$address_data = array(
						'address_1' => $order->get_shipping_address_1(),
						'address_2' => $order->get_shipping_address_2(),
						'city'      => $order->get_shipping_city(),
						'state'     => $order->get_shipping_state(),
						'postcode'  => $order->get_shipping_postcode(),
					);
				} else {
					$address_data = array(
						'address_1' => $order->get_billing_address_1(),
						'address_2' => $order->get_billing_address_2(),
						'city'      => $order->get_billing_city(),
						'state'     => $order->get_billing_state(),
						'postcode'  => $order->get_billing_postcode(),
					);
				}
				$res = 0;
				if ( ! empty( $address_data ) ) {

					if ( isset( $GLOBALS['is_before_pay_action'] ) && $GLOBALS['is_before_pay_action'] ) {
						$tax_status = 'Posted';
					} else {
						$tax_status = 'Quote';
					}
					$res = $this->cwafc_calculate_cere_tax( $address_data, $order, $tax_status );
				}

				$custom_fee_amount                  = $res;
				$total_rows['tax_fee']              = array(
					'label' => __( 'Tax', 'ceretax' ) . ':',
					'value' => wc_price( $custom_fee_amount, array( 'currency' => $order->get_currency() ) ),
				);
				$order_total                        = $order->get_total() + $custom_fee_amount;
				$total_rows['order_total']['value'] = wc_price( $order_total, array( 'currency' => $order->get_currency() ) );

				// Reorder the array.
				$reordered_array = array(
					'cart_subtotal' => $total_rows['cart_subtotal'],
					'tax_fee'       => $total_rows['tax_fee'],
					'order_total'   => $total_rows['order_total'],
				);
				$total_rows      = $reordered_array;

			}
			return $total_rows;
		}

		/**
		 * Add order fee meta for order pay page.
		 *
		 * @param string $order_id Order ID.
		 * @return mix
		 */
		public function cwafc_add_custom_fee_to_order_pay_page( $order_id ) {

			if ( is_wc_endpoint_url( 'order-pay' ) ) {

				// Set global varible.
				$GLOBALS['is_before_pay_action'] = true;

				$cere_tax_post_transactions = get_option( CWAFC_PREFIX . '_cere_tax_post_transactions' );
				if ( 'yes' !== $cere_tax_post_transactions ) {
					return false;
				}

				// Get the order object.
				$order = wc_get_order( $order_id );
				if ( ! $order ) {
					return;
				}
				$shipping_address_1 = $order->get_shipping_address_1();
				if ( ! empty( $shipping_address_1 ) ) {
					$address_data = array(
						'address_1' => $order->get_shipping_address_1(),
						'address_2' => $order->get_shipping_address_2(),
						'city'      => $order->get_shipping_city(),
						'state'     => $order->get_shipping_state(),
						'postcode'  => $order->get_shipping_postcode(),
					);
				} else {
					$address_data = array(
						'address_1' => $order->get_billing_address_1(),
						'address_2' => $order->get_billing_address_2(),
						'city'      => $order->get_billing_city(),
						'state'     => $order->get_billing_state(),
						'postcode'  => $order->get_billing_postcode(),
					);
				}
				$res = 0;
				if ( ! empty( $address_data ) ) {
					$res = $this->cwafc_calculate_cere_tax( $address_data, $order, 'Posted' );
				}

				// Define the fee amount and name.
				$fee_amount = $res;
				$fee_name   = __( 'Tax', 'ceretax' );

				// Check if the fee is already added to avoid duplicates.
				foreach ( $order->get_items( 'fee' ) as $item ) {
					if ( $item->get_name() === $fee_name ) {
						return; // Fee already added, skip further processing.
					}
				}
				// Create a new fee item.
				$fee = new WC_Order_Item_Fee();
				$fee->set_name( $fee_name );
				$fee->set_amount( $fee_amount );
				$fee->set_total( $fee_amount );

				// Add the fee item to the order.
				$order->add_item( $fee );

				// Recalculate order totals.
				$order->calculate_totals();
				$order->save();

			}
		}

		/**
		 * Add address validation button
		 *
		 * @return string
		 */
		public function action__cwafc_add_address_validate_button() {

			// Validate address only if option enable.
			if ( ! $this->cwafc_is_ceretax_enable_validate_addresses() ) {
				return false;
			}

			echo '<div class="validate-address-wrap" style="margin: 0 0 20px 0;">
				<button type="button" id="cwa-address-validate-button" class="button">' . esc_html__( 'Validate Address', 'ceretax' ) . '</button>
			</div>';
		}

		/**
		 * Overide admin order edit template.
		 *
		 * @param string $template      Default template file path.
		 * @param string $template_name Template file slug.
		 * @param string $template_path Template file name.
		 * @return string The new Template file path.
		 */
		public function filter__cwafc_woo_adon_plugin_template( $template, $template_name, $template_path ) {
			global $woocommerce;

			$_template = $template;
			if ( ! $template_path ) {
				$template_path = $woocommerce->template_url;
			}
			$plugin_path = untrailingslashit( CWAFC_DIR_PATH ) . '/woocommerce/';
			// Look within passed path within the plugin - this is priority.
			if ( file_exists( $plugin_path . $template_name ) ) {
				$template = $plugin_path . $template_name;
			}
			// Use default template.
			if ( ! $template ) {
				$template = $_template;
			}
			return $template;
		}

		/**
		 * Add/Update order meta.
		 *
		 * @param string $order_id Order ID.
		 * @return void
		 */
		public function action__cwafc_checkout_field_update_order_meta( $order_id ) {
			$ceretax_data = WC()->session->get( 'ceretax_data' );
			update_post_meta( $order_id, 'ceretax_data', wp_json_encode( $ceretax_data ) );
		}

		/**
		 * Add tax calculation table after shipping
		 *
		 * @param string $order_id Order ID.
		 * @return void
		 */
		public function action__cwafc_add_tax_calculation_table_after_shipping( $order_id ) {

			$ceretax_data = json_decode( get_post_meta( $order_id, 'ceretax_data', true ) );
			$tax_table    = '';
			if ( is_object( $ceretax_data ) ) {
				$ceretax_items_data = $ceretax_data->invoice->lineItems;
				if ( ! empty( $ceretax_items_data ) ) {
					$tax_table .= '<table class="woocommerce_order_items ceratax-tax-table">';
					foreach ( $ceretax_items_data as $ceretax_item_data_key => $ceretax_item_data ) {
						if ( ! empty( $ceretax_item_data->taxes ) ) {
							$tax_table .= '<thead>
								<tr>
									<th>' . __( 'Authority', 'ceretax' ) . '</th>
									<th>' . __( 'Description', 'ceretax' ) . '</th>
									<th>' . __( 'Tax Type', 'ceretax' ) . '</th>
									<th>' . __( 'Tax Type Class', 'ceretax' ) . '</th>
									<th>' . __( 'Tax Type Ref', 'ceretax' ) . '</th>
									<th>' . __( 'Taxable', 'ceretax' ) . '</th>
									<th>' . __( 'Calc Base', 'ceretax' ) . '</th>
									<th>' . __( 'NonTaxable', 'ceretax' ) . '</th>
									<th>' . __( 'Exempt', 'ceretax' ) . '</th>
									<th>' . __( '% Taxable', 'ceretax' ) . '</th>
									<th>' . __( 'Tax rate', 'ceretax' ) . '</th>
									<th>' . __( 'Total Tax', 'ceretax' ) . '</th>
								</tr>
							<thead>';
							$tax_table .= '<tbody>';
							foreach ( $ceretax_item_data->taxes as $ceretax_item ) {
								// phpcs:disable
								$tax_table .= '<tr>
									<td>' . $ceretax_item->taxAuthorityName . '</td>
									<td>' . $ceretax_item->description . '</td>
									<td>' . $ceretax_item->taxTypeDesc . '</td>
									<td>' . $ceretax_item->taxTypeClassDesc . '</td>
									<td>' . $ceretax_item->taxTypeRefDesc . '</td>
									<td>' . $ceretax_item->taxable . '</td>
									<td>' . $ceretax_item->calculationBaseAmt . '</td>
									<td>' . $ceretax_item->nonTaxableAmount . '</td>
									<td>' . $ceretax_item->exemptAmount . '</td>
									<td>' . $ceretax_item->percentTaxable . '</td>
									<td>' . $ceretax_item->rate . '</td>
									<td>' . $ceretax_item->totalTax . '</td>
								</tr>';
								// phpcs:enable
							}
							$tax_table .= '</tbody>';
						}
					}
					$tax_table .= '</table>';
				}
			}
			echo wp_kses_post( $tax_table );
		}

		/**
		 * Add tax calulation on order place.
		 *
		 * @param string $order_id Order ID.
		 * @param object $order    Order Object.
		 * @return mix
		 */
		public function action__cwafc_add_tax_calulation_when_order_placed( $order_id, $order ) {

			$cere_tax_post_transactions = get_option( CWAFC_PREFIX . '_cere_tax_post_transactions' );
			if ( 'yes' !== $cere_tax_post_transactions ) {
				return false;
			}
			// Check if WooCommerce Subscriptions is active and the order contains a subscription.
			if ( ! empty( $order ) && 'shop_subscription' === $order->get_type() ) {
				// Exit early if the order is a subscription.
				return;
			}
			$shipping_address_1 = $order->get_shipping_address_1();
			if ( ! empty( $shipping_address_1 ) ) {
				$address_data = array(
					'address_1' => $order->get_shipping_address_1(),
					'address_2' => $order->get_shipping_address_2(),
					'city'      => $order->get_shipping_city(),
					'state'     => $order->get_shipping_state(),
					'postcode'  => $order->get_shipping_postcode(),
				);
			} else {
				$address_data = array(
					'address_1' => $order->get_billing_address_1(),
					'address_2' => $order->get_billing_address_2(),
					'city'      => $order->get_billing_city(),
					'state'     => $order->get_billing_state(),
					'postcode'  => $order->get_billing_postcode(),
				);
			}
			$res = '';
			if ( ! empty( $address_data ) && ! empty( $order->get_items() ) ) {
				$res = $this->cwafc_calculate_cere_tax( $address_data, $order );
			}

			if ( is_admin() && ! empty( $res ) ) {

				foreach ( $order->get_fees() as $fee ) {
					if ( $fee->get_name() === 'Tax' ) {
						$new_fee_amount = $res;
						$fee->set_amount( $new_fee_amount );
						$fee->set_total( $new_fee_amount );
						$fee->save();
						$fee_exists = true;
						break;
					}
				}

				if ( ! $fee_exists ) {
					$item_fee = new WC_Order_Item_Fee();
					$item_fee->set_name( 'Tax' );
					$new_fee_amount = $res; // Replace this with the actual fee amount.
					$item_fee->set_amount( $new_fee_amount );
					$item_fee->set_total( $new_fee_amount );
					$order->add_item( $item_fee );
				}
				// Recalculate order totals.
				$order->calculate_totals();
				// Save the order.
				$order->save();
			}
		}

		/**
		 * Add tax calulation on renewal order place.
		 *
		 * @param object $renewal_order Order Object.
		 * @return mix
		 */
		public function filter__cwafc_add_tax_calulation_when_renewal_order_placed( $renewal_order ) {
			$cere_tax_post_transactions = get_option( CWAFC_PREFIX . '_cere_tax_post_transactions' );
			if ( 'yes' !== $cere_tax_post_transactions ) {
				return false;
			}

			$order_id           = $renewal_order->get_id();
			$r_order            = wc_get_order( $order_id );
			$shipping_address_1 = $r_order->get_shipping_address_1();
			if ( ! empty( $shipping_address_1 ) ) {
				$address_data = array(
					'address_1' => $r_order->get_shipping_address_1(),
					'address_2' => $r_order->get_shipping_address_2(),
					'city'      => $r_order->get_shipping_city(),
					'state'     => $r_order->get_shipping_state(),
					'postcode'  => $r_order->get_shipping_postcode(),
				);
			} else {
				$address_data = array(
					'address_1' => $r_order->get_billing_address_1(),
					'address_2' => $r_order->get_billing_address_2(),
					'city'      => $r_order->get_billing_city(),
					'state'     => $r_order->get_billing_state(),
					'postcode'  => $r_order->get_billing_postcode(),
				);
			}
			$res = '';
			if ( ! empty( $address_data ) ) {
				$res = $this->cwafc_calculate_cere_tax( $address_data, $r_order );
			}

			if ( ! empty( $res ) ) {

				foreach ( $r_order->get_fees() as $fee ) {
					if ( $fee->get_name() === 'Tax' ) {
						$new_fee_amount = $res;
						$fee->set_amount( $new_fee_amount );
						$fee->set_total( $new_fee_amount );
						$fee->save();
						$fee_exists = true;
						break;
					}
				}

				if ( ! $fee_exists ) {
					$item_fee = new WC_Order_Item_Fee();
					$item_fee->set_name( 'Tax' );
					$new_fee_amount = $res; // Replace this with the actual fee amount.
					$item_fee->set_amount( $new_fee_amount );
					$item_fee->set_total( $new_fee_amount );
					$r_order->add_item( $item_fee );
				}
				// Recalculate order totals.
				$r_order->calculate_totals();
				// Save the order.
				$r_order->save();
			}

			return $renewal_order;
		}

		/**
		 * Add tax calulation on order calculate totals.
		 *
		 * @param string $and_taxes Order ID.
		 * @param object $order     Order Object.
		 * @return mix
		 */
		public function action__cwafc_order_after_calculate_totals( $and_taxes, $order ) {

			$cere_tax_post_transactions = get_option( CWAFC_PREFIX . '_cere_tax_post_transactions' );
			if ( 'yes' !== $cere_tax_post_transactions ) {
				return false;
			}

			// Prevent infinite loop using a transient or custom flag.
			if ( get_transient( 'prevent_order_calculate_totals' ) ) {
				return;
			}

			$shipping_address_1 = $order->get_shipping_address_1();
			if ( ! empty( $shipping_address_1 ) ) {
				$address_data = array(
					'address_1' => $order->get_shipping_address_1(),
					'address_2' => $order->get_shipping_address_2(),
					'city'      => $order->get_shipping_city(),
					'state'     => $order->get_shipping_state(),
					'postcode'  => $order->get_shipping_postcode(),
				);
			} else {
				$address_data = array(
					'address_1' => $order->get_billing_address_1(),
					'address_2' => $order->get_billing_address_2(),
					'city'      => $order->get_billing_city(),
					'state'     => $order->get_billing_state(),
					'postcode'  => $order->get_billing_postcode(),
				);
			}
			$res = '';
			if ( ! empty( $address_data ) && ! empty( $order->get_items() ) ) {
				$res = $this->cwafc_calculate_cere_tax( $address_data, $order );
			}

			if ( ! empty( $res ) ) {
				$fee_exists = false;
				foreach ( $order->get_fees() as $fee ) {
					if ( $fee->get_name() === 'Tax' ) {
						$new_fee_amount = $res;
						$fee->set_amount( $new_fee_amount );
						$fee->set_total( $new_fee_amount );
						$fee->save();
						$fee_exists = true;
						break;
					}
				}

				if ( ! $fee_exists ) {
					$item_fee = new WC_Order_Item_Fee();
					$item_fee->set_name( 'Tax' );
					$new_fee_amount = $res; // Replace this with the actual fee amount.
					$item_fee->set_amount( $new_fee_amount );
					$item_fee->set_total( $new_fee_amount );
					$order->add_item( $item_fee );
				}

				// Set the transient to prevent recursion.
				set_transient( 'prevent_order_calculate_totals', true, 10 );

				// Recalculate order totals.
				$order->calculate_totals();

				// Remove the transient after recalculation.
				delete_transient( 'prevent_order_calculate_totals' );

				// Save the order.
				$order->save();
			}
		}

		/**
		 * On order placed calculate cera tax from API
		 *
		 * @param array  $address_data Address data array.
		 * @param object $order        Order object.
		 * @param string $tax_status   Cetatax tax status.
		 * @return mix
		 */
		public function cwafc_calculate_cere_tax( $address_data, $order, $tax_status = 'Posted' ) {

			// address is required for tax calulation.
			if ( ( isset( $address_data['address_1'] ) && empty( $address_data['address_1'] ) ) || ( isset( $address_data['city'] ) && empty( $address_data['city'] ) ) || ( isset( $address_data['state'] ) && empty( $address_data['state'] ) ) || ( isset( $address_data['postcode'] ) && empty( $address_data['postcode'] ) ) ) {
				return false;
			}
			$order_subtotal = $order->get_subtotal();
			$line_items     = array();

			$cere_tax_api_key                         = get_option( CWAFC_PREFIX . '_cere_tax_api_key' );
			$cere_tax_tax_env                         = get_option( CWAFC_PREFIX . '_cere_tax_environment' );
			$cere_tax_profile                         = get_option( CWAFC_PREFIX . '_cere_tax_profile', 'sales' );
			$cere_tax_business_type                   = get_option( CWAFC_PREFIX . '_cere_tax_business_type', '01' );
			$cere_tax_customer_type                   = get_option( CWAFC_PREFIX . '_cere_tax_customer_type', '02' );
			$cere_tax_ps_code                         = get_option( CWAFC_PREFIX . '_cere_tax_ps_code', '13010100' );
			$cere_tax_tax_included                    = get_option( CWAFC_PREFIX . '_cere_tax_tax_included' );
			$cere_tax_post_transactions               = get_option( CWAFC_PREFIX . '_cere_tax_post_transactions' );
			$cere_tax_validate_address_on_transaction = get_option( CWAFC_PREFIX . '_cere_tax_validate_on_transaction' );
			$cere_tax_tax_status                      = $tax_status;
			$cere_tax_tax_included                    = 'yes' === $cere_tax_tax_included ? true : false;
			$cere_tax_validate_address_on_transaction = 'yes' === $cere_tax_validate_address_on_transaction ? true : false;
			$calculation_type                         = 'S';
			$invoice_number                           = $calculation_type . '-' . wp_rand( 100000, 99999999 );
			$customer_account                         = ( is_user_logged_in() ) ? get_current_user_id() : wp_rand( 1000, 99999 );
			$shipping_charges                         = $order->get_shipping_total();
			$shipping_zones                           = WC_Shipping_Zones::get_zones();

			// Caclulate tax if only CereTax calculation option is not enabled.
			if ( ! $this->cwafc_is_ceretax_enable() ) {
				return false;
			}

			if ( 'production' === $cere_tax_tax_env ) {
				$api_url = 'https://calc.prod.ceretax.net/sale';
			} else {
				$api_url = 'https://calc.cert.ceretax.net/sale';
			}
			foreach ( $order->get_items() as $item_id => $item ) {
				$product_id = $item->get_product_id();
				$product    = wc_get_product( $product_id );

				// Get PS code.
				$product_ps_code = $product->get_meta( CWAFC_PREFIX . '_cere_tax_product_ps_code' );
				$product_terms   = get_the_terms( $product_id, 'product_cat' );
				if ( ! empty( $product_ps_code ) ) {
					$cere_tax_ps_code = $product_ps_code;
				} elseif ( ! empty( $product_terms ) ) {
					$product_term        = reset( $product_terms );
					$product_term_id     = ( isset( $product_term->term_id ) && ! empty( $product_term->term_id ) ) ? $product_term->term_id : 0;
					$product_cat_ps_code = get_term_meta( $product_term_id, CWAFC_PREFIX . '_cere_tax_product_cat_ps_code', true );
					if ( ! empty( $product_cat_ps_code ) ) {
						$cere_tax_ps_code = $product_cat_ps_code;
					} else {
						$cere_tax_ps_code = get_option( CWAFC_PREFIX . '_cere_tax_ps_code', '13010100' );
					}
				} else {
					$cere_tax_ps_code = get_option( CWAFC_PREFIX . '_cere_tax_ps_code', '13010100' );
				}

				$line_items[] = array(
					'lineID'             => (string) $item_id,
					'dateOfTransaction'  => gmdate( 'Y-m-d' ),
					'itemNumber'         => (string) $product_id,
					'itemDescription'    => esc_html( $product->get_title() ),
					'revenue'            => (float) $item->get_total(),
					'psCode'             => $cere_tax_ps_code,
					'revenueIncludesTax' => $cere_tax_tax_included,
					'units'              => array(
						'quantity' => (int) $item->get_quantity(),
						'type'     => '01',
					),
					'situs'              => array(
						'taxSitusRule'  => 'T',
						'shipToAddress' => array(
							'addressLine1'    => $address_data['address_1'],
							'addressLine2'    => $address_data['address_2'],
							'city'            => $address_data['city'],
							'state'           => $address_data['state'],
							'postalCode'      => $address_data['postcode'],
							'validateAddress' => $cere_tax_validate_address_on_transaction,
						),
					),
				);
			}

			$request_body = array(
				'configuration' => array(
					'status'          => $cere_tax_tax_status,
					'contentYear'     => gmdate( 'Y' ),
					'contentMonth'    => gmdate( 'm' ),
					'decimals'        => 2,
					'calculationType' => $calculation_type,
					'profileId'       => $cere_tax_profile,
				),
				'invoice'       => array(
					'invoiceDate'        => gmdate( 'Y-m-d' ),
					'invoiceNumber'      => (string) $order->get_id(),
					'customerAccount'    => (string) $customer_account,
					'businessType'       => $cere_tax_business_type,
					'customerType'       => $cere_tax_customer_type,
					'sellerType'         => '01',
					'invoiceTotalAmount' => (float) $order_subtotal,
					'transactionCharges' => array(
						array(
							'shipping'       => (float) $shipping_charges,
							'freightOnBoard' => 'D',
							'deliveryType'   => 'C',
							'isMandatory'    => true,
						),
					),
					'lineItems'          => $line_items,
				),
			);

			$body = wp_json_encode( $request_body );

			// add api request data in wc logs if log option enabled.
			if ( 'Posted' === $cere_tax_tax_status ) {
				$this->cwafc_wc_add_custom_logs( $request_body, 'ceretax-api-request-with-posted' );
			} else {
				$this->cwafc_wc_add_custom_logs( $request_body, 'ceretax-api-request' );
			}

			$ceretax_stau = get_post_meta( $order->get_id(), 'ceretax_stau', true );

			if ( ( empty( $ceretax_stau ) || 'not_found' === $ceretax_stau ) && ! empty( $body ) && ! empty( $cere_tax_api_key ) ) {

				$response = wp_remote_post(
					$api_url,
					array(
						'method'  => 'POST',
						'body'    => $body,
						'headers' => array(
							'x-api-key'    => $cere_tax_api_key,
							'Content-Type' => 'application/json',
						),
						'timeout' => 60,
					)
				);

				// add api response data in wc logs if log option enabled.
				if ( 'Posted' === $cere_tax_tax_status ) {
					$this->cwafc_wc_add_custom_logs( $response, 'ceretax-api-response-with-posted' );
				} else {
					$this->cwafc_wc_add_custom_logs( $response, 'ceretax-api-response' );
				}

				if ( is_wp_error( $response ) ) {
					return 'Request failed: ' . $response->get_error_message();
				} else {
					$data = wp_remote_retrieve_body( $response );
					$data = json_decode( $data, true );
				}

				if ( is_wp_error( $response ) ) {
					return false;
				}

				if ( isset( $data['invoice']['totalTaxInvoice'] ) ) {
					if ( ! empty( $data['systemTraceAuditNumber'] ) ) {
						update_post_meta( $order->get_id(), 'ceretax_stau', $data['systemTraceAuditNumber'] );
					}
					update_post_meta( $order->get_id(), 'ceretax_data', wp_json_encode( $data ) );
					return $data['invoice']['totalTaxInvoice'];
				}
			} else {
				$response = wp_remote_post(
					$api_url . '?systemTraceAuditNumber=' . $ceretax_stau,
					array(
						'method'  => 'PUT',
						'body'    => $body,
						'headers' => array(
							'x-api-key'    => $cere_tax_api_key,
							'Content-Type' => 'application/json',
						),
						'timeout' => 60,
					)
				);

				// add api response data in wc logs if log option enabled.
				if ( 'Posted' === $cere_tax_tax_status ) {
					$this->cwafc_wc_add_custom_logs( $response, 'ceretax-api-response-with-posted' );
				} else {
					$this->cwafc_wc_add_custom_logs( $response, 'ceretax-api-response' );
				}

				if ( is_wp_error( $response ) ) {
					return 'Request failed: ' . $response->get_error_message();
				} else {
					$data = wp_remote_retrieve_body( $response );
					$data = json_decode( $data, true );
				}

				if ( is_wp_error( $response ) ) {
					return false;
				}

				if ( isset( $data['invoice']['totalTaxInvoice'] ) ) {
					if ( ! empty( $data['systemTraceAuditNumber'] ) ) {
						update_post_meta( $order->get_id(), 'ceretax_stau', $data['systemTraceAuditNumber'] );
					}
					update_post_meta( $order->get_id(), 'ceretax_data', wp_json_encode( $data ) );
					return $data['invoice']['totalTaxInvoice'];
				}
			}
			return false;
		}

		/**
		 * Hide tax fields from checkout
		 *
		 * @param array $fields setting fields.
		 * @return array
		 */
		public function filter__cwafc_remove_tax_checkout_fields( $fields ) {
			unset( $fields['billing']['billing_tax_id'] );
			unset( $fields['billing']['billing_eu_vat_number'] );
			unset( $fields['shipping']['shipping_tax_id'] );
			return $fields;
		}

		/**
		 * Get static data from name.
		 *
		 * @param string $name pass the name for get static data.
		 * @return int
		 */
		public function cwafc_get_static_data( $name ) {
			return $this->static_data[ $name ];
		}

		/**
		 * Get the tax calulation form API.
		 *
		 * @param string $shipping_address_1 - get shipping address.
		 * @param string $shipping_address_2 - get shipping address 2.
		 * @param string $country - get country.
		 * @param string $city - get city.
		 * @param string $state - get state.
		 * @param string $postcode - get postcode.
		 * @return int
		 */
		public function cwafc_get_tax_rate_from_api( $shipping_address_1, $shipping_address_2, $country, $city, $state, $postcode ) {

			// Address required for tax calulation.
			if ( empty( $shipping_address_1 ) || empty( $country ) || empty( $city ) || empty( $state ) || empty( $postcode ) ) {
				return false;
			}

			$cart_total           = WC()->cart->cart_contents_total;
			$cart_items           = array();
			$invoice_total_amount = 0;

			$cere_tax_api_key                         = get_option( CWAFC_PREFIX . '_cere_tax_api_key' );
			$cere_tax_tax_env                         = get_option( CWAFC_PREFIX . '_cere_tax_environment' );
			$cere_tax_profile                         = get_option( CWAFC_PREFIX . '_cere_tax_profile', 'sales' );
			$cere_tax_business_type                   = get_option( CWAFC_PREFIX . '_cere_tax_business_type', '01' );
			$cere_tax_customer_type                   = get_option( CWAFC_PREFIX . '_cere_tax_customer_type', '02' );
			$cere_tax_ps_code                         = get_option( CWAFC_PREFIX . '_cere_tax_ps_code', '13010100' );
			$cere_tax_tax_included                    = get_option( CWAFC_PREFIX . '_cere_tax_tax_included' );
			$cere_tax_post_transactions               = get_option( CWAFC_PREFIX . '_cere_tax_post_transactions' );
			$cere_tax_validate_address_on_transaction = get_option( CWAFC_PREFIX . '_cere_tax_validate_on_transaction' );
			$cere_tax_tax_status                      = 'Quote';
			$cere_tax_tax_included                    = 'yes' === $cere_tax_tax_included ? true : false;
			$cere_tax_validate_address_on_transaction = 'yes' === $cere_tax_validate_address_on_transaction ? true : false;
			$calculation_type                         = 'S';
			$invoice_number                           = $calculation_type . '-' . wp_rand( 100000, 99999999 );
			$customer_account                         = ( is_user_logged_in() ) ? get_current_user_id() : wp_rand( 1000, 99999 );
			$shipping_charges                         = WC()->cart->get_shipping_total();
			$shipping_zones                           = WC_Shipping_Zones::get_zones();

			// Caclulate tax if only CereTax calculation option is not enabled.
			if ( ! $this->cwafc_is_ceretax_enable() ) {
				return false;
			}

			$checkout_data = array(
				'shipping_address_1' => $shipping_address_1,
				'shipping_address_2' => $shipping_address_2,
				'shipping_city'      => $city,
				'shipping_country'   => $country,
				'shipping_state'     => $state,
				'shipping_postcode'  => $postcode,
			);
			WC()->session->set( 'checkout_data', $checkout_data );

			if ( 'production' === $cere_tax_tax_env ) {
				$api_url = 'https://calc.prod.ceretax.net/sale';
			} else {
				$api_url = 'https://calc.cert.ceretax.net/sale';
			}

			if ( ! WC()->cart->is_empty() ) {
				foreach ( WC()->cart->get_cart() as $cart_item_id => $cart_item ) {
					$product_id = $cart_item['product_id'];
					$product    = wc_get_product( $product_id );

					// Get PS code.
					$product_ps_code = $product->get_meta( CWAFC_PREFIX . '_cere_tax_product_ps_code' );
					$product_terms   = get_the_terms( $product_id, 'product_cat' );
					if ( ! empty( $product_ps_code ) ) {
						$cere_tax_ps_code = $product_ps_code;
					} elseif ( ! empty( $product_terms ) ) {
						$product_term        = reset( $product_terms );
						$product_term_id     = ( isset( $product_term->term_id ) && ! empty( $product_term->term_id ) ) ? $product_term->term_id : 0;
						$product_cat_ps_code = get_term_meta( $product_term_id, CWAFC_PREFIX . '_cere_tax_product_cat_ps_code', true );
						if ( ! empty( $product_cat_ps_code ) ) {
							$cere_tax_ps_code = $product_cat_ps_code;
						} else {
							$cere_tax_ps_code = get_option( CWAFC_PREFIX . '_cere_tax_ps_code', '13010100' );
						}
					} else {
						$cere_tax_ps_code = get_option( CWAFC_PREFIX . '_cere_tax_ps_code', '13010100' );
					}

					// If set deposit.
					if ( isset( $cart_item['is_deposit'] ) && true === $cart_item['is_deposit'] && isset( $cart_item['deposit_amount'] ) && ! empty( $cart_item['deposit_amount'] ) ) {
						$line_total = (float) $cart_item['deposit_amount'];
						$line_total = $line_total * (int) $cart_item['quantity'];
					} else {
						$line_total = (float) $cart_item['line_total'];
					}
					$invoice_total_amount += $line_total;

					$cart_items[] = array(
						'lineID'             => $cart_item_id,
						'dateOfTransaction'  => gmdate( 'Y-m-d' ),
						'itemNumber'         => (string) $product_id,
						'itemDescription'    => esc_html( $product->get_title() ),
						'revenue'            => $line_total,
						'psCode'             => $cere_tax_ps_code,
						'revenueIncludesTax' => $cere_tax_tax_included,
						'units'              => array(
							'quantity' => (int) $cart_item['quantity'],
							'type'     => '01',
						),
						'situs'              => array(
							'taxSitusRule'  => 'T',
							'shipToAddress' => array(
								'addressLine1'    => $shipping_address_1,
								'addressLine2'    => $shipping_address_2,
								'city'            => $city,
								'state'           => $state,
								'postalCode'      => $postcode,
								'validateAddress' => $cere_tax_validate_address_on_transaction,
							),
						),
					);
				}
			}
			$request_body = array(
				'configuration' => array(
					'status'          => $cere_tax_tax_status,
					'contentYear'     => gmdate( 'Y' ),
					'contentMonth'    => gmdate( 'm' ),
					'decimals'        => 2,
					'calculationType' => $calculation_type,
					'profileId'       => $cere_tax_profile,
				),
				'invoice'       => array(
					'invoiceDate'        => gmdate( 'Y-m-d' ),
					'invoiceNumber'      => $invoice_number,
					'customerAccount'    => (string) $customer_account,
					'businessType'       => $cere_tax_business_type,
					'customerType'       => $cere_tax_customer_type,
					'sellerType'         => '01',
					'invoiceTotalAmount' => (float) $invoice_total_amount,
					'transactionCharges' => array(
						array(
							'shipping'       => (float) $shipping_charges,
							'freightOnBoard' => 'D',
							'deliveryType'   => 'C',
							'isMandatory'    => true,
						),
					),
					'lineItems'          => $cart_items,
				),
			);

			$body = wp_json_encode( $request_body );

			// add api request data in wc logs if log option enabled.
			$this->cwafc_wc_add_custom_logs( $request_body, 'ceretax-api-request' );

			$ceretax_stau = WC()->session->get( 'ceretax_stau' );

			if ( ( empty( $ceretax_stau ) || 'not_found' === $ceretax_stau ) && ! empty( $body ) && ! empty( $cere_tax_api_key ) ) {
				$response = wp_remote_post(
					$api_url,
					array(
						'method'  => 'POST',
						'body'    => $body,
						'headers' => array(
							'x-api-key'    => $cere_tax_api_key,
							'Content-Type' => 'application/json',
						),
						'timeout' => 60,
					)
				);

				// add api response data in wc logs if log option enabled.
				$this->cwafc_wc_add_custom_logs( $response, 'ceretax-api-response' );

				if ( is_wp_error( $response ) ) {
					return 'Request failed: ' . $response->get_error_message();
				} else {
					$data = wp_remote_retrieve_body( $response );
					$data = json_decode( $data, true );
					if ( ! empty( $data['systemTraceAuditNumber'] ) ) {
						WC()->session->set( 'ceretax_stau', $data['systemTraceAuditNumber'] );
					} else {
						WC()->session->set( 'ceretax_stau', 'not_found' );
					}
				}

				if ( is_wp_error( $response ) ) {
					return false;
				}

				if ( 200 === wp_remote_retrieve_response_code( $response ) ) {
					WC()->session->set( 'ceretax_data', $data );
					return $data['invoice']['totalTaxInvoice'];
				}
			} else {
				$response = wp_remote_post(
					$api_url . '?systemTraceAuditNumber=' . $ceretax_stau,
					array(
						'method'  => 'PUT',
						'body'    => $body,
						'headers' => array(
							'x-api-key'    => $cere_tax_api_key,
							'Content-Type' => 'application/json',
						),
						'timeout' => 60,
					)
				);

				// add api response data in wc logs if log option enabled.
				$this->cwafc_wc_add_custom_logs( $response, 'ceretax-api-response' );

				if ( is_wp_error( $response ) ) {
					return 'Request failed: ' . $response->get_error_message();
				} else {
					$data = wp_remote_retrieve_body( $response );
					$data = json_decode( $data, true );
					if ( ! empty( $data['systemTraceAuditNumber'] ) ) {
						WC()->session->set( 'ceretax_stau', $data['systemTraceAuditNumber'] );
					} else {
						WC()->session->set( 'ceretax_stau', 'not_found' );
					}
				}

				if ( is_wp_error( $response ) ) {
					return false;
				}

				if ( isset( $data['invoice']['totalTaxInvoice'] ) ) {
					WC()->session->set( 'ceretax_data', $data );
					return $data['invoice']['totalTaxInvoice'];
				}
			}
			return false;
		}

		/**
		 * Add WooCommerce logs for custom API call.
		 *
		 * @param object $log_data   pass the log data.
		 * @param string $log_source pass the source of the log.
		 * @return void
		 */
		public function cwafc_wc_add_custom_logs( $log_data, $log_source ) {
			$enable_logging = get_option( CWAFC_PREFIX . '_cere_tax_enable_logging' );
			if ( 'yes' === $enable_logging ) {
				$logger = wc_get_logger();
				$logger->info( wp_json_encode( $log_data, JSON_PRETTY_PRINT ), array( 'source' => $log_source ) );
			}
		}

		/**
		 * Check if tax option enable or not
		 *
		 * @return boolean
		 */
		public function cwafc_is_ceretax_enable() {
			// Caclulate tax if only CereTax calculation option is not enabled.
			$cere_tax_enable = get_option( CWAFC_PREFIX . '_cere_tax_enable' );
			return 'yes' === $cere_tax_enable ? true : false;
		}

		/**
		 * Check if validate addresses option enable or not
		 *
		 * @return boolean
		 */
		public function cwafc_is_ceretax_enable_validate_addresses() {
			// Caclulate tax if only CereTax calculation option is not enabled.
			$cere_tax_enable_validate_addresses = get_option( CWAFC_PREFIX . '_cere_tax_validate_customer_addresses' );
			return 'yes' === $cere_tax_enable_validate_addresses ? true : false;
		}

		/**
		 * Store checkout data in session
		 *
		 * @param object $posted_data Checkout posted data.
		 * @return void
		 */
		public function action__cwafc_save_checkout_values( $posted_data ) {
			parse_str( $posted_data, $output );
			WC()->session->set( 'checkout_data', $output );
		}

		/**
		 * Set default checked
		 *
		 * @param boolean $ship_to_different_address Ship to different address checkbox condition.
		 * @return boolean
		 */
		public function action___cwafc_default_checkout_checked( $ship_to_different_address ) {
			$ship_to_different_address = true;
			return $ship_to_different_address; // Default to checked.
		}
	}
}

/**
 * CWAFC create function to return instance.
 *
 * @return object
 */
if ( ! function_exists( 'cwafc' ) ) {
	function cwafc() {
		return CWAFC::get_instance();
	}
}
cwafc();
