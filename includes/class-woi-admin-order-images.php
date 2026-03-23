<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WOI_Admin_Order_Images {
	public function init() {
		add_action( 'woocommerce_after_order_itemmeta', array( $this, 'render_order_item_images' ), 10, 3 );
		add_action( 'woocommerce_order_item_meta_end', array( $this, 'render_frontend_order_item_images' ), 10, 4 );
		add_action( 'admin_post_woi_print_sheet', array( $this, 'render_print_page' ) );
		add_action( 'woocommerce_order_actions_end', array( $this, 'render_order_level_print_button' ), 10, 1 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_footer', array( $this, 'render_admin_crop_modal' ) );
		add_action( 'wp_ajax_woi_admin_update_order_crop', array( $this, 'ajax_update_order_crop' ) );
	}

	public function enqueue_admin_assets() {
		if ( ! $this->is_order_admin_screen() ) {
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
			'woi-admin-order-crop',
			WOI_PLUGIN_URL . 'assets/css/admin-order-crop.css',
			array( 'woi-cropper' ),
			WOI_VERSION
		);

		wp_enqueue_script(
			'woi-admin-order-crop',
			WOI_PLUGIN_URL . 'assets/js/admin-order-crop.js',
			array( 'woi-cropper' ),
			WOI_VERSION,
			true
		);

		wp_localize_script(
			'woi-admin-order-crop',
			'woiAdminCrop',
			array(
				'ajaxUrl'               => admin_url( 'admin-ajax.php' ),
				'nonce'                 => wp_create_nonce( 'woi_admin_update_order_crop' ),
				'editLabel'             => __( 'Adjust crop', 'woo-order-images' ),
				'saveLabel'             => __( 'Save Crop', 'woo-order-images' ),
				'cancelLabel'           => __( 'Cancel', 'woo-order-images' ),
				'swapPortraitLabel'     => __( 'Swap to Portrait', 'woo-order-images' ),
				'swapLandscapeLabel'    => __( 'Swap to Landscape', 'woo-order-images' ),
				'updateFailed'          => __( 'Unable to save the updated crop right now.', 'woo-order-images' ),
				'zoomLabel'             => __( 'Zoom', 'woo-order-images' ),
				'puzzleGridLabel'       => __( 'puzzle grid', 'woo-order-images' ),
				'swapToGridLabel'       => __( 'Swap to %1$d×%2$d', 'woo-order-images' ),
			)
		);
	}

	public function render_order_level_print_button( $order_id ) {
		$order = is_a( $order_id, 'WC_Abstract_Order' ) ? $order_id : wc_get_order( absint( $order_id ) );
		if ( ! $order ) {
			return;
		}

		$has_images = false;
		foreach ( $order->get_items() as $item ) {
			if ( $item instanceof WC_Order_Item_Product ) {
				$images = $this->get_order_item_images( $item );
				if ( ! empty( $images ) ) {
					$has_images = true;
					break;
				}
			}
		}

		if ( ! $has_images ) {
			return;
		}

		$print_sheet_url = add_query_arg(
			array(
				'action'   => 'woi_print_sheet',
				'order_id' => $order->get_id(),
			),
			admin_url( 'admin-post.php' )
		);
		echo '<li style="margin:6px 0 0;padding:0;list-style:none;"><a class="button button-secondary" href="' . esc_url( $print_sheet_url ) . '" target="_blank" rel="noopener noreferrer" style="width:100%;text-align:center;display:block;">' . esc_html__( 'Open Print Sheet', 'woo-order-images' ) . '</a></li>';
	}

	public function render_order_item_images( $item_id, $item, $product ) {
		if ( ! is_admin() || ! $item instanceof WC_Order_Item_Product ) {
			return;
		}

		$images = $this->get_order_item_images( $item );
		if ( empty( $images ) ) {
			return;
		}

		$base_spec = $this->get_item_spec( $item );

		echo '<div class="woi-admin-images" style="margin-top:8px;">';
		echo '<strong>' . esc_html__( 'Order Images', 'woo-order-images' ) . ':</strong>';
		echo '<div class="woi-admin-image-list" style="display:flex;flex-wrap:wrap;gap:8px;margin-top:6px;">';

		foreach ( $images as $index => $entry ) {
			$url         = isset( $entry['url'] ) ? $entry['url'] : '';
			$crop        = isset( $entry['crop'] ) && is_array( $entry['crop'] ) ? $entry['crop'] : array();
			if ( '' === $url ) {
				continue;
			}
			$image_entry = array(
				'url'         => $url,
				'crop'        => $crop,
				'puzzle_cols' => isset( $entry['puzzle_cols'] ) ? max( 0, (int) $entry['puzzle_cols'] ) : 0,
				'puzzle_rows' => isset( $entry['puzzle_rows'] ) ? max( 0, (int) $entry['puzzle_rows'] ) : 0,
			);
			$grid        = $this->resolve_puzzle_grid_for_entry( $base_spec, $url, $crop, $image_entry );
			$spec        = ! empty( $base_spec['is_puzzle'] ) ? $base_spec : $this->get_oriented_spec_for_crop( $base_spec, $url, $crop );
			$thumb_src   = $this->build_thumbnail_data_url( $url, $crop, 240 );
			if ( '' === $thumb_src ) {
				$thumb_src = $url;
			}
			$index_label = sprintf( __( 'Image %d', 'woo-order-images' ), ( $index + 1 ) );
			echo '<span class="woi-admin-image-thumb">';
			echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer" title="' . esc_attr( $index_label ) . '">';
			echo '<span class="woi-admin-image-frame" data-woi-admin-thumb-frame style="display:block;width:72px;aspect-ratio:' . esc_attr( $spec['visible_aspect_ratio'] ) . ';overflow:hidden;border:1px solid #ccd0d4;border-radius:4px;background:#f6f7f7;">';
			echo '<img src="' . esc_attr( $thumb_src ) . '" alt="' . esc_attr( $index_label ) . '" data-woi-admin-thumb-image style="width:100%;height:100%;object-fit:cover;display:block;" />';
			echo '</span>';
			echo '</a>';
			echo '<button type="button" class="button-link woi-admin-image-edit" data-woi-admin-edit-crop'
				. ' data-item-id="' . esc_attr( $item_id ) . '"'
				. ' data-image-index="' . esc_attr( $index ) . '"'
				. ' data-image-url="' . esc_url( $url ) . '"'
				. ' data-image-label="' . esc_attr( $index_label ) . '"'
				. ' data-image-crop="' . esc_attr( wp_json_encode( $crop ) ) . '"'
				. ' data-current-orientation="' . esc_attr( $this->get_entry_orientation( $base_spec, $crop, $grid ) ) . '"'
				. ' data-visible-width="' . esc_attr( $base_spec['visible_width'] ) . '"'
				. ' data-visible-height="' . esc_attr( $base_spec['visible_height'] ) . '"'
				. ' data-visible-width-percent="' . esc_attr( $base_spec['visible_width_percent'] ) . '"'
				. ' data-visible-height-percent="' . esc_attr( $base_spec['visible_height_percent'] ) . '"'
				. ' data-visible-aspect-ratio="' . esc_attr( $base_spec['visible_aspect_ratio'] ) . '"'
				. ' data-is-puzzle="' . ( ! empty( $base_spec['is_puzzle'] ) ? '1' : '0' ) . '"'
				. ' data-puzzle-cols="' . esc_attr( (int) $base_spec['puzzle_cols'] ) . '"'
				. ' data-puzzle-rows="' . esc_attr( (int) $base_spec['puzzle_rows'] ) . '"'
				. ' data-current-puzzle-cols="' . esc_attr( (int) $grid['cols'] ) . '"'
				. ' data-current-puzzle-rows="' . esc_attr( (int) $grid['rows'] ) . '"'
				. ' title="' . esc_attr__( 'Adjust crop', 'woo-order-images' ) . '"'
				. ' aria-label="' . esc_attr( sprintf( __( 'Adjust crop for %s', 'woo-order-images' ), $index_label ) ) . '">'
				. '<span aria-hidden="true">✎</span></button>';
			echo '</span>';
		}

		echo '</div>';
		echo '</div>';
	}

	public function render_admin_crop_modal() {
		if ( ! $this->is_order_admin_screen() ) {
			return;
		}
		?>
		<div class="woi-admin-crop-modal" data-woi-admin-crop-modal hidden>
			<div class="woi-admin-crop-backdrop" data-woi-admin-close></div>
			<div class="woi-admin-crop-content" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Adjust order image crop', 'woo-order-images' ); ?>">
				<div class="woi-admin-crop-title-row">
					<strong data-woi-admin-modal-title><?php esc_html_e( 'Adjust crop', 'woo-order-images' ); ?></strong>
				</div>
				<div class="woi-admin-cropper-wrap">
					<img data-woi-admin-cropper-image alt="">
					<div class="woi-admin-puzzle-grid" data-woi-admin-puzzle-grid hidden>
						<span data-woi-admin-puzzle-grid-label></span>
					</div>
				</div>
				<div class="woi-admin-zoom-row">
					<span class="woi-admin-zoom-label"><?php esc_html_e( 'Zoom', 'woo-order-images' ); ?></span>
					<input type="range" class="woi-admin-zoom-slider" data-woi-admin-zoom-slider min="0" max="100" value="50" step="1" aria-label="<?php esc_attr_e( 'Zoom', 'woo-order-images' ); ?>">
					<span class="woi-admin-zoom-value" data-woi-admin-zoom-value>100%</span>
				</div>
				<div class="woi-admin-crop-actions">
					<button type="button" class="button" data-woi-admin-swap><?php esc_html_e( 'Swap Orientation', 'woo-order-images' ); ?></button>
					<button type="button" class="button" data-woi-admin-close><?php esc_html_e( 'Cancel', 'woo-order-images' ); ?></button>
					<button type="button" class="button button-primary" data-woi-admin-save><?php esc_html_e( 'Save Crop', 'woo-order-images' ); ?></button>
				</div>
			</div>
		</div>
		<?php
	}

	public function ajax_update_order_crop() {
		check_ajax_referer( 'woi_admin_update_order_crop', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission to edit order images.', 'woo-order-images' ),
				),
				403
			);
		}

		$item_id     = isset( $_POST['item_id'] ) ? absint( wp_unslash( $_POST['item_id'] ) ) : 0;
		$image_index = isset( $_POST['image_index'] ) ? absint( wp_unslash( $_POST['image_index'] ) ) : -1;
		$crop        = isset( $_POST['crop'] ) && is_array( $_POST['crop'] ) ? $this->sanitize_crop_data( wp_unslash( $_POST['crop'] ) ) : array();
		$puzzle_cols = isset( $_POST['puzzle_cols'] ) ? max( 0, absint( wp_unslash( $_POST['puzzle_cols'] ) ) ) : 0;
		$puzzle_rows = isset( $_POST['puzzle_rows'] ) ? max( 0, absint( wp_unslash( $_POST['puzzle_rows'] ) ) ) : 0;

		$item = wc_get_order_item( $item_id );
		if ( ! $item instanceof WC_Order_Item_Product ) {
			wp_send_json_error(
				array(
					'message' => __( 'Order item not found.', 'woo-order-images' ),
				),
				404
			);
		}

		$images = $this->get_order_item_images( $item );
		if ( ! isset( $images[ $image_index ] ) || empty( $images[ $image_index ]['url'] ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Order image not found.', 'woo-order-images' ),
				),
				404
			);
		}

		$images[ $image_index ]['crop'] = $crop;

		$base_spec = $this->get_item_spec( $item );
		if ( ! empty( $base_spec['is_puzzle'] ) ) {
			if ( $puzzle_cols > 0 && $puzzle_rows > 0 ) {
				$images[ $image_index ]['puzzle_cols'] = $puzzle_cols;
				$images[ $image_index ]['puzzle_rows'] = $puzzle_rows;
			}
		}

		$item->update_meta_data( WOI_Order_Images::ORDER_META_IMAGES, array_values( $images ) );
		$item->update_meta_data( WOI_Order_Images::ORDER_META_COUNT, count( $images ) );
		$item->save();

		$entry      = $images[ $image_index ];
		$url        = (string) $entry['url'];
		$grid       = $this->resolve_puzzle_grid_for_entry( $base_spec, $url, $crop, $entry );
		$thumb_src  = $this->build_thumbnail_data_url( $url, $crop, 240 );
		$thumb_spec = ! empty( $base_spec['is_puzzle'] ) ? $base_spec : $this->get_oriented_spec_for_crop( $base_spec, $url, $crop );

		if ( '' === $thumb_src ) {
			$thumb_src = $url;
		}

		wp_send_json_success(
			array(
				'crop'                 => $crop,
				'thumbSrc'             => $thumb_src,
				'visibleAspectRatio'   => $thumb_spec['visible_aspect_ratio'],
				'puzzleCols'           => (int) $grid['cols'],
				'puzzleRows'           => (int) $grid['rows'],
				'currentOrientation'   => $this->get_entry_orientation( $base_spec, $crop, $grid ),
			)
		);
	}

	public function render_frontend_order_item_images( $item_id, $item, $order, $plain_text ) {
		if ( is_admin() || $plain_text || ! $item instanceof WC_Order_Item_Product ) {
			return;
		}

		$images = $this->get_order_item_images( $item );
		if ( empty( $images ) ) {
			return;
		}

		$base_spec = $this->get_item_spec( $item );

		echo '<div class="woi-order-history-images" style="display:flex;flex-wrap:wrap;gap:8px;margin-top:10px;">';
		foreach ( $images as $index => $entry ) {
			$url         = isset( $entry['url'] ) ? $entry['url'] : '';
			$crop        = isset( $entry['crop'] ) && is_array( $entry['crop'] ) ? $entry['crop'] : array();
			if ( '' === $url ) {
				continue;
			}
			$spec        = $this->get_oriented_spec_for_crop( $base_spec, $url, $crop );
			$thumb_src   = $this->build_thumbnail_data_url( $url, $crop, 220 );
			if ( '' === $thumb_src ) {
				$thumb_src = $url;
			}
			$index_label = sprintf( __( 'Image %d', 'woo-order-images' ), ( $index + 1 ) );
			echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer" title="' . esc_attr( $index_label ) . '">';
			echo '<span style="display:block;width:68px;aspect-ratio:' . esc_attr( $spec['visible_aspect_ratio'] ) . ';overflow:hidden;border:1px solid #ccd0d4;border-radius:4px;background:#f6f7f7;">';
			echo '<img src="' . esc_attr( $thumb_src ) . '" alt="' . esc_attr( $index_label ) . '" style="display:block;width:100%;height:100%;object-fit:cover;" />';
			echo '</span>';
			echo '</a>';
		}
		echo '</div>';
	}

	public function render_print_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'woo-order-images' ) );
		}

		$order_id = isset( $_GET['order_id'] ) ? absint( wp_unslash( $_GET['order_id'] ) ) : 0;
		$order    = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_die( esc_html__( 'Order not found.', 'woo-order-images' ) );
		}

		$entries = array();
		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}

			$images = $this->get_order_item_images( $item );
			if ( empty( $images ) ) {
				continue;
			}

			$base_spec = $this->get_item_spec( $item );
			foreach ( $images as $image_entry ) {
				$url  = isset( $image_entry['url'] ) ? $image_entry['url'] : '';
				$crop = isset( $image_entry['crop'] ) && is_array( $image_entry['crop'] ) ? $image_entry['crop'] : array();
				if ( '' === $url ) {
					continue;
				}

				if ( ! empty( $base_spec['is_puzzle'] ) ) {
					$grid = $this->resolve_puzzle_grid_for_entry( $base_spec, $url, $crop, $image_entry );
					$cols = $grid['cols'];
					$rows = $grid['rows'];
					for ( $row = 0; $row < $rows; $row++ ) {
						for ( $col = 0; $col < $cols; $col++ ) {
							$tile_url = $this->build_puzzle_tile_data_url( $url, $crop, $base_spec, $col, $row, $cols, $rows );
							if ( '' === $tile_url ) {
								continue;
							}
							$entries[] = array(
								'url'  => $tile_url,
								'spec' => $base_spec,
							);
						}
					}
				} else {
					$oriented_spec = $this->get_oriented_spec_for_crop( $base_spec, $url, $crop );
					$print_url     = $this->build_single_tile_data_url( $url, $crop, $oriented_spec );
					if ( '' === $print_url ) {
						continue;
					}
					$entries[] = array(
						'url'  => $print_url,
						'spec' => $oriented_spec,
					);
				}
			}
		}

		if ( empty( $entries ) ) {
			wp_die( esc_html__( 'No order images found for this order.', 'woo-order-images' ) );
		}

		$watermark_text = WOI_Settings::get_watermark_text();
		$margin_top     = WOI_Settings::get_print_margin_top();
		$margin_right   = WOI_Settings::get_print_margin_right();
		$margin_bottom  = WOI_Settings::get_print_margin_bottom();
		$margin_left    = WOI_Settings::get_print_margin_left();
		$gap            = WOI_Settings::get_print_gap();

		// Get page dimensions from settings, calculate printable area.
		$page_size_key  = WOI_Settings::get_print_page_size();
		$page_dims      = WOI_Settings::get_page_size_dimensions( $page_size_key );
		$full_page_w    = $page_dims['width'];
		$full_page_h    = $page_dims['height'];
		$page_width     = $full_page_w - $margin_left - $margin_right;
		$page_height    = $full_page_h - $margin_top - $margin_bottom;

		$pages       = array();
		$current     = array();
		$current_y   = 0.0;
		$row_items   = array();
		$row_width   = 0.0;
		$row_height  = 0.0;

		$flush_row = function () use ( &$current, &$current_y, &$row_items, &$row_width, &$row_height, $page_width, $gap ) {
			if ( empty( $row_items ) ) {
				return;
			}

			$tile_count = count( $row_items );
			$total_gaps = max( 0, $tile_count - 1 );
			$used_width = $row_width + ( $total_gaps * $gap );
			$start_x    = max( 0.0, ( $page_width - $used_width ) / 2 );
			$x          = $start_x;

			foreach ( $row_items as $index => $row_item ) {
				$row_items[ $index ]['x'] = $x;
				$row_items[ $index ]['y'] = $current_y;
				$current[]                = $row_items[ $index ];
				$x += $row_item['w'] + $gap;
			}

			$current_y += $row_height + $gap;
			$row_items = array();
			$row_width = 0.0;
			$row_height = 0.0;
		};

		$flush_page = function () use ( &$pages, &$current, &$current_y, &$row_items, &$row_width, &$row_height, $flush_row ) {
			$flush_row();
			if ( ! empty( $current ) ) {
				$pages[] = $current;
			}
			$current    = array();
			$current_y  = 0.0;
			$row_items  = array();
			$row_width  = 0.0;
			$row_height = 0.0;
		};

		foreach ( $entries as $entry ) {
			$spec       = $entry['spec'];
			$tile_w_in  = (float) $spec['full_width'];
			$tile_h_in  = (float) $spec['full_height'];
			if ( $tile_w_in <= 0 || $tile_h_in <= 0 ) {
				continue;
			}

			$tile = array(
				'url'  => $entry['url'],
				'spec' => $spec,
				'w'    => $tile_w_in,
				'h'    => $tile_h_in,
				'x'    => 0.0,
				'y'    => 0.0,
			);

			$next_row_width = empty( $row_items ) ? $tile_w_in : ( $row_width + $gap + $tile_w_in );
			if ( $next_row_width > $page_width + 0.0001 ) {
				$flush_row();
				$next_row_width = $tile_w_in;
			}

			$next_row_height = max( $row_height, $tile_h_in );
			$row_top         = $current_y;
			$row_bottom      = $row_top + $next_row_height;
			if ( $row_bottom > $page_height + 0.0001 ) {
				$flush_page();
				$next_row_width  = $tile_w_in;
				$next_row_height = $tile_h_in;
			}

			if ( $tile_h_in > $page_height + 0.0001 ) {
				continue;
			}

			$row_items[] = $tile;
			$row_width   = $next_row_width;
			$row_height  = $next_row_height;
		}

		$flush_page();

		header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
		echo '<!doctype html><html><head><meta charset="' . esc_attr( get_option( 'blog_charset' ) ) . '">';
		echo '<title>' . esc_html__( 'WOI Print Sheet', 'woo-order-images' ) . '</title>';
		echo '<style>';
		echo '@page{';
		echo 'margin:' . esc_attr( $margin_top ) . 'in ' . esc_attr( $margin_right ) . 'in ' . esc_attr( $margin_bottom ) . 'in ' . esc_attr( $margin_left ) . 'in;';
		echo 'size:' . esc_attr( $this->format_num( $full_page_w ) ) . 'in ' . esc_attr( $this->format_num( $full_page_h ) ) . 'in;';
		echo '-webkit-print-color-adjust:exact !important;';
		echo 'print-color-adjust:exact !important;';
		echo '}';
		echo '@media print{';
		echo '*{-webkit-print-color-adjust:exact !important;print-color-adjust:exact !important;}';
		echo 'html,body{margin:0;padding:0;background:#fff;}';
		echo '.woi-sheet-grid{gap:0.1in;}';
		echo '}';
		echo '</style>';
		echo '<link rel="stylesheet" href="' . esc_url( WOI_PLUGIN_URL . 'assets/css/print-sheet.css?ver=' . rawurlencode( WOI_VERSION ) ) . '">';
		echo '</head><body>';

		$page_count = count( $pages );
		foreach ( $pages as $page_index => $tiles ) {
			$is_last   = ( $page_index === $page_count - 1 );
			$brk_style = $is_last ? '' : 'page-break-after:always;break-after:page;';
			echo '<div class="woi-sheet-grid" style="width:' . esc_attr( $this->format_num( $page_width ) ) . 'in;height:' . esc_attr( $this->format_num( $page_height ) ) . 'in;display:block;position:relative;overflow:hidden;' . $brk_style . '">';

			foreach ( $tiles as $tile ) {
				$spec          = $tile['spec'];
				$url           = $tile['url'];
				$left_pct      = ( 100 - $spec['visible_width_percent'] ) / 2;
				$top_pct       = ( 100 - $spec['visible_height_percent'] ) / 2;
				$expand_x      = $left_pct * 0.125;
				$expand_y      = $top_pct * 0.125;
				$window_left   = max( 0, $left_pct - $expand_x );
				$window_top    = max( 0, $top_pct - $expand_y );
				$window_width  = min( 100, $spec['visible_width_percent'] + ( 2 * $expand_x ) );
				$window_height = min( 100, $spec['visible_height_percent'] + ( 2 * $expand_y ) );
				$window_right  = min( 100, $window_left + $window_width );
				$window_bottom = min( 100, $window_top + $window_height );
				$corner_cut_x  = min( 49.999, ( 2 * $spec['wrap_margin'] / max( 0.0001, $spec['full_width'] ) ) * 100 );
				$corner_cut_y  = min( 49.999, ( 2 * $spec['wrap_margin'] / max( 0.0001, $spec['full_height'] ) ) * 100 );
				$clip_polygon  = $this->get_cut_polygon_css( $corner_cut_x, $corner_cut_y );
				$cut_outline   = $this->get_cut_polygon_svg( $corner_cut_x, $corner_cut_y );

				$top_safe        = max( 0, $window_top );
				$bottom_safe     = max( 0, 100 - $window_bottom );
				$left_safe       = max( 0, $window_left );
				$right_safe      = max( 0, 100 - $window_right );
				$top_band_top    = 0;
				$top_band_height = $top_safe;
				$bottom_band_bottom = 0;
				$bottom_band_height = $bottom_safe;
				$left_band_left  = 0;
				$left_band_width = $left_safe;
				$right_band_right = 0;
				$right_band_width = $right_safe;
				$image_width     = $this->format_pct( 10000 / max( 0.0001, $window_width ) );
				$image_height    = $this->format_pct( 10000 / max( 0.0001, $window_height ) );
				$image_left      = $this->format_pct( -100 * $window_left / max( 0.0001, $window_width ) );
				$image_top       = $this->format_pct( -100 * $window_top / max( 0.0001, $window_height ) );

				echo '<div class="woi-tile" style="position:absolute;left:' . esc_attr( $this->format_num( $tile['x'] ) ) . 'in;top:' . esc_attr( $this->format_num( $tile['y'] ) ) . 'in;width:' . esc_attr( $this->format_num( $tile['w'] ) ) . 'in;height:' . esc_attr( $this->format_num( $tile['h'] ) ) . 'in;">';
				echo '<div class="woi-bleed" style="aspect-ratio:' . esc_attr( $spec['full_width'] ) . ' / ' . esc_attr( $spec['full_height'] ) . ';clip-path:polygon(' . esc_attr( $clip_polygon ) . ');">';
				echo '<div class="woi-visible-window" style="left:' . esc_attr( $this->format_pct( $window_left ) ) . ';top:' . esc_attr( $this->format_pct( $window_top ) ) . ';width:' . esc_attr( $this->format_pct( $window_width ) ) . ';height:' . esc_attr( $this->format_pct( $window_height ) ) . ';">';
				echo '<img src="' . esc_attr( $url ) . '" alt="" style="left:' . esc_attr( $image_left ) . ';top:' . esc_attr( $image_top ) . ';width:' . esc_attr( $image_width ) . ';height:' . esc_attr( $image_height ) . ';">';
				echo '</div>';

				if ( '' !== trim( $watermark_text ) ) {
					// Keep the watermark modest relative to the bleed tab and slightly smaller than before.
					$wm_font_pt = $this->format_num( max( 4, $spec['wrap_margin'] * 19.2 ) );
					$wm_style   = 'font-size:' . esc_attr( $wm_font_pt ) . 'pt;';
					echo '<div class="woi-watermark-band woi-watermark-top" style="left:0;top:' . esc_attr( $this->format_pct( $top_band_top ) ) . ';width:100%;height:' . esc_attr( $this->format_pct( $top_band_height ) ) . ';"><span style="' . $wm_style . '">' . esc_html( $watermark_text ) . '</span></div>';
					echo '<div class="woi-watermark-band woi-watermark-bottom" style="left:0;bottom:' . esc_attr( $this->format_pct( $bottom_band_bottom ) ) . ';width:100%;height:' . esc_attr( $this->format_pct( $bottom_band_height ) ) . ';"><span style="' . $wm_style . '">' . esc_html( $watermark_text ) . '</span></div>';
					echo '<div class="woi-watermark-band woi-watermark-left" style="left:' . esc_attr( $this->format_pct( $left_band_left ) ) . ';top:' . esc_attr( $this->format_pct( $top_pct ) ) . ';width:' . esc_attr( $this->format_pct( $left_band_width ) ) . ';height:' . esc_attr( $this->format_pct( $spec['visible_height_percent'] ) ) . ';"><span style="' . $wm_style . '">' . esc_html( $watermark_text ) . '</span></div>';
					echo '<div class="woi-watermark-band woi-watermark-right" style="right:' . esc_attr( $this->format_pct( $right_band_right ) ) . ';top:' . esc_attr( $this->format_pct( $top_pct ) ) . ';width:' . esc_attr( $this->format_pct( $right_band_width ) ) . ';height:' . esc_attr( $this->format_pct( $spec['visible_height_percent'] ) ) . ';"><span style="' . $wm_style . '">' . esc_html( $watermark_text ) . '</span></div>';
				}

				echo '</div>';
				echo '<div class="woi-cut-outline"><svg viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true"><polygon points="' . esc_attr( $cut_outline ) . '" fill="none" stroke="#000" stroke-width="0.7" vector-effect="non-scaling-stroke" /></svg></div>';
				echo '</div>';
			}

			echo '</div>';
		}

		echo '</body></html>';
		exit;
	}

	private function get_order_item_images( $item ) {
		$images = $item->get_meta( WOI_Order_Images::ORDER_META_IMAGES, true );
		if ( ! empty( $images ) && is_array( $images ) ) {
			$normalized = array();
			foreach ( $images as $image ) {
				if ( ! is_array( $image ) || empty( $image['url'] ) ) {
					continue;
				}

				$normalized[] = array(
					'url'         => esc_url_raw( $image['url'] ),
					'crop'        => isset( $image['crop'] ) && is_array( $image['crop'] ) ? $image['crop'] : array(),
					'puzzle_cols' => isset( $image['puzzle_cols'] ) ? max( 0, (int) $image['puzzle_cols'] ) : 0,
					'puzzle_rows' => isset( $image['puzzle_rows'] ) ? max( 0, (int) $image['puzzle_rows'] ) : 0,
				);
			}

			if ( ! empty( $normalized ) ) {
				return $normalized;
			}
		}

		return array();
	}

	private function get_oriented_spec( $spec, $url ) {
		$ratio = $this->get_image_ratio_from_url( $url );

		if ( null === $ratio || abs( $spec['visible_aspect_ratio'] - 1 ) < 0.0001 ) {
			return $spec;
		}

		$image_landscape   = $ratio >= 1;
		$product_landscape = $spec['visible_aspect_ratio'] >= 1;

		if ( $image_landscape === $product_landscape ) {
			return $spec;
		}

		$swapped = $spec;
		$swapped['visible_width']          = $spec['visible_height'];
		$swapped['visible_height']         = $spec['visible_width'];
		$swapped['full_width']             = $spec['full_height'];
		$swapped['full_height']            = $spec['full_width'];
		$swapped['visible_aspect_ratio']   = 1 / max( 0.0001, $spec['visible_aspect_ratio'] );
		$swapped['visible_width_percent']  = $spec['visible_height_percent'];
		$swapped['visible_height_percent'] = $spec['visible_width_percent'];

		return $swapped;
	}

	private function get_oriented_spec_for_crop( $spec, $url, $crop ) {
		if ( is_array( $crop ) && ! empty( $crop['width'] ) && ! empty( $crop['height'] ) ) {
			$crop_w = (float) $crop['width'];
			$crop_h = (float) $crop['height'];
			if ( $crop_w > 0 && $crop_h > 0 ) {
				$ratio = $crop_w / $crop_h;
				if ( abs( $spec['visible_aspect_ratio'] - 1 ) < 0.0001 ) {
					return $spec;
				}

				$image_landscape   = $ratio >= 1;
				$product_landscape = $spec['visible_aspect_ratio'] >= 1;
				if ( $image_landscape !== $product_landscape ) {
					$swapped = $spec;
					$swapped['visible_width']          = $spec['visible_height'];
					$swapped['visible_height']         = $spec['visible_width'];
					$swapped['full_width']             = $spec['full_height'];
					$swapped['full_height']            = $spec['full_width'];
					$swapped['visible_aspect_ratio']   = 1 / max( 0.0001, $spec['visible_aspect_ratio'] );
					$swapped['visible_width_percent']  = $spec['visible_height_percent'];
					$swapped['visible_height_percent'] = $spec['visible_width_percent'];

					return $swapped;
				}
			}
		}

		return $this->get_oriented_spec( $spec, $url );
	}

	private function build_thumbnail_data_url( $source_url, $crop, $max_side = 220 ) {
		if ( ! is_array( $crop ) || empty( $crop['width'] ) || empty( $crop['height'] ) ) {
			return '';
		}

		$path = $this->url_to_upload_path( $source_url );
		if ( ! $path || ! is_file( $path ) ) {
			return '';
		}

		if ( ! function_exists( 'imagecreatefromstring' ) || ! function_exists( 'imagecreatetruecolor' ) ) {
			return '';
		}

		$raw = @file_get_contents( $path );
		if ( false === $raw || '' === $raw ) {
			return '';
		}

		$source = @imagecreatefromstring( $raw );
		if ( ! $source ) {
			return '';
		}

		$src_w = imagesx( $source );
		$src_h = imagesy( $source );
		if ( $src_w <= 0 || $src_h <= 0 ) {
			imagedestroy( $source );
			return '';
		}

		$rect = $this->normalize_crop_rect( $crop, $src_w, $src_h );
		if ( $rect['w'] <= 0 || $rect['h'] <= 0 ) {
			imagedestroy( $source );
			return '';
		}

		$max_side = max( 1, (int) $max_side );
		$scale    = min( 1, $max_side / max( $rect['w'], $rect['h'] ) );
		$thumb_w  = max( 1, (int) round( $rect['w'] * $scale ) );
		$thumb_h  = max( 1, (int) round( $rect['h'] * $scale ) );

		$thumb = imagecreatetruecolor( $thumb_w, $thumb_h );
		if ( ! $thumb ) {
			imagedestroy( $source );
			return '';
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

		ob_start();
		imagejpeg( $thumb, null, 88 );
		$jpeg = ob_get_clean();

		imagedestroy( $thumb );
		imagedestroy( $source );

		if ( empty( $jpeg ) ) {
			return '';
		}

		return 'data:image/jpeg;base64,' . base64_encode( $jpeg );
	}

	private function build_single_tile_data_url( $source_url, $crop, $spec ) {
		$path = $this->url_to_upload_path( $source_url );
		if ( ! $path || ! is_file( $path ) ) {
			return '';
		}

		if ( ! function_exists( 'imagecreatefromstring' ) || ! function_exists( 'imagecreatetruecolor' ) ) {
			return '';
		}

		$raw = @file_get_contents( $path );
		if ( false === $raw || '' === $raw ) {
			return '';
		}

		$source = @imagecreatefromstring( $raw );
		if ( ! $source ) {
			return '';
		}

		$src_w = imagesx( $source );
		$src_h = imagesy( $source );
		if ( $src_w <= 0 || $src_h <= 0 ) {
			imagedestroy( $source );
			return '';
		}

		$rect = $this->normalize_crop_rect( $crop, $src_w, $src_h );
		if ( $rect['w'] <= 0 || $rect['h'] <= 0 ) {
			imagedestroy( $source );
			return '';
		}

		$target_aspect = max( 0.0001, (float) $spec['visible_width'] / max( 0.0001, (float) $spec['visible_height'] ) );
		$rect          = $this->fit_crop_rect_to_aspect_ratio( $rect, $target_aspect, $src_w, $src_h );

		$visible_w_px = max( 1, (int) round( $rect['w'] ) );
		$visible_h_px = max( 1, (int) round( $rect['h'] ) );

		$visible_width_in  = max( 0.0001, (float) $spec['visible_width'] );
		$visible_height_in = max( 0.0001, (float) $spec['visible_height'] );
		$wrap_margin_in    = max( 0, (float) $spec['wrap_margin'] );

		$scale_x = $visible_w_px / $visible_width_in;
		$scale_y = $visible_h_px / $visible_height_in;

		$bleed_x_px = max( 0, (int) round( $wrap_margin_in * $scale_x ) );
		$bleed_y_px = max( 0, (int) round( $wrap_margin_in * $scale_y ) );

		$full_w_px = $visible_w_px + ( 2 * $bleed_x_px );
		$full_h_px = $visible_h_px + ( 2 * $bleed_y_px );

		$tile_img = imagecreatetruecolor( $full_w_px, $full_h_px );
		if ( ! $tile_img ) {
			imagedestroy( $source );
			return '';
		}

		$white = imagecolorallocate( $tile_img, 255, 255, 255 );
		imagefill( $tile_img, 0, 0, $white );

		imagecopyresampled(
			$tile_img,
			$source,
			$bleed_x_px,
			$bleed_y_px,
			$rect['x'],
			$rect['y'],
			$visible_w_px,
			$visible_h_px,
			$rect['w'],
			$rect['h']
		);

		$this->extend_tile_bleed_from_visible( $tile_img, $bleed_x_px, $bleed_y_px, $visible_w_px, $visible_h_px );

		ob_start();
		imagejpeg( $tile_img, null, 92 );
		$jpeg = ob_get_clean();

		imagedestroy( $tile_img );
		imagedestroy( $source );

		if ( empty( $jpeg ) ) {
			return '';
		}

		return 'data:image/jpeg;base64,' . base64_encode( $jpeg );
	}

	private function get_image_ratio_from_url( $url ) {
		$path = $this->url_to_upload_path( $url );
		if ( ! $path || ! file_exists( $path ) ) {
			return null;
		}

		$size = @getimagesize( $path );
		if ( ! is_array( $size ) || empty( $size[0] ) || empty( $size[1] ) ) {
			return null;
		}

		return (float) $size[0] / (float) $size[1];
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

	private function get_cut_polygon_css( $corner_cut_x_pct, $corner_cut_y_pct ) {
		$right_pct  = 100 - $corner_cut_x_pct;
		$bottom_pct = 100 - $corner_cut_y_pct;

		return implode(
			', ',
			array(
				$this->format_pct( $corner_cut_x_pct ) . ' 0%',
				$this->format_pct( $right_pct ) . ' 0%',
				'100% ' . $this->format_pct( $corner_cut_y_pct ),
				'100% ' . $this->format_pct( $bottom_pct ),
				$this->format_pct( $right_pct ) . ' 100%',
				$this->format_pct( $corner_cut_x_pct ) . ' 100%',
				'0% ' . $this->format_pct( $bottom_pct ),
				'0% ' . $this->format_pct( $corner_cut_y_pct ),
			)
		);
	}

	private function get_cut_polygon_svg( $corner_cut_x_pct, $corner_cut_y_pct ) {
		$right_pct  = 100 - $corner_cut_x_pct;
		$bottom_pct = 100 - $corner_cut_y_pct;

		return implode(
			' ',
			array(
				$this->format_num( $corner_cut_x_pct ) . ',0',
				$this->format_num( $right_pct ) . ',0',
				'100,' . $this->format_num( $corner_cut_y_pct ),
				'100,' . $this->format_num( $bottom_pct ),
				$this->format_num( $right_pct ) . ',100',
				$this->format_num( $corner_cut_x_pct ) . ',100',
				'0,' . $this->format_num( $bottom_pct ),
				'0,' . $this->format_num( $corner_cut_y_pct ),
			)
		);
	}

	private function format_pct( $value ) {
		return rtrim( rtrim( number_format( (float) $value, 3, '.', '' ), '0' ), '.' ) . '%';
	}

	private function format_num( $value ) {
		return rtrim( rtrim( number_format( (float) $value, 3, '.', '' ), '0' ), '.' );
	}

	private function get_item_spec( $item ) {
		$visible_width  = (float) $item->get_meta( WOI_Order_Images::ORDER_META_VISIBLE_WIDTH, true );
		$visible_height = (float) $item->get_meta( WOI_Order_Images::ORDER_META_VISIBLE_HEIGHT, true );
		$wrap_margin    = WOI_Settings::get_print_bleed();
		$is_puzzle      = 'yes' === $item->get_meta( WOI_Order_Images::ORDER_META_IS_PUZZLE, true );
		$puzzle_cols    = max( 1, (int) $item->get_meta( WOI_Order_Images::ORDER_META_PUZZLE_COLS, true ) );
		$puzzle_rows    = max( 1, (int) $item->get_meta( WOI_Order_Images::ORDER_META_PUZZLE_ROWS, true ) );

		if ( $visible_width <= 0 ) {
			$visible_width = 2.0;
		}

		if ( $visible_height <= 0 ) {
			$visible_height = 2.0;
		}

		$full_width  = $visible_width + ( 2 * $wrap_margin );
		$full_height = $visible_height + ( 2 * $wrap_margin );

		return array(
			'visible_width'          => $visible_width,
			'visible_height'         => $visible_height,
			'wrap_margin'            => $wrap_margin,
			'is_puzzle'              => $is_puzzle,
			'puzzle_cols'            => $puzzle_cols,
			'puzzle_rows'            => $puzzle_rows,
			'full_width'             => $full_width,
			'full_height'            => $full_height,
			'visible_aspect_ratio'   => $visible_height > 0 ? $visible_width / $visible_height : 1,
			'visible_width_percent'  => $full_width > 0 ? ( $visible_width / $full_width ) * 100 : 100,
			'visible_height_percent' => $full_height > 0 ? ( $visible_height / $full_height ) * 100 : 100,
		);
	}

	private function resolve_puzzle_grid_for_entry( $spec, $url, $crop, $entry ) {
		$default_cols = max( 1, (int) $spec['puzzle_cols'] );
		$default_rows = max( 1, (int) $spec['puzzle_rows'] );
		$entry_cols   = isset( $entry['puzzle_cols'] ) ? max( 0, (int) $entry['puzzle_cols'] ) : 0;
		$entry_rows   = isset( $entry['puzzle_rows'] ) ? max( 0, (int) $entry['puzzle_rows'] ) : 0;

		if ( $entry_cols > 0 && $entry_rows > 0 ) {
			return array(
				'cols' => $entry_cols,
				'rows' => $entry_rows,
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
			$image_ratio = $this->get_image_ratio_from_url( $url );
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

	private function sanitize_crop_data( $crop ) {
		$x      = isset( $crop['x'] ) ? (float) $crop['x'] : 0.0;
		$y      = isset( $crop['y'] ) ? (float) $crop['y'] : 0.0;
		$width  = isset( $crop['width'] ) ? (float) $crop['width'] : 0.0;
		$height = isset( $crop['height'] ) ? (float) $crop['height'] : 0.0;

		return array(
			'x'      => max( 0, $x ),
			'y'      => max( 0, $y ),
			'width'  => max( 0, $width ),
			'height' => max( 0, $height ),
		);
	}

	private function get_entry_orientation( $spec, $crop, $grid ) {
		if ( ! empty( $spec['is_puzzle'] ) ) {
			$cols = isset( $grid['cols'] ) ? (int) $grid['cols'] : 1;
			$rows = isset( $grid['rows'] ) ? (int) $grid['rows'] : 1;

			return $cols >= $rows ? 'landscape' : 'portrait';
		}

		if ( is_array( $crop ) && ! empty( $crop['width'] ) && ! empty( $crop['height'] ) ) {
			$crop_w = (float) $crop['width'];
			$crop_h = (float) $crop['height'];
			if ( $crop_w > 0 && $crop_h > 0 ) {
				return $crop_w >= $crop_h ? 'landscape' : 'portrait';
			}
		}

		return ! empty( $spec['visible_aspect_ratio'] ) && (float) $spec['visible_aspect_ratio'] >= 1 ? 'landscape' : 'portrait';
	}

	private function is_order_admin_screen() {
		if ( ! is_admin() || ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();
		if ( ! $screen ) {
			return false;
		}

		$screen_id = (string) $screen->id;
		$base      = (string) $screen->base;

		if ( 'shop_order' === $screen_id || 'shop_order' === $base ) {
			return true;
		}

		return false !== strpos( $screen_id, 'wc-orders' );
	}

	private function fit_crop_rect_to_aspect_ratio( $rect, $target_aspect, $src_w, $src_h ) {
		$target_aspect = (float) $target_aspect;
		if ( $target_aspect <= 0 || empty( $rect['w'] ) || empty( $rect['h'] ) ) {
			return $rect;
		}

		$current_aspect = (float) $rect['w'] / max( 0.0001, (float) $rect['h'] );
		if ( abs( $current_aspect - $target_aspect ) < 0.0001 ) {
			return $rect;
		}

		$adjusted = $rect;

		if ( $current_aspect > $target_aspect ) {
			$new_width      = max( 1.0, (float) $rect['h'] * $target_aspect );
			$adjusted['x'] += ( (float) $rect['w'] - $new_width ) / 2;
			$adjusted['w']  = $new_width;
		} else {
			$new_height     = max( 1.0, (float) $rect['w'] / $target_aspect );
			$adjusted['y'] += ( (float) $rect['h'] - $new_height ) / 2;
			$adjusted['h']  = $new_height;
		}

		$adjusted['w'] = min( (float) $adjusted['w'], (float) $src_w );
		$adjusted['h'] = min( (float) $adjusted['h'], (float) $src_h );
		$adjusted['x'] = max( 0.0, min( (float) $adjusted['x'], max( 0.0, (float) $src_w - (float) $adjusted['w'] ) ) );
		$adjusted['y'] = max( 0.0, min( (float) $adjusted['y'], max( 0.0, (float) $src_h - (float) $adjusted['h'] ) ) );

		return array(
			'x' => (int) round( $adjusted['x'] ),
			'y' => (int) round( $adjusted['y'] ),
			'w' => max( 1, (int) round( $adjusted['w'] ) ),
			'h' => max( 1, (int) round( $adjusted['h'] ) ),
		);
	}

	private function build_puzzle_tile_data_url( $source_url, $crop, $spec, $col, $row, $cols, $rows ) {
		$path = $this->url_to_upload_path( $source_url );
		if ( ! $path || ! is_file( $path ) ) {
			return '';
		}

		if ( ! function_exists( 'imagecreatefromstring' ) || ! function_exists( 'imagecreatetruecolor' ) ) {
			return '';
		}

		$raw = @file_get_contents( $path );
		if ( false === $raw || '' === $raw ) {
			return '';
		}

		$source = @imagecreatefromstring( $raw );
		if ( ! $source ) {
			return '';
		}

		$src_w = imagesx( $source );
		$src_h = imagesy( $source );
		if ( $src_w <= 0 || $src_h <= 0 ) {
			imagedestroy( $source );
			return '';
		}

		$rect = $this->normalize_crop_rect( $crop, $src_w, $src_h );
		if ( $rect['w'] <= 0 || $rect['h'] <= 0 ) {
			imagedestroy( $source );
			return '';
		}

		$target_aspect = ( max( 1, (int) $cols ) * max( 0.0001, (float) $spec['visible_width'] ) ) / ( max( 1, (int) $rows ) * max( 0.0001, (float) $spec['visible_height'] ) );
		$rect          = $this->fit_crop_rect_to_aspect_ratio( $rect, $target_aspect, $src_w, $src_h );

		$visible_w_px = max( 1, (int) round( $rect['w'] / max( 1, $cols ) ) );
		$visible_h_px = max( 1, (int) round( $rect['h'] / max( 1, $rows ) ) );

		$visible_width_in  = max( 0.0001, (float) $spec['visible_width'] );
		$visible_height_in = max( 0.0001, (float) $spec['visible_height'] );
		$wrap_margin_in    = max( 0, (float) $spec['wrap_margin'] );

		$scale_x = $visible_w_px / $visible_width_in;
		$scale_y = $visible_h_px / $visible_height_in;

		$bleed_x_px = max( 0, (int) round( $wrap_margin_in * $scale_x ) );
		$bleed_y_px = max( 0, (int) round( $wrap_margin_in * $scale_y ) );

		$overlap_in = 1 / 16;
		$overlap_x_px = max( 1, (int) round( $overlap_in * $scale_x ) );
		$overlap_y_px = max( 1, (int) round( $overlap_in * $scale_y ) );

		$left_overlap   = $col > 0 ? $overlap_x_px : 0;
		$right_overlap  = $col < ( $cols - 1 ) ? $overlap_x_px : 0;
		$top_overlap    = $row > 0 ? $overlap_y_px : 0;
		$bottom_overlap = $row < ( $rows - 1 ) ? $overlap_y_px : 0;

		$src_x0 = $rect['x'] + ( $col * $rect['w'] ) / max( 1, $cols );
		$src_x1 = $rect['x'] + ( ( $col + 1 ) * $rect['w'] ) / max( 1, $cols );
		$src_y0 = $rect['y'] + ( $row * $rect['h'] ) / max( 1, $rows );
		$src_y1 = $rect['y'] + ( ( $row + 1 ) * $rect['h'] ) / max( 1, $rows );

		$full_w_px = $visible_w_px + ( 2 * $bleed_x_px );
		$full_h_px = $visible_h_px + ( 2 * $bleed_y_px );

		$tile_img = imagecreatetruecolor( $full_w_px, $full_h_px );
		if ( ! $tile_img ) {
			imagedestroy( $source );
			return '';
		}

		$white = imagecolorallocate( $tile_img, 255, 255, 255 );
		imagefill( $tile_img, 0, 0, $white );

		$sample_x0 = $src_x0 - $left_overlap;
		$sample_x1 = $src_x1 + $right_overlap;
		$sample_y0 = $src_y0 - $top_overlap;
		$sample_y1 = $src_y1 + $bottom_overlap;

		$sample_x0 = max( 0, $sample_x0 );
		$sample_y0 = max( 0, $sample_y0 );
		$sample_x1 = min( $src_w, $sample_x1 );
		$sample_y1 = min( $src_h, $sample_y1 );

		$sample_w = max( 1, (int) round( $sample_x1 - $sample_x0 ) );
		$sample_h = max( 1, (int) round( $sample_y1 - $sample_y0 ) );

		imagecopyresampled(
			$tile_img,
			$source,
			$bleed_x_px,
			$bleed_y_px,
			(int) round( $sample_x0 ),
			(int) round( $sample_y0 ),
			$visible_w_px,
			$visible_h_px,
			$sample_w,
			$sample_h
		);

		$this->extend_tile_bleed_from_visible( $tile_img, $bleed_x_px, $bleed_y_px, $visible_w_px, $visible_h_px );

		ob_start();
		imagejpeg( $tile_img, null, 92 );
		$jpeg = ob_get_clean();

		imagedestroy( $tile_img );
		imagedestroy( $source );

		if ( empty( $jpeg ) ) {
			return '';
		}

		return 'data:image/jpeg;base64,' . base64_encode( $jpeg );
	}

	private function build_puzzle_segments( $src_start, $src_end, $dest_total, $left_overlap, $right_overlap ) {
		$segments = array();
		$cursor = 0;

		if ( $left_overlap > 0 ) {
			$segments[] = array(
				'dst_start' => $cursor,
				'dst_len'   => $left_overlap,
				'src_start' => $src_start - $left_overlap,
				'src_len'   => $left_overlap,
			);
			$cursor += $left_overlap;
		}

		$center_len = max( 1, $dest_total - $left_overlap - $right_overlap );
		$segments[] = array(
			'dst_start' => $cursor,
			'dst_len'   => $center_len,
			'src_start' => $src_start + $left_overlap,
			'src_len'   => max( 1, ( $src_end - $src_start ) - $left_overlap - $right_overlap ),
		);
		$cursor += $center_len;

		if ( $right_overlap > 0 ) {
			$segments[] = array(
				'dst_start' => $cursor,
				'dst_len'   => $right_overlap,
				'src_start' => $src_end,
				'src_len'   => $right_overlap,
			);
		}

		return $segments;
	}

	private function extend_tile_bleed_from_visible( $tile_img, $bleed_x, $bleed_y, $visible_w, $visible_h ) {
		$full_h = imagesy( $tile_img );
		$vis_x = $bleed_x;
		$vis_y = $bleed_y;

		if ( $bleed_y > 0 ) {
			imagecopyresampled( $tile_img, $tile_img, $vis_x, 0, $vis_x, $vis_y, $visible_w, $bleed_y, $visible_w, 1 );
			imagecopyresampled( $tile_img, $tile_img, $vis_x, $vis_y + $visible_h, $vis_x, $vis_y + $visible_h - 1, $visible_w, $bleed_y, $visible_w, 1 );
		}

		if ( $bleed_x > 0 ) {
			imagecopyresampled( $tile_img, $tile_img, 0, 0, $vis_x, 0, $bleed_x, $full_h, 1, $full_h );
			imagecopyresampled( $tile_img, $tile_img, $vis_x + $visible_w, 0, $vis_x + $visible_w - 1, 0, $bleed_x, $full_h, 1, $full_h );
		}
	}
}
