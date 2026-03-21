<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WOI_Settings {
	const OPTION_WATERMARK_TEXT = 'woi_watermark_text';
	const DEFAULT_WATERMARK_TEXT = 'BestLifeMagnets.com';

	public function init() {
		add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public function register_settings_page() {
		add_submenu_page(
			'woocommerce',
			__( 'Order Images Settings', 'woo-order-images' ),
			__( 'Order Images', 'woo-order-images' ),
			'manage_woocommerce',
			'woi-settings',
			array( $this, 'render_settings_page' )
		);
	}

	public function register_settings() {
		register_setting(
			'woi_settings_group',
			self::OPTION_WATERMARK_TEXT,
			array(
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => self::DEFAULT_WATERMARK_TEXT,
			)
		);

		add_settings_section(
			'woi_print_section',
			__( 'Print Sheet', 'woo-order-images' ),
			'__return_false',
			'woi-settings'
		);

		add_settings_field(
			self::OPTION_WATERMARK_TEXT,
			__( 'Watermark text', 'woo-order-images' ),
			array( $this, 'render_watermark_field' ),
			'woi-settings',
			'woi_print_section'
		);
	}

	public function render_watermark_field() {
		$value = self::get_watermark_text();
		echo '<input type="text" class="regular-text" name="' . esc_attr( self::OPTION_WATERMARK_TEXT ) . '" value="' . esc_attr( $value ) . '" />';
		echo '<p class="description">' . esc_html__( 'Text printed in the bleed-area flaps outside the visible area.', 'woo-order-images' ) . '</p>';
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'woo-order-images' ) );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Woo Order Images Settings', 'woo-order-images' ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'woi_settings_group' );
				do_settings_sections( 'woi-settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	public static function get_watermark_text() {
		$value = get_option( self::OPTION_WATERMARK_TEXT, self::DEFAULT_WATERMARK_TEXT );
		$value = is_string( $value ) ? trim( $value ) : '';

		return '' !== $value ? $value : self::DEFAULT_WATERMARK_TEXT;
	}
}