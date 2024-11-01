<?php
// ============================================================+
// File name : vamaship.php
// Version : 1.0.0
// Begin : 2002-08-03
// Last Update : 2015-06-18
// Author : Vamaship Shipping Company
// License : GNU-LGPL v3 (http://www.gnu.org/copyleft/lesser.html)
// -------------------------------------------------------------------
//
// Description :
// This is a PHP class for Vamaship shipping Rate .
class vamaship {
	// Get all the Pincode of destination from Owner pincode for WordPress
	// This is wp_remote_post based request
	public function vmp_vamaship_shipping_get_all_postcode( $owner_pincode, $payment_type, $owner_api_tokan, $vamaship_url ) {
		$body_arg    = array(
			'type'    => $payment_type,
			'origin'  => $owner_pincode,
			'subtype' => 'general',
		);
		$url         = $vamaship_url . '/dom/coverage';
		$args        = array(
			'method'      => 'POST',
			'timeout'     => 45,
			'redirection' => 5,
			'httpversion' => '1.0',
			'blocking'    => true,
			'headers'     => array(
				'Authorization' => 'Bearer ' . $owner_api_tokan,
			),
			'body'        => $body_arg,
		);
		$response    = wp_safe_remote_post( $url, $args );
		$all_pincode = json_decode( wp_remote_retrieve_body( $response ), true );
		return $all_pincode;
	}
	/*
	 This function use for get shipping cost in for shipments in WordPress
	 * $seller is a associative array which hold all deatils of seller like Address , City , State
	 * , Pincode ,Name , Phone , Pincode, Email
	 * $shipments hold the details information of each product in cart . It also hold the details *
	 * of customer address where is shipment being ship .
	 * $owner_api_tokan is the key of vamaship tokan
	 */
	public function vmp_vamaship_shipping_get_shipping_cost( $seller, $shipments, $owner_api_tokan, $vamaship_url ) {
		$body_arg      = array(
			'seller'    => $seller,
			'shipments' => $shipments,
		);
		$url           = $vamaship_url . '/surface/quote';
		$args          = array(
			'method'      => 'POST',
			'timeout'     => 45,
			'redirection' => 5,
			'httpversion' => '1.0',
			'blocking'    => true,
			'headers'     => array(
				'Authorization' => 'Bearer ' . $owner_api_tokan,
				'Content-Type'  => 'application/json; charset=utf-8',
			),
			'body'        => wp_json_encode( $body_arg ),
		);
		$response      = wp_remote_post( $url, $args );
		$shipping_cost = json_decode( wp_remote_retrieve_body( $response ), true );
		return $shipping_cost;
	}
	/*
	 This function use for create booking for order in wp .
	 * $seller is a associative array which hold all deatils of seller like Address , City , State
	 * , Pincode ,Name , Phone , Pincode, Email
	 * $shipments hold the details information of each product in cart . It also hold the details *
	 * of customer address where is shipment being ship .
	 * $owner_api_tokan is the key of vamaship tokan
	 */
	public function vmp_vamaship_shipping_create_booking_for_order( $seller, $shipments, $owner_api_tokan, $vamaship_url, $shipment_type ) {
		$body_arg = array(
			'seller'    => $seller,
			'shipments' => $shipments,
			'callback'  => 'http:\/\/test.com\/callback',
		);

		$log_dir = WC_LOG_DIR . 'vamaship-logs.log';

		if ( ! is_dir( $log_dir ) ) {
			// phpcs:disable
			@fopen( WC_LOG_DIR . 'vamaship-logs.log', 'a' );
			// phpcs:enable
		}

		$log = '=======================================================' . PHP_EOL;
		// phpcs:disable
		file_put_contents( $log_dir, $log, FILE_APPEND );
		// phpcs:enable

		if ( $shipment_type == 'domestic_air' ) {
			$url = $vamaship_url . '/dom/book';
		} elseif ( $shipment_type == 'surface_b2c' ) {
			$url = $vamaship_url . '/surface/book';
		} elseif ( $shipment_type == 'surface_b2b' ) {
			$url = $vamaship_url . '/surface/book';
		} elseif ( $shipment_type == 'air_internatoinal' ) {
			$url = $vamaship_url . '/intl/book';
		}
		$args          = array(
			'method'      => 'POST',
			'timeout'     => 45,
			'redirection' => 5,
			'httpversion' => '1.0',
			'blocking'    => true,
			'headers'     => array(
				'Authorization' => 'Bearer ' . $owner_api_tokan,
				'Content-Type'  => 'application/json; charset=utf-8',
			),
			'body'        => wp_json_encode( $body_arg ),
		);
		$response      = wp_remote_post( $url, $args );
		$order_details = json_decode( wp_remote_retrieve_body( $response ), true );

		$log = 'Data ' . wp_json_encode( $args ) . PHP_EOL;
		// phpcs:disable
		file_put_contents( $log_dir, $log, FILE_APPEND );
		// phpcs:enable
		$log = 'Order details' . wp_json_encode( $order_details ) . PHP_EOL;
		// phpcs:disable
		file_put_contents( $log_dir, $log, FILE_APPEND );
		// phpcs:enable
		return $order_details;
	}
	/*
	 This fuction for call back url if AWB is not provided while booking time
	** This function is create booking for order if AWB not provided and create shipment
	 * This function return the all document of order like Invoice ,label , packing slip .
	 * This is a GET request so just call this function after booking request .
	 * $refid is refernce id which you get when AWB not present
	 */
	public function vamaship_shipping_callback_function_for_order_booking( $refid, $owner_api_tokan ) {
		$url           = 'https://api.vamaship.com/ecom/v1/' . $refid;
		$args          = array(
			'method'      => 'GET',
			'timeout'     => 45,
			'redirection' => 5,
			'httpversion' => '1.0',
			'blocking'    => true,
			'headers'     => array(
				'Authorization' => 'Bearer ' . $owner_api_tokan,
				'Content-Type'  => 'application/json; charset=utf-8',
			),
		);
		$response      = wp_safe_remote_get( $url, $args );
		$order_details = json_decode( wp_remote_retrieve_body( $response ), true );
		return $order_details;
	}
	/*
	 This function use for track the shipment .
	** This is also use to get the status of Order ..
	* this is a GET request
	* $orderid is vamaship orderid which we recive after book the shipment
	*/
	public function vmp_vamaship_shipping_track_the_order( $url, $order_id, $owner_api_tokan ) {
		$url          = $url . '/track/' . $order_id;
		$args         = array(
			'method'      => 'GET',
			'timeout'     => 45,
			'redirection' => 5,
			'httpversion' => '1.0',
			'blocking'    => true,
			'headers'     => array(
				'Authorization' => 'Bearer ' . $owner_api_tokan,
				'Content-Type'  => 'application/json; charset=utf-8',
			),
		);
		$response     = wp_safe_remote_get( $url, $args );
		$order_status = json_decode( wp_remote_retrieve_body( $response ), true );
		return $order_status;
	}
	public function vmp_vamaship_shipping_generate_manifest_for_order( $url, $owner_api_tokan ) {
		$url      = $url;
		$args     = array(
			'method'      => 'GET',
			'timeout'     => 45,
			'redirection' => 5,
			'httpversion' => '1.0',
			'blocking'    => true,
			'headers'     => array(
				'Authorization' => 'Bearer ' . $owner_api_tokan,
				'Content-Type'  => 'application/json; charset=utf-8',
			),
		);
		$response = wp_remote_post( $url, $args );
		return $response['body'];
	}
	public function vmp_vamaship_shipping_generate_shipment_from_reference_no( $url, $refernce_no, $owner_api_tokan ) {
		$url              = $url . '/details/' . $refernce_no;
		$args             = array(
			'method'      => 'GET',
			'timeout'     => 45,
			'redirection' => 5,
			'httpversion' => '1.0',
			'blocking'    => true,
			'headers'     => array(
				'Authorization' => 'Bearer ' . $owner_api_tokan,
				'Content-Type'  => 'application/json; charset=utf-8',
			),
		);
		$response         = wp_safe_remote_get( $url, $args );
		$shipment_details = json_decode( wp_remote_retrieve_body( $response ), true );
		return $shipment_details;
	}
}
