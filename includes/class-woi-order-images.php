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
		add_action( 'woocommerce_add_to_cart', array( $this, 'replace_updated_cart_item' ), 10, 6 );
		add_filter( 'woocommerce_add_to_cart_message_html', array( $this, 'filter_add_to_cart_message_html' ), 10, 2 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'render_cart_item_data' ), 10, 2 );
		add_filter( 'woocommerce_cart_item_name', array( $this, 'render_cart_item_name' ), 10, 3 );
		add_filter( 'woocommerce_hidden_order_itemmeta', array( $this, 'hide_order_item_meta' ), 10, 1 );
		add_action( 'woocommerce_check_cart_items', array( $this, 'validate_cart_items' ) );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_order_item_meta' ), 10, 4 );
	}

	public function filter_add_to_cart_message_html( $message, $products ) {
		$update_cart_key = isset( $_REQUEST['update_cart'] ) ? wc_clean( wp_unslash( $_REQUEST['update_cart'] ) ) : '';
		if ( '' === $update_cart_key ) {
			return $message;
		}

		return esc_html__( 'Item images have been updated in your cart.', 'woo-order-images' );
	}

	public function replace_updated_cart_item( $new_cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
		$update_cart_key = isset( $_REQUEST['update_cart'] ) ? wc_clean( wp_unslash( $_REQUEST['update_cart'] ) ) : '';
		if ( '' === $update_cart_key || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}

		$cart = WC()->cart->get_cart();
		if ( ! isset( $cart[ $update_cart_key ] ) ) {
			return;
		}

		if ( $update_cart_key !== $new_cart_item_key ) {
			WC()->cart->remove_cart_item( $update_cart_key );
		}
	}

	public function validate_required_images( $passed, $product_id, $quantity, $variation_id = 0, $variations = array() ) {
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
		foreach ( $images as $image_value ) {
			if ( $this->is_valid_data_url( $image_value ) ) {
				$file = $this->save_data_url_image( $image_value );
				if ( ! empty( $file['url'] ) ) {
					$saved[] = $file;
				}
				continue;
			}

			if ( $this->is_valid_existing_upload_url( $image_value ) ) {
				$saved[] = array(
					'url'  => esc_url_raw( $image_value ),
					'path' => $this->url_to_upload_path( $image_value ),
				);
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
		$product_id = isset( $cart_item['variation_id'] ) && $cart_item['variation_id'] ? $cart_item['variation_id'] : ( isset( $cart_item['product_id'] ) ? $cart_item['product_id'] : 0 );
		$enabled    = get_post_meta( $product_id, WOI_Admin_Product_Settings::META_ENABLED, true );
		if ( 'yes' !== $enabled && ! empty( $cart_item['product_id'] ) && (int) $cart_item['product_id'] !== (int) $product_id ) {
			$product_id = (int) $cart_item['product_id'];
			$enabled    = get_post_meta( $product_id, WOI_Admin_Product_Settings::META_ENABLED, true );
		}
		if ( 'yes' !== $enabled ) {
			return $item_data;
		}

		$qty              = isset( $cart_item['quantity'] ) ? max( 1, (int) $cart_item['quantity'] ) : 1;
		$required_per_qty = isset( $cart_item[ self::CART_REQUIRED_PER_QTY_KEY ] ) ? (int) $cart_item[ self::CART_REQUIRED_PER_QTY_KEY ] : (int) get_post_meta( $product_id, WOI_Admin_Product_Settings::META_REQUIRED_COUNT, true );
		$required_per_qty = max( 1, $required_per_qty );
		$required_total   = $required_per_qty * $qty;
		$current          = ( ! empty( $cart_item[ self::CART_KEY ] ) && is_array( $cart_item[ self::CART_KEY ] ) ) ? count( $cart_item[ self::CART_KEY ] ) : 0;
		$complete         = $current >= $required_total;

		$label = $complete
			? sprintf( __( '%1$d of %2$d images ✓', 'woo-order-images' ), $current, $required_total )
			: sprintf( __( '%1$d of %2$d images', 'woo-order-images' ), $current, $required_total );

		$item_data[] = array(
			'name'  => '',
			'value' => $label,
		);

		$product_url = get_permalink( $product_id );
		$cart_key    = isset( $cart_item['key'] ) ? (string) $cart_item['key'] : '';
		$edit_url    = ( $product_url && '' !== $cart_key ) ? add_query_arg( 'update_cart', rawurlencode( $cart_key ), $product_url ) : '';
		if ( $edit_url ) {
			$edit_label = $complete ? __( 'Edit Images', 'woo-order-images' ) : __( 'Edit Images (Required)', 'woo-order-images' );
			$edit_value = $complete
				? '<a href="' . esc_url( $edit_url ) . '">' . esc_html( $edit_label ) . '</a>'
				: '<a href="' . esc_url( $edit_url ) . '"><strong>' . esc_html( $edit_label ) . '</strong></a>';
			$item_data[] = array(
				'name'  => '',
				'value' => $edit_value,
			);
		}

		return $item_data;
	}

	public function render_cart_item_name( $product_name, $cart_item, $cart_item_key ) {
		$product_id = isset( $cart_item['variation_id'] ) && $cart_item['variation_id'] ? $cart_item['variation_id'] : ( isset( $cart_item['product_id'] ) ? $cart_item['product_id'] : 0 );
		$enabled    = get_post_meta( $product_id, WOI_Admin_Product_Settings::META_ENABLED, true );
		if ( 'yes' !== $enabled && ! empty( $cart_item['product_id'] ) && (int) $cart_item['product_id'] !== (int) $product_id ) {
			$product_id = (int) $cart_item['product_id'];
			$enabled    = get_post_meta( $product_id, WOI_Admin_Product_Settings::META_ENABLED, true );
		}
		if ( 'yes' !== $enabled ) {
			return $product_name;
		}

		$qty              = isset( $cart_item['quantity'] ) ? max( 1, (int) $cart_item['quantity'] ) : 1;
		$required_per_qty = isset( $cart_item[ self::CART_REQUIRED_PER_QTY_KEY ] ) ? (int) $cart_item[ self::CART_REQUIRED_PER_QTY_KEY ] : (int) get_post_meta( $product_id, WOI_Admin_Product_Settings::META_REQUIRED_COUNT, true );
		$required_per_qty = max( 1, $required_per_qty );
		$required_total   = $required_per_qty * $qty;
		$images           = ( ! empty( $cart_item[ self::CART_KEY ] ) && is_array( $cart_item[ self::CART_KEY ] ) ) ? $cart_item[ self::CART_KEY ] : array();
		$current          = count( $images );
		$complete         = $current >= $required_total;

		$badge_style = $complete
			? 'display:inline-block;margin-left:6px;padding:2px 8px;border-radius:3px;font-size:0.8em;font-weight:600;background:#d4edda;color:#155724;'
			: 'display:inline-block;margin-left:6px;padding:2px 8px;border-radius:3px;font-size:0.8em;font-weight:600;background:#fff3cd;color:#856404;';

		$badge = '<span style="' . esc_attr( $badge_style ) . '">';
		$badge .= $complete
			? esc_html( sprintf( __( '%1$d/%2$d images ✓', 'woo-order-images' ), $current, $required_total ) )
			: esc_html( sprintf( __( '%1$d/%2$d images needed', 'woo-order-images' ), $current, $required_total ) );
		$badge .= '</span>';

		// Build the edit-images URL: product page with ?update_cart=KEY
		$parent_product_id = isset( $cart_item['product_id'] ) ? (int) $cart_item['product_id'] : $product_id;
		$product_url       = get_permalink( $parent_product_id );
		$edit_url          = $product_url ? add_query_arg( 'update_cart', rawurlencode( $cart_item_key ), $product_url ) : '';

		$suffix = $product_name . $badge;

		if ( $edit_url ) {
			$link_style = $complete
				? 'display:inline-block;margin-left:8px;font-size:0.8em;'
				: 'display:inline-block;margin-left:8px;font-size:0.8em;font-weight:600;';
			$link_text = $complete ? __( 'Edit Images', 'woo-order-images' ) : __( 'Edit Images (Required)', 'woo-order-images' );
			$suffix   .= ' <a href="' . esc_url( $edit_url ) . '" style="' . esc_attr( $link_style ) . '">' . esc_html( $link_text ) . '</a>';
		}

		if ( ! empty( $images ) ) {
			$visible_width  = isset( $cart_item[ self::CART_VISIBLE_WIDTH_KEY ] ) ? (float) $cart_item[ self::CART_VISIBLE_WIDTH_KEY ] : 1;
			$visible_height = isset( $cart_item[ self::CART_VISIBLE_HEIGHT_KEY ] ) ? (float) $cart_item[ self::CART_VISIBLE_HEIGHT_KEY ] : 1;
			$suffix        .= $this->get_thumbnail_markup( $images, $visible_width, $visible_height, 56 );
		}

		return $suffix;
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

		$missing_items = array();

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( empty( $cart_item['data'] ) || ! $cart_item['data'] instanceof WC_Product ) {
				continue;
			}

			$product = $cart_item['data'];
			$product_id = $product->get_id();
			$enabled    = get_post_meta( $product_id, WOI_Admin_Product_Settings::META_ENABLED, true );
			if ( 'yes' !== $enabled && ! empty( $cart_item['product_id'] ) && (int) $cart_item['product_id'] !== (int) $product_id ) {
				$product_id = (int) $cart_item['product_id'];
				$enabled    = get_post_meta( $product_id, WOI_Admin_Product_Settings::META_ENABLED, true );
			}
			if ( 'yes' !== $enabled ) {
				continue;
			}

			$required_per_qty = isset( $cart_item[ self::CART_REQUIRED_PER_QTY_KEY ] ) ? (int) $cart_item[ self::CART_REQUIRED_PER_QTY_KEY ] : (int) get_post_meta( $product_id, WOI_Admin_Product_Settings::META_REQUIRED_COUNT, true );
			$required_per_qty = max( 1, $required_per_qty );
			$qty              = isset( $cart_item['quantity'] ) ? max( 1, (int) $cart_item['quantity'] ) : 1;
			$required_total   = $required_per_qty * $qty;
			$current_images   = isset( $cart_item[ self::CART_KEY ] ) && is_array( $cart_item[ self::CART_KEY ] ) ? count( $cart_item[ self::CART_KEY ] ) : 0;

			if ( $current_images !== $required_total ) {
				$missing_items[] = array(
					'name'     => $product->get_name(),
					'required' => $required_total,
					'current'  => $current_images,
					'qty'      => $qty,
				);
			}
		}

		if ( 1 === count( $missing_items ) ) {
			$item = $missing_items[0];
			wc_add_notice(
				sprintf(
					__( 'Product "%1$s" requires %2$d image(s) for quantity %3$d, but %4$d are attached. Please edit this item in your cart before checkout.', 'woo-order-images' ),
					$item['name'],
					$item['required'],
					$item['qty'],
					$item['current']
				),
				'error'
			);
		} elseif ( count( $missing_items ) > 1 ) {
			wc_add_notice(
				__( 'Some of your items are missing required images. Please edit each flagged item in your cart before checkout.', 'woo-order-images' ),
				'error'
			);
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

	private function is_valid_existing_upload_url( $value ) {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return false;
		}

		$uploads = wp_upload_dir();
		if ( empty( $uploads['baseurl'] ) ) {
			return false;
		}

		$baseurl = trailingslashit( $uploads['baseurl'] ) . 'woo-order-images/';
		if ( 0 !== strpos( $value, $baseurl ) ) {
			return false;
		}

		$path = $this->url_to_upload_path( $value );
		return (bool) ( $path && file_exists( $path ) );
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
