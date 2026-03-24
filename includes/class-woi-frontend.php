<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WOI_Frontend {
	public function init() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'render_placeholder' ), 15 );
		add_filter( 'woocommerce_product_single_add_to_cart_text', array( $this, 'filter_add_to_cart_text' ) );
	}

	public function filter_add_to_cart_text( $text ) {
		$cart_key = isset( $_REQUEST['update_cart'] ) ? wc_clean( wp_unslash( $_REQUEST['update_cart'] ) ) : '';
		if ( '' === $cart_key ) {
			return $text;
		}

		return __( 'Update Cart', 'woo-order-images' );
	}

	public function enqueue_assets() {
		if ( ! is_product() ) {
			return;
		}

		wp_enqueue_style(
			'woi-cropper',
			WOI_PLUGIN_URL . 'assets/vendor/cropper/cropper.min.css',
			array(),
			'1.6.2'
		);

		wp_enqueue_script(
			'woi-cropper',
			WOI_PLUGIN_URL . 'assets/vendor/cropper/cropper.min.js',
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

		wp_localize_script(
			'woi-frontend',
			'woiFrontend',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'woi_stage_image_upload' ),
			)
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
		$spec           = WOI_Admin_Product_Settings::get_product_spec( $product->get_id() );
		if ( ! empty( $spec['is_puzzle'] ) ) {
			$required = 1;
		}
		$update_cart_key = isset( $_REQUEST['update_cart'] ) ? wc_clean( wp_unslash( $_REQUEST['update_cart'] ) ) : '';
		$is_update      = '' !== $update_cart_key;
		$existing_images = array();
		$existing_qty    = 0;

		if ( $is_update && function_exists( 'WC' ) && WC()->cart ) {
			$cart = WC()->cart->get_cart();
			if ( isset( $cart[ $update_cart_key ] ) && is_array( $cart[ $update_cart_key ] ) ) {
				$cart_item = $cart[ $update_cart_key ];
				$item_product_id = isset( $cart_item['product_id'] ) ? (int) $cart_item['product_id'] : 0;
				$item_variation_id = isset( $cart_item['variation_id'] ) ? (int) $cart_item['variation_id'] : 0;

				if ( $product->get_id() === $item_product_id || $product->get_id() === $item_variation_id ) {
					$existing_qty = isset( $cart_item['quantity'] ) ? max( 1, (int) $cart_item['quantity'] ) : 0;
					if ( ! empty( $cart_item[ WOI_Order_Images::CART_KEY ] ) && is_array( $cart_item[ WOI_Order_Images::CART_KEY ] ) ) {
						foreach ( $cart_item[ WOI_Order_Images::CART_KEY ] as $image ) {
							if ( is_array( $image ) && ! empty( $image['url'] ) ) {
								$entry = array(
									'url'  => esc_url_raw( $image['url'] ),
									'crop' => isset( $image['crop'] ) && is_array( $image['crop'] ) ? $image['crop'] : array(),
								);
								$existing_images[] = $entry;
							} elseif ( is_string( $image ) && '' !== $image ) {
								$existing_images[] = array(
									'url'  => esc_url_raw( $image ),
									'crop' => array(),
								);
							}
						}
					}
				}
			}
		}
		?>
		<div
			class="woi-product-note"
			data-woi-required-base="<?php echo esc_attr( $required ); ?>"
			data-woi-aspect-ratio="<?php echo esc_attr( $spec['full_aspect_ratio'] ); ?>"
			data-woi-visible-aspect-ratio="<?php echo esc_attr( $spec['visible_aspect_ratio'] ); ?>"
			data-woi-visible-width-percent="<?php echo esc_attr( $spec['visible_width_percent'] ); ?>"
			data-woi-visible-height-percent="<?php echo esc_attr( $spec['visible_height_percent'] ); ?>"
			data-woi-is-puzzle="<?php echo ! empty( $spec['is_puzzle'] ) ? '1' : '0'; ?>"
			data-woi-puzzle-cols="<?php echo esc_attr( (int) $spec['puzzle_cols'] ); ?>"
			data-woi-puzzle-rows="<?php echo esc_attr( (int) $spec['puzzle_rows'] ); ?>"
			data-woi-puzzle-aspect-ratio="<?php echo esc_attr( $spec['puzzle_aspect_ratio'] ); ?>"
			data-woi-existing-images="<?php echo esc_attr( wp_json_encode( array_values( $existing_images ) ) ); ?>"
			data-woi-existing-qty="<?php echo esc_attr( $existing_qty ); ?>"
		>
			<?php if ( $is_update ) : ?>
				<input type="hidden" name="update_cart" value="<?php echo esc_attr( $update_cart_key ); ?>">
			<?php endif; ?>
			<p><strong><?php esc_html_e( 'Image Uploads Required', 'woo-order-images' ); ?></strong></p>
			<p class="woi-requirement-text">
				<?php
				if ( ! empty( $spec['is_puzzle'] ) ) {
					echo esc_html(
						sprintf(
							__( 'Puzzle mode: upload 1 image per quantity. Crop uses a %1$d×%2$d grid overlay for this product and swaps to %3$d×%4$d for landscape images. Use Swap Orientation to override.', 'woo-order-images' ),
							(int) $spec['puzzle_cols'],
							(int) $spec['puzzle_rows'],
							(int) $spec['puzzle_rows'],
							(int) $spec['puzzle_cols']
						)
					);
				} elseif ( $is_update ) {
					echo esc_html(
						sprintf(
							__( 'This product requires %d image(s) per quantity ordered. Upload and crop each image, then click Update Cart.', 'woo-order-images' ),
							$required
						)
					);
				} else {
					echo esc_html(
						sprintf(
							__( 'This product requires %d image(s) per quantity ordered. Upload and crop each image before adding to cart.', 'woo-order-images' ),
							$required
						)
					);
				}
				?>
			</p>
			<p class="woi-spec-text">
				<?php
				if ( ! empty( $spec['is_puzzle'] ) ) {
					echo esc_html(
						sprintf(
							__( 'Tile visible area: %1$s" × %2$s". Puzzle grid: %3$d×%4$d (overall crop ratio %5$s:%6$s). Bleed area: %7$s" on each side.', 'woo-order-images' ),
							$spec['visible_width'],
							$spec['visible_height'],
							(int) $spec['puzzle_cols'],
							(int) $spec['puzzle_rows'],
							$spec['puzzle_cols'] * $spec['visible_width'],
							$spec['puzzle_rows'] * $spec['visible_height'],
							$spec['wrap_margin']
						)
					);
				} else {
					echo esc_html(
						sprintf(
							__( 'Visible area: %1$s" × %2$s". Bleed area: %3$s" on each side. Non-square images auto-match portrait/landscape; use Swap Orientation while cropping to override.', 'woo-order-images' ),
							$spec['visible_width'],
							$spec['visible_height'],
							$spec['wrap_margin']
						)
					);
				}
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
					<div class="woi-puzzle-grid" data-woi-puzzle-grid hidden>
						<span data-woi-puzzle-grid-label></span>
					</div>
				</div>
				<div class="woi-zoom-row">
					<span class="woi-zoom-label"><?php esc_html_e( 'Zoom', 'woo-order-images' ); ?></span>
					<input type="range" class="woi-zoom-slider" data-woi-zoom-slider min="0" max="100" value="50" step="1" aria-label="<?php esc_attr_e( 'Zoom', 'woo-order-images' ); ?>">
					<span class="woi-zoom-value" data-woi-zoom-value>100%</span>
				</div>
				<div class="woi-modal-actions">
					<button type="button" class="button" data-woi-swap-orientation><?php esc_html_e( 'Swap Orientation', 'woo-order-images' ); ?></button>
					<button type="button" class="button" data-woi-rotate><?php esc_html_e( 'Rotate Image', 'woo-order-images' ); ?></button>
					<button type="button" class="button" data-woi-close><?php esc_html_e( 'Cancel', 'woo-order-images' ); ?></button>
					<button type="button" class="button button-primary" data-woi-save-crop><?php esc_html_e( 'Save Crop', 'woo-order-images' ); ?></button>
				</div>
			</div>
		</div>
		<?php
	}
}
