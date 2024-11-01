<?php
/**
 * This file manages to send order details to Zendesk.
 *
 * @link       https://vamaship.com/
 * @since      1.0.0
 *
 * @package    vamaship-shipping
 * @subpackage vamaship-shipping/includes
 */

/**
 * Exit if accessed directly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Check Vamaship_Shipping Class exists
 */
if ( ! class_exists( 'Vamaship_Shipping' ) ) {
	/**
	 * This is vamaship class
	 */
	class Vamaship_Shipping extends WC_Shipping_Method {
		/**
		 * This is construct methode of vamaship
		 */
		public function __construct() {
			$this->vmp_vamaship_init();
			$this->vmp_vamaship_save_option();
			$this->vmp_vamaship_init_form_fields();
			$this->init_settings();
			add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
		}
		/**
		 * Initialize setting for Vamaship
		 *
		 * @name ced_spp_init
		 * @author Vamaship <www.vamaship.com>
		 */
		public function vmp_vamaship_init() {
			$this->id                 = 'vamaship';
			$this->method_title       = __( 'Vamaship Shipping', 'vamaship' );
			$this->method_description = __( 'This Shipping Method Use for Vamaship Shipping', 'vamaship' );
			$this->enabled            = $this->get_option( 'enabled' );
			$this->title              = $this->get_option( 'title' );
		}
		public function vmp_vamaship_init_form_fields() {
			if(!is_admin() || $_GET['section'] != 'vamaship'){
				return ;
			}

			wp_nonce_field( 'vmp_vamaship_setting_nonce', 'vmp_vamaship_nonce' );

			$this->form_fields = array(
				'enabled'                        => array(
					'title'       => __( 'Enable', 'vamaship' ),
					'type'        => 'checkbox',
					'description' => __( 'Enable this shipping.', 'vamaship' ),
					'default'     => 'yes',
				),
				'vamaship_api_tokan'             => array(
					'title'       => __( 'Vamaship Api Token', 'vamaship' ),
					'type'        => 'text',
					'description' => __( 'API key get from vamaship', 'vamaship' ),
				),
				'vamaship_api_url'               => array(
					'title'       => __( 'Vamaship Domestic Air URL ', 'vamaship' ),
					'type'        => 'text',
					'description' => __( 'Please enter the api request URL', 'vamaship' ),
				),
				'vamaship_surface_api_url'       => array(
					'title'       => __( 'Vamaship Surface Request URL', 'vamaship' ),
					'type'        => 'text',
					'description' => __( 'Please enter the api request URL', 'vamaship' ),
				),
				'vamaship_international_api_url' => array(
					'title'       => __( 'Vamaship Air Internatoinal Request URL', 'vamaship' ),
					'type'        => 'text',
					'description' => __( 'Please enter the api request URL', 'vamaship' ),
				),
				'vamaship_vender_gst_no'         => array(
					'title'       => __( 'GST NO ', 'vamaship' ),
					'type'        => 'text',
					'description' => __( 'Please enter your GST number', 'vamaship' ),
				),
				'seller_phone_no'                => array(
					'title'       => __( 'Seller Phone No', 'vamaship' ),
					'type'        => 'text',
					'description' => __( 'Please enter Seller Phone No , it is required', 'vamaship' ),
				),
				'vamaship_default_shipping'      => array(
					'title'             => __( 'Select Default Shipping', 'vamaship' ),
					'type'              => 'select',
					'desc'              => __( 'Select default shipping option', 'vamaship' ),
					'desc_tip'          => true,
					'options'           => $this->vmp_vamaship_shipping_option(),
					'custom_attributes' => array(
						'data-placeholder' => __( 'select shipping method', 'vamaship' ),
					),
				),
			);
		}

		/**
		 * Function to show shipping dropdown list
		 */
		public function vmp_vamaship_shipping_option() {

			$existing_stages = array();

			$existing_stages['domestic_air']      = 'Generate VS Domestic Air Shipments';
			$existing_stages['surface_b2c']       = 'Generate VS Surface B2C Shipments';
			$existing_stages['surface_b2b']       = 'Generate VS Surface B2B Shipments';
			$existing_stages['air_internatoinal'] = 'Generate VS Air Internatoinal Shipments';

			return $existing_stages;
		}

		/**
		 * Function for save the data in by post in vamaship shipping api
		 * This is also for save the pincode data at inital state
		 */
		public function vmp_vamaship_save_option() {

			if(!is_admin()){
				return ;
			}

			$vamaship_api_tokan = get_option( 'woocommerce_vamaship_settings', true );
			if ( ! empty( $_POST['woocommerce_vamaship_vamaship_api_tokan'] ) && ! empty( $_POST['woocommerce_vamaship_vamaship_api_url'] ) ) {
				// Nonce verification
				check_admin_referer( 'vmp_vamaship_setting_nonce', 'vmp_vamaship_nonce' );
				$owner_api_tokan = map_deep( wp_unslash( $_POST['woocommerce_vamaship_vamaship_api_tokan'] ), 'sanitize_text_field' );
				if ( $owner_api_tokan === $vamaship_api_tokan['vamaship_api_tokan'] ) {
					require_once VAMASHIP_DIR_PATH . '/vamaship/vamaship.php';
					$owner_pincode    = get_option( 'woocommerce_store_postcode' );
					$type_of_order    = 'cod';
					$vamaship_api_url = map_deep( wp_unslash( $_POST['woocommerce_vamaship_vamaship_api_url'] ), 'sanitize_text_field' );
					if ( class_exists( 'vamaship' ) ) {
						$vamaship_shipping_object = new vamaship();
						$all_pincode              = $vamaship_shipping_object->vmp_vamaship_shipping_get_all_postcode( $owner_pincode, $type_of_order, $owner_api_tokan, $vamaship_api_url );

						if ( isset( $all_pincode['status'] ) && $all_pincode['status'] == 200 && isset( $all_pincode['success'] ) && $all_pincode['success'] == 1 ) {
							  $pincode_data = wp_json_encode( $all_pincode['covered'] );
							  update_option( 'vamaship_shipping_covered_pincode', $pincode_data, $autoload = null );
							  add_action( 'admin_notices', array( $this, 'vmp_vamaship_admin_notices_success' ) );
						} else {
							add_action( 'admin_notices', array( $this, 'vmp_vamaship_admin_notices_error' ) );
						}
					}
				}
			}

		}
		// This is function for add notices in admin area after any error ..
		public function vmp_vamaship_admin_notices_error() {
			esc_html(
				'<div class="notice notice-warning is-dismissible">
      <p>Sorry , there is any error in API Key or API URL.</p>
      </div>'
			);
		}
		// This is function for add notices in admin area after get success..
		public function vmp_vamaship_admin_notices_success() {
			esc_html(
				'<div class="updated notice">
      <p>All pincode and API key updated.</p>
      </div>'
			);
		}

	}
	new vamaship_shipping();
}
