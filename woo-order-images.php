<?php
/**
 * Plugin Name: Woo Order Images
 * Description: Adds order-linked image requirements to WooCommerce products.
 * Version: 0.3.0
 * Requires at least: 6.8
 * Requires PHP: 7.4
 * Author: Tim Riker
 * Author URI: https://rikers.org
 * Text Domain: woo-order-images
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WOI_VERSION' ) ) {
	define( 'WOI_VERSION', '0.3.0' );
}

if ( ! defined( 'WOI_PLUGIN_FILE' ) ) {
	define( 'WOI_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'WOI_PLUGIN_DIR' ) ) {
	define( 'WOI_PLUGIN_DIR', plugin_dir_path( WOI_PLUGIN_FILE ) );
}

if ( ! defined( 'WOI_PLUGIN_URL' ) ) {
	define( 'WOI_PLUGIN_URL', plugin_dir_url( WOI_PLUGIN_FILE ) );
}

require_once WOI_PLUGIN_DIR . 'includes/class-woi-plugin.php';

add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		WOI_Plugin::instance()->init();
	}
);
