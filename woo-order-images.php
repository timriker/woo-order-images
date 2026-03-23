<?php
/**
 * Plugin Name: Woo Order Images
 * Description: Collects customer image uploads for WooCommerce products and generates order print sheets, including puzzle layouts.
 * Version: 0.5.1
 * Requires at least: 6.8
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Author: Tim Riker
 * Author URI: https://rikers.org
 * Text Domain: woo-order-images
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WOI_VERSION' ) ) {
	define( 'WOI_VERSION', '0.5.1' );
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

add_filter(
	'plugin_action_links_' . plugin_basename( WOI_PLUGIN_FILE ),
	static function ( $links ) {
		$settings_url = admin_url( 'admin.php?page=woi-settings' );
		$settings     = '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'woo-order-images' ) . '</a>';

		array_unshift( $links, $settings );

		return $links;
	}
);

add_filter(
	'plugin_row_meta',
	static function ( $links, $file ) {
		if ( plugin_basename( WOI_PLUGIN_FILE ) !== $file ) {
			return $links;
		}

		$links[] = '<a href="' . esc_url( 'https://github.com/bestlifemagnets/woo-order-images' ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'GitHub', 'woo-order-images' ) . '</a>';

		return $links;
	},
	10,
	2
);
