<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once WOI_PLUGIN_DIR . 'includes/class-woi-admin-product-settings.php';
require_once WOI_PLUGIN_DIR . 'includes/class-woi-admin-order-images.php';
require_once WOI_PLUGIN_DIR . 'includes/class-woi-frontend.php';
require_once WOI_PLUGIN_DIR . 'includes/class-woi-order-images.php';
require_once WOI_PLUGIN_DIR . 'includes/class-woi-settings.php';

class WOI_Plugin {
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function init() {
		$settings = new WOI_Settings();
		$settings->init();

		$admin = new WOI_Admin_Product_Settings();
		$admin->init();

		$frontend = new WOI_Frontend();
		$frontend->init();

		$order_images = new WOI_Order_Images();
		$order_images->init();

		$admin_order_images = new WOI_Admin_Order_Images();
		$admin_order_images->init();
	}
}
