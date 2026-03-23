<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WOI_Admin_Product_Settings {
	const META_ENABLED        = '_woi_enabled';
	const META_REQUIRED_COUNT = '_woi_required_image_count';
	const META_VISIBLE_WIDTH  = '_woi_visible_width';
	const META_VISIBLE_HEIGHT = '_woi_visible_height';
	const META_PUZZLE_ENABLED = '_woi_puzzle_enabled';
	const META_PUZZLE_COLS    = '_woi_puzzle_cols';
	const META_PUZZLE_ROWS    = '_woi_puzzle_rows';

	public function init() {
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'render_panel' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save' ) );
		add_filter( 'manage_edit-product_columns', array( $this, 'add_product_list_column' ), 20 );
		add_filter( 'manage_edit-product_sortable_columns', array( $this, 'add_sortable_product_list_column' ) );
		add_action( 'manage_product_posts_custom_column', array( $this, 'render_product_list_column' ), 10, 2 );
		add_action( 'pre_get_posts', array( $this, 'prepare_product_list_sort' ) );
		add_filter( 'posts_clauses', array( $this, 'apply_product_list_sort_clauses' ), 10, 2 );
		add_action( 'admin_head', array( $this, 'print_product_list_styles' ) );
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
		$puzzle_enabled = get_post_meta( $post->ID, self::META_PUZZLE_ENABLED, true );
		$puzzle_cols    = get_post_meta( $post->ID, self::META_PUZZLE_COLS, true );
		$puzzle_rows    = get_post_meta( $post->ID, self::META_PUZZLE_ROWS, true );
		$puzzle_cols    = '' === $puzzle_cols ? 3 : (int) $puzzle_cols;
		$puzzle_rows    = '' === $puzzle_rows ? 3 : (int) $puzzle_rows;
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

				woocommerce_wp_checkbox(
					array(
						'id'          => self::META_PUZZLE_ENABLED,
						'label'       => __( 'Enable puzzle mode', 'woo-order-images' ),
						'description' => __( 'Use one uploaded image split into a puzzle grid of tiles.', 'woo-order-images' ),
						'value'       => 'yes' === $puzzle_enabled ? 'yes' : 'no',
					)
				);

				woocommerce_wp_text_input(
					array(
						'id'                => self::META_PUZZLE_COLS,
						'label'             => __( 'Puzzle columns', 'woo-order-images' ),
						'description'       => __( 'Horizontal tile count for puzzle products (for example, 3 for a 3×4 puzzle).', 'woo-order-images' ),
						'desc_tip'          => true,
						'type'              => 'number',
						'custom_attributes' => array(
							'min'  => 1,
							'step' => 1,
						),
						'value'             => max( 1, $puzzle_cols ),
					)
				);

				woocommerce_wp_text_input(
					array(
						'id'                => self::META_PUZZLE_ROWS,
						'label'             => __( 'Puzzle rows', 'woo-order-images' ),
						'description'       => __( 'Vertical tile count for puzzle products (for example, 4 for a 3×4 puzzle).', 'woo-order-images' ),
						'desc_tip'          => true,
						'type'              => 'number',
						'custom_attributes' => array(
							'min'  => 1,
							'step' => 1,
						),
						'value'             => max( 1, $puzzle_rows ),
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

		$puzzle_enabled = isset( $_POST[ self::META_PUZZLE_ENABLED ] ) ? 'yes' : 'no';
		update_post_meta( $post_id, self::META_PUZZLE_ENABLED, $puzzle_enabled );

		$puzzle_cols = isset( $_POST[ self::META_PUZZLE_COLS ] ) ? absint( wp_unslash( $_POST[ self::META_PUZZLE_COLS ] ) ) : 3;
		$puzzle_cols = max( 1, $puzzle_cols );
		update_post_meta( $post_id, self::META_PUZZLE_COLS, $puzzle_cols );

		$puzzle_rows = isset( $_POST[ self::META_PUZZLE_ROWS ] ) ? absint( wp_unslash( $_POST[ self::META_PUZZLE_ROWS ] ) ) : 3;
		$puzzle_rows = max( 1, $puzzle_rows );
		update_post_meta( $post_id, self::META_PUZZLE_ROWS, $puzzle_rows );
	}

	public function add_product_list_column( $columns ) {
		$updated = array();

		foreach ( $columns as $key => $label ) {
			$updated[ $key ] = $label;

			if ( 'name' === $key ) {
				$updated['woi_order_images'] = '<span title="' . esc_attr__( 'Woo Order Images', 'woo-order-images' ) . '">' . esc_html__( 'WOI', 'woo-order-images' ) . '</span>';
			}
		}

		if ( ! isset( $updated['woi_order_images'] ) ) {
			$updated['woi_order_images'] = '<span title="' . esc_attr__( 'Woo Order Images', 'woo-order-images' ) . '">' . esc_html__( 'WOI', 'woo-order-images' ) . '</span>';
		}

		return $updated;
	}

	public function render_product_list_column( $column, $post_id ) {
		if ( 'woi_order_images' !== $column ) {
			return;
		}

		$enabled = 'yes' === get_post_meta( $post_id, self::META_ENABLED, true );
		if ( ! $enabled ) {
			echo '<span aria-hidden="true">&mdash;</span>';
			return;
		}

		$puzzle_enabled = 'yes' === get_post_meta( $post_id, self::META_PUZZLE_ENABLED, true );
		$puzzle_cols    = max( 1, (int) get_post_meta( $post_id, self::META_PUZZLE_COLS, true ) );
		$puzzle_rows    = max( 1, (int) get_post_meta( $post_id, self::META_PUZZLE_ROWS, true ) );
		$required_count = max( 1, (int) get_post_meta( $post_id, self::META_REQUIRED_COUNT, true ) );
		$visible_width  = self::get_meta_float( $post_id, self::META_VISIBLE_WIDTH, 2.0 );
		$visible_height = self::get_meta_float( $post_id, self::META_VISIBLE_HEIGHT, 2.0 );

		$badge_text = $puzzle_enabled
			? sprintf(
				'%1$s-%2$s',
				$this->format_admin_dimension_pair( $puzzle_cols, $puzzle_rows, false ),
				$this->format_admin_dimension_pair( $visible_width, $visible_height, true )
			)
			: sprintf(
				'%1$d-%2$s',
				$required_count,
				$this->format_admin_dimension_pair( $visible_width, $visible_height, true )
			);

		echo '<span class="woi-admin-badge' . ( $puzzle_enabled ? ' woi-admin-badge--puzzle' : '' ) . '">' . esc_html( $badge_text ) . '</span>';
	}

	public function add_sortable_product_list_column( $columns ) {
		$columns['woi_order_images'] = 'woi_order_images';

		return $columns;
	}

	public function prepare_product_list_sort( $query ) {
		if ( ! $query instanceof WP_Query || ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( 'product' !== $query->get( 'post_type' ) ) {
			return;
		}

		if ( 'woi_order_images' !== $query->get( 'orderby' ) ) {
			return;
		}

		$query->set( 'woi_sort_order_images', true );
	}

	public function apply_product_list_sort_clauses( $clauses, $query ) {
		if ( ! $query instanceof WP_Query || ! $query->get( 'woi_sort_order_images' ) ) {
			return $clauses;
		}

		global $wpdb;

		$order = 'DESC' === strtoupper( (string) $query->get( 'order' ) ) ? 'DESC' : 'ASC';

		$enabled_join = " LEFT JOIN {$wpdb->postmeta} AS woi_enabled_pm ON ({$wpdb->posts}.ID = woi_enabled_pm.post_id AND woi_enabled_pm.meta_key = '" . esc_sql( self::META_ENABLED ) . "')";
		$puzzle_join  = " LEFT JOIN {$wpdb->postmeta} AS woi_puzzle_pm ON ({$wpdb->posts}.ID = woi_puzzle_pm.post_id AND woi_puzzle_pm.meta_key = '" . esc_sql( self::META_PUZZLE_ENABLED ) . "')";

		if ( false === strpos( $clauses['join'], 'woi_enabled_pm' ) ) {
			$clauses['join'] .= $enabled_join;
		}

		if ( false === strpos( $clauses['join'], 'woi_puzzle_pm' ) ) {
			$clauses['join'] .= $puzzle_join;
		}

		if ( 'ASC' === $order ) {
			$sort_order = "CASE WHEN woi_enabled_pm.meta_value = 'yes' THEN 0 ELSE 1 END ASC, CASE WHEN woi_enabled_pm.meta_value = 'yes' AND woi_puzzle_pm.meta_value = 'yes' THEN 1 WHEN woi_enabled_pm.meta_value = 'yes' THEN 0 ELSE 2 END ASC";
		} else {
			$sort_order = "CASE WHEN woi_enabled_pm.meta_value = 'yes' THEN 1 ELSE 0 END ASC, CASE WHEN woi_enabled_pm.meta_value = 'yes' AND woi_puzzle_pm.meta_value = 'yes' THEN 0 WHEN woi_enabled_pm.meta_value = 'yes' THEN 1 ELSE 2 END ASC";
		}

		$clauses['orderby'] = $sort_order . ", {$wpdb->posts}.post_title ASC";

		return $clauses;
	}

	public function print_product_list_styles() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit-product' !== $screen->id ) {
			return;
		}
		?>
		<style>
			.column-woi_order_images {
				width: 13%;
			}

			.woi-admin-badge {
				display: inline-block;
				padding: 3px 8px;
				border-radius: 999px;
				background: #e8f3ea;
				color: #115c2b;
				font-size: 12px;
				font-weight: 600;
				line-height: 1.4;
				white-space: nowrap;
			}

			.woi-admin-badge--puzzle {
				background: #eef2ff;
				color: #1e3a8a;
			}
		</style>
		<?php
	}

	public static function get_product_spec( $product_id ) {
		$visible_width  = self::get_meta_float( $product_id, self::META_VISIBLE_WIDTH, 2.0 );
		$visible_height = self::get_meta_float( $product_id, self::META_VISIBLE_HEIGHT, 2.0 );
		$wrap_margin    = WOI_Settings::get_print_bleed();
		$puzzle_enabled = 'yes' === get_post_meta( $product_id, self::META_PUZZLE_ENABLED, true );
		$puzzle_cols    = (int) get_post_meta( $product_id, self::META_PUZZLE_COLS, true );
		$puzzle_rows    = (int) get_post_meta( $product_id, self::META_PUZZLE_ROWS, true );
		$puzzle_cols    = max( 1, $puzzle_cols > 0 ? $puzzle_cols : 3 );
		$puzzle_rows    = max( 1, $puzzle_rows > 0 ? $puzzle_rows : 3 );
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
			'is_puzzle'              => $puzzle_enabled,
			'puzzle_cols'            => $puzzle_cols,
			'puzzle_rows'            => $puzzle_rows,
			'puzzle_total_tiles'     => $puzzle_cols * $puzzle_rows,
			'puzzle_aspect_ratio'    => ( $puzzle_rows > 0 && $visible_height > 0 ) ? ( ( $puzzle_cols * $visible_width ) / ( $puzzle_rows * $visible_height ) ) : 1,
		);
	}

	private static function get_meta_float( $post_id, $meta_key, $default ) {
		$value = get_post_meta( $post_id, $meta_key, true );
		if ( '' === $value || null === $value ) {
			return (float) $default;
		}

		return (float) $value;
	}

	private function format_admin_dimension_pair( $width, $height, $round_up = false ) {
		return sprintf(
			'%1$sx%2$s',
			$this->format_admin_dimension_value( $width, $round_up ),
			$this->format_admin_dimension_value( $height, $round_up )
		);
	}

	private function format_admin_dimension_value( $value, $round_up = false ) {
		$value = (float) $value;
		if ( $round_up ) {
			return (string) max( 1, (int) ceil( $value ) );
		}

		if ( abs( $value - round( $value ) ) < 0.001 ) {
			return (string) (int) round( $value );
		}

		return rtrim( rtrim( number_format( $value, 2, '.', '' ), '0' ), '.' );
	}
}
