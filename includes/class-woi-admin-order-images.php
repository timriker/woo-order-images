<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WOI_Admin_Order_Images {
	public function init() {
		add_action( 'woocommerce_after_order_itemmeta', array( $this, 'render_order_item_images' ), 10, 3 );
		add_action( 'woocommerce_order_item_meta_end', array( $this, 'render_frontend_order_item_images' ), 10, 4 );
		add_action( 'admin_post_woi_print_sheet', array( $this, 'render_print_page' ) );
		add_action( 'admin_post_woi_print_image', array( $this, 'render_print_image' ) );
		add_action( 'woocommerce_order_actions_end', array( $this, 'render_order_level_print_button' ), 10, 1 );
		add_filter( 'woocommerce_admin_order_actions', array( $this, 'add_admin_order_actions_print_action' ), 10, 2 );
		add_filter( 'woocommerce_shop_order_list_table_order_actions', array( $this, 'add_order_list_print_action' ), 10, 2 );
		add_filter( 'post_row_actions', array( $this, 'add_legacy_order_row_print_action' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_footer', array( $this, 'render_admin_crop_modal' ) );
		add_action( 'wp_ajax_woi_admin_update_order_crop', array( $this, 'ajax_update_order_crop' ) );
		add_action( 'wp_ajax_woi_admin_get_order_crop_state', array( $this, 'ajax_get_order_crop_state' ) );
		add_action( 'wp_ajax_woi_admin_image_info', array( $this, 'ajax_image_info' ) );
	}

	public function enqueue_admin_assets() {
		if ( ! $this->is_order_admin_screen() ) {
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
				'rotateLabel'           => __( 'Rotate Image', 'woo-order-images' ),
				'viewLabel'             => __( 'View Image', 'woo-order-images' ),
				'menuLabel'             => __( 'Image options', 'woo-order-images' ),
				'saveLabel'             => __( 'Save Crop', 'woo-order-images' ),
				'cancelLabel'           => __( 'Cancel', 'woo-order-images' ),
				'swapPortraitLabel'     => __( 'Swap to Portrait', 'woo-order-images' ),
				'swapLandscapeLabel'    => __( 'Swap to Landscape', 'woo-order-images' ),
				'updateFailed'          => __( 'Unable to save the updated crop right now.', 'woo-order-images' ),
				'loadFailed'            => __( 'Unable to load the latest saved crop right now.', 'woo-order-images' ),
				'zoomLabel'             => __( 'Zoom', 'woo-order-images' ),
				'puzzleGridLabel'       => __( 'puzzle grid', 'woo-order-images' ),
				'swapToGridLabel'       => __( 'Swap to %1$d×%2$d', 'woo-order-images' ),
				'infoLabel'             => __( 'Show Info', 'woo-order-images' ),
				'infoNonce'             => wp_create_nonce( 'woi_admin_image_info' ),
				'stateNonce'            => wp_create_nonce( 'woi_admin_get_order_crop_state' ),
			)
		);
	}

	public function render_order_level_print_button( $order_id ) {
		$order = is_a( $order_id, 'WC_Abstract_Order' ) ? $order_id : wc_get_order( absint( $order_id ) );
		if ( ! $order ) {
			return;
		}

		if ( ! $this->order_has_woi_images( $order ) ) {
			return;
		}

		$print_sheet_url = $this->get_print_sheet_url( $order->get_id() );
		echo '<li style="margin:6px 0 0;padding:0;list-style:none;"><a class="button button-secondary" href="' . esc_url( $print_sheet_url ) . '" target="_blank" rel="noopener noreferrer" style="width:100%;text-align:center;display:block;">' . esc_html__( 'Open Print Sheet', 'woo-order-images' ) . '</a></li>';
	}

	public function add_order_list_print_action( $actions, $order ) {
		if ( ! $order instanceof WC_Order ) {
			return $actions;
		}

		$print_action = $this->get_order_list_print_action( $order );
		if ( empty( $print_action ) ) {
			return $actions;
		}

		$actions['woi_print_sheet'] = $print_action;

		return $actions;
	}

	public function add_admin_order_actions_print_action( $actions, $order ) {
		if ( ! $order instanceof WC_Order ) {
			return $actions;
		}

		$print_action = $this->get_order_list_print_action( $order );
		if ( empty( $print_action ) ) {
			return $actions;
		}

		$actions['woi_print_sheet'] = $print_action;

		return $actions;
	}

	private function get_order_list_print_action( $order ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return array();
		}

		if ( ! $this->order_has_woi_images( $order ) ) {
			return array();
		}

		return array(
			'url'    => $this->get_print_sheet_url( $order->get_id() ),
			'name'   => __( 'Print Sheet', 'woo-order-images' ),
			'action' => 'view',
		);
	}

	public function add_legacy_order_row_print_action( $actions, $post ) {
		if ( ! is_admin() || ! current_user_can( 'manage_woocommerce' ) ) {
			return $actions;
		}

		if ( ! $post instanceof WP_Post || 'shop_order' !== $post->post_type ) {
			return $actions;
		}

		if ( $this->is_hpos_enabled() ) {
			// HPOS list table uses its own actions hook above.
			return $actions;
		}

		$order = wc_get_order( (int) $post->ID );
		if ( ! $order || ! $this->order_has_woi_images( $order ) ) {
			return $actions;
		}

		$actions['woi_print_sheet'] = '<a href="' . esc_url( $this->get_print_sheet_url( $order->get_id() ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Print Sheet', 'woo-order-images' ) . '</a>';

		return $actions;
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
			$rotation    = isset( $entry['rotation'] ) ? WOI_Order_Images::normalize_rotation_value( $entry['rotation'] ) : 0;
			if ( '' === $url ) {
				continue;
			}
			$image_entry = array(
				'url'         => $url,
				'crop'        => $crop,
				'puzzle_cols' => isset( $entry['puzzle_cols'] ) ? max( 0, (int) $entry['puzzle_cols'] ) : 0,
				'puzzle_rows' => isset( $entry['puzzle_rows'] ) ? max( 0, (int) $entry['puzzle_rows'] ) : 0,
				'rotation'    => $rotation,
			);
			$context     = WOI_Order_Images::resolve_thumbnail_context(
				$image_entry,
				$base_spec['visible_width'],
				$base_spec['visible_height'],
				! empty( $base_spec['is_puzzle'] ),
				(int) $base_spec['puzzle_cols'],
				(int) $base_spec['puzzle_rows']
			);
			$index_label = sprintf( __( 'Image %d', 'woo-order-images' ), ( $index + 1 ) );

			$thumbnail_html = WOI_Order_Images::render_thumbnail_html_static( $url, $crop, (int) $context['puzzle_cols'], (int) $context['puzzle_rows'], $context['visible_width'], $context['visible_height'], 72, 'woi-admin', $rotation );

			echo '<span class="woi-admin-image-thumb">';
			echo '<button type="button" class="woi-admin-image-menu-trigger" data-woi-admin-image-menu-trigger'
				. ' data-item-id="' . esc_attr( $item_id ) . '"'
				. ' data-image-index="' . esc_attr( $index ) . '"'
				. ' data-image-url="' . esc_url( $url ) . '"'
				. ' data-image-label="' . esc_attr( $index_label ) . '"'
				. ' data-image-crop="' . esc_attr( wp_json_encode( $crop ) ) . '"'
				. ' data-image-rotation="' . esc_attr( $rotation ) . '"'
				. ' data-current-orientation="' . esc_attr( $context['orientation'] ) . '"'
				. ' data-visible-width="' . esc_attr( $base_spec['visible_width'] ) . '"'
				. ' data-visible-height="' . esc_attr( $base_spec['visible_height'] ) . '"'
				. ' data-visible-width-percent="' . esc_attr( $base_spec['visible_width_percent'] ) . '"'
				. ' data-visible-height-percent="' . esc_attr( $base_spec['visible_height_percent'] ) . '"'
				. ' data-visible-aspect-ratio="' . esc_attr( $base_spec['visible_aspect_ratio'] ) . '"'
				. ' data-is-puzzle="' . ( ! empty( $base_spec['is_puzzle'] ) ? '1' : '0' ) . '"'
				. ' data-puzzle-cols="' . esc_attr( (int) $base_spec['puzzle_cols'] ) . '"'
				. ' data-puzzle-rows="' . esc_attr( (int) $base_spec['puzzle_rows'] ) . '"'
				. ' data-current-puzzle-cols="' . esc_attr( (int) $context['puzzle_cols'] ) . '"'
				. ' data-current-puzzle-rows="' . esc_attr( (int) $context['puzzle_rows'] ) . '"'
				. ' title="' . esc_attr( $index_label ) . '"'
				. ' aria-label="' . esc_attr( sprintf( __( '%s - click for options', 'woo-order-images' ), $index_label ) ) . '">'
				. '<span class="woi-admin-image-frame" data-woi-admin-thumb-frame style="display:block;aspect-ratio:' . esc_attr( $context['frame_ratio'] ) . ';overflow:hidden;border:1px solid #ccd0d4;border-radius:4px;background:#f6f7f7;position:relative;">'
				. $thumbnail_html
				. '</span>'
				. '</button>';
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
					<button type="button" class="button" data-woi-admin-rotate><?php esc_html_e( 'Rotate Image', 'woo-order-images' ); ?></button>
					<button type="button" class="button" data-woi-admin-swap><?php esc_html_e( 'Swap Orientation', 'woo-order-images' ); ?></button>
					<button type="button" class="button" data-woi-admin-close><?php esc_html_e( 'Cancel', 'woo-order-images' ); ?></button>
					<button type="button" class="button button-primary" data-woi-admin-save><?php esc_html_e( 'Save Crop', 'woo-order-images' ); ?></button>
				</div>
			</div>
		</div>

		<div class="woi-admin-info-modal" data-woi-admin-info-modal hidden>
			<div class="woi-admin-crop-backdrop" data-woi-admin-info-close></div>
			<div class="woi-admin-info-content" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Image info', 'woo-order-images' ); ?>">
				<div class="woi-admin-crop-title-row">
					<strong data-woi-admin-info-title><?php esc_html_e( 'Image Info', 'woo-order-images' ); ?></strong>
				</div>
				<p class="woi-admin-info-loading" data-woi-admin-info-loading><?php esc_html_e( 'Loading…', 'woo-order-images' ); ?></p>
				<p class="woi-admin-info-error" data-woi-admin-info-error hidden></p>
				<table class="woi-admin-info-table" data-woi-admin-info-table hidden>
					<tbody>
						<tr class="woi-admin-info-section"><th colspan="2"><?php esc_html_e( 'File', 'woo-order-images' ); ?></th></tr>
						<tr><td><?php esc_html_e( 'Filename', 'woo-order-images' ); ?></td><td><span data-woi-info="filename"></span></td></tr>
						<tr><td><?php esc_html_e( 'File size', 'woo-order-images' ); ?></td><td><span data-woi-info="file_size"></span></td></tr>
						<tr><td><?php esc_html_e( 'Uploaded', 'woo-order-images' ); ?></td><td><span data-woi-info="file_modified"></span></td></tr>
						<tr><td><?php esc_html_e( 'Raw dimensions', 'woo-order-images' ); ?></td><td><span data-woi-info="raw_dims"></span></td></tr>
						<tr class="woi-admin-info-section"><th colspan="2"><?php esc_html_e( 'Orientation', 'woo-order-images' ); ?></th></tr>
						<tr><td><?php esc_html_e( 'EXIF flag', 'woo-order-images' ); ?></td><td><span data-woi-info="exif_orientation"></span></td></tr>
						<tr><td><?php esc_html_e( 'EXIF date', 'woo-order-images' ); ?></td><td><span data-woi-info="exif_date"></span></td></tr>
						<tr><td><?php esc_html_e( 'GPS location', 'woo-order-images' ); ?></td><td><span data-woi-info="gps_location"></span></td></tr>
						<tr><td><?php esc_html_e( 'Store rotation', 'woo-order-images' ); ?></td><td><span data-woi-info="woi_rotation"></span></td></tr>
						<tr><td><?php esc_html_e( 'Effective dimensions', 'woo-order-images' ); ?></td><td><span data-woi-info="eff_dims"></span></td></tr>
						<tr class="woi-admin-info-section"><th colspan="2"><?php esc_html_e( 'Crop', 'woo-order-images' ); ?></th></tr>
						<tr><td><?php esc_html_e( 'Origin (x, y)', 'woo-order-images' ); ?></td><td><span data-woi-info="crop_origin"></span></td></tr>
						<tr><td><?php esc_html_e( 'Size (w × h)', 'woo-order-images' ); ?></td><td><span data-woi-info="crop_size"></span></td></tr>
						<tr><td><?php esc_html_e( 'Crop aspect ratio', 'woo-order-images' ); ?></td><td><span data-woi-info="crop_aspect"></span></td></tr>
						<tr class="woi-admin-info-section"><th colspan="2"><?php esc_html_e( 'Product', 'woo-order-images' ); ?></th></tr>
						<tr><td><?php esc_html_e( 'Visible print size', 'woo-order-images' ); ?></td><td><span data-woi-info="product_vis"></span></td></tr>
						<tr><td><?php esc_html_e( 'Full size (with bleed)', 'woo-order-images' ); ?></td><td><span data-woi-info="product_full"></span></td></tr>
						<tr><td><?php esc_html_e( 'Product aspect ratio', 'woo-order-images' ); ?></td><td><span data-woi-info="product_aspect"></span></td></tr>
						<tr class="woi-admin-info-section"><th colspan="2"><?php esc_html_e( 'Print Resolution', 'woo-order-images' ); ?></th></tr>
						<tr><td><?php esc_html_e( 'Estimated DPI', 'woo-order-images' ); ?></td><td><span data-woi-info="print_dpi"></span></td></tr>
					</tbody>
				</table>
				<div class="woi-admin-crop-actions">
					<button type="button" class="button" data-woi-admin-info-close><?php esc_html_e( 'Close', 'woo-order-images' ); ?></button>
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
		$rotation    = isset( $_POST['rotation'] ) ? WOI_Order_Images::normalize_rotation_value( wp_unslash( $_POST['rotation'] ) ) : 0;

		$item = $this->get_order_item_by_id( $item_id );
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
		if ( $rotation > 0 ) {
			$images[ $image_index ]['rotation'] = $rotation;
		} else {
			unset( $images[ $image_index ]['rotation'] );
		}

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
		$rotation   = isset( $entry['rotation'] ) ? WOI_Order_Images::normalize_rotation_value( $entry['rotation'] ) : 0;
		$context    = WOI_Order_Images::resolve_thumbnail_context(
			$entry,
			$base_spec['visible_width'],
			$base_spec['visible_height'],
			! empty( $base_spec['is_puzzle'] ),
			(int) $base_spec['puzzle_cols'],
			(int) $base_spec['puzzle_rows']
		);
		$thumb_src  = WOI_Order_Images::build_thumbnail_image_url( $url, $crop, 240, (int) $context['puzzle_cols'], (int) $context['puzzle_rows'], $rotation );

		if ( '' === $thumb_src ) {
			$thumb_src = $url;
		}

		wp_send_json_success(
			array(
				'crop'                 => $crop,
				'rotation'             => $rotation,
				'thumbSrc'             => $thumb_src,
				'visibleAspectRatio'   => $context['frame_ratio'],
				'puzzleCols'           => (int) $context['puzzle_cols'],
				'puzzleRows'           => (int) $context['puzzle_rows'],
				'currentOrientation'   => $context['orientation'],
			)
		);
	}

	public function ajax_get_order_crop_state() {
		check_ajax_referer( 'woi_admin_get_order_crop_state', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission to view order images.', 'woo-order-images' ),
				),
				403
			);
		}

		$item_id     = isset( $_POST['item_id'] ) ? absint( wp_unslash( $_POST['item_id'] ) ) : 0;
		$image_index = isset( $_POST['image_index'] ) ? absint( wp_unslash( $_POST['image_index'] ) ) : -1;

		$item = $this->get_order_item_by_id( $item_id );
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

		$entry     = $images[ $image_index ];
		$url       = isset( $entry['url'] ) ? (string) $entry['url'] : '';
		$crop      = isset( $entry['crop'] ) && is_array( $entry['crop'] ) ? $entry['crop'] : array();
		$rotation  = isset( $entry['rotation'] ) ? WOI_Order_Images::normalize_rotation_value( $entry['rotation'] ) : 0;
		$base_spec = $this->get_item_spec( $item );
		$context   = WOI_Order_Images::resolve_thumbnail_context(
			$entry,
			$base_spec['visible_width'],
			$base_spec['visible_height'],
			! empty( $base_spec['is_puzzle'] ),
			(int) $base_spec['puzzle_cols'],
			(int) $base_spec['puzzle_rows']
		);

		wp_send_json_success(
			array(
				'imageUrl'           => $url,
				'crop'               => $crop,
				'rotation'           => $rotation,
				'currentOrientation' => $context['orientation'],
				'puzzleCols'         => (int) $context['puzzle_cols'],
				'puzzleRows'         => (int) $context['puzzle_rows'],
			)
		);
	}

	private function get_thumbnail_frame_aspect_ratio( $spec, $grid, $is_puzzle ) {
		if ( ! $is_puzzle ) {
			return max( 0.0001, (float) $spec['visible_aspect_ratio'] );
		}

		$cols = isset( $grid['cols'] ) ? max( 1, (int) $grid['cols'] ) : 1;
		$rows = isset( $grid['rows'] ) ? max( 1, (int) $grid['rows'] ) : 1;

		$visible_width  = isset( $spec['visible_width'] ) ? max( 0.0001, (float) $spec['visible_width'] ) : 1.0;
		$visible_height = isset( $spec['visible_height'] ) ? max( 0.0001, (float) $spec['visible_height'] ) : 1.0;

		$overall_width  = $visible_width * $cols;
		$overall_height = $visible_height * $rows;

		if ( $overall_width <= 0 || $overall_height <= 0 ) {
			return max( 0.0001, (float) $spec['visible_aspect_ratio'] );
		}

		return max( 0.0001, $overall_width / $overall_height );
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
			$rotation    = isset( $entry['rotation'] ) ? WOI_Order_Images::normalize_rotation_value( $entry['rotation'] ) : 0;
			if ( '' === $url ) {
				continue;
			}
			$context     = WOI_Order_Images::resolve_thumbnail_context(
				$entry,
				$base_spec['visible_width'],
				$base_spec['visible_height'],
				! empty( $base_spec['is_puzzle'] ),
				(int) $base_spec['puzzle_cols'],
				(int) $base_spec['puzzle_rows']
			);
			$thumbnail_html = WOI_Order_Images::render_thumbnail_html_static( $url, $crop, (int) $context['puzzle_cols'], (int) $context['puzzle_rows'], $context['visible_width'], $context['visible_height'], 68, 'woi-history', $rotation );
			$index_label = sprintf( __( 'Image %d', 'woo-order-images' ), ( $index + 1 ) );
			echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer" title="' . esc_attr( $index_label ) . '">';
			echo '<span style="display:block;line-height:0;position:relative;overflow:hidden;border:1px solid #ccd0d4;border-radius:4px;background:#f6f7f7;">';
			echo $thumbnail_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
				$rotation = isset( $image_entry['rotation'] ) ? WOI_Order_Images::normalize_rotation_value( $image_entry['rotation'] ) : 0;
				if ( '' === $url ) {
					continue;
				}

				if ( ! empty( $base_spec['is_puzzle'] ) ) {
					$grid = $this->resolve_puzzle_grid_for_entry( $base_spec, $url, $crop, $image_entry );
					$cols = $grid['cols'];
					$rows = $grid['rows'];
					for ( $row = 0; $row < $rows; $row++ ) {
						for ( $col = 0; $col < $cols; $col++ ) {
								$entries[] = array(
									'url'  => $this->get_print_image_url( $order->get_id(), $item->get_id(), $image_entry, $base_spec, $row, $col, $cols, $rows ),
									'spec' => $base_spec,
								);
						}
					}
				} else {
					$oriented_spec = $this->get_oriented_spec_for_crop( $base_spec, $url, $crop, $rotation );
					$entries[] = array(
						'url'  => $this->get_print_image_url( $order->get_id(), $item->get_id(), $image_entry, $oriented_spec ),
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

	public function render_print_image() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this image.', 'woo-order-images' ) );
		}

		$order_id    = isset( $_GET['order_id'] ) ? absint( wp_unslash( $_GET['order_id'] ) ) : 0;
		$item_id     = isset( $_GET['item_id'] ) ? absint( wp_unslash( $_GET['item_id'] ) ) : 0;
		$image_index = isset( $_GET['image_index'] ) ? absint( wp_unslash( $_GET['image_index'] ) ) : -1;
		$row         = isset( $_GET['row'] ) ? absint( wp_unslash( $_GET['row'] ) ) : -1;
		$col         = isset( $_GET['col'] ) ? absint( wp_unslash( $_GET['col'] ) ) : -1;

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_die( esc_html__( 'Order not found.', 'woo-order-images' ) );
		}

		$item = $order->get_item( $item_id );
		if ( ! $item instanceof WC_Order_Item_Product ) {
			wp_die( esc_html__( 'Order item not found.', 'woo-order-images' ) );
		}

		$images = $this->get_order_item_images( $item );
		if ( ! isset( $images[ $image_index ] ) ) {
			wp_die( esc_html__( 'Order image not found.', 'woo-order-images' ) );
		}

		$image_entry = $images[ $image_index ];
		$url         = isset( $image_entry['url'] ) ? (string) $image_entry['url'] : '';
		$crop        = isset( $image_entry['crop'] ) && is_array( $image_entry['crop'] ) ? $image_entry['crop'] : array();
		$rotation    = isset( $image_entry['rotation'] ) ? WOI_Order_Images::normalize_rotation_value( $image_entry['rotation'] ) : 0;
		if ( '' === $url ) {
			wp_die( esc_html__( 'Order image not found.', 'woo-order-images' ) );
		}

		$base_spec = $this->get_item_spec( $item );
		if ( ! empty( $base_spec['is_puzzle'] ) ) {
			$grid = $this->resolve_puzzle_grid_for_entry( $base_spec, $url, $crop, $image_entry );
			if ( $row < 0 || $col < 0 || $row >= $grid['rows'] || $col >= $grid['cols'] ) {
				wp_die( esc_html__( 'Puzzle tile not found.', 'woo-order-images' ) );
			}

			$cache_key = $this->get_print_image_cache_key( $image_entry, $base_spec, $row, $col, $grid['cols'], $grid['rows'] );
			$jpeg = $this->build_puzzle_tile_jpeg( $url, $crop, $base_spec, $col, $row, $grid['cols'], $grid['rows'], $rotation );
		} else {
			$spec = $this->get_oriented_spec_for_crop( $base_spec, $url, $crop, $rotation );
			$cache_key = $this->get_print_image_cache_key( $image_entry, $spec );
			$jpeg = $this->build_single_tile_jpeg( $url, $crop, $spec, $rotation );
		}

		if ( empty( $jpeg ) ) {
			wp_die( esc_html__( 'Unable to generate the print image.', 'woo-order-images' ) );
		}

		$etag = '"' . $cache_key . '"';
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
					'rotation'    => isset( $image['rotation'] ) ? WOI_Order_Images::normalize_rotation_value( $image['rotation'] ) : 0,
				);
			}

			if ( ! empty( $normalized ) ) {
				return $normalized;
			}
		}

		return array();
	}

	private function order_has_woi_images( $order ) {
		if ( ! $order instanceof WC_Abstract_Order ) {
			return false;
		}

		foreach ( $order->get_items() as $item ) {
			if ( $item instanceof WC_Order_Item_Product ) {
				$images = $this->get_order_item_images( $item );
				if ( ! empty( $images ) ) {
					return true;
				}
			}
		}

		return false;
	}

	private function get_print_sheet_url( $order_id ) {
		return add_query_arg(
			array(
				'action'   => 'woi_print_sheet',
				'order_id' => absint( $order_id ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	private function get_oriented_spec( $spec, $url, $rotation = 0 ) {
		$ratio = $this->get_image_ratio_from_url( $url, $rotation );

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

	private function get_oriented_spec_for_crop( $spec, $url, $crop, $rotation = 0 ) {
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

				return $spec;
			}
		}

		return $this->get_oriented_spec( $spec, $url, $rotation );
	}


	private function build_single_tile_jpeg( $source_url, $crop, $spec, $rotation = 0 ) {
		$prepared = WOI_Order_Images::load_transformed_source_from_url( $source_url, $rotation );
		if ( ! is_array( $prepared ) || empty( $prepared['image'] ) ) {
			return null;
		}

		$source = $prepared['image'];
		$src_w = (int) $prepared['width'];
		$src_h = (int) $prepared['height'];
		if ( $src_w <= 0 || $src_h <= 0 ) {
			imagedestroy( $source );
			return null;
		}

		$rect = $this->normalize_crop_rect( $crop, $src_w, $src_h );
		if ( $rect['w'] <= 0 || $rect['h'] <= 0 ) {
			imagedestroy( $source );
			return null;
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
			return null;
		}

		$white = imagecolorallocate( $tile_img, 255, 255, 255 );
		imagefill( $tile_img, 0, 0, $white );

		$this->copy_tile_with_source_bleed(
			$tile_img,
			$source,
			(float) $rect['x'],
			(float) $rect['y'],
			(float) $rect['x'] + (float) $rect['w'],
			(float) $rect['y'] + (float) $rect['h'],
			$visible_w_px,
			$visible_h_px,
			$bleed_x_px,
			$bleed_y_px,
			$src_w,
			$src_h
		);

		ob_start();
		imagejpeg( $tile_img, null, 92 );
		$jpeg = ob_get_clean();

		imagedestroy( $tile_img );
		imagedestroy( $source );

		return empty( $jpeg ) ? null : $jpeg;
	}

	private function get_image_ratio_from_url( $url, $rotation = 0 ) {
		return WOI_Order_Images::get_transformed_image_ratio_from_url( $url, $rotation );
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
		$puzzle_cols    = $is_puzzle ? max( 1, (int) $item->get_meta( WOI_Order_Images::ORDER_META_PUZZLE_COLS, true ) ) : 0;
		$puzzle_rows    = $is_puzzle ? max( 1, (int) $item->get_meta( WOI_Order_Images::ORDER_META_PUZZLE_ROWS, true ) ) : 0;

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
		if ( empty( $spec['is_puzzle'] ) ) {
			return array(
				'cols' => 0,
				'rows' => 0,
			);
		}

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
			$rotation = isset( $entry['rotation'] ) ? WOI_Order_Images::normalize_rotation_value( $entry['rotation'] ) : 0;
			$image_ratio = $this->get_image_ratio_from_url( $url, $rotation );
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

	private function is_hpos_enabled() {
		if ( ! function_exists( 'wc_get_container' ) ) {
			return false;
		}

		if ( ! class_exists( '\\Automattic\\WooCommerce\\Internal\\DataStores\\Orders\\CustomOrdersTableController' ) ) {
			return false;
		}

		$controller = wc_get_container()->get( '\\Automattic\\WooCommerce\\Internal\\DataStores\\Orders\\CustomOrdersTableController' );
		if ( ! $controller || ! method_exists( $controller, 'custom_orders_table_usage_is_enabled' ) ) {
			return false;
		}

		return (bool) $controller->custom_orders_table_usage_is_enabled();
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

	private function build_puzzle_tile_jpeg( $source_url, $crop, $spec, $col, $row, $cols, $rows, $rotation = 0 ) {
		$prepared = WOI_Order_Images::load_transformed_source_from_url( $source_url, $rotation );
		if ( ! is_array( $prepared ) || empty( $prepared['image'] ) ) {
			return null;
		}

		$source = $prepared['image'];
		$src_w = (int) $prepared['width'];
		$src_h = (int) $prepared['height'];
		if ( $src_w <= 0 || $src_h <= 0 ) {
			imagedestroy( $source );
			return null;
		}

		$rect = $this->normalize_crop_rect( $crop, $src_w, $src_h );
		if ( $rect['w'] <= 0 || $rect['h'] <= 0 ) {
			imagedestroy( $source );
			return null;
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

		$src_x0 = $rect['x'] + ( $col * $rect['w'] ) / max( 1, $cols );
		$src_x1 = $rect['x'] + ( ( $col + 1 ) * $rect['w'] ) / max( 1, $cols );
		$src_y0 = $rect['y'] + ( $row * $rect['h'] ) / max( 1, $rows );
		$src_y1 = $rect['y'] + ( ( $row + 1 ) * $rect['h'] ) / max( 1, $rows );

		$full_w_px = $visible_w_px + ( 2 * $bleed_x_px );
		$full_h_px = $visible_h_px + ( 2 * $bleed_y_px );

		$tile_img = imagecreatetruecolor( $full_w_px, $full_h_px );
		if ( ! $tile_img ) {
			imagedestroy( $source );
			return null;
		}

		$white = imagecolorallocate( $tile_img, 255, 255, 255 );
		imagefill( $tile_img, 0, 0, $white );

		$this->copy_tile_with_source_bleed(
			$tile_img,
			$source,
			$src_x0,
			$src_y0,
			$src_x1,
			$src_y1,
			$visible_w_px,
			$visible_h_px,
			$bleed_x_px,
			$bleed_y_px,
			$src_w,
			$src_h
		);

		ob_start();
		imagejpeg( $tile_img, null, 92 );
		$jpeg = ob_get_clean();

		imagedestroy( $tile_img );
		imagedestroy( $source );

		return empty( $jpeg ) ? null : $jpeg;
	}

	private function get_print_image_url( $order_id, $item_id, $image_entry, $spec, $row = null, $col = null, $resolved_cols = null, $resolved_rows = null ) {
		$order  = wc_get_order( $order_id );
		$item   = $order ? $order->get_item( $item_id ) : null;
		$images = $item instanceof WC_Order_Item_Product ? $this->get_order_item_images( $item ) : array();
		$index  = array_search( $image_entry, $images, true );

		$args = array(
			'action'      => 'woi_print_image',
			'order_id'    => $order_id,
			'item_id'     => $item_id,
			'image_index' => false === $index ? 0 : (int) $index,
		);

		if ( ! empty( $spec['is_puzzle'] ) && null !== $row && null !== $col ) {
			$args['row'] = (int) $row;
			$args['col'] = (int) $col;
		}

		$args['v'] = $this->get_print_image_cache_key( $image_entry, $spec, $row, $col, $resolved_cols, $resolved_rows );

		return add_query_arg( $args, admin_url( 'admin-post.php' ) );
	}

	private function get_print_image_cache_key( $image_entry, $spec, $row = null, $col = null, $resolved_cols = null, $resolved_rows = null ) {
		$payload = array(
			'url'           => isset( $image_entry['url'] ) ? (string) $image_entry['url'] : '',
			'crop'          => isset( $image_entry['crop'] ) && is_array( $image_entry['crop'] ) ? $image_entry['crop'] : array(),
			'rotation'      => isset( $image_entry['rotation'] ) ? WOI_Order_Images::normalize_rotation_value( $image_entry['rotation'] ) : 0,
			'puzzle_cols'   => isset( $image_entry['puzzle_cols'] ) ? (int) $image_entry['puzzle_cols'] : 0,
			'puzzle_rows'   => isset( $image_entry['puzzle_rows'] ) ? (int) $image_entry['puzzle_rows'] : 0,
			'resolved_cols' => null === $resolved_cols ? null : (int) $resolved_cols,
			'resolved_rows' => null === $resolved_rows ? null : (int) $resolved_rows,
			'row'           => null === $row ? null : (int) $row,
			'col'           => null === $col ? null : (int) $col,
			'spec'          => array(
				'is_puzzle'              => ! empty( $spec['is_puzzle'] ),
				'visible_width'          => isset( $spec['visible_width'] ) ? (float) $spec['visible_width'] : 0.0,
				'visible_height'         => isset( $spec['visible_height'] ) ? (float) $spec['visible_height'] : 0.0,
				'wrap_margin'            => isset( $spec['wrap_margin'] ) ? (float) $spec['wrap_margin'] : 0.0,
				'full_width'             => isset( $spec['full_width'] ) ? (float) $spec['full_width'] : 0.0,
				'full_height'            => isset( $spec['full_height'] ) ? (float) $spec['full_height'] : 0.0,
				'visible_width_percent'  => isset( $spec['visible_width_percent'] ) ? (float) $spec['visible_width_percent'] : 0.0,
				'visible_height_percent' => isset( $spec['visible_height_percent'] ) ? (float) $spec['visible_height_percent'] : 0.0,
				'puzzle_cols'            => isset( $spec['puzzle_cols'] ) ? (int) $spec['puzzle_cols'] : 0,
				'puzzle_rows'            => isset( $spec['puzzle_rows'] ) ? (int) $spec['puzzle_rows'] : 0,
			),
		);

		return substr( md5( wp_json_encode( $payload ) ), 0, 12 );
	}

	public function ajax_image_info() {
		check_ajax_referer( 'woi_admin_image_info', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission.', 'woo-order-images' ) ), 403 );
		}

		$item_id     = isset( $_POST['item_id'] ) ? absint( wp_unslash( $_POST['item_id'] ) ) : 0;
		$image_index = isset( $_POST['image_index'] ) ? absint( wp_unslash( $_POST['image_index'] ) ) : 0;

		$item = $this->get_order_item_by_id( $item_id );
		if ( ! $item instanceof WC_Order_Item_Product ) {
			wp_send_json_error( array( 'message' => __( 'Order item not found.', 'woo-order-images' ) ) );
		}

		$images = $this->get_order_item_images( $item );
		if ( ! isset( $images[ $image_index ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Image not found.', 'woo-order-images' ) ) );
		}

		$entry    = $images[ $image_index ];
		$url      = isset( $entry['url'] ) ? (string) $entry['url'] : '';
		$crop     = isset( $entry['crop'] ) && is_array( $entry['crop'] ) ? $entry['crop'] : array();
		$rotation = isset( $entry['rotation'] ) ? WOI_Order_Images::normalize_rotation_value( $entry['rotation'] ) : 0;

		if ( '' === $url ) {
			wp_send_json_error( array( 'message' => __( 'Image URL missing.', 'woo-order-images' ) ) );
		}

		$path = $this->url_to_upload_path( $url );

		// File info
		$file_size     = ( $path && file_exists( $path ) ) ? filesize( $path ) : null;
		$file_modified = ( $path && file_exists( $path ) ) ? filemtime( $path ) : null;
		$raw_size      = ( $path && file_exists( $path ) ) ? @getimagesize( $path ) : null;
		$raw_w         = is_array( $raw_size ) ? (int) $raw_size[0] : null;
		$raw_h         = is_array( $raw_size ) ? (int) $raw_size[1] : null;

		// EXIF orientation
		$exif_orientation = 1;
		$exif_data        = null;
		$exif_date        = null;
		$gps_lat          = null;
		$gps_lng          = null;
		if ( $path && file_exists( $path ) && function_exists( 'exif_read_data' ) ) {
			$img_type = @exif_imagetype( $path );
			if ( IMAGETYPE_JPEG === $img_type ) {
				$exif_data = @exif_read_data( $path );
				if ( is_array( $exif_data ) && ! empty( $exif_data['Orientation'] ) ) {
					$val = (int) $exif_data['Orientation'];
					if ( $val >= 1 && $val <= 8 ) {
						$exif_orientation = $val;
					}
				}
				if ( is_array( $exif_data ) ) {
					$date_fields = array( 'DateTimeOriginal', 'DateTimeDigitized', 'DateTime' );
					foreach ( $date_fields as $field ) {
						if ( ! empty( $exif_data[ $field ] ) && is_string( $exif_data[ $field ] ) ) {
							$raw_date = trim( $exif_data[ $field ] );
							$timestamp = strtotime( str_replace( ':', '-', substr( $raw_date, 0, 10 ) ) . substr( $raw_date, 10 ) );
							$exif_date = $timestamp ? gmdate( 'Y-m-d H:i', $timestamp ) . ' UTC' : $raw_date;
							break;
						}
					}

					if ( ! empty( $exif_data['GPSLatitude'] ) && ! empty( $exif_data['GPSLatitudeRef'] ) && ! empty( $exif_data['GPSLongitude'] ) && ! empty( $exif_data['GPSLongitudeRef'] ) ) {
						$gps_lat = $this->exif_gps_coordinate_to_decimal( $exif_data['GPSLatitude'], $exif_data['GPSLatitudeRef'] );
						$gps_lng = $this->exif_gps_coordinate_to_decimal( $exif_data['GPSLongitude'], $exif_data['GPSLongitudeRef'] );
					}
				}
			}
		}

		$exif_orientation_map = array(
			1 => __( 'Normal (no rotation needed)', 'woo-order-images' ),
			2 => __( 'Mirror horizontal', 'woo-order-images' ),
			3 => __( 'Rotate 180°', 'woo-order-images' ),
			4 => __( 'Mirror vertical', 'woo-order-images' ),
			5 => __( 'Mirror horizontal + rotate 270° CW', 'woo-order-images' ),
			6 => __( 'Rotate 90° CW', 'woo-order-images' ),
			7 => __( 'Mirror horizontal + rotate 90° CW', 'woo-order-images' ),
			8 => __( 'Rotate 270° CW', 'woo-order-images' ),
		);
		$exif_desc = isset( $exif_orientation_map[ $exif_orientation ] ) ? $exif_orientation_map[ $exif_orientation ] : __( 'Unknown', 'woo-order-images' );

		// Effective dimensions after EXIF normalisation + WOI rotation
		$eff_w = $raw_w;
		$eff_h = $raw_h;
		if ( null !== $eff_w && null !== $eff_h ) {
			if ( in_array( $exif_orientation, array( 5, 6, 7, 8 ), true ) ) {
				list( $eff_w, $eff_h ) = array( $eff_h, $eff_w );
			}
			if ( in_array( $rotation, array( 90, 270 ), true ) ) {
				list( $eff_w, $eff_h ) = array( $eff_h, $eff_w );
			}
		}

		// Crop info
		$crop_x      = isset( $crop['x'] ) ? (float) $crop['x'] : null;
		$crop_y      = isset( $crop['y'] ) ? (float) $crop['y'] : null;
		$crop_w      = isset( $crop['width'] ) ? (float) $crop['width'] : null;
		$crop_h      = isset( $crop['height'] ) ? (float) $crop['height'] : null;
		$crop_aspect = ( null !== $crop_w && null !== $crop_h && $crop_h > 0 )
			? round( $crop_w / $crop_h, 4 )
			: null;

		// Product spec
		$base_spec      = $this->get_item_spec( $item );
		$spec           = $this->get_oriented_spec_for_crop( $base_spec, $url, $crop, $rotation );
		$product_aspect = ( $spec['visible_width'] > 0 && $spec['visible_height'] > 0 )
			? round( (float) $spec['visible_width'] / (float) $spec['visible_height'], 4 )
			: null;

		// Print DPI estimate
		$print_dpi   = null;
		$dpi_quality = null;
		if ( null !== $crop_w && null !== $crop_h && $spec['visible_width'] > 0 && $spec['visible_height'] > 0 ) {
			$dpi_x     = $crop_w / (float) $spec['visible_width'];
			$dpi_y     = $crop_h / (float) $spec['visible_height'];
			$print_dpi = (int) round( min( $dpi_x, $dpi_y ) );
			if ( $print_dpi >= 300 ) {
				$dpi_quality = 'excellent';
			} elseif ( $print_dpi >= 200 ) {
				$dpi_quality = 'good';
			} elseif ( $print_dpi >= 150 ) {
				$dpi_quality = 'fair';
			} else {
				$dpi_quality = 'low';
			}
		}

		wp_send_json_success(
			array(
				'filename'         => basename( $url ),
				'image_url'        => $url,
				'file_size'        => $file_size,
				'file_modified'    => $file_modified ? gmdate( 'Y-m-d H:i', $file_modified ) . ' UTC' : null,
				'raw_width'        => $raw_w,
				'raw_height'       => $raw_h,
				'exif_orientation' => $exif_orientation,
				'exif_desc'        => $exif_desc,
				'exif_date'        => $exif_date,
				'gps_lat'          => $gps_lat,
				'gps_lng'          => $gps_lng,
				'woi_rotation'     => $rotation,
				'eff_width'        => $eff_w,
				'eff_height'       => $eff_h,
				'crop_x'           => $crop_x,
				'crop_y'           => $crop_y,
				'crop_w'           => $crop_w,
				'crop_h'           => $crop_h,
				'crop_aspect'      => $crop_aspect,
				'product_vis_w'    => $spec['visible_width'],
				'product_vis_h'    => $spec['visible_height'],
				'product_full_w'   => isset( $spec['full_width'] ) ? $spec['full_width'] : null,
				'product_full_h'   => isset( $spec['full_height'] ) ? $spec['full_height'] : null,
				'product_aspect'   => $product_aspect,
				'wrap_margin'      => isset( $spec['wrap_margin'] ) ? $spec['wrap_margin'] : null,
				'print_dpi'        => $print_dpi,
				'dpi_quality'      => $dpi_quality,
			)
		);
	}

	private function exif_gps_coordinate_to_decimal( $coordinate, $hemisphere ) {
		if ( ! is_array( $coordinate ) || count( $coordinate ) < 3 ) {
			return null;
		}

		$degrees = $this->exif_fraction_to_float( $coordinate[0] );
		$minutes = $this->exif_fraction_to_float( $coordinate[1] );
		$seconds = $this->exif_fraction_to_float( $coordinate[2] );
		if ( null === $degrees || null === $minutes || null === $seconds ) {
			return null;
		}

		$decimal = $degrees + ( $minutes / 60 ) + ( $seconds / 3600 );
		$hemisphere = strtoupper( (string) $hemisphere );
		if ( in_array( $hemisphere, array( 'S', 'W' ), true ) ) {
			$decimal *= -1;
		}

		return round( $decimal, 6 );
	}

	private function exif_fraction_to_float( $value ) {
		if ( is_numeric( $value ) ) {
			return (float) $value;
		}

		if ( ! is_string( $value ) || false === strpos( $value, '/' ) ) {
			return null;
		}

		list( $numerator, $denominator ) = array_pad( explode( '/', $value, 2 ), 2, null );
		$numerator   = is_numeric( $numerator ) ? (float) $numerator : null;
		$denominator = is_numeric( $denominator ) ? (float) $denominator : null;
		if ( null === $numerator || null === $denominator || 0.0 === $denominator ) {
			return null;
		}

		return $numerator / $denominator;
	}

	private function get_order_item_by_id( $item_id ) {
		$item_id = absint( $item_id );
		if ( $item_id <= 0 ) {
			return null;
		}

		global $wpdb;

		$order_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT order_id FROM {$wpdb->prefix}woocommerce_order_items WHERE order_item_id = %d LIMIT 1",
				$item_id
			)
		);

		if ( $order_id <= 0 ) {
			return null;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return null;
		}

		$item = $order->get_item( $item_id );
		return $item instanceof WC_Order_Item_Product ? $item : null;
	}

	private function copy_tile_with_source_bleed( $tile_img, $source, $src_x0, $src_y0, $src_x1, $src_y1, $visible_w, $visible_h, $bleed_x, $bleed_y, $src_w, $src_h ) {
		$src_x0 = max( 0.0, (float) $src_x0 );
		$src_y0 = max( 0.0, (float) $src_y0 );
		$src_x1 = min( (float) $src_w, (float) $src_x1 );
		$src_y1 = min( (float) $src_h, (float) $src_y1 );

		$available_left   = min( $bleed_x, max( 0, (int) floor( $src_x0 ) ) );
		$available_top    = min( $bleed_y, max( 0, (int) floor( $src_y0 ) ) );
		$available_right  = min( $bleed_x, max( 0, (int) floor( $src_w - $src_x1 ) ) );
		$available_bottom = min( $bleed_y, max( 0, (int) floor( $src_h - $src_y1 ) ) );

		$sample_x0 = max( 0, (int) round( $src_x0 - $available_left ) );
		$sample_y0 = max( 0, (int) round( $src_y0 - $available_top ) );
		$sample_x1 = min( $src_w, (int) round( $src_x1 + $available_right ) );
		$sample_y1 = min( $src_h, (int) round( $src_y1 + $available_bottom ) );

		$sample_w = max( 1, $sample_x1 - $sample_x0 );
		$sample_h = max( 1, $sample_y1 - $sample_y0 );
		$dest_x   = max( 0, $bleed_x - $available_left );
		$dest_y   = max( 0, $bleed_y - $available_top );
		$dest_w   = max( 1, $available_left + $visible_w + $available_right );
		$dest_h   = max( 1, $available_top + $visible_h + $available_bottom );

		imagecopyresampled(
			$tile_img,
			$source,
			$dest_x,
			$dest_y,
			$sample_x0,
			$sample_y0,
			$dest_w,
			$dest_h,
			$sample_w,
			$sample_h
		);

		$this->extend_tile_edges( $tile_img, $dest_x, $dest_y, $dest_w, $dest_h );
	}

	private function extend_tile_edges( $tile_img, $copy_x, $copy_y, $copy_w, $copy_h ) {
		$full_w = imagesx( $tile_img );
		$full_h = imagesy( $tile_img );
		$copy_right = $copy_x + $copy_w;
		$copy_bottom = $copy_y + $copy_h;

		if ( $copy_y > 0 ) {
			imagecopyresampled( $tile_img, $tile_img, $copy_x, 0, $copy_x, $copy_y, $copy_w, $copy_y, $copy_w, 1 );
		}
		if ( $copy_bottom < $full_h ) {
			imagecopyresampled( $tile_img, $tile_img, $copy_x, $copy_bottom, $copy_x, $copy_bottom - 1, $copy_w, $full_h - $copy_bottom, $copy_w, 1 );
		}
		if ( $copy_x > 0 ) {
			imagecopyresampled( $tile_img, $tile_img, 0, 0, $copy_x, 0, $copy_x, $full_h, 1, $full_h );
		}
		if ( $copy_right < $full_w ) {
			imagecopyresampled( $tile_img, $tile_img, $copy_right, 0, $copy_right - 1, 0, $full_w - $copy_right, $full_h, 1, $full_h );
		}
	}
}
