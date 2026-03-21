<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WOI_Settings {
	const OPTION_WATERMARK_TEXT = 'woi_watermark_text';
	const DEFAULT_WATERMARK_TEXT = 'BestLifeMagnets.com';

	const OPTION_PRINT_MARGIN_TOP = 'woi_print_margin_top';
	const OPTION_PRINT_MARGIN_RIGHT = 'woi_print_margin_right';
	const OPTION_PRINT_MARGIN_BOTTOM = 'woi_print_margin_bottom';
	const OPTION_PRINT_MARGIN_LEFT = 'woi_print_margin_left';

	const DEFAULT_PRINT_MARGIN_TOP = 0.25;
	const DEFAULT_PRINT_MARGIN_RIGHT = 0.25;
	const DEFAULT_PRINT_MARGIN_BOTTOM = 0.5;
	const DEFAULT_PRINT_MARGIN_LEFT = 0.25;

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

		register_setting(
			'woi_settings_group',
			self::OPTION_PRINT_MARGIN_TOP,
			array(
				'sanitize_callback' => array( $this, 'sanitize_float' ),
				'default'           => self::DEFAULT_PRINT_MARGIN_TOP,
			)
		);

		register_setting(
			'woi_settings_group',
			self::OPTION_PRINT_MARGIN_RIGHT,
			array(
				'sanitize_callback' => array( $this, 'sanitize_float' ),
				'default'           => self::DEFAULT_PRINT_MARGIN_RIGHT,
			)
		);

		register_setting(
			'woi_settings_group',
			self::OPTION_PRINT_MARGIN_BOTTOM,
			array(
				'sanitize_callback' => array( $this, 'sanitize_float' ),
				'default'           => self::DEFAULT_PRINT_MARGIN_BOTTOM,
			)
		);

		register_setting(
			'woi_settings_group',
			self::OPTION_PRINT_MARGIN_LEFT,
			array(
				'sanitize_callback' => array( $this, 'sanitize_float' ),
				'default'           => self::DEFAULT_PRINT_MARGIN_LEFT,
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

		add_settings_field(
			self::OPTION_PRINT_MARGIN_TOP,
			__( 'Top margin (in)', 'woo-order-images' ),
			array( $this, 'render_margin_field_top' ),
			'woi-settings',
			'woi_print_section'
		);

		add_settings_field(
			self::OPTION_PRINT_MARGIN_RIGHT,
			__( 'Right margin (in)', 'woo-order-images' ),
			array( $this, 'render_margin_field_right' ),
			'woi-settings',
			'woi_print_section'
		);

		add_settings_field(
			self::OPTION_PRINT_MARGIN_BOTTOM,
			__( 'Bottom margin (in)', 'woo-order-images' ),
			array( $this, 'render_margin_field_bottom' ),
			'woi-settings',
			'woi_print_section'
		);

		add_settings_field(
			self::OPTION_PRINT_MARGIN_LEFT,
			__( 'Left margin (in)', 'woo-order-images' ),
			array( $this, 'render_margin_field_left' ),
			'woi-settings',
			'woi_print_section'
		);
	}

	public function render_watermark_field() {
		$value = self::get_watermark_text();
		echo '<input type="text" class="regular-text" name="' . esc_attr( self::OPTION_WATERMARK_TEXT ) . '" value="' . esc_attr( $value ) . '" />';
		echo '<p class="description">' . esc_html__( 'Text printed in the bleed-area flaps outside the visible area.', 'woo-order-images' ) . '</p>';
	}

	public function render_margin_field_top() {
		$value = self::get_print_margin_top();
		echo '<input type="number" step="0.01" min="0" class="small-text" name="' . esc_attr( self::OPTION_PRINT_MARGIN_TOP ) . '" value="' . esc_attr( $value ) . '" />';
	}

	public function render_margin_field_right() {
		$value = self::get_print_margin_right();
		echo '<input type="number" step="0.01" min="0" class="small-text" name="' . esc_attr( self::OPTION_PRINT_MARGIN_RIGHT ) . '" value="' . esc_attr( $value ) . '" />';
	}

	public function render_margin_field_bottom() {
		$value = self::get_print_margin_bottom();
		echo '<input type="number" step="0.01" min="0" class="small-text" name="' . esc_attr( self::OPTION_PRINT_MARGIN_BOTTOM ) . '" value="' . esc_attr( $value ) . '" />';
	}

	public function render_margin_field_left() {
		$value = self::get_print_margin_left();
		echo '<input type="number" step="0.01" min="0" class="small-text" name="' . esc_attr( self::OPTION_PRINT_MARGIN_LEFT ) . '" value="' . esc_attr( $value ) . '" />';
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

	public function sanitize_float( $input ) {
		return is_numeric( $input ) ? (float) $input : 0;
	}

	public static function get_watermark_text() {
		$value = get_option( self::OPTION_WATERMARK_TEXT, self::DEFAULT_WATERMARK_TEXT );
		$value = is_string( $value ) ? trim( $value ) : '';

		return '' !== $value ? $value : self::DEFAULT_WATERMARK_TEXT;
	}

	public static function get_print_margin_top() {
		return (float) get_option( self::OPTION_PRINT_MARGIN_TOP, self::DEFAULT_PRINT_MARGIN_TOP );
	}

	public static function get_print_margin_right() {
		return (float) get_option( self::OPTION_PRINT_MARGIN_RIGHT, self::DEFAULT_PRINT_MARGIN_RIGHT );
	}

	public static function get_print_margin_bottom() {
		return (float) get_option( self::OPTION_PRINT_MARGIN_BOTTOM, self::DEFAULT_PRINT_MARGIN_BOTTOM );
	}

	public static function get_print_margin_left() {
		return (float) get_option( self::OPTION_PRINT_MARGIN_LEFT, self::DEFAULT_PRINT_MARGIN_LEFT );
	}
}