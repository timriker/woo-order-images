<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WOI_Frontend {
	public function init() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'render_placeholder' ), 15 );
	}

	public function enqueue_assets() {
		if ( ! is_product() ) {
			return;
		}

		wp_enqueue_style(
			'woi-cropper',
			'https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css',
			array(),
			'1.6.2'
		);

		wp_enqueue_script(
			'woi-cropper',
			'https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js',
			array(),
			'1.6.2',
			true
		);

		wp_enqueue_style(
			'woi-frontend',
			WOI_PLUGIN_URL . 'assets/css/frontend.css',
			array( 'woi-cropper' ),
			WOI_VERSION
		);

		wp_enqueue_script(
			'woi-frontend',
			WOI_PLUGIN_URL . 'assets/js/frontend.js',
			array( 'woi-cropper' ),
			WOI_VERSION,
			true
		);
	}

	public function render_placeholder() {
		global $product;

		if ( ! $product instanceof WC_Product ) {
			return;
		}

		$enabled = get_post_meta( $product->get_id(), WOI_Admin_Product_Settings::META_ENABLED, true );
		if ( 'yes' !== $enabled ) {
			return;
		}

		$required = (int) get_post_meta( $product->get_id(), WOI_Admin_Product_Settings::META_REQUIRED_COUNT, true );
		$required = max( 1, $required );
		$spec     = WOI_Admin_Product_Settings::get_product_spec( $product->get_id() );
		$left_pct = ( 100 - $spec['visible_width_percent'] ) / 2;
		$top_pct  = ( 100 - $spec['visible_height_percent'] ) / 2;
		?>
		<div
			class="woi-product-note"
			data-woi-required-base="<?php echo esc_attr( $required ); ?>"
			data-woi-aspect-ratio="<?php echo esc_attr( $spec['full_aspect_ratio'] ); ?>"
			data-woi-visible-aspect-ratio="<?php echo esc_attr( $spec['visible_aspect_ratio'] ); ?>"
			data-woi-visible-width-percent="<?php echo esc_attr( $spec['visible_width_percent'] ); ?>"
			data-woi-visible-height-percent="<?php echo esc_attr( $spec['visible_height_percent'] ); ?>"
		>
			<p><strong><?php esc_html_e( 'Image Uploads Required', 'woo-order-images' ); ?></strong></p>
			<p class="woi-requirement-text">
				<?php
				echo esc_html(
					sprintf(
						__( 'This product requires %d image(s) per quantity ordered. Upload and crop each image before adding to cart.', 'woo-order-images' ),
						$required
					)
				);
				?>
			</p>
			<p class="woi-spec-text">
				<?php
				echo esc_html(
					sprintf(
						__( 'Visible area: %1$s" × %2$s". Wrap area: %3$s" on each side. Non-square images auto-match portrait/landscape; use Swap Orientation while cropping to override.', 'woo-order-images' ),
						$spec['visible_width'],
						$spec['visible_height'],
						$spec['wrap_margin']
					)
				);
				?>
			</p>
			<div class="woi-file-picker-row">
				<label class="button" for="woi-file-picker-<?php echo esc_attr( $product->get_id() ); ?>"><?php esc_html_e( 'Choose Files', 'woo-order-images' ); ?></label>
				<input id="woi-file-picker-<?php echo esc_attr( $product->get_id() ); ?>" type="file" accept="image/*" class="woi-multi-file-input" data-woi-multi-file multiple>
			</div>
			<div class="woi-upload-slots" data-woi-upload-slots></div>
			<template class="woi-upload-slot-template">
				<div class="woi-upload-slot" data-woi-slot>
					<label class="woi-upload-title"></label>
					<div class="woi-preview-wrap">
						<img class="woi-preview" data-woi-preview alt="">
					</div>
					<div class="woi-slot-actions">
						<button type="button" class="button" data-woi-replace><?php esc_html_e( 'Replace', 'woo-order-images' ); ?></button>
						<button type="button" class="button" data-woi-edit disabled><?php esc_html_e( 'Crop / Edit', 'woo-order-images' ); ?></button>
					</div>
					<input type="file" accept="image/*" class="woi-replace-input" data-woi-file>
					<input type="hidden" name="woi_images[]" value="" data-woi-hidden>
				</div>
			</template>
		</div>

		<div class="woi-modal" data-woi-modal hidden>
			<div class="woi-modal-backdrop" data-woi-close></div>
			<div class="woi-modal-content" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Crop image', 'woo-order-images' ); ?>">
				<div class="woi-cropper-wrap">
					<img data-woi-cropper-image alt="">
					<div
						class="woi-visible-area-guide"
						style="left:<?php echo esc_attr( $left_pct ); ?>%;top:<?php echo esc_attr( $top_pct ); ?>%;width:<?php echo esc_attr( $spec['visible_width_percent'] ); ?>%;height:<?php echo esc_attr( $spec['visible_height_percent'] ); ?>%;"
					>
						<span><?php esc_html_e( 'Visible area', 'woo-order-images' ); ?></span>
					</div>
					<div class="woi-print-area-guide"><span><?php esc_html_e( 'Print area with wrap', 'woo-order-images' ); ?></span></div>
				</div>
				<div class="woi-modal-actions">
					<button type="button" class="button" data-woi-swap-orientation><?php esc_html_e( 'Swap Orientation', 'woo-order-images' ); ?></button>
					<button type="button" class="button" data-woi-close><?php esc_html_e( 'Cancel', 'woo-order-images' ); ?></button>
					<button type="button" class="button button-primary" data-woi-save-crop><?php esc_html_e( 'Save Crop', 'woo-order-images' ); ?></button>
				</div>
			</div>
		</div>
		<?php
	}
}
