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
 * Check Vamaship_Shiping_Method class exist
 */

if ( ! class_exists( 'Vamaship_Shiping_Method' ) ) {
	class Vamaship_Shiping_Method {
		/**
		 * this is construct methode of vamaship
		 */
		public function __construct() {
			/*
			This action for add shipping methode .
			* This action also use for enter the name of vamaship in shipping method
			*
			*/
			add_filter( 'woocommerce_shipping_methods', array( $this, 'vmp_vamaship_shipping_method' ) );
			add_action( 'woocommerce_shipping_init', array( $this, 'vmp_vamaship_shipping_method_init' ) );
			// This action use for add field in Product section
			// This is Use for add hsn code in woocommerce product section
			add_action( 'woocommerce_product_options_general_product_data', array( $this, 'vmp_vamaship_shipping_add_input_fields_in_product' ), 10, 1 );
			// Save Fields
			add_action( 'woocommerce_process_product_meta', array( $this, 'vmp_vamaship_shipping_save_input_fields_data' ) );
			// add shipment gereration  in action button in order section
			add_filter( 'woocommerce_admin_order_actions_end', array( $this, 'vmp_vamaship_shipping_generate_shipping_button' ), 10, 2 );
			// generate the shipment from order section ...
			add_action( 'wp', array( $this, 'vmp_vamaship_shipping_generate_shipment_from_order' ) );
			// Add Button for Generate the Label and manifests from Order Page.
			add_action( 'add_meta_boxes_shop_order', array( $this, 'vmp_vamaship_shipping_add_vamaship_generate_label_button' ) );
			// Generate the Vamaship Invoice and Label in ....
			add_action( 'admin_init', array( $this, 'vmp_vamaship_shipping_generate_invoic_label' ) );
			// Enqueue The Admin js ....
			add_action( 'admin_enqueue_scripts', array( $this, 'vmp_vamaship_shipping_enqueue_scripts' ) );
			// Generate the Bulk Shipment from The Order...
			add_action( 'load-edit.php', array( $this, 'vmp_vamaship_shipping_bulk_shipments_generate' ) );
			add_filter( 'woocommerce_my_account_my_orders_actions', array( $this, 'vmp_vamaship_shipping_track_order_action' ), 10, 2 );
			// Get the Tracking details in user panel...
			add_action( 'wp_footer', array( $this, 'vmp_vamaship_shipping_footer_show' ) );

			// To add meta file to fetch shipping on order edit page
			add_action( 'add_meta_boxes', array( $this, 'vmp_vamaship_shipping_meta_box' ) );
			add_action( 'init', array( $this, 'load_plugin_textdomain' ) );
		}

		public function load_plugin_textdomain() {

			$test = load_plugin_textdomain(
			'vamaship',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
			);

		}

		public function vmp_vamaship_shipping_meta_box() {
			global $post;
			if ( $post->post_type != 'shop_order' ) {
				return;
			}
			add_meta_box( 'vmp_vamaship_meta', __( 'Package Dimensions', 'vamaship' ), array( $this, 'vmp_vamaship_shipping_meta_hook' ), 'shop_order', 'normal', 'default' );
		}

		public function vmp_vamaship_shipping_meta_hook() {

			global $post;

			$order = new WC_Order( $post->ID );

			$all_pincode_of_owner = get_option( 'vamaship_shipping_covered_pincode' );
			$all_pincode_of_owner = json_decode( $all_pincode_of_owner );
			$unit                 = get_option( 'woocommerce_dimension_unit' );

			?>
		  <table>
			<tr>
			  <th><?php esc_html_e( 'Max Length', 'vamaship' ); ?></th>
			  <th><?php esc_html_e( 'Max Width', 'vamaship' ); ?></th>
			  <th><?php esc_html_e( 'Max Height', 'vamaship' ); ?></th>
			  <th><?php esc_html_e( 'Total Weight', 'vamaship' ); ?></th>
			  <th></th>
			</tr>

			<?php

			if ( in_array( $order->get_billing_postcode(), $all_pincode_of_owner ) ) {
				$order_items = $order->get_items();

				$updated_dimensions = get_post_meta( $post->ID, 'vamaship_shipping_order_dimensions', true );

				if ( isset( $updated_dimensions ) && ! empty( $updated_dimensions ) ) {

					$max_length = $updated_dimensions['max_length'];
					$max_height = $updated_dimensions['max_height'];
					$max_width  = $updated_dimensions['max_width'];
					$max_weight = $updated_dimensions['max_weight'];
				} else {

					$max_length = 0;
					$max_height = 0;
					$max_width  = 0;
					$max_weight = 0;
				}

				if ( isset( $_GET['format'] ) ) {
					$get_format = sanitize_key( $_GET ['format'] );
					if ( 'get_reshipment_details' === $get_format ) {
						$max_length = isset( $_GET['max_length'] ) ? map_deep( wp_unslash( $_GET['max_length'] ), 'sanitize_text_field' ) : '';
						$max_height = isset( $_GET['max_height'] ) ? map_deep( wp_unslash( $_GET['max_height'] ), 'sanitize_text_field' ) : '';
						$max_width  = isset( $_GET['max_width'] ) ? map_deep( wp_unslash( $_GET['max_width'] ), 'sanitize_text_field' ) : '';
						$max_weight = isset( $_GET['max_weight'] ) ? map_deep( wp_unslash( $_GET['max_weight'] ), 'sanitize_text_field' ) : '';

						$recal_shiprate = array(
							'max_width'  => $max_width,
							'max_weight' => $max_weight,
							'max_length' => $max_length,
							'max_height' => $max_height,
						);

						update_post_meta( $post->ID, 'vamaship_shipping_order_dimensions', $recal_shiprate );
					}
				} else {

					if ( $max_length === 0 ) {

						foreach ( $order_items as $item_id => $item_data ) {

							$product       = $item_data->get_product();
							$item_quantity = $item_data->get_quantity();
							$height        = $product->get_height();
							$width         = $product->get_width();
							$length        = $product->get_length();
							$weight        = $product->get_weight();

							$weight_unit = get_option( 'woocommerce_weight_unit' );

							if ( $weight_unit == 'g' ) {
								$weight = ( $weight / 1000 ) * $item_quantity;
							} else {
								$weight = $weight * $item_quantity;
							}

							$max_length  = ( $max_length < $length ) ? $length : $max_length;
							$max_height  = ( $max_height < $height ) ? $height : $max_height;
							$max_width   = ( $max_width < $width ) ? $width : $max_width;
							$max_weight += $weight;

						}
					}
				}
				?>
			<tr>
			  <td><input class ="vmp_recal_ship_values vmp_max_lenght" type="number" name="vmp_max_lenght" value="<?php echo esc_attr( $max_length ); ?>"></td>
			  <td><input class ="vmp_recal_ship_values vmp_max_width" type="number" name="vmp_max_width" value="<?php echo esc_attr( $max_width ); ?>"></td>
			  <td><input class ="vmp_recal_ship_values vmp_max_height" type="number" name="vmp_max_height" value="<?php echo esc_attr( $max_height ); ?>"></td>
			  <td><input class ="vmp_recal_ship_values vmp_max_weight" type="number" name="vmp_max_weight" value="<?php echo esc_attr( $max_weight ); ?>"></td>
			  <td><a class="vmp_update_shipping_cost" href="#"><?php esc_html_e( 'Update', 'vamaship' ); ?></a></td>
			</tr>
				<?php
			}
			?>
		  </table>
			<?php
		}

		public function vmp_vamaship_shipping_footer_show() {
			if ( isset( $_GET['order_id'] ) && $_GET['order_id'] != '' ) {
				?>
		  <div class="mwb_demo" id="mwb_id"> 
				<?php
				$order_id = isset( $_GET['order_id'] ) ? sanitize_key( $_GET['order_id'] ) : '';
				$this->vmp_vamaship_shipping_order_shipping_status( $order_id );
				?>
		  </div>
				<?php
			}
		}
		public function vmp_vamaship_shipping_track_order_action( $action, $order ) {
			$action['vamaship_track'] = array(
				'url'  => get_permalink( get_option( 'woocommerce_myaccount_page_id' ) ) . '/orders/?order_id=' . $order->get_id() . '#mwb_id',
				'name' => __( 'Track Your Order', 'vamaship' ),
			);
			return $action;
		}
		/**This function for add the scripts in admin section ....
		 * This is enqueue the admin sceript for bulk generation functionality...
		 */
		public function vmp_vamaship_shipping_enqueue_scripts() {
			global $post_type;
			$action_settings = false;
			if ( $post_type === 'shop_order' ) {
				$action_settings = true;
			}
			wp_enqueue_script( 'vamaship_shipping_admin-js', VAMASHIP_DIR_URL . 'assets/js/vamaship_shipping_admin-js.js', array( 'jquery' ), '1.1.0', true );
			wp_localize_script(
				'vamaship_shipping_admin-js',
				'vamaship_shipping_bulk_shipment',
				array(
					'ajaxurl'      => admin_url( 'admin-ajax.php' ),
					'bulk_action'  => $action_settings,
					'action_text1' => __( 'Generate Vamaship Shipment', 'vamaship' ),
					'action_text2' => __( 'Generate Vamaship Shipment - Express', 'vamaship' ),
					'action_text3' => __( 'Generate Vamaship Shipment - Standard Surface B2C', 'vamaship' ),
					'action_text4' => __( 'Generate Vamaship Shipment - Standard Surface B2B', 'vamaship' ),
					'action_text5' => __( 'Generate Vamaship Shipment - International', 'vamaship' ),
				)
			);
		}
		/**
		 * Adds a new Shipping Method
		 *
		 * @name ced_spp_shipping_method
		 * @author   CedCommerce <plugins@cedcommerce.com>
		 * @param array $methods
		 * @return array
		 */
		public function vmp_vamaship_shipping_method( $methods ) {
			$methods['vamaship_id'] = __( 'Vamaship_Shipping', 'vamaship' );
			return $methods;
		}
		// This method create the shipping method
		public function vmp_vamaship_shipping_method_init() {
			include VAMASHIP_DIR_PATH . '/includes/vamaship-shipping-option.php';
		}
		// this function create the product field in product meta tab
		public function vmp_vamaship_shipping_add_input_fields_in_product() {
			global $woocommerce, $post;
			wp_nonce_field( 'vmp_vamaship_hsn_setting_nonce', 'vmp_vamaship_hsn_nonce' );

			woocommerce_wp_textarea_input(
				array(
					'id'          => 'hsn_code_product',
					'placeholder' => 'Please enter the hsn code',
					'label'       => 'HSN CODE',
					'description' => 'Enter the HSN code of product',
					'desc_tip'    => 'true',
				)
			);
		}
		// This function save the data of product field in post meta
		public function vmp_vamaship_shipping_save_input_fields_data( $postid ) {
			// Nonce verification
			check_admin_referer( 'vmp_vamaship_hsn_setting_nonce', 'vmp_vamaship_hsn_nonce' );
			if ( isset( $_POST['hsn_code_product'] ) ) {
				update_post_meta( $postid, 'hsn_code_product', map_deep( wp_unslash( $_POST['hsn_code_product'] ), 'sanitize_text_field' ) );
			}
		}
		// Function for add the button to generate the shipment from order..
		public function vmp_vamaship_shipping_generate_shipping_button( $actions ) {
			global  $post;
			$listing_actions['shipment_generation'] = array(
				'url'    => $url = esc_url(
					add_query_arg(
						array(
							'format'  => 'shipment_generation',
							'orderID' => $post->ID,
						)
					)
				),
				'name'   => __( 'Generate Shipment', 'woocommerce' ),
				'action' => 'generate-shipment',
			);
			apply_filters( 'woocommerce_admin_order_actions', $listing_actions, $actions );
			foreach ( $listing_actions as $key => $data ) {
				?>
		  <a href="<?php echo esc_url( $data['url'] ); ?>" class="button tips" data-tip="<?php echo esc_attr( $data['name'] ); ?>">
			<img src="<?php echo esc_url( VAMASHIP_DIR_URL . '/assets/images/shipment.png' ); ?>" alt="shipment image " style="width: 100%;" >
		  </a>
				<?php
			}
		}
		// Function for add the Button in Order section for Generating the Label and manifests
		public function vmp_vamaship_shipping_add_vamaship_generate_label_button( $order ) {
			$manifests             = get_post_meta( $order->ID, 'vamaship_order_manifests', true );
			$reference_no_of_order = get_post_meta( $order->ID, 'order_shipment_reference_no', true );
			if ( ( isset( $manifests ) && $manifests !== '' ) || ( isset( $reference_no_of_order ) && $reference_no_of_order !== '' ) ) {
				add_meta_box( 'vamaship_shipping_invoice_generate', 'Generate Vamaship Shipping Labels', array( $this, 'vmp_vamaship_shipping_generate_label_section' ), 'shop_order', 'side', 'default' );
			}
			if ( isset( $manifests ) && $manifests !== '' ) {
				add_meta_box( 'vamaship_shipping_tracking_status', ' Order Shipping Status', array( $this, 'vmp_vamaship_shipping_order_shipping_status' ), 'shop_order', 'normal', 'default' );
			}
		}
		// This function is use for Tracking status of your order ..
		public function vmp_vamaship_shipping_order_shipping_status( $shoporder ) {
			global $woocommerce;
			$order_id           = $shoporder;
			$order              = new WC_Order( $order_id );
			$vamaship_api_tokan = get_option( 'woocommerce_vamaship_settings', true );
			$tracking_details   = get_post_meta( $order_id, 'order_tracking_details', true );
			require_once VAMASHIP_DIR_PATH . '/vamaship/vamaship.php';
			if ( class_exists( 'vamaship' ) ) {
				$track_order_class = new vamaship();
				$order_status      = $track_order_class->vmp_vamaship_shipping_track_the_order( $vamaship_api_tokan['vamaship_api_url'], $tracking_details['vamaship_order_id'], $vamaship_api_tokan['vamaship_api_tokan'] );
				if ( isset( $order_status['tracking_details'] ) && $order_status['tracking_details'] ) {
					?>
			<table>
			  <caption><h2><?php esc_html_e( 'There are all shipping details of Order', 'vamaship' ); ?></h2> </caption>
			  <thead>
				<tr>
				  <th> <?php esc_html_e( 'Date', 'vamaship' ); ?></th>
				  <th> <?php esc_html_e( 'Time', 'vamaship' ); ?></th>
				  <th> <?php esc_html_e( 'Order Status', 'vamaship' ); ?></th>
				  <th> <?php esc_html_e( 'Location', 'vamaship' ); ?></th>
				  <th>  <?php esc_html_e( 'Comments', 'vamaship' ); ?></th>
				</tr>
			  </thead>
			  <tbody>
					<?php
					$status = '';
					foreach ( $order_status['tracking_details'][ $tracking_details['vamaship_order_id'] ]['trackingEvents'] as $key => $value ) {
						$date   = date( 'd/m/Y,h:i:s A', strtotime( $value['datetime'] ) );
						$date   = explode( ',', $date );
						$status = $value['status'];
						?>
				  <tr>
					<td><?php echo esc_html( $date['0'] ); ?></td>
					<td><?php echo esc_html( $date['1'] ); ?></td>
					<td><?php echo esc_html( $value['status'] ); ?></td>
					<td><?php echo esc_html( $value['location'] ); ?></td>
					<td><?php echo esc_html( $value['comments'] ); ?></td>
				  </tr>
						<?php
					}
					if ( $status === 'Delivered' ) {
						$order->update_status( 'Complete' );
					} elseif ( $status === 'RTO' ) {
						$order->update_status( 'Returned' );
					} elseif ( $status === 'Manifested' ) {
						$order->update_status( 'Processing' );
					} elseif ( $status === 'In-Transit' ) {
						$order->update_status( 'Processing' );
					} elseif ( $status === 'Dispatched' ) {
						$order->update_status( 'Processing' );
					}
					?>
							
			  </tbody>
			</table>
					<?php
				} else {
					?>
			<div><h3>  <?php esc_html_e( 'There is no Tracking Details Available for this Order', 'vamaship' ); ?> </h3></div>
					<?php
				}
			}
		} // function close of tracking ...
		// This is for Generating the Invoice and Packing slip..
		public function vmp_vamaship_shipping_generate_label_section( $order ) {
			$manifests                = esc_url(
				add_query_arg(
					array(
						'format' => 'get_vamaship_manifests',
						'data'   => 'manifests',
						'post'   => $order->ID,
						'action' => 'edit',
					),
					admin_url( 'post.php' )
				)
			);
			$label                    = esc_url(
				add_query_arg(
					array(
						'format' => 'get_vamaship_manifests',
						'data'   => 'label',
						'post'   => $order->ID,
						'action' => 'edit',
					),
					admin_url( 'post.php' )
				)
			);
			$get_the_shipment_details = esc_url(
				add_query_arg(
					array(
						'format' => 'get_shipment_details',
						'data'   => 'shipment_details',
						'post'   => $order->ID,
						'action' => 'edit',
					),
					admin_url( 'post.php' )
				)
			);
			$manifests_details        = get_post_meta( $order->ID, 'vamaship_order_manifests', true );
			$reference_no_of_order    = get_post_meta( $order->ID, 'order_shipment_reference_no', true );
			if ( ( isset( $manifests_details ) && $manifests_details !== '' ) ) {
				?>
		<ul>
		  <li><a href="<?php echo esc_url( $manifests ); ?>"> <?php esc_html_e( 'Get manifests', 'vamaship' ); ?></a></li>
		  <li><a href="<?php echo esc_url( $label ); ?>">  <?php esc_html_e( 'Get Label', 'vamaship' ); ?></a></li>    
		</ul>
				<?php
			} elseif ( ( isset( $reference_no_of_order ) && $reference_no_of_order !== '' ) ) {
				?>
		<ul>
		  <p> <?php esc_html_e( 'Shipment Generation is being Processing at Vamaship panel.', 'vamaship' ); ?></p>
		  <li><a href="<?php echo esc_url( $get_the_shipment_details ); ?>"> <?php esc_html_e( 'Get Shipment details', 'vamaship' ); ?>  </a></li>
		</ul>
		<?php    }
		}

		public function vmp_vamaship_shipping_generate_shipment_from_order() {
			global $post;

			$format                    = isset( $_GET ['format'] ) ? map_deep( wp_unslash( $_GET['format'] ), 'sanitize_text_field' ) : null;
			$order_id                  = isset( $_GET ['orderID'] ) ? map_deep( wp_unslash( $_GET['orderID'] ), 'sanitize_text_field' ) : null;
			$vamaship_tracking_details = array();
			$vamaship_refernce_no      = '';
			$vamaship_tracking_details = get_post_meta( $order_id, 'order_tracking_details' );
			$vamaship_refernce_no      = get_post_meta( $order_id, 'order_shipment_reference_no', true );
			if ( http_response_code() != '404' ) {
				// Function for generating the Shipment from Order Section ...
				if ( $format == 'shipment_generation' ) {
					if ( empty( $vamaship_tracking_details ) && $vamaship_refernce_no == '' ) {
						// seller_details....
						$vamaship_api_tokan = get_option( 'woocommerce_vamaship_settings', true );
						  // Getting  the Seller Info ....
						$store_address_1 = get_option( 'woocommerce_store_address' );
						$store_address_2 = get_option( 'woocommerce_store_address_2' );
						$store_city      = get_option( 'woocommerce_store_city' );
						$store_postcode  = get_option( 'woocommerce_store_postcode' );
						$store_address   = $store_address_1 . ',' . $store_address_2;
						  // The country/state
						$store_raw_country    = get_option( 'woocommerce_default_country' );
						$store_location       = wc_format_country_state_string( $store_raw_country );
						$seller_countery_name = WC()->countries->countries[ $store_location['country'] ];
						$states               = WC()->countries->get_states( $store_location['country'] );
						$seller_state         = ! empty( $states[ $store_location['state'] ] ) ? $states[ $store_location['state'] ] : '';
						$seller_email         = get_option( 'woocommerce_stock_email_recipient' );
						$seller_name          = get_bloginfo( 'name' );
						$seller_details       = array(
							'address' => $store_address,
							'city'    => $store_city,
							'country' => $seller_countery_name,
							'email'   => $seller_email,
							'name'    => $seller_name,
							'phone'   => $vamaship_api_tokan['seller_phone_no'],
							'pincode' => $store_postcode,
							'state'   => $seller_state,
						);
						// $seller_details=json_encode($seller_details);
						// Getting the Buyer Info and product Info  ..
						$order_detail                   = wc_get_order( $order_id );
						$shipping_first_name            = $order_detail->get_shipping_first_name();
						$shipping_lastname              = $order_detail->get_shipping_last_name();
						$shipping_name                  = $shipping_first_name . ' ' . $shipping_lastname;
						$shipping_company               = $order_detail->get_shipping_company();
						$shipping_address_first_line    = $order_detail->get_shipping_address_1();
						$shipping_address_second_line   = $order_detail->get_shipping_address_2();
						$buyer_address                  = $shipping_address_first_line . $shipping_address_second_line;
						$shipping_city                  = $order_detail->get_shipping_city();
						$shipping_state                 = $order_detail->get_shipping_state();
						$shipping_country               = $order_detail->get_shipping_country();
						$states                         = WC()->countries->get_states( $shipping_country );
						$shipping_country               = WC()->countries->countries[ $shipping_country ];
						$shipping_state                 = ! empty( $states[ $shipping_state ] ) ? $states[ $shipping_state ] : '';
						$shipping_payment_methode       = $order_detail->get_payment_method();
						$shipping_payment_methode_title = $order_detail->get_payment_method_title();
						$buyer_email                    = $order_detail->get_billing_email();
						$buyer_phone                    = $order_detail->get_billing_phone();
						$shipping_postcode              = $order_detail->get_shipping_postcode();
						$unit                           = get_option( 'woocommerce_dimension_unit' );
						$buyer_details                  = array(
							'gst_tin' => $vamaship_api_tokan['vamaship_vender_gst_no'],
							'address' => $buyer_address,
							'name'    => $shipping_name,
							'phone'   => $buyer_phone,
							'email'   => $buyer_email,
							'pincode' => $shipping_postcode,
							'country' => $shipping_country,
							'city'    => $shipping_city,
							'state'   => $shipping_state,
							'is_cod'  => ( $shipping_payment_methode === 'cod' ) ? 'true' : 'false',
						);
						$order_product                  = $order_detail->get_items();
						$product_quantity               = 1;
						$product_name                   = '';
						$product_breadth                = 0;
						$product_height                 = 0;
						$product_lengh                  = 0;
						$product_value                  = 0;
						$product_weight                 = 0;
						foreach ( $order_product as $sp_key => $sp_value ) {
							$single_product         = $sp_value->get_data();
							$single_product_details = wc_get_product( $single_product['product_id'] );
							$single_product_details = $single_product_details->get_data();
							$product_name           = ( $product_name !== '' ) ? ( $product_name . ',' . $single_product['name'] ) : $single_product['name'];

							$product_breadth = $product_breadth + $single_product_details['width'];
							$product_height  = $product_height + $single_product_details['height'];
							$product_lengh   = $product_lengh + $single_product_details['length'];
							$product_value   = $product_value + $single_product_details['price'];
							$product_weight  = $product_weight + $single_product_details['weight'] * $single_product['quantity'];
						}
						$weight_unit = get_option( 'woocommerce_weight_unit' );

						if ( $weight_unit == 'g' ) {
							$product_weight = ( $product_weight / 1000 );
						}

						$single_order_shipment_value = array(
							'product'          => $product_name,
							'quantity'         => $product_quantity,
							'breadth'          => ( $product_breadth > 30 ) ? 30 : $product_breadth,
							'height'           => ( $product_height > 30 ) ? 30 : $product_height,
							'length'           => ( $product_lengh > 30 ) ? 30 : $product_lengh,
							'pickup_date'      => date( DATE_ATOM ),
							'product_value'    => $order_detail->get_total(),
							'unit'             => $unit,
							'weight'           => $product_weight,
							'surface_category' => 'b2c',
						);
						$single_order_details[]      = array_merge( $buyer_details, $single_order_shipment_value );
						$order_shipment_booking      = $single_order_details;
						// Create the Booking of order in vamaship panel and also generate the shipment
						require_once VAMASHIP_DIR_PATH . '/vamaship/vamaship.php';
						if ( class_exists( 'vamaship' ) ) {
							$vamaship_create_shipment_order = new vamaship();
							$order_generated_shipment       = $vamaship_create_shipment_order->vmp_vamaship_shipping_create_booking_for_order( $seller_details, $order_shipment_booking, $vamaship_api_tokan['vamaship_api_tokan'], $vamaship_api_tokan['vamaship_api_url'], 'surface_b2c' );
							if ( isset( $order_generated_shipment['shipments'] ) && ! empty( $order_generated_shipment['shipments'] ) ) {
								if ( isset( $order_generated_shipment['documents'] ) && ! empty( $order_generated_shipment['documents'] ) ) {
										  update_post_meta( $order_id, 'vamaship_order_manifests', $order_generated_shipment['documents']['manifests']['0'] );
										  update_post_meta( $order_id, 'vamaship_order_labels', $order_generated_shipment['documents']['labels']['0'] );
									foreach ( $order_generated_shipment['shipments'] as $ship_key => $ship_value ) {
										if ( $order_id == $ship_value['reference1'] ) {
											$order_awb                   = $ship_value['awb'];
											$vamaship_order_no_for_order = $ship_value['order_id'];
										}
									}
										  $order_tracking_data         = array(
											  'order_awb' => $order_awb,
											  'vamaship_order_id' => $vamaship_order_no_for_order,
										  );
										  update_post_meta( $order_id, 'order_tracking_details', $order_tracking_data );
										  $note = 'Shipment For this is Created  AWB No :-' . $order_awb . ', Vamaship Order No for this Order is :-' . $vamaship_order_no_for_order . '.';
										  $order_detail->add_order_note( $note );
										  $order_detail->save();
										  $label_url                = $order_generated_shipment['documents']['labels']['0'];
										  $manifest_url             = $order_generated_shipment['documents']['manifests']['0'];
										  $vamaship_create_manifest = new vamaship();
										  $label_data               = $vamaship_create_manifest->vamaship_shipping_generate_manifest_for_order( $label_url, $vamaship_api_tokan['vamaship_api_tokan'] );
										  $manifest_data            = $vamaship_create_manifest->vamaship_shipping_generate_manifest_for_order( $manifest_url, $vamaship_api_tokan['vamaship_api_tokan'] );
										  // phpcs:disable
										  file_put_contents( VAMASHIP_UPLOAD_DIR_PATH . '/vamaship/vamaship_label.pdf', $label_data );
										  file_put_contents( VAMASHIP_UPLOAD_DIR_PATH . '/vamaship/vamaship_manifest.pdf', $manifest_data );
										  // phpcs:enable
										  $zip         = new ZipArchive();
										  $zipfilename = VAMASHIP_UPLOAD_DIR_PATH . '/vamaship/label-and-manifest.zip';
										  if ( $zip->open( $zipfilename, ZIPARCHIVE::CREATE ) == true ) {
											  $zip->addFile( VAMASHIP_UPLOAD_DIR_PATH . '/vamaship/vamaship_label.pdf', 'vamaship_bulk_label.pdf' );
											  $zip->addFile( VAMASHIP_UPLOAD_DIR_PATH . '/vamaship/vamaship_manifest.pdf', 'vamaship_bulk_manifest.pdf' );
										  }
											$zip->close();
											$zipname = 'bulk-label-and-manifest.zip';
												// Download code for Label and Manifest...
										  if ( filesize( VAMASHIP_UPLOAD_DIR_PATH . '/vamaship/label-and-manifest.zip' ) !== 0 ) {
											  header( 'Content-Type: application/zip' );
											  $zip = VAMASHIP_UPLOAD_DIR_PATH . '/vamaship/label-and-manifest.zip';
											  header( 'Content-Disposition: attachment; filename=' . $zipname );
											  header( 'Content-Type: application/zip' );
											  header( 'Content-Type: application/download' );
											  header( 'Content-Description: File Transfer' );
											  header( 'Content-Length: ' . filesize( $zip ) );
											  flush(); // this doesn't really matter.
											  // phpcs:disable
											  readfile( $zip );
											  unlink( $zip );
											  // phpcs:enable
											  die;
										  }
								}
							} elseif ( isset( $order_generated_shipment['refid'] ) && ! empty( $order_generated_shipment['refid'] ) ) {
								update_post_meta( $order_id, 'order_shipment_reference_no', $order_generated_shipment['refid'] );
								$message = 'Shipment is processing  please hit the callback api in order section.';
								include_once 'vamaship_admin_notices.php';
								new Vamamship_message( $message );

							} else {
								if ( isset( $order_generated_shipment['quotes'] ) && ! empty( $order_generated_shipment['quotes']['0']['messages'] ) ) {
										$message = $order_generated_shipment['quotes']['0']['messages'][0];
								} elseif ( isset( $order_generated_shipment['error'] ) && $order_generated_shipment['status_code'] == '901' ) {
									  $message = $order_generated_shipment['error'];
								} else {
									$message = 'There is some issue to generate the shipment';
								}
								include_once 'vamaship_admin_notices.php';
								new Vamamship_message( $message );
							}
						}
					} //End of Function for generating the Shipment ....
					else {

						$message = 'Shipment Already Generated.';

						include_once 'vamaship_admin_notices.php';

						new Vamamship_message( $message );
					}
				}
			}
		}
		// Generating the Invoice for Vamaship ..
		public function vmp_vamaship_shipping_generate_invoic_label() {
			$vamaship_api_tokan = get_option( 'woocommerce_vamaship_settings', true );
			if ( isset( $_GET ['format'] ) && 'get_vamaship_manifests' === sanitize_key( $_GET ['format'] ) ) {
				$data     = isset( $_GET['data'] ) ? sanitize_key( $_GET['data'] ) : '';
				$order_id = isset( $_GET['post'] ) ? sanitize_key( $_GET['post'] ) : '';
				if ( $data == 'manifests' ) {
					$url      = get_post_meta( $order_id, 'vamaship_order_manifests', true );
					$filename = 'Order_' . $order_id . '_manifest.pdf';
				} elseif ( $data == 'label' ) {
					$url      = get_post_meta( $order_id, 'vamaship_order_labels', true );
					$filename = 'Order_' . $order_id . '_label.pdf';
				}
				require_once VAMASHIP_DIR_PATH . '/vamaship/vamaship.php';
				if ( class_exists( 'vamaship' ) ) {
					$vamaship_create_manifest = new vamaship();
					$mainifest_data           = $vamaship_create_manifest->vmp_vamaship_shipping_generate_manifest_for_order( $url, $vamaship_api_tokan['vamaship_api_tokan'] );
					// phpcs:disable
					file_put_contents( VAMASHIP_UPLOAD_DIR_PATH . '/vamaship/vamaship_manifest.pdf', $mainifest_data );
					// phpcs:enable
					// Download code for Label and Manifest...
					if ( filesize( VAMASHIP_UPLOAD_DIR_PATH . '/vamaship/vamaship_manifest.pdf' ) !== 0 ) {
						header( 'Content-Type: application/octet-stream' );
						$file = VAMASHIP_UPLOAD_DIR_PATH . '/vamaship/vamaship_manifest.pdf';
						header( 'Content-Disposition: attachment; filename=' . $filename );
						header( 'Content-Type: application/octet-stream' );
						header( 'Content-Type: application/download' );
						header( 'Content-Description: File Transfer' );
						header( 'Content-Length: ' . filesize( $file ) );
						flush(); // this doesn't really matter.
						// phpcs:disable
						$fp = fopen( $file, 'r' );
						while ( ! feof( $fp ) ) {
							echo esc_html( fread( $fp, 65536 ) );
							flush(); // this is essential for large downloads
						}
						fclose( $fp );
						// phpcs:enable
					}
				}
			} // Generate the label and mainfest is function
			// Generate the shipment details ....
			elseif ( isset( $_GET ['format'] ) && 'get_shipment_details' === sanitize_key( $_GET ['format'] ) ) {
				$order_id              = map_deep( wp_unslash( $_GET['post'] ), 'sanitize_text_field' );
				$order_detail          = wc_get_order( $order_id );
				$reference_no_of_order = get_post_meta( $order_id, 'order_shipment_reference_no', true );
				require_once VAMASHIP_DIR_PATH . '/vamaship/vamaship.php';
				if ( class_exists( 'vamaship' ) ) {
					$vamaship_generate_shipment = new vamaship();
					$shipment_details           = $vamaship_generate_shipment->vmp_vamaship_shipping_generate_shipment_from_reference_no( $vamaship_api_tokan['vamaship_api_url'], $reference_no_of_order, $vamaship_api_tokan['vamaship_api_tokan'] );
					if ( isset( $shipment_details['shipments'] ) && ! empty( $shipment_details['shipments'] ) ) {
						if ( isset( $shipment_details['documents'] ) && ! empty( $shipment_details['documents'] ) ) {
							  update_post_meta( $order_id, 'vamaship_order_manifests', $shipment_details['documents']['manifests']['0'] );
							  update_post_meta( $order_id, 'vamaship_order_labels', $shipment_details['documents']['labels']['0'] );
							foreach ( $shipment_details['shipments'] as $ship_key => $ship_value ) {
								if ( $order_id == $ship_value['reference1'] ) {
									$order_awb                   = $ship_value['awb'];
									$vamaship_order_no_for_order = $ship_value['order_id'];
								}
							}
							  $order_tracking_data         = array(
								  'order_awb'         => $order_awb,
								  'vamaship_order_id' => $vamaship_order_no_for_order,
							  );
							  update_post_meta( $order_id, 'order_tracking_details', $order_tracking_data );
							  $note = 'Shipment For this is Created  AWB No :-' . $order_awb . ', Vamaship Order No for this Order is :-' . $vamaship_order_no_for_order . '.';
							  $order_detail->add_order_note( $note );
							  $order_detail->save();
						}
						require_once VAMASHIP_DIR_PATH . '/vamaship/vamaship.php';
						if ( class_exists( 'vamaship' ) ) {
							$label_url                = $shipment_details['documents']['labels']['0'];
							$manifest_url             = $shipment_details['documents']['manifests']['0'];
							$vamaship_create_manifest = new vamaship();
							$label_data               = $vamaship_create_manifest->vmp_vamaship_shipping_generate_manifest_for_order( $label_url, $vamaship_api_tokan['vamaship_api_tokan'] );
							$manifest_data            = $vamaship_create_manifest->vmp_vamaship_shipping_generate_manifest_for_order( $manifest_url, $vamaship_api_tokan['vamaship_api_tokan'] );
							// phpcs:disable
							file_put_contents( VAMASHIP_UPLOAD_DIR_PATH . '/vamaship/vamaship_label.pdf', $label_data );
							file_put_contents( VAMASHIP_UPLOAD_DIR_PATH . '/vamaship/vamaship_manifest.pdf', $manifest_data );
							// phpcs:enable
							$zip         = new ZipArchive();
							$zipfilename = VAMASHIP_UPLOAD_DIR_PATH . '/vamaship/label-and-manifest.zip';
							if ( $zip->open( $zipfilename, ZIPARCHIVE::CREATE ) == true ) {
								$zip->addFile( VAMASHIP_UPLOAD_DIR_PATH . '/vamaship/vamaship_label.pdf', 'vamaship_bulk_label.pdf' );
								$zip->addFile( VAMASHIP_UPLOAD_DIR_PATH . '/vamaship/vamaship_manifest.pdf', 'vamaship_bulk_manifest.pdf' );
							}
							$zip->close();
							$zipname = 'bulk-label-and-manifest.zip';
							// Download code for Label and Manifest...
							if ( filesize( VAMASHIP_UPLOAD_DIR_PATH . '/vamaship/label-and-manifest.zip' ) !== 0 ) {
								header( 'Content-Type: application/zip' );
								$zip = VAMASHIP_UPLOAD_DIR_PATH . '/vamaship/label-and-manifest.zip';
								header( 'Content-Disposition: attachment; filename=' . $zipname );
								header( 'Content-Type: application/zip' );
								header( 'Content-Type: application/download' );
								header( 'Content-Description: File Transfer' );
								header( 'Content-Length: ' . filesize( $zip ) );
								flush(); // this doesn't really matter.
								// phpcs:disable
								readfile( $zip );
								unlink( $zip );
								// phpcs:enable
								die;
							}
						}
					} else {
						$message = 'There is Problem to generate the Documents Please wait for some time then generate the Documents';
						include_once 'vamaship_admin_notices.php';
						new Vamamship_message( $message );
					}
				}
			}
		}
		// Function close to generate the label and invoice..
		/** Function for generating Bulk Shipments ...
		 * This is generate the Bulk shipment from order ..
		 *
		 * @Vamaship shipping
		 */
		public function vmp_vamaship_shipping_bulk_shipments_generate() {
			global $typenow;
			$wp_list_table = _get_list_table( 'WP_Posts_List_Table' );
			$action        = $wp_list_table->current_action();
			if ( $action === 'generate_shipments_vamaship' ) {
				$vamaship_api_tokan = get_option( 'woocommerce_vamaship_settings', true );
				if ( isset( $_REQUEST ['post'] ) ) {
					$post     = map_deep( wp_unslash( $_REQUEST['post'] ), 'sanitize_text_field' );
					$post_ids = array_map( 'intval', $post );
				}

				$shipment_type = $vamaship_api_tokan['vamaship_default_shipping'];
				$this->vmp_vamaship_shipping_order_bulk_shipment_generation( $post_ids, $shipment_type );
			} else if ( $action === 'generate_shipments_air_domestic' ) {
				if ( isset( $_REQUEST ['post'] ) ) {
					$post_ids = array_map( 'intval', $_REQUEST ['post'] );
				}
				$shipment_type = 'domestic_air';
				$this->vmp_vamaship_shipping_order_bulk_shipment_generation( $post_ids, $shipment_type );
			} elseif ( $action === 'generate_shipments_surface_b2c' ) {
				if ( isset( $_REQUEST ['post'] ) ) {
					$post_ids = array_map( 'intval', $_REQUEST ['post'] );
				}
				$shipment_type = 'surface_b2c';
				$this->vmp_vamaship_shipping_order_bulk_shipment_generation( $post_ids, $shipment_type );
			} elseif ( $action === 'generate_shipments_surface_b2b' ) {
				if ( isset( $_REQUEST ['post'] ) ) {
					$post_ids = array_map( 'intval', $_REQUEST ['post'] );
				}
				$shipment_type = 'surface_b2b';
				$this->vmp_vamaship_shipping_order_bulk_shipment_generation( $post_ids, $shipment_type );
			} elseif ( $action === 'generate_shipments_air_internatoinal' ) {
				if ( isset( $_REQUEST ['post'] ) ) {
					$post_ids = array_map( 'intval', $_REQUEST ['post'] );
				}
				$shipment_type = 'air_internatoinal';
				$this->vmp_vamaship_shipping_order_bulk_shipment_generation( $post_ids, $shipment_type );
			}
		}
		// Bulk generation for multiple order ...
		public function vmp_vamaship_shipping_order_bulk_shipment_generation( $post_ids, $shipment_type ) {
			// Getting Vamaship Shipping  Details...
			$vamaship_api_tokan = get_option( 'woocommerce_vamaship_settings', true );
			// Geting the shipment url type ....
			$extra_shipment_parameters = array();
			if ( $shipment_type == 'domestic_air' ) {
				$shipment_url = $vamaship_api_tokan['vamaship_api_url'];
			} elseif ( $shipment_type == 'surface_b2c' ) {
				$shipment_url              = $vamaship_api_tokan['vamaship_surface_api_url'];
				$extra_shipment_parameters = array(
					'surface_category' => 'b2c',
				);
			} elseif ( $shipment_type == 'surface_b2b' ) {
				$shipment_url              = $vamaship_api_tokan['vamaship_surface_api_url'];
				$extra_shipment_parameters = array(
					'surface_category' => 'b2b',
				);
			} elseif ( $shipment_type == 'air_internatoinal' ) {
				$shipment_url              = $vamaship_api_tokan['vamaship_international_api_url'];
				$extra_shipment_parameters = array(
					'cargo_type'      => 'general',
					'liability'       => 'no',
					'is_express'      => '0',
					'duty_applicable' => 'yes',
					'net_weight'      => '',
					'product_type'    => 'Other',
					'Shipment_type'   => 'Package',
					'package_type'    => 'other',
				);
			}
			// Getting  the Seller Info ....
			$store_address_1 = get_option( 'woocommerce_store_address' );
			$store_address_2 = get_option( 'woocommerce_store_address_2' );
			$store_city      = get_option( 'woocommerce_store_city' );
			$store_postcode  = get_option( 'woocommerce_store_postcode' );
			$store_address   = $store_address_1 . ',' . $store_address_2;
			// The country/state
			$store_raw_country      = get_option( 'woocommerce_default_country' );
			$store_location         = wc_format_country_state_string( $store_raw_country );
			$seller_countery_name   = WC()->countries->countries[ $store_location['country'] ];
			$states                 = WC()->countries->get_states( $store_location['country'] );
			$seller_state           = ! empty( $states[ $store_location['state'] ] ) ? $states[ $store_location['state'] ] : '';
			$seller_email           = get_option( 'woocommerce_stock_email_recipient' );
			$seller_name            = get_bloginfo( 'name' );
			$seller_details         = array(
				'address' => $store_address,
				'city'    => $store_city,
				'country' => $seller_countery_name,
				'email'   => $seller_email,
				'name'    => $seller_name,
				'phone'   => $vamaship_api_tokan['seller_phone_no'],
				'pincode' => $store_postcode,
				'state'   => $seller_state,
			);
			$order_shipment_booking = array();
			foreach ( $post_ids as $key => $order_id ) {
				$vamaship_tracking_details = get_post_meta( $order_id, 'order_tracking_details', true );
				$vamaship_refernce_no      = get_post_meta( $order_id, 'order_shipment_reference_no', true );
				$updated_dimensions        = get_post_meta( $order_id, 'vamaship_shipping_order_dimensions', true );

				if ( ! empty( $vamaship_tracking_details ) || $vamaship_refernce_no !== '' ) {
					continue;
				} else {
					$order_data = wc_get_order( $order_id )->get_items( 'shipping' );
					// Getting the Buyer Info and product Info  ..
					$order_detail                   = wc_get_order( $order_id );
					$shipping_first_name            = $order_detail->get_shipping_first_name();
					$shipping_lastname              = $order_detail->get_shipping_last_name();
					$shipping_name                  = $shipping_first_name . ' ' . $shipping_lastname;
					$shipping_company               = $order_detail->get_shipping_company();
					$shipping_address_first_line    = $order_detail->get_shipping_address_1();
					$shipping_address_second_line   = $order_detail->get_shipping_address_2();
					$buyer_address                  = $shipping_address_first_line . $shipping_address_second_line;
					$shipping_city                  = $order_detail->get_shipping_city();
					$shipping_state                 = $order_detail->get_shipping_state();
					$shipping_country               = $order_detail->get_shipping_country();
					$states                         = WC()->countries->get_states( $shipping_country );
					$shipping_country               = WC()->countries->countries[ $shipping_country ];
					$shipping_state                 = ! empty( $states[ $shipping_state ] ) ? $states[ $shipping_state ] : '';
					$shipping_payment_methode       = $order_detail->get_payment_method();
					$shipping_payment_methode_title = $order_detail->get_payment_method_title();
					$buyer_email                    = $order_detail->get_billing_email();
					$buyer_phone                    = ( $order_detail->get_billing_phone() != '' ) ? $order_detail->get_billing_phone() : '9999999999';
					$shipping_postcode              = $order_detail->get_shipping_postcode();
					$unit                           = get_option( 'woocommerce_dimension_unit' );
					$buyer_details                  = array(
						'gst_tin'    => $vamaship_api_tokan['vamaship_vender_gst_no'],
						'address'    => $buyer_address,
						'name'       => $shipping_name,
						'phone'      => $buyer_phone,
						'email'      => $buyer_email,
						'pincode'    => $shipping_postcode,
						'country'    => $shipping_country,
						'city'       => $shipping_city,
						'state'      => $shipping_state,
						'is_cod'     => ( $shipping_payment_methode === 'cod' ) ? 'true' : 'false',
						'reference1' => $order_id,
						'reference2' => '',
						'hsn_code'   => '',
					);
					$order_product                  = $order_detail->get_items();
					$product_quantity               = 1;
					$product_name                   = '';
					$product_breadth                = 0;
					$product_height                 = 0;
					$product_lengh                  = 0;
					$product_weight                 = 0;
					foreach ( $order_product as $sp_key => $sp_value ) {
						$single_product         = $sp_value->get_data();
						$single_product_details = wc_get_product( $single_product['product_id'] );
						$single_product_details = $single_product_details->get_data();
						$product_name           = ( $product_name !== '' ) ? ( $product_name . ',' . $single_product['name'] ) : $single_product['name'];

						$product_breadth = $product_breadth + $single_product_details['width'];
						$product_height  = $product_height + $single_product_details['height'];
						$product_lengh   = $product_lengh + $single_product_details['length'];
						$product_weight  = $product_weight + $single_product_details['weight'] * $single_product['quantity'];

					}

					if ( isset( $updated_dimensions ) && ! empty( $updated_dimensions ) ) {

						$product_lengh   = $updated_dimensions['max_length'];
						$product_height  = $updated_dimensions['max_height'];
						$product_breadth = $updated_dimensions['max_width'];
						$product_weight  = $updated_dimensions['max_weight'];
					}

					$weight_unit = get_option( 'woocommerce_weight_unit' );

					if ( $weight_unit == 'g' ) {
						$product_weight = ( $product_weight / 1000 );
					}
					$single_order_shipment_value = array(
						'product'       => $product_name,
						'quantity'      => $product_quantity,
						'breadth'       => ( $product_breadth > 30 ) ? 30 : $product_breadth,
						'height'        => ( $product_height > 30 ) ? 30 : $product_height,
						'length'        => ( $product_lengh > 30 ) ? 30 : $product_lengh,
						'pickup_date'   => date( DATE_ATOM ),
						'product_value' => $order_detail->get_total(),
						'unit'          => $unit,
						'weight'        => $product_weight,
					);

					if ( isset( $extra_shipment_parameters ) && ! empty( $extra_shipment_parameters ) ) {
						if ( isset( $extra_shipment_parameters['net_weight'] ) && $extra_shipment_parameters['net_weight'] != '' ) {
							$extra_shipment_parameters['net_weight'] = $product_weight;
						}
						$single_order_shipment_value = array_merge( $single_order_shipment_value, $extra_shipment_parameters );
					}
					$single_order_details     = array_merge( $buyer_details, $single_order_shipment_value );
					$order_shipment_booking[] = $single_order_details;
				}
			}
			// Create the Booking of order in vamaship panel and also generate the shipment
			require_once VAMASHIP_DIR_PATH . '/vamaship/vamaship.php';
			if ( class_exists( 'vamaship' ) ) {
				$vamaship_create_shipment_order = new vamaship();
				$order_generated_shipment       = $vamaship_create_shipment_order->vmp_vamaship_shipping_create_booking_for_order( $seller_details, $order_shipment_booking, $vamaship_api_tokan['vamaship_api_tokan'], $shipment_url, $shipment_type );
				if ( isset( $order_generated_shipment['shipments'] ) && ! empty( $order_generated_shipment['shipments'] ) && ! empty( $order_generated_shipment['documents'] ) ) {
					foreach ( $post_ids as $order_id ) {
						$order_detail              = wc_get_order( $order_id );
						$vamaship_tracking_details = get_post_meta( $order_id, 'order_tracking_details', true );
						$vamaship_refernce_no      = get_post_meta( $order_id, 'order_shipment_reference_no', true );
						if ( ! empty( $vamaship_tracking_details ) || $vamaship_refernce_no !== '' ) {
								continue;
						} else {
							if ( isset( $order_generated_shipment['documents'] ) && ! empty( $order_generated_shipment['documents'] ) ) {
								update_post_meta( $order_id, 'vamaship_order_manifests', $order_generated_shipment['documents']['manifests']['0'] );
								update_post_meta( $order_id, 'vamaship_order_labels', $order_generated_shipment['documents']['labels']['0'] );
							}
							foreach ( $order_generated_shipment['shipments'] as $ship_key => $ship_value ) {
								if ( $order_id == $ship_value['reference1'] ) {
									$order_awb                   = $ship_value['awb'];
									$vamaship_order_no_for_order = $ship_value['order_id'];
								}
							}
							$order_tracking_data = array(
								'order_awb'         => $order_awb,
								'vamaship_order_id' => $vamaship_order_no_for_order,
							);
							update_post_meta( $order_id, 'order_tracking_details', $order_tracking_data );
							$note = 'Shipment For this is Created  AWB No :-' . $order_awb . ', Vamaship Order No for this Order is :-' . $vamaship_order_no_for_order . '.';
							$order_detail->add_order_note( $note );
							$order_detail->save();
						}
					}
					require_once VAMASHIP_DIR_PATH . '/vamaship/vamaship.php';
					if ( class_exists( 'vamaship' ) ) {
						$label_url                = $order_generated_shipment['documents']['labels']['0'];
						$manifest_url             = $order_generated_shipment['documents']['manifests']['0'];
						$vamaship_create_manifest = new vamaship();
						$label_data               = $vamaship_create_manifest->vmp_vamaship_shipping_generate_manifest_for_order( $label_url, $vamaship_api_tokan['vamaship_api_tokan'] );
						$manifest_data            = $vamaship_create_manifest->vmp_vamaship_shipping_generate_manifest_for_order( $manifest_url, $vamaship_api_tokan['vamaship_api_tokan'] );
						// phpcs:disable
						file_put_contents( VAMASHIP_UPLOAD_DIR_PATH . '/vamaship/vamaship_label.pdf', $label_data );
						file_put_contents( VAMASHIP_UPLOAD_DIR_PATH . '/vamaship/vamaship_manifest.pdf', $manifest_data );
						// phpcs:enable
						$zip         = new ZipArchive();
						$zipfilename = VAMASHIP_UPLOAD_DIR_PATH . '/vamaship/label-and-manifest.zip';
						if ( $zip->open( $zipfilename, ZIPARCHIVE::CREATE ) == true ) {
							$zip->addFile( VAMASHIP_UPLOAD_DIR_PATH . '/vamaship/vamaship_label.pdf', 'vamaship_bulk_label.pdf' );
							$zip->addFile( VAMASHIP_UPLOAD_DIR_PATH . '/vamaship/vamaship_manifest.pdf', 'vamaship_bulk_manifest.pdf' );
						}
						$zip->close();
						$zipname = 'bulk-label-and-manifest.zip';
						// Download code for Label and Manifest...
						if ( filesize( VAMASHIP_UPLOAD_DIR_PATH . '/vamaship/label-and-manifest.zip' ) !== 0 ) {
							header( 'Content-Type: application/zip' );
							$zip = VAMASHIP_UPLOAD_DIR_PATH . '/vamaship/label-and-manifest.zip';
							header( 'Content-Disposition: attachment; filename=' . $zipname );
							header( 'Content-Type: application/zip' );
							header( 'Content-Type: application/download' );
							header( 'Content-Description: File Transfer' );
							header( 'Content-Length: ' . filesize( $zip ) );
							flush(); // this doesn't really matter.
							// phpcs:disable
							readfile( $zip );
							unlink( $zip );
							// phpcs:enable
							die;
						}
					}
				} elseif ( isset( $order_generated_shipment['refid'] ) && ! empty( $order_generated_shipment['refid'] ) ) {
					foreach ( $post_ids as $order_id ) {
						$vamaship_tracking_details = get_post_meta( $order_id, 'order_tracking_details', true );
						$vamaship_refernce_no      = get_post_meta( $order_id, 'order_shipment_reference_no', true );
						if ( ! empty( $vamaship_tracking_details ) || $vamaship_refernce_no !== '' ) {
								continue;
						} else {
								update_post_meta( $order_id, 'order_shipment_reference_no', $order_generated_shipment['refid'] );
						}
					}

					$message = 'Shipment is processing  please hit the callback api in order edit section.';
					include_once 'vamaship_admin_notices.php';
					new Vamamship_message( $message );
				} else {

					if ( isset( $order_generated_shipment['quotes'] ) && ! empty( $order_generated_shipment['quotes']['0']['messages'] ) ) {

						$message = $order_generated_shipment['quotes']['0']['messages'][0];
					} elseif ( isset( $order_generated_shipment['error'] ) && $order_generated_shipment['status_code'] == '901' ) {
						$message = $order_generated_shipment['error'];
					} else {
						$message = 'There is some issue to generate the shipment';
					}
					include_once 'vamaship_admin_notices.php';
					new Vamamship_message( $message );
				}
			}
		} //End of Function for Bulk  generating the Shipments from order panel ......
	}
	new Vamaship_Shiping_Method();
}
?>
