<?php
/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link https://www.vamaship.com
 * @since 1.0.1
 * @package Vamaship_shipping
 *
 * @wordpress-plugin
 * Plugin Name: VAMASHIP SHIPPING
 * Plugin URI: https://www.vamaship.com
 * Description: The Vamaship shipping is only available in India.Vamaship is an integrated logistics marketplace for businesses.
 * Version: 1.1.0
 * Author: Vamaship
 * Author URI: https://www.vamaship.com
 * License: GPL-3.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: vamaship
 * Tested up to:     5.2.4
 * WC tested up to: 3.7.0
 * Domain Path: /languages
 */
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {

	die;
}
/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'VAMASHIP_SHIPPING_VERSION', '1.0.0' );
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( function_exists( 'is_multisite' ) && is_multisite() ) {
	include_once ABSPATH . 'wp-admin/includes/plugin.php';
}
define( 'VAMASHIP_DIR_PATH', plugin_dir_path( __FILE__ ) );
define( 'VAMASHIP_DIR_URL', plugin_dir_url( __FILE__ ) );

define( 'VAMASHIP_UPLOAD_DIR_PATH', WP_CONTENT_DIR . '/uploads' );
$activated = true;
if ( function_exists( 'is_multisite' ) && is_multisite() ) {
	include_once ABSPATH . 'wp-admin/includes/plugin.php';
	if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
		$activated = false;
	}
} else {
	if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
		$activated = false;
	}
}
if ( $activated ) {
	// This event happen while plugin activating time ...
	register_activation_hook( __FILE__, 'vmp_vamaship_shipping_initial_settings' );
	function vmp_vamaship_shipping_initial_settings() {
		$dir_path = VAMASHIP_UPLOAD_DIR_PATH . '/vamaship';
		if ( ! is_dir( $dir_path ) ) {
			mkdir( $dir_path, 0755, true );
		}
		// Schedule the cron for pincode update
		if ( ! wp_next_scheduled( 'vmp_vamaship_shipping_update_pincode' ) ) {
			wp_schedule_event( time(), 'daily', 'vmp_vamaship_shipping_update_pincode' );
		}
	}
	// Event Occuers when the plugin going to Deactivate
	register_deactivation_hook( __FILE__, 'vamaship_shipping_deactivation' );
	function vamaship_shipping_deactivation() {
		wp_clear_scheduled_hook( 'vamaship_shipping_update_pincode' );
	}
	// add cron functionality for pincode update
	add_action( 'vmp_vamaship_shipping_update_pincode', 'vmp_vamaship_shipping_update_the_pincode' );

	function vmp_vamaship_shipping_update_the_pincode() {

		require_once VAMASHIP_DIR_PATH . '/vamaship/vamaship.php';
		$vamaship_api_tokan = get_option( 'woocommerce_vamaship_settings', true );
		if ( isset( $vamaship_api_tokan ) && ! empty( $vamaship_api_tokan ) ) {
			$owner_pincode = get_option( 'woocommerce_store_postcode' );

			$owner_api_tokan  = $vamaship_api_tokan['vamaship_api_tokan'];
			$type_of_order    = 'cod';
			$vamaship_api_url = $vamaship_api_tokan['vamaship_api_url'];

			if ( class_exists( 'vamaship' ) ) {
				$vamaship_shipping_object = new vamaship();
				$all_pincode              = $vamaship_shipping_object->vmp_vamaship_shipping_get_all_postcode( $owner_pincode, $type_of_order, $owner_api_tokan, $vamaship_api_url );

				if ( isset( $all_pincode['status'] ) && $all_pincode['status'] == 200 && isset( $all_pincode['success'] ) && $all_pincode['success'] == 1 ) {
					$pincode_data = wp_json_encode( $all_pincode['covered'] );
					update_option( 'vamaship_shipping_covered_pincode', $pincode_data, $autoload = null );
				}
			}
		}
	}


	require_once VAMASHIP_DIR_PATH . '/includes/class-vamaship-shiping-method.php';
}
