<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WOI_Admin_Product_Settings {
	const META_ENABLED        = '_woi_enabled';
	const META_REQUIRED_COUNT = '_woi_required_image_count';
	const META_VISIBLE_WIDTH  = '_woi_visible_width';
	const META_VISIBLE_HEIGHT = '_woi_visible_height';
	const META_WRAP_MARGIN    = '_woi_wrap_margin';

	public function init() {
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'render_panel' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save' ) );
	}

	public function add_tab( $tabs ) {
		$tabs['woi_order_images'] = array(
			'label'    => __( 'Order Images', 'woo-order-images' ),
			'target'   => 'woi_order_images_panel',
			'class'    => array( 'show_if_simple', 'show_if_variable' ),
			'priority' => 80,
		);

		return $tabs;
	}

	public function render_panel() {
		global $post;

		$enabled        = get_post_meta( $post->ID, self::META_ENABLED, true );
		$required_count = get_post_meta( $post->ID, self::META_REQUIRED_COUNT, true );
		$required_count = '' === $required_count ? 1 : (int) $required_count;
		$visible_width  = self::get_meta_float( $post->ID, self::META_VISIBLE_WIDTH, 2.0 );
		$visible_height = self::get_meta_float( $post->ID, self::META_VISIBLE_HEIGHT, 2.0 );
		$wrap_margin    = self::get_meta_float( $post->ID, self::META_WRAP_MARGIN, 0.25 );
		?>
		<div id="woi_order_images_panel" class="panel woocommerce_options_panel hidden">
			<div class="options_group">
				<?php
				woocommerce_wp_checkbox(
					array(
						'id'          => self::META_ENABLED,
						'label'       => __( 'Require order images', 'woo-order-images' ),
						'description' => __( 'Enable image requirements for this product.', 'woo-order-images' ),
						'value'       => 'yes' === $enabled ? 'yes' : 'no',
					)
				);

				woocommerce_wp_text_input(
					array(
						'id'                => self::META_REQUIRED_COUNT,
						'label'             => __( 'Required images', 'woo-order-images' ),
						'description'       => __( 'Default number of required images. This will be multiplied by quantity later unless configured otherwise.', 'woo-order-images' ),
						'desc_tip'          => true,
						'type'              => 'number',
						'custom_attributes' => array(
							'min'  => 1,
							'step' => 1,
						),
						'value'             => $required_count,
					)
				);

				woocommerce_wp_text_input(
					array(
						'id'                => self::META_VISIBLE_WIDTH,
						'label'             => __( 'Visible width (inches)', 'woo-order-images' ),
						'description'       => __( 'Finished visible magnet width.', 'woo-order-images' ),
						'desc_tip'          => true,
						'type'              => 'number',
						'custom_attributes' => array(
							'min'  => 0.01,
							'step' => 0.01,
						),
						'value'             => $visible_width,
					)
				);

				woocommerce_wp_text_input(
					array(
						'id'                => self::META_VISIBLE_HEIGHT,
						'label'             => __( 'Visible height (inches)', 'woo-order-images' ),
						'description'       => __( 'Finished visible magnet height.', 'woo-order-images' ),
						'desc_tip'          => true,
						'type'              => 'number',
						'custom_attributes' => array(
							'min'  => 0.01,
							'step' => 0.01,
						),
						'value'             => $visible_height,
					)
				);

				woocommerce_wp_text_input(
					array(
						'id'                => self::META_WRAP_MARGIN,
						'label'             => __( 'Bleed area (wrap margin, inches)', 'woo-order-images' ),
						'description'       => __( 'Extra bleed area outside the visible area on all sides. This becomes the wrapping flaps.', 'woo-order-images' ),
						'desc_tip'          => true,
						'type'              => 'number',
						'custom_attributes' => array(
							'min'  => 0,
							'step' => 0.01,
						),
						'value'             => $wrap_margin,
					)
				);
				?>
			</div>
		</div>
		<?php
	}

	public function save( $post_id ) {
		$enabled = isset( $_POST[ self::META_ENABLED ] ) ? 'yes' : 'no';
		update_post_meta( $post_id, self::META_ENABLED, $enabled );

		$required_count = isset( $_POST[ self::META_REQUIRED_COUNT ] ) ? absint( wp_unslash( $_POST[ self::META_REQUIRED_COUNT ] ) ) : 1;
		$required_count = max( 1, $required_count );
		update_post_meta( $post_id, self::META_REQUIRED_COUNT, $required_count );

		$visible_width = isset( $_POST[ self::META_VISIBLE_WIDTH ] ) ? (float) wc_format_decimal( wp_unslash( $_POST[ self::META_VISIBLE_WIDTH ] ) ) : 2.0;
		$visible_width = max( 0.01, $visible_width );
		update_post_meta( $post_id, self::META_VISIBLE_WIDTH, $visible_width );

		$visible_height = isset( $_POST[ self::META_VISIBLE_HEIGHT ] ) ? (float) wc_format_decimal( wp_unslash( $_POST[ self::META_VISIBLE_HEIGHT ] ) ) : 2.0;
		$visible_height = max( 0.01, $visible_height );
		update_post_meta( $post_id, self::META_VISIBLE_HEIGHT, $visible_height );

		$wrap_margin = isset( $_POST[ self::META_WRAP_MARGIN ] ) ? (float) wc_format_decimal( wp_unslash( $_POST[ self::META_WRAP_MARGIN ] ) ) : 0.25;
		$wrap_margin = max( 0, $wrap_margin );
		update_post_meta( $post_id, self::META_WRAP_MARGIN, $wrap_margin );
	}

	public static function get_product_spec( $product_id ) {
		$visible_width  = self::get_meta_float( $product_id, self::META_VISIBLE_WIDTH, 2.0 );
		$visible_height = self::get_meta_float( $product_id, self::META_VISIBLE_HEIGHT, 2.0 );
		$wrap_margin    = self::get_meta_float( $product_id, self::META_WRAP_MARGIN, 0.25 );
		$full_width     = $visible_width + ( 2 * $wrap_margin );
		$full_height    = $visible_height + ( 2 * $wrap_margin );

		return array(
			'visible_width'          => $visible_width,
			'visible_height'         => $visible_height,
			'wrap_margin'            => $wrap_margin,
			'full_width'             => $full_width,
			'full_height'            => $full_height,
			'visible_aspect_ratio'   => $visible_height > 0 ? ( $visible_width / $visible_height ) : 1,
			'full_aspect_ratio'      => $full_height > 0 ? ( $full_width / $full_height ) : 1,
			'visible_width_percent'  => $full_width > 0 ? ( $visible_width / $full_width ) * 100 : 100,
			'visible_height_percent' => $full_height > 0 ? ( $visible_height / $full_height ) * 100 : 100,
		);
	}

	private static function get_meta_float( $post_id, $meta_key, $default ) {
		$value = get_post_meta( $post_id, $meta_key, true );
		if ( '' === $value || null === $value ) {
			return (float) $default;
		}

		return (float) $value;
	}
}
