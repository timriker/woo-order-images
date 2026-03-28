<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WOI_Order_Images {
	const LEGACY_ORDER_MIGRATION_OPTION = 'woi_legacy_order_image_meta_migrated_v3';
	const LEGACY_ORDER_MIGRATION_LOCK_OPTION = 'woi_legacy_order_image_meta_migrating_v3';
	const LEGACY_ORDER_MIGRATION_NOTICE_OPTION = 'woi_legacy_order_image_meta_migration_notice_v3';
	const CART_KEY = 'woi_images';
	const CART_REQUIRED_PER_QTY_KEY = 'woi_required_per_qty';
	const CART_VISIBLE_WIDTH_KEY = 'woi_visible_width';
	const CART_VISIBLE_HEIGHT_KEY = 'woi_visible_height';
	const CART_IS_PUZZLE_KEY = 'woi_is_puzzle';
	const CART_PUZZLE_COLS_KEY = 'woi_puzzle_cols';
	const CART_PUZZLE_ROWS_KEY = 'woi_puzzle_rows';
	const ORDER_META_IMAGES = '_woi_images';
	const ORDER_META_COUNT = '_woi_image_count';
	const ORDER_META_VISIBLE_WIDTH = '_woi_visible_width';
	const ORDER_META_VISIBLE_HEIGHT = '_woi_visible_height';
	const ORDER_META_WRAP_MARGIN = '_woi_wrap_margin';
	const ORDER_META_IS_PUZZLE = '_woi_is_puzzle';
	const ORDER_META_PUZZLE_COLS = '_woi_puzzle_cols';
	const ORDER_META_PUZZLE_ROWS = '_woi_puzzle_rows';

	public function init() {
		add_action( 'admin_init', array( $this, 'maybe_run_legacy_order_image_meta_migration' ) );
		add_action( 'admin_notices', array( $this, 'maybe_show_legacy_order_image_meta_migration_notice' ) );
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_required_images' ), 10, 5 );
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 4 );
		add_filter( 'woocommerce_add_to_cart_redirect', array( $this, 'redirect_after_add_to_cart' ), 10, 2 );
		add_action( 'woocommerce_add_to_cart', array( $this, 'replace_updated_cart_item' ), 10, 6 );
		add_action( 'wp_ajax_woi_stage_image_upload', array( $this, 'ajax_stage_image_upload' ) );
		add_action( 'wp_ajax_nopriv_woi_stage_image_upload', array( $this, 'ajax_stage_image_upload' ) );
		add_action( 'admin_post_woi_thumb_image', array( $this, 'render_thumbnail_image' ) );
		add_action( 'admin_post_nopriv_woi_thumb_image', array( $this, 'render_thumbnail_image' ) );
		add_filter( 'woocommerce_add_to_cart_message_html', array( $this, 'filter_add_to_cart_message_html' ), 10, 2 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'render_cart_item_data' ), 10, 2 );
		add_filter( 'woocommerce_cart_item_name', array( $this, 'render_cart_item_name' ), 10, 3 );
		add_action( 'woocommerce_after_cart_item_name', array( $this, 'render_cart_item_thumbnails' ), 12, 2 );
		add_filter( 'woocommerce_hidden_order_itemmeta', array( $this, 'hide_order_item_meta' ), 10, 1 );
		add_action( 'woocommerce_check_cart_items', array( $this, 'validate_cart_items' ) );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_order_item_meta' ), 10, 4 );
	}

	public function maybe_run_legacy_order_image_meta_migration() {
		if ( 'yes' === get_option( self::LEGACY_ORDER_MIGRATION_OPTION, 'no' ) ) {
			return;
		}

		if ( '1' === get_option( self::LEGACY_ORDER_MIGRATION_LOCK_OPTION, '0' ) ) {
			return;
		}

		update_option( self::LEGACY_ORDER_MIGRATION_LOCK_OPTION, '1', false );

		try {
			$stats = $this->run_legacy_order_image_meta_migration();
			update_option( self::LEGACY_ORDER_MIGRATION_NOTICE_OPTION, $stats, false );
			update_option( self::LEGACY_ORDER_MIGRATION_OPTION, 'yes', false );
		} finally {
			delete_option( self::LEGACY_ORDER_MIGRATION_LOCK_OPTION );
		}
	}

	public function maybe_show_legacy_order_image_meta_migration_notice() {
		if ( ! is_admin() || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$stats = get_option( self::LEGACY_ORDER_MIGRATION_NOTICE_OPTION, null );
		if ( ! is_array( $stats ) ) {
			return;
		}

		delete_option( self::LEGACY_ORDER_MIGRATION_NOTICE_OPTION );

		$items_examined      = isset( $stats['items_examined'] ) ? (int) $stats['items_examined'] : 0;
		$items_migrated      = isset( $stats['items_migrated'] ) ? (int) $stats['items_migrated'] : 0;
		$items_skipped       = isset( $stats['items_skipped'] ) ? (int) $stats['items_skipped'] : 0;
		$images_normalized   = isset( $stats['images_normalized'] ) ? (int) $stats['images_normalized'] : 0;
		$data_urls_converted = isset( $stats['data_urls_converted'] ) ? (int) $stats['data_urls_converted'] : 0;
		$uploads_relocated  = isset( $stats['uploads_relocated'] ) ? (int) $stats['uploads_relocated'] : 0;

		echo '<div class="notice notice-success is-dismissible"><p>';
		echo esc_html(
			sprintf(
				__( 'Woo Order Images migration complete. Examined %1$d order items, migrated %2$d, skipped %3$d, normalized %4$d images, converted %5$d data-URL images, and relocated %6$d uploads into canonical WOI storage.', 'woo-order-images' ),
				$items_examined,
				$items_migrated,
				$items_skipped,
				$images_normalized,
				$data_urls_converted,
				$uploads_relocated
			)
		);
		echo '</p></div>';
	}

	private function run_legacy_order_image_meta_migration() {
		global $wpdb;

		$stats = array(
			'items_examined'      => 0,
			'items_migrated'      => 0,
			'items_skipped'       => 0,
			'images_normalized'   => 0,
			'data_urls_converted' => 0,
			'uploads_relocated'   => 0,
		);

		$table = $wpdb->prefix . 'woocommerce_order_itemmeta';
		$rows  = $wpdb->get_results(
			"SELECT meta_id, order_item_id, meta_key, meta_value
			FROM {$table}
			WHERE meta_key IN ('_woi_images', '_woi_image_urls')
			ORDER BY order_item_id ASC, meta_id ASC"
		);

		if ( empty( $rows ) ) {
			return $stats;
		}

		$grouped = array();
		foreach ( $rows as $row ) {
			$item_id = isset( $row->order_item_id ) ? (int) $row->order_item_id : 0;
			if ( $item_id <= 0 ) {
				continue;
			}

			if ( ! isset( $grouped[ $item_id ] ) ) {
				$grouped[ $item_id ] = array();
			}

			$grouped[ $item_id ][] = $row;
		}

		foreach ( $grouped as $item_id => $item_rows ) {
			$stats['items_examined']++;
			$images_meta      = null;
			$legacy_urls_meta = null;

			foreach ( $item_rows as $meta_row ) {
				if ( '_woi_images' === $meta_row->meta_key && null === $images_meta ) {
					$images_meta = $meta_row;
				} elseif ( '_woi_image_urls' === $meta_row->meta_key && null === $legacy_urls_meta ) {
					$legacy_urls_meta = $meta_row;
				}
			}

			$current_images = ( $images_meta && is_string( $images_meta->meta_value ) ) ? maybe_unserialize( $images_meta->meta_value ) : array();
			$legacy_urls    = ( $legacy_urls_meta && is_string( $legacy_urls_meta->meta_value ) ) ? maybe_unserialize( $legacy_urls_meta->meta_value ) : array();

			$converted_data_urls = 0;
			$uploads_relocated   = 0;
			$normalized          = $this->normalize_migrated_image_entries(
				is_array( $current_images ) ? $current_images : array(),
				is_array( $legacy_urls ) ? $legacy_urls : array(),
				$converted_data_urls,
				$uploads_relocated
			);
			if ( empty( $normalized ) ) {
				$stats['items_skipped']++;
				continue;
			}

			$stats['items_migrated']++;
			$stats['images_normalized'] += count( $normalized );
			$stats['data_urls_converted'] += $converted_data_urls;
			$stats['uploads_relocated'] += $uploads_relocated;

			$serialized = maybe_serialize( array_values( $normalized ) );

			if ( $images_meta ) {
				if ( (string) $images_meta->meta_value !== $serialized ) {
					$wpdb->update(
						$table,
						array( 'meta_value' => $serialized ),
						array( 'meta_id' => (int) $images_meta->meta_id ),
						array( '%s' ),
						array( '%d' )
					);
				}
			} else {
				$wpdb->insert(
					$table,
					array(
						'order_item_id' => (int) $item_id,
						'meta_key'      => self::ORDER_META_IMAGES,
						'meta_value'    => $serialized,
					),
					array( '%d', '%s', '%s' )
				);
			}

			$count_meta = maybe_serialize( count( $normalized ) );
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$table} WHERE order_item_id = %d AND meta_key = %s",
					(int) $item_id,
					self::ORDER_META_COUNT
				)
			);
			$wpdb->insert(
				$table,
				array(
					'order_item_id' => (int) $item_id,
					'meta_key'      => self::ORDER_META_COUNT,
					'meta_value'    => $count_meta,
				),
				array( '%d', '%s', '%s' )
			);

			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$table} WHERE order_item_id = %d AND meta_key = %s",
					(int) $item_id,
					'_woi_image_urls'
				)
			);
		}

		return $stats;
	}

	private function normalize_migrated_image_entries( $images, $legacy_urls, &$converted_data_urls = 0, &$uploads_relocated = 0 ) {
		$normalized = array();

		foreach ( (array) $images as $entry ) {
			if ( is_array( $entry ) ) {
				$source = '';
				if ( ! empty( $entry['url'] ) && is_string( $entry['url'] ) ) {
					$source = trim( $entry['url'] );
				} elseif ( ! empty( $entry['source'] ) && is_string( $entry['source'] ) ) {
					$source = trim( $entry['source'] );
				}

				$normalized_entry = $this->normalize_migrated_single_image_entry(
					$source,
					isset( $entry['crop'] ) && is_array( $entry['crop'] ) ? $entry['crop'] : array(),
					isset( $entry['puzzle_cols'] ) ? (int) $entry['puzzle_cols'] : 0,
					isset( $entry['puzzle_rows'] ) ? (int) $entry['puzzle_rows'] : 0,
					$converted_data_urls,
					$uploads_relocated
				);

				if ( null !== $normalized_entry ) {
					$normalized[] = $normalized_entry;
				}
			} elseif ( is_string( $entry ) ) {
				$normalized_entry = $this->normalize_migrated_single_image_entry( trim( $entry ), array(), 0, 0, $converted_data_urls, $uploads_relocated );
				if ( null !== $normalized_entry ) {
					$normalized[] = $normalized_entry;
				}
			}
		}

		if ( empty( $normalized ) ) {
			foreach ( (array) $legacy_urls as $legacy_url ) {
				if ( ! is_string( $legacy_url ) ) {
					continue;
				}

				$normalized_entry = $this->normalize_migrated_single_image_entry( trim( $legacy_url ), array(), 0, 0, $converted_data_urls, $uploads_relocated );
				if ( null !== $normalized_entry ) {
					$normalized[] = $normalized_entry;
				}
			}
		}

		if ( empty( $normalized ) ) {
			return array();
		}

		$unique = array();
		$seen   = array();
		foreach ( $normalized as $entry ) {
			$key = md5( wp_json_encode( $entry ) );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ] = true;
			$unique[]     = $entry;
		}

		return $unique;
	}

	private function normalize_migrated_single_image_entry( $source, $crop, $puzzle_cols, $puzzle_rows, &$converted_data_urls = 0, &$uploads_relocated = 0 ) {
		if ( '' === $source ) {
			return null;
		}

		$url = '';
		if ( $this->is_valid_existing_upload_url( $source ) ) {
			$target_url = $this->relocate_upload_url_to_woi_storage( $source );
			if ( '' !== $target_url ) {
				$url = esc_url_raw( $target_url );
				if ( $target_url !== $source ) {
					$uploads_relocated++;
				}
			}
		} elseif ( $this->is_valid_data_url( $source ) ) {
			$file = $this->save_data_url_image( $source );
			if ( ! empty( $file['url'] ) ) {
				$url = esc_url_raw( $file['url'] );
				$converted_data_urls++;
			}
		}

		if ( '' === $url ) {
			return null;
		}

		return array(
			'url'         => $url,
			'crop'        => is_array( $crop ) ? $this->sanitize_crop_data( $crop ) : array(),
			'puzzle_cols' => max( 0, (int) $puzzle_cols ),
			'puzzle_rows' => max( 0, (int) $puzzle_rows ),
		);
	}

	private function relocate_upload_url_to_woi_storage( $source_url ) {
		$source_url = is_string( $source_url ) ? trim( $source_url ) : '';
		if ( '' === $source_url ) {
			return '';
		}

		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) || empty( $uploads['baseurl'] ) || empty( $uploads['basedir'] ) ) {
			return '';
		}

		$baseurl      = trailingslashit( $uploads['baseurl'] );
		$woi_baseurl  = $baseurl . 'woo-order-images/';

		if ( 0 === strpos( $source_url, $woi_baseurl ) ) {
			return $source_url;
		}

		$source_path = $this->url_to_upload_path( $source_url );
		$extension   = strtolower( pathinfo( wp_parse_url( $source_url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
		if ( '' === $extension ) {
			$extension = 'jpg';
		}

		$ext_map = array(
			'jpeg' => 'jpg',
			'jpg'  => 'jpg',
			'png'  => 'png',
			'webp' => 'webp',
		);
		if ( isset( $ext_map[ $extension ] ) ) {
			$extension = $ext_map[ $extension ];
		}

		$subfolder = gmdate( 'Y/m' );
		$dest_dir  = trailingslashit( $uploads['basedir'] ) . 'woo-order-images/' . $subfolder . '/';
		if ( ! wp_mkdir_p( $dest_dir ) ) {
			return '';
		}

		$hash             = md5( $source_url );
		$target_filename  = 'woi-migrated-' . substr( $hash, 0, 16 ) . '.' . $extension;
		$target_path      = $dest_dir . $target_filename;
		$target_url       = $woi_baseurl . $subfolder . '/' . $target_filename;

		if ( is_file( $target_path ) ) {
			return esc_url_raw( $target_url );
		}

		if ( ! $source_path || ! is_file( $source_path ) ) {
			$recovered = $this->find_previously_migrated_woi_url_for_source( $source_url, $extension );
			return '' !== $recovered ? $recovered : '';
		}

		if ( ! @copy( $source_path, $target_path ) ) {
			return '';
		}

		return esc_url_raw( $target_url );
	}

	private function find_previously_migrated_woi_url_for_source( $source_url, $extension ) {
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) || empty( $uploads['baseurl'] ) || empty( $uploads['basedir'] ) ) {
			return '';
		}

		$pattern = trailingslashit( $uploads['basedir'] ) . 'woo-order-images/*/*/woi-migrated-*.' . $extension;
		$files   = glob( $pattern );
		if ( ! is_array( $files ) || empty( $files ) ) {
			return '';
		}

		$base_dir = trailingslashit( wp_normalize_path( $uploads['basedir'] ) );
		$base_url = trailingslashit( $uploads['baseurl'] );

		foreach ( $files as $file ) {
			$normalized_file = wp_normalize_path( $file );
			$expected_hash   = substr( md5( $source_url . '|' . filesize( $file ) . '|' . filemtime( $file ) ), 0, 16 );
			$expected_name   = 'woi-migrated-' . $expected_hash . '.' . $extension;
			if ( basename( $file ) !== $expected_name ) {
				continue;
			}

			if ( 0 !== strpos( $normalized_file, $base_dir ) ) {
				continue;
			}

			$relative = ltrim( substr( $normalized_file, strlen( $base_dir ) ), '/' );
			return esc_url_raw( $base_url . str_replace( '\\', '/', $relative ) );
		}

		return '';
	}

	public function cleanup_order_images_on_wp_trash( $post_id, $previous_status ) {
		if ( 'shop_order' !== get_post_type( $post_id ) ) {
			return;
		}

		$this->cleanup_order_images_for_order( (int) $post_id );
	}

	public function cleanup_order_images_on_order_trash( $order_id ) {
		$this->cleanup_order_images_for_order( (int) $order_id );
	}

	private function cleanup_order_images_for_order( $order_id ) {
		if ( $order_id <= 0 ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$uploads = wp_upload_dir();
		if ( empty( $uploads['basedir'] ) ) {
			return;
		}

		$woi_base_path = wp_normalize_path( trailingslashit( $uploads['basedir'] ) . 'woo-order-images/' );
		$paths_to_delete = array();

		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}

			$images = $item->get_meta( self::ORDER_META_IMAGES, true );
			if ( empty( $images ) || ! is_array( $images ) ) {
				continue;
			}

			foreach ( $images as $image ) {
				$url = is_array( $image ) && ! empty( $image['url'] ) ? (string) $image['url'] : '';
				if ( ! is_string( $url ) || '' === trim( $url ) ) {
					continue;
				}

				$path = $this->url_to_upload_path( $url );
				if ( ! $path ) {
					continue;
				}

				$normalized_path = wp_normalize_path( $path );
				if ( 0 !== strpos( $normalized_path, $woi_base_path ) ) {
					continue;
				}

				$paths_to_delete[ $normalized_path ] = true;
			}
		}

		foreach ( array_keys( $paths_to_delete ) as $path ) {
			if ( is_file( $path ) ) {
				@unlink( $path );
			}
		}
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

	public function redirect_after_add_to_cart( $url, $adding_to_cart = null ) {
		$product_id = 0;

		if ( $adding_to_cart instanceof WC_Product ) {
			$product_id = (int) $adding_to_cart->get_id();
		} elseif ( isset( $_REQUEST['add-to-cart'] ) ) {
			$product_id = absint( wp_unslash( $_REQUEST['add-to-cart'] ) );
		}

		if ( $product_id <= 0 || ! $this->is_woi_product( $product_id ) ) {
			return $url;
		}

		$shop_url = wc_get_page_permalink( 'shop' );
		return $shop_url ? $shop_url : home_url( '/' );
	}

	public function ajax_stage_image_upload() {
		check_ajax_referer( 'woi_stage_image_upload', 'nonce' );

		$source = isset( $_POST['source'] ) ? (string) wp_unslash( $_POST['source'] ) : '';
		if ( ! $this->is_valid_data_url( $source ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid image payload.', 'woo-order-images' ),
				),
				400
			);
		}

		$file = $this->save_data_url_image( $source );
		if ( empty( $file['url'] ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Unable to save image. Try a smaller file.', 'woo-order-images' ),
				),
				500
			);
		}

		wp_send_json_success(
			array(
				'url' => esc_url_raw( $file['url'] ),
			)
		);
	}

	public function render_thumbnail_image() {
		$source_url = isset( $_GET['src'] ) ? esc_url_raw( wp_unslash( $_GET['src'] ) ) : '';
		$max_side   = isset( $_GET['s'] ) ? absint( wp_unslash( $_GET['s'] ) ) : 220;
		$puzzle_cols = isset( $_GET['pc'] ) ? absint( wp_unslash( $_GET['pc'] ) ) : 0;
		$puzzle_rows = isset( $_GET['pr'] ) ? absint( wp_unslash( $_GET['pr'] ) ) : 0;

		if ( ! $this->is_valid_existing_upload_url( $source_url ) ) {
			status_header( 404 );
			exit;
		}

		$crop = array(
			'x'      => isset( $_GET['x'] ) ? (float) wp_unslash( $_GET['x'] ) : 0.0,
			'y'      => isset( $_GET['y'] ) ? (float) wp_unslash( $_GET['y'] ) : 0.0,
			'width'  => isset( $_GET['w'] ) ? (float) wp_unslash( $_GET['w'] ) : 0.0,
			'height' => isset( $_GET['h'] ) ? (float) wp_unslash( $_GET['h'] ) : 0.0,
		);

		$jpeg = $this->build_thumbnail_jpeg( $source_url, $crop, max( 32, $max_side ), $puzzle_cols, $puzzle_rows );
		if ( empty( $jpeg ) ) {
			status_header( 404 );
			exit;
		}

		$etag_seed = wp_json_encode(
			array(
				'src'  => $source_url,
				'crop' => $crop,
				's'    => max( 32, $max_side ),
				'pc'   => $puzzle_cols,
				'pr'   => $puzzle_rows,
			)
		);
		$etag = '"' . substr( md5( (string) $etag_seed ), 0, 12 ) . '"';

		if ( isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ) {
			$if_none_match = trim( (string) wp_unslash( $_SERVER['HTTP_IF_NONE_MATCH'] ) );
			if ( $if_none_match === $etag ) {
				status_header( 304 );
				header_remove( 'Pragma' );
				header( 'ETag: ' . $etag );
				header( 'Cache-Control: private, max-age=3600, stale-while-revalidate=86400' );
				header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', time() + HOUR_IN_SECONDS ) . ' GMT' );
				exit;
			}
		}

		header_remove( 'Pragma' );
		header( 'Content-Type: image/jpeg' );
		header( 'Content-Length: ' . strlen( $jpeg ) );
		header( 'ETag: ' . $etag );
		header( 'Cache-Control: private, max-age=3600, stale-while-revalidate=86400' );
		header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', time() + HOUR_IN_SECONDS ) . ' GMT' );
		echo $jpeg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	public function add_cart_item_data( $cart_item_data, $product_id, $variation_id, $quantity ) {
		$product_id_for_meta = $variation_id ? $variation_id : $product_id;
		$enabled             = get_post_meta( $product_id_for_meta, WOI_Admin_Product_Settings::META_ENABLED, true );

		if ( 'yes' !== $enabled ) {
			return $cart_item_data;
		}

		$image_entries = $this->get_posted_image_entries();
		if ( empty( $image_entries ) ) {
			return $cart_item_data;
		}

		$spec      = WOI_Admin_Product_Settings::get_product_spec( $product_id_for_meta );
		$is_puzzle = ! empty( $spec['is_puzzle'] );

		$saved = array();
		foreach ( $image_entries as $entry ) {
			$source_value = isset( $entry['source'] ) ? (string) $entry['source'] : '';
			$crop         = isset( $entry['crop'] ) && is_array( $entry['crop'] ) ? $this->sanitize_crop_data( $entry['crop'] ) : array();
			$orientation  = isset( $entry['orientation'] ) && '' !== $entry['orientation'] ? (string) $entry['orientation'] : '';
			$rotation     = isset( $entry['rotation'] ) ? max( 0, (int) $entry['rotation'] ) : 0;
			$puzzle_cols  = isset( $entry['puzzle_cols'] ) ? max( 0, (int) $entry['puzzle_cols'] ) : 0;
			$puzzle_rows  = isset( $entry['puzzle_rows'] ) ? max( 0, (int) $entry['puzzle_rows'] ) : 0;
			$file         = array();

			if ( $this->is_valid_data_url( $source_value ) ) {
				$file = $this->save_data_url_image( $source_value );
			} elseif ( $this->is_valid_existing_upload_url( $source_value ) ) {
				$file = array(
					'url'  => esc_url_raw( $source_value ),
					'path' => $this->url_to_upload_path( $source_value ),
				);
			}

			if ( empty( $file['url'] ) ) {
				continue;
			}

			$file['crop'] = $crop;
			if ( '' !== $orientation ) {
				$file['orientation'] = $orientation;
			}
			if ( $rotation > 0 ) {
				$file['rotation'] = $rotation;
			}
			if ( $is_puzzle ) {
				$grid = $this->resolve_puzzle_grid( $spec, $crop, $source_value, $puzzle_cols, $puzzle_rows );
				$file['puzzle_cols'] = $grid['cols'];
				$file['puzzle_rows'] = $grid['rows'];
				$cart_item_data[ self::CART_PUZZLE_COLS_KEY ] = $grid['cols'];
				$cart_item_data[ self::CART_PUZZLE_ROWS_KEY ] = $grid['rows'];
			}
			$saved[]      = $file;
		}

		if ( ! empty( $saved ) ) {
			$cart_item_data[ self::CART_KEY ] = $saved;
			$required_per_qty = max( 1, (int) get_post_meta( $product_id_for_meta, WOI_Admin_Product_Settings::META_REQUIRED_COUNT, true ) );
			if ( $is_puzzle ) {
				$required_per_qty = 1;
			}
			$cart_item_data[ self::CART_REQUIRED_PER_QTY_KEY ] = $required_per_qty;
			$cart_item_data[ self::CART_VISIBLE_WIDTH_KEY ] = $spec['visible_width'];
			$cart_item_data[ self::CART_VISIBLE_HEIGHT_KEY ] = $spec['visible_height'];
			$cart_item_data[ self::CART_IS_PUZZLE_KEY ] = $is_puzzle ? 'yes' : 'no';
			if ( $is_puzzle ) {
				if ( ! isset( $cart_item_data[ self::CART_PUZZLE_COLS_KEY ] ) ) {
					$cart_item_data[ self::CART_PUZZLE_COLS_KEY ] = max( 1, (int) $spec['puzzle_cols'] );
				}
				if ( ! isset( $cart_item_data[ self::CART_PUZZLE_ROWS_KEY ] ) ) {
					$cart_item_data[ self::CART_PUZZLE_ROWS_KEY ] = max( 1, (int) $spec['puzzle_rows'] );
				}
			} else {
				$cart_item_data[ self::CART_PUZZLE_COLS_KEY ] = 0;
				$cart_item_data[ self::CART_PUZZLE_ROWS_KEY ] = 0;
			}
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
		$images           = ( ! empty( $cart_item[ self::CART_KEY ] ) && is_array( $cart_item[ self::CART_KEY ] ) ) ? $cart_item[ self::CART_KEY ] : array();
		$current          = count( $images );
		$complete         = $current >= $required_total;

		$label = $complete
			? sprintf( __( '%1$d of %2$d ✓', 'woo-order-images' ), $current, $required_total )
			: sprintf( __( '%1$d of %2$d', 'woo-order-images' ), $current, $required_total );

		$summary = $label;

		$product_url = get_permalink( $product_id );
		$cart_key    = isset( $cart_item['key'] ) ? (string) $cart_item['key'] : '';
		$edit_url    = ( $product_url && '' !== $cart_key ) ? add_query_arg( 'update_cart', rawurlencode( $cart_key ), $product_url ) : '';
		if ( $edit_url ) {
			$edit_label = $complete ? __( 'Edit', 'woo-order-images' ) : __( 'Edit', 'woo-order-images' );
			$edit_link = $complete
				? '<a href="' . esc_url( $edit_url ) . '">' . esc_html( $edit_label ) . '</a>'
				: '<a href="' . esc_url( $edit_url ) . '"><strong>' . esc_html( $edit_label ) . '</strong></a>';
			$summary .= ' / ' . $edit_link;
		}

		$item_data[] = array(
			'name'  => __( 'Images', 'woo-order-images' ),
			'value' => $summary,
		);

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

		return $product_name;
	}

	public function render_cart_item_thumbnails( $cart_item, $cart_item_key ) {
		if ( ! function_exists( 'is_cart' ) || ! is_cart() ) {
			return;
		}

		$product_id = isset( $cart_item['variation_id'] ) && $cart_item['variation_id'] ? $cart_item['variation_id'] : ( isset( $cart_item['product_id'] ) ? $cart_item['product_id'] : 0 );
		$enabled    = get_post_meta( $product_id, WOI_Admin_Product_Settings::META_ENABLED, true );
		if ( 'yes' !== $enabled && ! empty( $cart_item['product_id'] ) && (int) $cart_item['product_id'] !== (int) $product_id ) {
			$product_id = (int) $cart_item['product_id'];
			$enabled    = get_post_meta( $product_id, WOI_Admin_Product_Settings::META_ENABLED, true );
		}
		if ( 'yes' !== $enabled ) {
			return;
		}

		$images = ( ! empty( $cart_item[ self::CART_KEY ] ) && is_array( $cart_item[ self::CART_KEY ] ) ) ? $cart_item[ self::CART_KEY ] : array();
		if ( empty( $images ) ) {
			return;
		}

		$visible_width  = isset( $cart_item[ self::CART_VISIBLE_WIDTH_KEY ] ) ? (float) $cart_item[ self::CART_VISIBLE_WIDTH_KEY ] : 1;
		$visible_height = isset( $cart_item[ self::CART_VISIBLE_HEIGHT_KEY ] ) ? (float) $cart_item[ self::CART_VISIBLE_HEIGHT_KEY ] : 1;

		// For puzzle products, ensure each image has puzzle dimensions attached
		$is_puzzle = isset( $cart_item[ self::CART_IS_PUZZLE_KEY ] ) && 'yes' === $cart_item[ self::CART_IS_PUZZLE_KEY ];
		if ( $is_puzzle ) {
			$puzzle_cols = isset( $cart_item[ self::CART_PUZZLE_COLS_KEY ] ) ? max( 1, (int) $cart_item[ self::CART_PUZZLE_COLS_KEY ] ) : 1;
			$puzzle_rows = isset( $cart_item[ self::CART_PUZZLE_ROWS_KEY ] ) ? max( 1, (int) $cart_item[ self::CART_PUZZLE_ROWS_KEY ] ) : 1;
			foreach ( $images as &$image ) {
				if ( ! isset( $image['puzzle_cols'] ) || 0 === (int) $image['puzzle_cols'] ) {
					$image['puzzle_cols'] = $puzzle_cols;
				}
				if ( ! isset( $image['puzzle_rows'] ) || 0 === (int) $image['puzzle_rows'] ) {
					$image['puzzle_rows'] = $puzzle_rows;
				}
			}
			unset( $image );
		}

		$thumbs_markup  = $this->get_thumbnail_markup( $images, $visible_width, $visible_height, 56 );
		if ( '' === $thumbs_markup ) {
			return;
		}

		echo $thumbs_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public function hide_order_item_meta( $hidden_keys ) {
		$hidden_keys[] = 'Order image URLs';
		$hidden_keys[] = 'Order image count';
		$hidden_keys[] = __( 'Order image URLs', 'woo-order-images' );
		$hidden_keys[] = __( 'Order image count', 'woo-order-images' );
		$hidden_keys[] = self::ORDER_META_IMAGES;
		$hidden_keys[] = self::ORDER_META_COUNT;
		$hidden_keys[] = self::ORDER_META_VISIBLE_WIDTH;
		$hidden_keys[] = self::ORDER_META_VISIBLE_HEIGHT;
		$hidden_keys[] = self::ORDER_META_WRAP_MARGIN;
		$hidden_keys[] = self::ORDER_META_IS_PUZZLE;
		$hidden_keys[] = self::ORDER_META_PUZZLE_COLS;
		$hidden_keys[] = self::ORDER_META_PUZZLE_ROWS;

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

		$images = array();
		foreach ( $values[ self::CART_KEY ] as $image ) {
			if ( ! empty( $image['url'] ) ) {
				$url = esc_url_raw( $image['url'] );
				$images[] = array(
					'url'         => $url,
					'crop'        => isset( $image['crop'] ) && is_array( $image['crop'] ) ? $this->sanitize_crop_data( $image['crop'] ) : array(),
					'puzzle_cols' => isset( $image['puzzle_cols'] ) ? max( 0, (int) $image['puzzle_cols'] ) : 0,
					'puzzle_rows' => isset( $image['puzzle_rows'] ) ? max( 0, (int) $image['puzzle_rows'] ) : 0,
				);
			}
		}

		if ( ! empty( $images ) ) {
			$visible_width  = isset( $values[ self::CART_VISIBLE_WIDTH_KEY ] ) ? (float) $values[ self::CART_VISIBLE_WIDTH_KEY ] : 1;
			$visible_height = isset( $values[ self::CART_VISIBLE_HEIGHT_KEY ] ) ? (float) $values[ self::CART_VISIBLE_HEIGHT_KEY ] : 1;
			$is_puzzle      = isset( $values[ self::CART_IS_PUZZLE_KEY ] ) && 'yes' === $values[ self::CART_IS_PUZZLE_KEY ];
			$puzzle_cols    = $is_puzzle ? ( isset( $values[ self::CART_PUZZLE_COLS_KEY ] ) ? max( 1, (int) $values[ self::CART_PUZZLE_COLS_KEY ] ) : 1 ) : 0;
			$puzzle_rows    = $is_puzzle ? ( isset( $values[ self::CART_PUZZLE_ROWS_KEY ] ) ? max( 1, (int) $values[ self::CART_PUZZLE_ROWS_KEY ] ) : 1 ) : 0;

			$item->add_meta_data( self::ORDER_META_IMAGES, $images );
			$item->add_meta_data( self::ORDER_META_COUNT, count( $images ) );
			$item->add_meta_data( self::ORDER_META_VISIBLE_WIDTH, $visible_width );
			$item->add_meta_data( self::ORDER_META_VISIBLE_HEIGHT, $visible_height );
			$item->add_meta_data( self::ORDER_META_IS_PUZZLE, $is_puzzle ? 'yes' : 'no' );
			$item->add_meta_data( self::ORDER_META_PUZZLE_COLS, $puzzle_cols );
			$item->add_meta_data( self::ORDER_META_PUZZLE_ROWS, $puzzle_rows );
		}
	}

	public function get_thumbnail_markup( $images, $visible_width, $visible_height, $size = 64 ) {
		$image_entries = array();
		foreach ( (array) $images as $image ) {
			if ( is_array( $image ) && ! empty( $image['url'] ) ) {
				$image_entries[] = array(
					'url'         => $image['url'],
					'crop'        => isset( $image['crop'] ) && is_array( $image['crop'] ) ? $image['crop'] : array(),
					'puzzle_cols' => isset( $image['puzzle_cols'] ) ? max( 0, (int) $image['puzzle_cols'] ) : 0,
					'puzzle_rows' => isset( $image['puzzle_rows'] ) ? max( 0, (int) $image['puzzle_rows'] ) : 0,
				);
			} elseif ( is_string( $image ) && '' !== $image ) {
				$image_entries[] = array(
					'url'         => $image,
					'crop'        => array(),
					'puzzle_cols' => 0,
					'puzzle_rows' => 0,
				);
			}
		}

		if ( empty( $image_entries ) ) {
			return '';
		}

		$html  = '<div class="woi-inline-thumbnails" style="display:flex;flex-wrap:wrap;gap:8px;margin-top:8px;align-items:flex-start;">';

		foreach ( $image_entries as $index => $entry ) {
			$url  = isset( $entry['url'] ) ? (string) $entry['url'] : '';
			$crop = isset( $entry['crop'] ) && is_array( $entry['crop'] ) ? $entry['crop'] : array();
			if ( '' === $url ) {
				continue;
			}

			$puzzle_cols = isset( $entry['puzzle_cols'] ) ? max( 0, (int) $entry['puzzle_cols'] ) : 0;
			$puzzle_rows = isset( $entry['puzzle_rows'] ) ? max( 0, (int) $entry['puzzle_rows'] ) : 0;

			$thumbnail_inner = $this->render_thumbnail_html( $url, $crop, $puzzle_cols, $puzzle_rows, $visible_width, $visible_height, 56, 'woi-inline' );
			if ( '' !== $thumbnail_inner ) {
				$html .= '<span class="woi-inline-thumb-link" style="display:inline-block;line-height:0;position:relative;">';
				$html .= '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer" style="display:block;line-height:0;">';
				$html .= $thumbnail_inner; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				$html .= '</a>';
				$html .= '</span>';
			}
		}

		$html .= '</div>';

		return $html;
	}

	private function get_oriented_ratio_from_image_entry( $url, $crop, $visible_width, $visible_height, $puzzle_cols = 0, $puzzle_rows = 0 ) {
		if ( $puzzle_cols > 0 && $puzzle_rows > 0 ) {
			$overall_width  = (float) $visible_width * (float) $puzzle_cols;
			$overall_height = (float) $visible_height * (float) $puzzle_rows;

			if ( $overall_width > 0 && $overall_height > 0 ) {
				return $overall_width / $overall_height;
			}
		}

		$default_ratio = $visible_height > 0 ? $visible_width / $visible_height : 1;

		if ( abs( $default_ratio - 1 ) < 0.0001 ) {
			return 1;
		}

		if ( is_array( $crop ) && ! empty( $crop['width'] ) && ! empty( $crop['height'] ) ) {
			$crop_w = (float) $crop['width'];
			$crop_h = (float) $crop['height'];
			if ( $crop_w > 0 && $crop_h > 0 ) {
				$image_ratio = $crop_w / $crop_h;
				$image_landscape = $image_ratio >= 1;
				$default_landscape = $default_ratio >= 1;

				if ( $image_landscape !== $default_landscape ) {
					return 1 / $default_ratio;
				}

				return $default_ratio;
			}
		}

		return $this->get_oriented_ratio_from_url( $url, $visible_width, $visible_height );
	}

	private function get_thumbnail_image_url( $source_url, $crop, $max_side = 220, $puzzle_cols = 0, $puzzle_rows = 0 ) {
		$url = is_string( $source_url ) ? trim( $source_url ) : '';
		if ( '' === $url || ! $this->is_valid_existing_upload_url( $url ) ) {
			return '';
		}

		return self::build_thumbnail_image_url( $url, $crop, $max_side, $puzzle_cols, $puzzle_rows );
	}

	public static function build_thumbnail_image_url( $source_url, $crop, $max_side = 220, $puzzle_cols = 0, $puzzle_rows = 0 ) {
		$url = is_string( $source_url ) ? trim( $source_url ) : '';
		if ( '' === $url ) {
			return '';
		}

		$crop_data = is_array( $crop ) ? $crop : array(
			'x' => 0.0,
			'y' => 0.0,
			'width' => 0.0,
			'height' => 0.0,
		);
		$crop_data = array(
			'x'      => isset( $crop_data['x'] ) ? (float) $crop_data['x'] : 0.0,
			'y'      => isset( $crop_data['y'] ) ? (float) $crop_data['y'] : 0.0,
			'width'  => isset( $crop_data['width'] ) ? max( 0.0, (float) $crop_data['width'] ) : 0.0,
			'height' => isset( $crop_data['height'] ) ? max( 0.0, (float) $crop_data['height'] ) : 0.0,
		);
		$max_side = max( 32, (int) $max_side );
		$puzzle_cols = max( 0, (int) $puzzle_cols );
		$puzzle_rows = max( 0, (int) $puzzle_rows );

		$args = array(
			'action' => 'woi_thumb_image',
			'src'    => $url,
			'x'      => $crop_data['x'],
			'y'      => $crop_data['y'],
			'w'      => $crop_data['width'],
			'h'      => $crop_data['height'],
			's'      => $max_side,
			'pc'     => $puzzle_cols,
			'pr'     => $puzzle_rows,
			'v'      => substr( md5( wp_json_encode( array( $url, $crop_data, $max_side, $puzzle_cols, $puzzle_rows ) ) ), 0, 12 ),
		);

		return add_query_arg( $args, admin_url( 'admin-post.php' ) );
	}

	/**
	 * Render a thumbnail with optional puzzle grid overlay.
	 * Unified method used by both cart and admin views.
	 *
	 * @param string $url Image URL.
	 * @param array  $crop Crop data.
	 * @param int    $puzzle_cols Puzzle grid columns (0 = non-puzzle).
	 * @param int    $puzzle_rows Puzzle grid rows (0 = non-puzzle).
	 * @param float  $visible_width Visible width ratio for aspect calculation.
	 * @param float  $visible_height Visible height ratio for aspect calculation.
	 * @param int    $base_size Base thumbnail size in pixels (puzzle will be 2x).
	 * @param string $class_prefix CSS class prefix ('woi-inline' for cart, 'woi-admin' for admin).
	 * @return string HTML markup for thumbnail with grid overlay.
	 */
	public function render_thumbnail_html( $url, $crop, $puzzle_cols, $puzzle_rows, $visible_width, $visible_height, $base_size = 72, $class_prefix = 'woi-inline' ) {
		$url  = is_string( $url ) ? trim( $url ) : '';
		$crop = is_array( $crop ) ? $crop : array();
		$puzzle_cols = max( 0, (int) $puzzle_cols );
		$puzzle_rows = max( 0, (int) $puzzle_rows );
		$visible_width = max( 0.0001, (float) $visible_width );
		$visible_height = max( 0.0001, (float) $visible_height );
		$base_size = max( 24, (int) $base_size );

		if ( '' === $url ) {
			return '';
		}

		$show_grid = $puzzle_cols > 1 || $puzzle_rows > 1;

		// Calculate aspect ratio and display dimensions
		$ratio = $this->get_oriented_ratio_from_image_entry( $url, $crop, $visible_width, $visible_height, $puzzle_cols, $puzzle_rows );
		$display_size = $show_grid ? (int) round( $base_size * 2 ) : $base_size;

		$display_w = $display_size;
		$display_h = $display_size;
		if ( $ratio > 0 ) {
			if ( $ratio >= 1 ) {
				$display_h = max( 1, (int) round( $display_size / $ratio ) );
			} else {
				$display_w = max( 1, (int) round( $display_size * $ratio ) );
			}
		}

		// Get thumbnail source
		$thumb_src = $this->get_thumbnail_image_url( $url, $crop, max( 64, $base_size * 3 ), $puzzle_cols, $puzzle_rows );
		if ( '' === $thumb_src ) {
			$thumb_src = $url;
		}

		// Build HTML — width/height in inline style (not HTML attributes) so external CSS rules (e.g. .woocommerce-cart table.cart img) cannot override them.
		$html = '<img src="' . esc_attr( $thumb_src ) . '" alt="Image" loading="lazy" decoding="async" class="' . esc_attr( $class_prefix ) . '-thumb-image" style="display:block;object-fit:cover;width:' . esc_attr( (string) $display_w ) . 'px;height:' . esc_attr( (string) $display_h ) . 'px;" />';

		return $html;
	}

	/**
	 * Static wrapper for render_thumbnail_html to support admin view.
	 *
	 * @param string $url Image URL.
	 * @param array  $crop Crop data.
	 * @param int    $puzzle_cols Puzzle grid columns.
	 * @param int    $puzzle_rows Puzzle grid rows.
	 * @param float  $visible_width Visible width ratio.
	 * @param float  $visible_height Visible height ratio.
	 * @param int    $base_size Base thumbnail size in pixels.
	 * @param string $class_prefix CSS class prefix.
	 * @return string HTML markup for thumbnail with grid overlay.
	 */
	public static function render_thumbnail_html_static( $url, $crop, $puzzle_cols, $puzzle_rows, $visible_width, $visible_height, $base_size = 72, $class_prefix = 'woi-inline' ) {
		$instance = new self();
		return $instance->render_thumbnail_html( $url, $crop, $puzzle_cols, $puzzle_rows, $visible_width, $visible_height, $base_size, $class_prefix );
	}

	private function build_thumbnail_jpeg( $source_url, $crop, $max_side = 220, $puzzle_cols = 0, $puzzle_rows = 0 ) {
		$path = $this->url_to_upload_path( $source_url );
		if ( ! $path || ! is_file( $path ) ) {
			return null;
		}

		if ( ! function_exists( 'imagecreatefromstring' ) || ! function_exists( 'imagecreatetruecolor' ) ) {
			return null;
		}

		$raw = @file_get_contents( $path );
		if ( false === $raw || '' === $raw ) {
			return null;
		}

		$source = @imagecreatefromstring( $raw );
		if ( ! $source ) {
			return null;
		}

		$src_w = imagesx( $source );
		$src_h = imagesy( $source );
		if ( $src_w <= 0 || $src_h <= 0 ) {
			imagedestroy( $source );
			return null;
		}

		$rect = $this->normalize_crop_rect( $crop, $src_w, $src_h );
		if ( $rect['w'] <= 0 || $rect['h'] <= 0 ) {
			imagedestroy( $source );
			return null;
		}

		$max_side = max( 1, (int) $max_side );
		$scale    = min( 1, $max_side / max( $rect['w'], $rect['h'] ) );
		$thumb_w  = max( 1, (int) round( $rect['w'] * $scale ) );
		$thumb_h  = max( 1, (int) round( $rect['h'] * $scale ) );

		$thumb = imagecreatetruecolor( $thumb_w, $thumb_h );
		if ( ! $thumb ) {
			imagedestroy( $source );
			return null;
		}

		imagecopyresampled(
			$thumb,
			$source,
			0,
			0,
			$rect['x'],
			$rect['y'],
			$thumb_w,
			$thumb_h,
			$rect['w'],
			$rect['h']
		);

		$this->draw_puzzle_grid_overlay_on_thumbnail( $thumb, $thumb_w, $thumb_h, $puzzle_cols, $puzzle_rows );

		ob_start();
		imagejpeg( $thumb, null, 88 );
		$jpeg = ob_get_clean();

		imagedestroy( $thumb );
		imagedestroy( $source );

		if ( empty( $jpeg ) ) {
			return null;
		}

		return $jpeg;
	}

	private function draw_puzzle_grid_overlay_on_thumbnail( $thumb, $thumb_w, $thumb_h, $puzzle_cols, $puzzle_rows ) {
		$cols = max( 0, (int) $puzzle_cols );
		$rows = max( 0, (int) $puzzle_rows );

		if ( ! $thumb || ( $cols <= 1 && $rows <= 1 ) ) {
			return;
		}

		$white = imagecolorallocatealpha( $thumb, 255, 255, 255, 0 );
		$black = imagecolorallocatealpha( $thumb, 0, 0, 0, 0 );
		if ( false === $white || false === $black ) {
			return;
		}

		$style = array(
			$white, $white, $white,
			$black, $black, $black,
		);
		imagesetstyle( $thumb, $style );
		imagesetthickness( $thumb, 1 );

		for ( $c = 1; $c < $cols; $c++ ) {
			$x = (int) round( ( $thumb_w * $c ) / $cols );
			imageline( $thumb, $x, 0, $x, max( 0, $thumb_h - 1 ), IMG_COLOR_STYLED );
		}

		for ( $r = 1; $r < $rows; $r++ ) {
			$y = (int) round( ( $thumb_h * $r ) / $rows );
			imageline( $thumb, 0, $y, max( 0, $thumb_w - 1 ), $y, IMG_COLOR_STYLED );
		}
	}

	private function normalize_crop_rect( $crop, $src_w, $src_h ) {
		$src_w = max( 1, (int) $src_w );
		$src_h = max( 1, (int) $src_h );

		if ( ! is_array( $crop ) || empty( $crop['width'] ) || empty( $crop['height'] ) ) {
			return array(
				'x' => 0,
				'y' => 0,
				'w' => $src_w,
				'h' => $src_h,
			);
		}

		$x = isset( $crop['x'] ) ? (float) $crop['x'] : 0.0;
		$y = isset( $crop['y'] ) ? (float) $crop['y'] : 0.0;
		$w = isset( $crop['width'] ) ? (float) $crop['width'] : 0.0;
		$h = isset( $crop['height'] ) ? (float) $crop['height'] : 0.0;

		$w = max( 1.0, $w );
		$h = max( 1.0, $h );
		$x = max( 0.0, $x );
		$y = max( 0.0, $y );

		$x1 = min( (float) $src_w, $x + $w );
		$y1 = min( (float) $src_h, $y + $h );
		$x  = min( $x, $x1 - 1.0 );
		$y  = min( $y, $y1 - 1.0 );
		$w  = max( 1.0, $x1 - $x );
		$h  = max( 1.0, $y1 - $y );

		return array(
			'x' => (int) round( $x ),
			'y' => (int) round( $y ),
			'w' => max( 1, (int) round( $w ) ),
			'h' => max( 1, (int) round( $h ) ),
		);
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

	private function get_posted_image_entries() {
		$raw_images = isset( $_POST['woi_images'] ) ? (array) wp_unslash( $_POST['woi_images'] ) : array();
		$entries    = array();

		foreach ( $raw_images as $raw_value ) {
			if ( ! is_string( $raw_value ) ) {
				continue;
			}

			$value = trim( $raw_value );
			if ( '' === $value ) {
				continue;
			}

			$decoded = json_decode( $value, true );
			if ( ! is_array( $decoded ) || empty( $decoded['source'] ) ) {
				continue;
			}

			$entries[] = array(
				'source'      => (string) $decoded['source'],
				'crop'        => isset( $decoded['crop'] ) && is_array( $decoded['crop'] ) ? $decoded['crop'] : array(),
				'orientation' => isset( $decoded['orientation'] ) ? (string) $decoded['orientation'] : '',
				'rotation'    => isset( $decoded['rotation'] ) ? max( 0, (int) $decoded['rotation'] ) : 0,
				'puzzle_cols' => isset( $decoded['puzzle_cols'] ) ? max( 1, (int) $decoded['puzzle_cols'] ) : 0,
				'puzzle_rows' => isset( $decoded['puzzle_rows'] ) ? max( 1, (int) $decoded['puzzle_rows'] ) : 0,
			);
		}

		return array_values( $entries );
	}

	private function sanitize_crop_data( $crop ) {
		$x      = isset( $crop['x'] ) ? (float) $crop['x'] : 0.0;
		$y      = isset( $crop['y'] ) ? (float) $crop['y'] : 0.0;
		$width  = isset( $crop['width'] ) ? (float) $crop['width'] : 0.0;
		$height = isset( $crop['height'] ) ? (float) $crop['height'] : 0.0;

		return array(
			'x'      => $x,
			'y'      => $y,
			'width'  => max( 0.0, $width ),
			'height' => max( 0.0, $height ),
		);
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

		$baseurl = trailingslashit( $uploads['baseurl'] );
		if ( 0 !== strpos( $value, $baseurl ) ) {
			return false;
		}

		$path = $this->url_to_upload_path( $value );
		return (bool) ( $path && file_exists( $path ) );
	}

	private function is_woi_product( $product_id ) {
		$product_id = (int) $product_id;
		if ( $product_id <= 0 ) {
			return false;
		}

		$enabled = get_post_meta( $product_id, WOI_Admin_Product_Settings::META_ENABLED, true );
		if ( 'yes' === $enabled ) {
			return true;
		}

		$parent_id = wp_get_post_parent_id( $product_id );
		if ( $parent_id > 0 ) {
			return 'yes' === get_post_meta( $parent_id, WOI_Admin_Product_Settings::META_ENABLED, true );
		}

		return false;
	}

	private function resolve_puzzle_grid( $spec, $crop, $source_url, $explicit_cols = 0, $explicit_rows = 0 ) {
		$default_cols = max( 1, (int) $spec['puzzle_cols'] );
		$default_rows = max( 1, (int) $spec['puzzle_rows'] );

		if ( $explicit_cols > 0 && $explicit_rows > 0 ) {
			return array(
				'cols' => $explicit_cols,
				'rows' => $explicit_rows,
			);
		}

		$default_ratio = ( $default_rows > 0 && ! empty( $spec['visible_height'] ) )
			? ( ( $default_cols * (float) $spec['visible_width'] ) / ( $default_rows * (float) $spec['visible_height'] ) )
			: 1;

		$image_ratio = null;
		if ( is_array( $crop ) && ! empty( $crop['width'] ) && ! empty( $crop['height'] ) ) {
			$crop_w = (float) $crop['width'];
			$crop_h = (float) $crop['height'];
			if ( $crop_w > 0 && $crop_h > 0 ) {
				$image_ratio = $crop_w / $crop_h;
			}
		}

		if ( null === $image_ratio ) {
			$image_ratio = $this->get_image_ratio_from_url( $source_url );
		}

		if ( null === $image_ratio ) {
			return array(
				'cols' => $default_cols,
				'rows' => $default_rows,
			);
		}

		$image_landscape   = $image_ratio >= 1;
		$default_landscape = $default_ratio >= 1;

		if ( $image_landscape === $default_landscape ) {
			return array(
				'cols' => $default_cols,
				'rows' => $default_rows,
			);
		}

		return array(
			'cols' => $default_rows,
			'rows' => $default_cols,
		);
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
