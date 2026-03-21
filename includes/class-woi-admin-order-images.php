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
	}

	public function render_order_level_print_button( $order_id ) {
		$order = is_a( $order_id, 'WC_Abstract_Order' ) ? $order_id : wc_get_order( absint( $order_id ) );
		if ( ! $order ) {
			return;
		}

		$has_images = false;
		foreach ( $order->get_items() as $item ) {
			if ( $item instanceof WC_Order_Item_Product ) {
				$urls = $item->get_meta( WOI_Order_Images::ORDER_META_URLS, true );
				if ( ! empty( $urls ) && is_array( $urls ) ) {
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

		$urls = $item->get_meta( WOI_Order_Images::ORDER_META_URLS, true );
		if ( empty( $urls ) || ! is_array( $urls ) ) {
			return;
		}

		$base_spec = $this->get_item_spec( $item );

		echo '<div class="woi-admin-images" style="margin-top:8px;">';
		echo '<strong>' . esc_html__( 'Order Images', 'woo-order-images' ) . ':</strong>';
		echo '<div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:6px;">';

		foreach ( $urls as $index => $url ) {
			$spec        = $this->get_oriented_spec( $base_spec, $url );
			$index_label = sprintf( __( 'Image %d', 'woo-order-images' ), ( $index + 1 ) );
			echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer" title="' . esc_attr( $index_label ) . '">';
			echo '<span style="display:block;width:72px;aspect-ratio:' . esc_attr( $spec['visible_aspect_ratio'] ) . ';overflow:hidden;border:1px solid #ccd0d4;border-radius:4px;background:#f6f7f7;">';
			echo '<img src="' . esc_url( $url ) . '" alt="' . esc_attr( $index_label ) . '" style="width:100%;height:100%;object-fit:cover;display:block;" />';
			echo '</span>';
			echo '</a>';
		}

		echo '</div>';
		echo '</div>';
	}

	public function render_frontend_order_item_images( $item_id, $item, $order, $plain_text ) {
		if ( is_admin() || $plain_text || ! $item instanceof WC_Order_Item_Product ) {
			return;
		}

		$urls = $item->get_meta( WOI_Order_Images::ORDER_META_URLS, true );
		if ( empty( $urls ) || ! is_array( $urls ) ) {
			return;
		}

		$base_spec = $this->get_item_spec( $item );

		echo '<div class="woi-order-history-images" style="display:flex;flex-wrap:wrap;gap:8px;margin-top:10px;">';
		foreach ( $urls as $index => $url ) {
			$spec        = $this->get_oriented_spec( $base_spec, $url );
			$index_label = sprintf( __( 'Image %d', 'woo-order-images' ), ( $index + 1 ) );
			echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer" title="' . esc_attr( $index_label ) . '">';
			echo '<span style="display:block;width:68px;aspect-ratio:' . esc_attr( $spec['visible_aspect_ratio'] ) . ';overflow:hidden;border:1px solid #ccd0d4;border-radius:4px;background:#f6f7f7;">';
			echo '<img src="' . esc_url( $url ) . '" alt="' . esc_attr( $index_label ) . '" style="display:block;width:100%;height:100%;object-fit:cover;" />';
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

			$urls = $item->get_meta( WOI_Order_Images::ORDER_META_URLS, true );
			if ( empty( $urls ) || ! is_array( $urls ) ) {
				continue;
			}

			$base_spec = $this->get_item_spec( $item );
			foreach ( $urls as $url ) {
				$entries[] = array(
					'url'  => $url,
					'spec' => $this->get_oriented_spec( $base_spec, $url ),
				);
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

		// Printable area: 8.5in paper - left/right margins = width;
		// 11in paper - top/bottom margins = height.
		$page_width     = 8.5 - $margin_left - $margin_right;
		$page_height    = 11.0 - $margin_top - $margin_bottom;
		$gap         = 0.125;

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
		echo 'size:8.5in 11in;';
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
				echo '<img src="' . esc_url( $url ) . '" alt="" style="left:' . esc_attr( $image_left ) . ';top:' . esc_attr( $image_top ) . ';width:' . esc_attr( $image_width ) . ';height:' . esc_attr( $image_height ) . ';">';
				echo '</div>';

				if ( '' !== trim( $watermark_text ) ) {
					// Font size = 1/3 of one bleed tab (wrap_margin × 72 pt/in ÷ 3), in pt.
					$wm_font_pt = $this->format_num( max( 4, $spec['wrap_margin'] * 24 ) );
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
		$wrap_margin    = (float) $item->get_meta( WOI_Order_Images::ORDER_META_WRAP_MARGIN, true );

		if ( $visible_width <= 0 ) {
			$visible_width = 2.0;
		}

		if ( $visible_height <= 0 ) {
			$visible_height = 2.0;
		}

		if ( $wrap_margin < 0 ) {
			$wrap_margin = 0.25;
		}

		$full_width  = $visible_width + ( 2 * $wrap_margin );
		$full_height = $visible_height + ( 2 * $wrap_margin );

		return array(
			'visible_width'          => $visible_width,
			'visible_height'         => $visible_height,
			'wrap_margin'            => $wrap_margin,
			'full_width'             => $full_width,
			'full_height'            => $full_height,
			'visible_aspect_ratio'   => $visible_height > 0 ? $visible_width / $visible_height : 1,
			'visible_width_percent'  => $full_width > 0 ? ( $visible_width / $full_width ) * 100 : 100,
			'visible_height_percent' => $full_height > 0 ? ( $visible_height / $full_height ) * 100 : 100,
		);
	}
}
