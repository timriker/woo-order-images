<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WOI_Admin_Order_Images {
	public function init() {
		add_action( 'woocommerce_after_order_itemmeta', array( $this, 'render_order_item_images' ), 10, 3 );
		add_action( 'woocommerce_order_item_meta_end', array( $this, 'render_frontend_order_item_images' ), 10, 4 );
		add_action( 'admin_post_woi_print_sheet', array( $this, 'render_print_page' ) );
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

		$order_id        = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;
		$print_sheet_url = add_query_arg(
			array(
				'action'   => 'woi_print_sheet',
				'order_id' => $order_id,
				'item_id'  => absint( $item_id ),
			),
			admin_url( 'admin-post.php' )
		);
		echo '<p style="margin:8px 0 0;"><a class="button" href="' . esc_url( $print_sheet_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Open Print Sheet', 'woo-order-images' ) . '</a></p>';
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
		$item_id  = isset( $_GET['item_id'] ) ? absint( wp_unslash( $_GET['item_id'] ) ) : 0;

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_die( esc_html__( 'Order not found.', 'woo-order-images' ) );
		}

		$item = $order->get_item( $item_id );
		if ( ! $item instanceof WC_Order_Item_Product ) {
			wp_die( esc_html__( 'Order item not found.', 'woo-order-images' ) );
		}

		$urls = $item->get_meta( WOI_Order_Images::ORDER_META_URLS, true );
		if ( empty( $urls ) || ! is_array( $urls ) ) {
			wp_die( esc_html__( 'No order images found for this item.', 'woo-order-images' ) );
		}

		$base_spec      = $this->get_item_spec( $item );
		$watermark_text = WOI_Settings::get_watermark_text();

		header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
		echo '<!doctype html><html><head><meta charset="' . esc_attr( get_option( 'blog_charset' ) ) . '">';
		echo '<title>' . esc_html__( 'WOI Print Sheet', 'woo-order-images' ) . '</title>';
		echo '<style>
			*{box-sizing:border-box;}
			html,body{margin:0;padding:0;background:#fff;}
			body{font-family:Arial,sans-serif;color:#111;padding:0.2in;}
			.woi-sheet-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:0.12in;align-items:start;}
			.woi-tile{position:relative;}
			.woi-bleed{position:relative;overflow:hidden;background:#fff;}
			.woi-bleed img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;}
			.woi-cut-outline{position:absolute;inset:0;pointer-events:none;z-index:4;}
			.woi-cut-outline svg{display:block;width:100%;height:100%;}
			.woi-watermark-band{position:absolute;display:flex;align-items:center;justify-content:center;pointer-events:none;z-index:2;overflow:hidden;}
			.woi-watermark-band span{font-size:7.5pt;line-height:1.1;color:rgba(0,0,0,0.65);white-space:nowrap;letter-spacing:0.02em;background:rgba(255,255,255,0.88);padding:2px 8px;border-radius:2px;}
			.woi-watermark-top span{transform:rotate(180deg);}
			.woi-watermark-left span,.woi-watermark-right span{transform:rotate(-90deg);}
			@page{margin:0;}
			@media print{html,body{margin:0;padding:0;background:#fff;}body{padding:0.12in;} .woi-sheet-grid{gap:0.1in;}}
		</style>';
		echo '</head><body>';
		echo '<div class="woi-sheet-grid">';

		foreach ( $urls as $index => $url ) {
			$spec         = $this->get_oriented_spec( $base_spec, $url );
			$left_pct     = ( 100 - $spec['visible_width_percent'] ) / 2;
			$top_pct      = ( 100 - $spec['visible_height_percent'] ) / 2;
			$right_pct    = $left_pct;
			$bottom_pct   = $top_pct;
			$clip_polygon = $this->get_cut_polygon_css( $left_pct, $top_pct );
			$cut_outline  = $this->get_cut_polygon_svg( $left_pct, $top_pct );

			echo '<div class="woi-tile">';
			echo '<div class="woi-bleed" style="aspect-ratio:' . esc_attr( $spec['full_width'] ) . ' / ' . esc_attr( $spec['full_height'] ) . ';clip-path:polygon(' . esc_attr( $clip_polygon ) . ');">';
			echo '<img src="' . esc_url( $url ) . '" alt="">';

			if ( '' !== trim( $watermark_text ) ) {
				echo '<div class="woi-watermark-band woi-watermark-top" style="left:0;top:0;width:100%;height:' . esc_attr( $top_pct ) . '%;"><span>' . esc_html( $watermark_text ) . '</span></div>';
				echo '<div class="woi-watermark-band" style="left:0;bottom:0;width:100%;height:' . esc_attr( $bottom_pct ) . '%;"><span>' . esc_html( $watermark_text ) . '</span></div>';
				echo '<div class="woi-watermark-band woi-watermark-left" style="left:0;top:' . esc_attr( $top_pct ) . '%;width:' . esc_attr( $left_pct ) . '%;height:' . esc_attr( $spec['visible_height_percent'] ) . '%;"><span>' . esc_html( $watermark_text ) . '</span></div>';
				echo '<div class="woi-watermark-band woi-watermark-right" style="right:0;top:' . esc_attr( $top_pct ) . '%;width:' . esc_attr( $right_pct ) . '%;height:' . esc_attr( $spec['visible_height_percent'] ) . '%;"><span>' . esc_html( $watermark_text ) . '</span></div>';
			}

			echo '</div>';
			echo '<div class="woi-cut-outline"><svg viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true"><polygon points="' . esc_attr( $cut_outline ) . '" fill="none" stroke="#000" stroke-width="0.7" vector-effect="non-scaling-stroke" /></svg></div>';
			echo '</div>';
		}

		echo '</div></body></html>';
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

	private function get_cut_polygon_css( $left_pct, $top_pct ) {
		$right_pct  = 100 - $left_pct;
		$bottom_pct = 100 - $top_pct;

		return implode(
			', ',
			array(
				$this->format_pct( $left_pct ) . ' 0%',
				$this->format_pct( $right_pct ) . ' 0%',
				'100% ' . $this->format_pct( $top_pct ),
				'100% ' . $this->format_pct( $bottom_pct ),
				$this->format_pct( $right_pct ) . ' 100%',
				$this->format_pct( $left_pct ) . ' 100%',
				'0% ' . $this->format_pct( $bottom_pct ),
				'0% ' . $this->format_pct( $top_pct ),
			)
		);
	}

	private function get_cut_polygon_svg( $left_pct, $top_pct ) {
		$right_pct  = 100 - $left_pct;
		$bottom_pct = 100 - $top_pct;

		return implode(
			' ',
			array(
				$this->format_num( $left_pct ) . ',0',
				$this->format_num( $right_pct ) . ',0',
				'100,' . $this->format_num( $top_pct ),
				'100,' . $this->format_num( $bottom_pct ),
				$this->format_num( $right_pct ) . ',100',
				$this->format_num( $left_pct ) . ',100',
				'0,' . $this->format_num( $bottom_pct ),
				'0,' . $this->format_num( $top_pct ),
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
