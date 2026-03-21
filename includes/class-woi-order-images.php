<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WOI_Order_Images {
	const CART_KEY = 'woi_images';
	const CART_REQUIRED_PER_QTY_KEY = 'woi_required_per_qty';
	const CART_VISIBLE_WIDTH_KEY = 'woi_visible_width';
	const CART_VISIBLE_HEIGHT_KEY = 'woi_visible_height';
	const CART_WRAP_MARGIN_KEY = 'woi_wrap_margin';
	const ORDER_META_URLS = '_woi_image_urls';
	const ORDER_META_COUNT = '_woi_image_count';
	const ORDER_META_VISIBLE_WIDTH = '_woi_visible_width';
	const ORDER_META_VISIBLE_HEIGHT = '_woi_visible_height';
	const ORDER_META_WRAP_MARGIN = '_woi_wrap_margin';

	public function init() {
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_required_images' ), 10, 5 );
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 4 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'render_cart_item_data' ), 10, 2 );
		add_filter( 'woocommerce_cart_item_name', array( $this, 'render_cart_item_name' ), 10, 3 );
		add_filter( 'woocommerce_hidden_order_itemmeta', array( $this, 'hide_order_item_meta' ), 10, 1 );
		add_action( 'woocommerce_check_cart_items', array( $this, 'validate_cart_items' ) );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_order_item_meta' ), 10, 4 );
	}

	public function validate_required_images( $passed, $product_id, $quantity, $variation_id = 0, $variations = array() ) {
		$product_id_for_meta = $variation_id ? $variation_id : $product_id;
		$enabled             = get_post_meta( $product_id_for_meta, WOI_Admin_Product_Settings::META_ENABLED, true );

		if ( 'yes' !== $enabled ) {
			return $passed;
		}

		$base_required = (int) get_post_meta( $product_id_for_meta, WOI_Admin_Product_Settings::META_REQUIRED_COUNT, true );
		$base_required = max( 1, $base_required );
		$qty           = max( 1, (int) $quantity );
		$required      = $base_required * $qty;

		$images = $this->get_posted_images();
		if ( count( $images ) !== $required ) {
			wc_add_notice(
				sprintf(
					__( 'Please provide exactly %d image(s) for this product before adding it to cart.', 'woo-order-images' ),
					$required
				),
				'error'
			);
			return false;
		}

		foreach ( $images as $index => $image_data ) {
			if ( ! $this->is_valid_data_url( $image_data ) ) {
				wc_add_notice(
					sprintf(
						__( 'Image %d is missing or invalid. Please crop and save each image before adding to cart.', 'woo-order-images' ),
						$index + 1
					),
					'error'
				);
				return false;
			}
		}

		return $passed;
	}

	public function add_cart_item_data( $cart_item_data, $product_id, $variation_id, $quantity ) {
		$product_id_for_meta = $variation_id ? $variation_id : $product_id;
		$enabled             = get_post_meta( $product_id_for_meta, WOI_Admin_Product_Settings::META_ENABLED, true );

		if ( 'yes' !== $enabled ) {
			return $cart_item_data;
		}

		$images = $this->get_posted_images();
		if ( empty( $images ) ) {
			return $cart_item_data;
		}

		$saved = array();
		foreach ( $images as $data_url ) {
			$file = $this->save_data_url_image( $data_url );
			if ( ! empty( $file['url'] ) && ! empty( $file['path'] ) ) {
				$saved[] = $file;
			}
		}

		if ( ! empty( $saved ) ) {
			$spec = WOI_Admin_Product_Settings::get_product_spec( $product_id_for_meta );
			$cart_item_data[ self::CART_KEY ] = $saved;
			$cart_item_data[ self::CART_REQUIRED_PER_QTY_KEY ] = max( 1, (int) get_post_meta( $product_id_for_meta, WOI_Admin_Product_Settings::META_REQUIRED_COUNT, true ) );
			$cart_item_data[ self::CART_VISIBLE_WIDTH_KEY ] = $spec['visible_width'];
			$cart_item_data[ self::CART_VISIBLE_HEIGHT_KEY ] = $spec['visible_height'];
			$cart_item_data[ self::CART_WRAP_MARGIN_KEY ] = $spec['wrap_margin'];
			$cart_item_data['woi_unique']     = md5( wp_json_encode( $saved ) . microtime( true ) );
		}

		return $cart_item_data;
	}

	public function render_cart_item_data( $item_data, $cart_item ) {
		if ( empty( $cart_item[ self::CART_KEY ] ) || ! is_array( $cart_item[ self::CART_KEY ] ) ) {
			return $item_data;
		}

		$item_data[] = array(
			'name'  => __( 'Uploaded images', 'woo-order-images' ),
			'value' => (string) count( $cart_item[ self::CART_KEY ] ),
		);

		return $item_data;
	}

	public function render_cart_item_name( $product_name, $cart_item, $cart_item_key ) {
		if ( empty( $cart_item[ self::CART_KEY ] ) || ! is_array( $cart_item[ self::CART_KEY ] ) ) {
			return $product_name;
		}

		$visible_width  = isset( $cart_item[ self::CART_VISIBLE_WIDTH_KEY ] ) ? (float) $cart_item[ self::CART_VISIBLE_WIDTH_KEY ] : 1;
		$visible_height = isset( $cart_item[ self::CART_VISIBLE_HEIGHT_KEY ] ) ? (float) $cart_item[ self::CART_VISIBLE_HEIGHT_KEY ] : 1;

		return $product_name . $this->get_thumbnail_markup( $cart_item[ self::CART_KEY ], $visible_width, $visible_height, 56 );
	}

	public function hide_order_item_meta( $hidden_keys ) {
		$hidden_keys[] = 'Order image URLs';
		$hidden_keys[] = 'Order image count';
		$hidden_keys[] = __( 'Order image URLs', 'woo-order-images' );
		$hidden_keys[] = __( 'Order image count', 'woo-order-images' );

		return array_values( array_unique( $hidden_keys ) );
	}

	public function validate_cart_items() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( empty( $cart_item['data'] ) || ! $cart_item['data'] instanceof WC_Product ) {
				continue;
			}

			$product = $cart_item['data'];
			$enabled = get_post_meta( $product->get_id(), WOI_Admin_Product_Settings::META_ENABLED, true );
			if ( 'yes' !== $enabled ) {
				continue;
			}

			$required_per_qty = isset( $cart_item[ self::CART_REQUIRED_PER_QTY_KEY ] ) ? (int) $cart_item[ self::CART_REQUIRED_PER_QTY_KEY ] : (int) get_post_meta( $product->get_id(), WOI_Admin_Product_Settings::META_REQUIRED_COUNT, true );
			$required_per_qty = max( 1, $required_per_qty );
			$qty              = isset( $cart_item['quantity'] ) ? max( 1, (int) $cart_item['quantity'] ) : 1;
			$required_total   = $required_per_qty * $qty;
			$current_images   = isset( $cart_item[ self::CART_KEY ] ) && is_array( $cart_item[ self::CART_KEY ] ) ? count( $cart_item[ self::CART_KEY ] ) : 0;

			if ( $current_images !== $required_total ) {
				wc_add_notice(
					sprintf(
						__( 'Product "%1$s" requires %2$d image(s) for quantity %3$d, but %4$d are attached. Please remove this item and add it again with the correct images.', 'woo-order-images' ),
						$product->get_name(),
						$required_total,
						$qty,
						$current_images
					),
					'error'
				);
			}
		}
	}

	public function add_order_item_meta( $item, $cart_item_key, $values, $order ) {
		if ( empty( $values[ self::CART_KEY ] ) || ! is_array( $values[ self::CART_KEY ] ) ) {
			return;
		}

		$urls = array();
		foreach ( $values[ self::CART_KEY ] as $image ) {
			if ( ! empty( $image['url'] ) ) {
				$urls[] = esc_url_raw( $image['url'] );
			}
		}

		if ( ! empty( $urls ) ) {
			$visible_width  = isset( $values[ self::CART_VISIBLE_WIDTH_KEY ] ) ? (float) $values[ self::CART_VISIBLE_WIDTH_KEY ] : 1;
			$visible_height = isset( $values[ self::CART_VISIBLE_HEIGHT_KEY ] ) ? (float) $values[ self::CART_VISIBLE_HEIGHT_KEY ] : 1;
			$wrap_margin    = isset( $values[ self::CART_WRAP_MARGIN_KEY ] ) ? (float) $values[ self::CART_WRAP_MARGIN_KEY ] : 0.25;

			$item->add_meta_data( self::ORDER_META_URLS, $urls );
			$item->add_meta_data( self::ORDER_META_COUNT, count( $urls ) );
			$item->add_meta_data( self::ORDER_META_VISIBLE_WIDTH, $visible_width );
			$item->add_meta_data( self::ORDER_META_VISIBLE_HEIGHT, $visible_height );
			$item->add_meta_data( self::ORDER_META_WRAP_MARGIN, $wrap_margin );
		}
	}

	public function get_thumbnail_markup( $images, $visible_width, $visible_height, $size = 64 ) {
		$urls = array();
		foreach ( (array) $images as $image ) {
			if ( is_array( $image ) && ! empty( $image['url'] ) ) {
				$urls[] = $image['url'];
			} elseif ( is_string( $image ) && '' !== $image ) {
				$urls[] = $image;
			}
		}

		if ( empty( $urls ) ) {
			return '';
		}

		$html  = '<div class="woi-inline-thumbnails" style="display:flex;flex-wrap:wrap;gap:6px;margin-top:8px;">';

		foreach ( $urls as $index => $url ) {
			$ratio = $this->get_oriented_ratio_from_url( $url, $visible_width, $visible_height );
			$label = sprintf( __( 'Image %d', 'woo-order-images' ), $index + 1 );
			$html .= '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer" style="display:block;">';
			$html .= '<span style="display:block;width:' . (int) $size . 'px;aspect-ratio:' . esc_attr( $ratio ) . ';overflow:hidden;border:1px solid #ccd0d4;border-radius:4px;background:#f6f7f7;">';
			$html .= '<img src="' . esc_url( $url ) . '" alt="' . esc_attr( $label ) . '" style="display:block;width:100%;height:100%;object-fit:cover;" />';
			$html .= '</span>';
			$html .= '</a>';
		}

		$html .= '</div>';

		return $html;
	}

	private function get_oriented_ratio_from_url( $url, $visible_width, $visible_height ) {
		$default_ratio = $visible_height > 0 ? $visible_width / $visible_height : 1;

		if ( abs( $default_ratio - 1 ) < 0.0001 ) {
			return 1;
		}

		$local_path = $this->url_to_upload_path( $url );
		if ( ! $local_path || ! file_exists( $local_path ) ) {
			return $default_ratio;
		}

		$size = @getimagesize( $local_path );
		if ( ! is_array( $size ) || empty( $size[0] ) || empty( $size[1] ) ) {
			return $default_ratio;
		}

		$image_ratio      = (float) $size[0] / (float) $size[1];
		$image_landscape  = $image_ratio >= 1;
		$default_landscape = $default_ratio >= 1;

		if ( $image_landscape === $default_landscape ) {
			return $default_ratio;
		}

		return 1 / $default_ratio;
	}

	private function url_to_upload_path( $url ) {
		$uploads = wp_upload_dir();
		if ( empty( $uploads['baseurl'] ) || empty( $uploads['basedir'] ) ) {
			return '';
		}

		$baseurl = trailingslashit( $uploads['baseurl'] );
		$basedir = trailingslashit( $uploads['basedir'] );

		if ( 0 !== strpos( $url, $baseurl ) ) {
			return '';
		}

		$relative = ltrim( substr( $url, strlen( $baseurl ) ), '/' );

		return $basedir . str_replace( array( '../', '..\\' ), '', $relative );
	}

	private function get_posted_images() {
		$images = isset( $_POST['woi_images'] ) ? (array) wp_unslash( $_POST['woi_images'] ) : array();
		$images = array_map( 'trim', $images );
		$images = array_filter( $images, static function ( $value ) {
			return '' !== $value;
		} );

		return array_values( $images );
	}

	private function is_valid_data_url( $value ) {
		if ( ! is_string( $value ) ) {
			return false;
		}

		return (bool) preg_match( '#^data:image/(png|jpeg|jpg|webp);base64,#i', $value );
	}

	private function save_data_url_image( $data_url ) {
		$matches = array();
		if ( ! preg_match( '#^data:image/(png|jpeg|jpg|webp);base64,(.+)$#is', $data_url, $matches ) ) {
			return array();
		}

		$raw = base64_decode( $matches[2], true );
		if ( false === $raw || empty( $raw ) ) {
			return array();
		}

		$max_size = 12 * 1024 * 1024;
		if ( strlen( $raw ) > $max_size ) {
			return array();
		}

		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['error'] ) ) {
			return array();
		}

		$subdir = trailingslashit( $upload_dir['basedir'] ) . 'woo-order-images/' . gmdate( 'Y/m' ) . '/';
		if ( ! wp_mkdir_p( $subdir ) ) {
			return array();
		}

		$ext_map = array(
			'png'  => 'png',
			'jpeg' => 'jpg',
			'jpg'  => 'jpg',
			'webp' => 'webp',
		);
		$ext     = strtolower( $matches[1] );
		$ext     = isset( $ext_map[ $ext ] ) ? $ext_map[ $ext ] : 'jpg';

		$filename = wp_unique_filename( $subdir, 'woi-' . wp_generate_password( 12, false, false ) . '.' . $ext );
		$path     = $subdir . $filename;

		if ( false === file_put_contents( $path, $raw ) ) {
			return array();
		}

		$url = trailingslashit( $upload_dir['baseurl'] ) . 'woo-order-images/' . gmdate( 'Y/m' ) . '/' . $filename;

		return array(
			'path' => $path,
			'url'  => $url,
		);
	}
}
