<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WOI_Settings {
	const OPTION_WATERMARK_TEXT = 'woi_watermark_text';
	const DEFAULT_WATERMARK_TEXT = 'BestLifeMagnets.com';

	const OPTION_PRINT_MARGIN_TOP = 'woi_print_margin_top';
	const OPTION_PRINT_MARGIN_RIGHT = 'woi_print_margin_right';
	const OPTION_PRINT_MARGIN_BOTTOM = 'woi_print_margin_bottom';
	const OPTION_PRINT_MARGIN_LEFT = 'woi_print_margin_left';
	const OPTION_PRINT_GAP = 'woi_print_gap';
	const OPTION_PRINT_PAGE_SIZE = 'woi_print_page_size';

	const DEFAULT_PRINT_MARGIN_TOP = 0.25;
	const DEFAULT_PRINT_MARGIN_RIGHT = 0.25;
	const DEFAULT_PRINT_MARGIN_BOTTOM = 0.5;
	const DEFAULT_PRINT_MARGIN_LEFT = 0.25;
	const DEFAULT_PRINT_GAP = 0.125;
	const DEFAULT_PRINT_PAGE_SIZE = 'letter';

	/**
	 * Page size presets (width x height in inches).
	 */
	private static $PAGE_SIZES = array(
		'letter'       => array( 'name' => 'Letter (8.5" × 11")', 'width' => 8.5, 'height' => 11.0 ),
		'legal'        => array( 'name' => 'Legal (8.5" × 14")', 'width' => 8.5, 'height' => 14.0 ),
		'a4'           => array( 'name' => 'A4 (8.27" × 11.69")', 'width' => 8.27, 'height' => 11.69 ),
		'a5'           => array( 'name' => 'A5 (5.83" × 8.27")', 'width' => 5.83, 'height' => 8.27 ),
	);

	public function init() {
		add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'handle_cleanup_request' ) );
	}

	public function register_settings_page() {
		add_submenu_page(
			'woocommerce',
			__( 'Order Images Settings', 'woo-order-images' ),
			__( 'Order Images', 'woo-order-images' ),
			'manage_woocommerce',
			'woi-settings',
			array( $this, 'render_settings_page' )
		);
	}

	public function register_settings() {
		register_setting(
			'woi_settings_group',
			self::OPTION_WATERMARK_TEXT,
			array(
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => self::DEFAULT_WATERMARK_TEXT,
			)
		);

		register_setting(
			'woi_settings_group',
			self::OPTION_PRINT_MARGIN_TOP,
			array(
				'sanitize_callback' => array( $this, 'sanitize_float' ),
				'default'           => self::DEFAULT_PRINT_MARGIN_TOP,
			)
		);

		register_setting(
			'woi_settings_group',
			self::OPTION_PRINT_MARGIN_RIGHT,
			array(
				'sanitize_callback' => array( $this, 'sanitize_float' ),
				'default'           => self::DEFAULT_PRINT_MARGIN_RIGHT,
			)
		);

		register_setting(
			'woi_settings_group',
			self::OPTION_PRINT_MARGIN_BOTTOM,
			array(
				'sanitize_callback' => array( $this, 'sanitize_float' ),
				'default'           => self::DEFAULT_PRINT_MARGIN_BOTTOM,
			)
		);

		register_setting(
			'woi_settings_group',
			self::OPTION_PRINT_MARGIN_LEFT,
			array(
				'sanitize_callback' => array( $this, 'sanitize_float' ),
				'default'           => self::DEFAULT_PRINT_MARGIN_LEFT,
			)
		);

		register_setting(
			'woi_settings_group',
			self::OPTION_PRINT_GAP,
			array(
				'sanitize_callback' => array( $this, 'sanitize_float' ),
				'default'           => self::DEFAULT_PRINT_GAP,
			)
		);

		register_setting(
			'woi_settings_group',
			self::OPTION_PRINT_PAGE_SIZE,
			array(
				'sanitize_callback' => array( $this, 'sanitize_page_size' ),
				'default'           => self::DEFAULT_PRINT_PAGE_SIZE,
			)
		);

		add_settings_section(
			'woi_print_section',
			__( 'Print Sheet', 'woo-order-images' ),
			'__return_false',
			'woi-settings'
		);

		add_settings_field(
			self::OPTION_WATERMARK_TEXT,
			__( 'Watermark text', 'woo-order-images' ),
			array( $this, 'render_watermark_field' ),
			'woi-settings',
			'woi_print_section'
		);

		add_settings_field(
			self::OPTION_PRINT_MARGIN_TOP,
			__( 'Top margin (in)', 'woo-order-images' ),
			array( $this, 'render_margin_field_top' ),
			'woi-settings',
			'woi_print_section'
		);

		add_settings_field(
			self::OPTION_PRINT_MARGIN_RIGHT,
			__( 'Right margin (in)', 'woo-order-images' ),
			array( $this, 'render_margin_field_right' ),
			'woi-settings',
			'woi_print_section'
		);

		add_settings_field(
			self::OPTION_PRINT_MARGIN_BOTTOM,
			__( 'Bottom margin (in)', 'woo-order-images' ),
			array( $this, 'render_margin_field_bottom' ),
			'woi-settings',
			'woi_print_section'
		);

		add_settings_field(
			self::OPTION_PRINT_MARGIN_LEFT,
			__( 'Left margin (in)', 'woo-order-images' ),
			array( $this, 'render_margin_field_left' ),
			'woi-settings',
			'woi_print_section'
		);

		add_settings_field(
			self::OPTION_PRINT_GAP,
			__( 'Gap between tiles (in)', 'woo-order-images' ),
			array( $this, 'render_gap_field' ),
			'woi-settings',
			'woi_print_section'
		);

		add_settings_field(
			self::OPTION_PRINT_PAGE_SIZE,
			__( 'Page size', 'woo-order-images' ),
			array( $this, 'render_page_size_field' ),
			'woi-settings',
			'woi_print_section'
		);
	}

	public function render_watermark_field() {
		$value = self::get_watermark_text();
		echo '<input type="text" class="regular-text" name="' . esc_attr( self::OPTION_WATERMARK_TEXT ) . '" value="' . esc_attr( $value ) . '" />';
		echo '<p class="description">' . esc_html__( 'Text printed in the bleed-area flaps outside the visible area.', 'woo-order-images' ) . '</p>';
	}

	public function render_margin_field_top() {
		$value = self::get_print_margin_top();
		echo '<input type="number" step="0.01" min="0" class="small-text" name="' . esc_attr( self::OPTION_PRINT_MARGIN_TOP ) . '" value="' . esc_attr( $value ) . '" />';
		echo '<p class="description">' . esc_html__( 'Top margin to accommodate printer hardware limit. Default: 0.25 inch.', 'woo-order-images' ) . '</p>';
	}

	public function render_margin_field_right() {
		$value = self::get_print_margin_right();
		echo '<input type="number" step="0.01" min="0" class="small-text" name="' . esc_attr( self::OPTION_PRINT_MARGIN_RIGHT ) . '" value="' . esc_attr( $value ) . '" />';
		echo '<p class="description">' . esc_html__( 'Right margin to accommodate printer hardware limit. Default: 0.25 inch.', 'woo-order-images' ) . '</p>';
	}

	public function render_margin_field_bottom() {
		$value = self::get_print_margin_bottom();
		echo '<input type="number" step="0.01" min="0" class="small-text" name="' . esc_attr( self::OPTION_PRINT_MARGIN_BOTTOM ) . '" value="' . esc_attr( $value ) . '" />';
		echo '<p class="description">' . esc_html__( 'Bottom margin to accommodate printer hardware limit. Default: 0.5 inch.', 'woo-order-images' ) . '</p>';
	}

	public function render_margin_field_left() {
		$value = self::get_print_margin_left();
		echo '<input type="number" step="0.01" min="0" class="small-text" name="' . esc_attr( self::OPTION_PRINT_MARGIN_LEFT ) . '" value="' . esc_attr( $value ) . '" />';
		echo '<p class="description">' . esc_html__( 'Left margin to accommodate printer hardware limit. Default: 0.25 inch.', 'woo-order-images' ) . '</p>';
	}

	public function render_gap_field() {
		$value = self::get_print_gap();
		echo '<input type="number" step="0.01" min="0.01" class="small-text" name="' . esc_attr( self::OPTION_PRINT_GAP ) . '" value="' . esc_attr( $value ) . '" />';
		echo '<p class="description">' . esc_html__( 'Minimum space between tiles on print sheet. Default: 0.125 inch (1/8 inch).', 'woo-order-images' ) . '</p>';
	}

	public function render_page_size_field() {
		$value = self::get_print_page_size();
		echo '<select name="' . esc_attr( self::OPTION_PRINT_PAGE_SIZE ) . '" class="regular-text">';
		foreach ( self::$PAGE_SIZES as $key => $info ) {
			$selected = selected( $value, $key, false );
			echo '<option value="' . esc_attr( $key ) . '" ' . $selected . '>' . esc_html( $info['name'] ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Paper size for print sheet. Default: Letter (8.5" × 11").', 'woo-order-images' ) . '</p>';
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'woo-order-images' ) );
		}

		// Display cleanup result message if redirected from cleanup.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['woi_cleanup_result'] ) ) {
			$deleted = absint( wp_unslash( $_GET['woi_cleanup_result'] ) );
			if ( $deleted > 0 ) {
				echo '<div class="notice notice-success is-dismissible"><p>';
				echo esc_html( sprintf( __( 'Cleanup complete: Deleted %d orphaned image file(s).', 'woo-order-images' ), $deleted ) );
				echo '</p></div>';
			} else {
				echo '<div class="notice notice-info is-dismissible"><p>';
				echo esc_html__( 'Cleanup complete: No orphaned files to delete.', 'woo-order-images' );
				echo '</p></div>';
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Woo Order Images Settings', 'woo-order-images' ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'woi_settings_group' );
				do_settings_sections( 'woi-settings' );
				submit_button();
				?>
			</form>

			<hr style="margin: 2em 0;">

			<div class="postbox">
				<h2 class="hndle"><span><?php esc_html_e( 'Image Cleanup', 'woo-order-images' ); ?></span></h2>
				<div class="inside">
					<p><?php esc_html_e( 'Cleanup orphaned image files that are no longer attached to any order.', 'woo-order-images' ); ?></p>
					<?php
					$image_counts = $this->get_image_cleanup_counts();
					echo '<p>' . esc_html( sprintf( __( 'Images currently in use by orders: %d', 'woo-order-images' ), $image_counts['in_use_count'] ) ) . '</p>';
					$orphaned_count = $image_counts['orphaned_count'];
					if ( $orphaned_count > 0 ) {
						echo '<p><strong>' . esc_html( sprintf( __( 'Found %d orphaned file(s)', 'woo-order-images' ), $orphaned_count ) ) . '</strong></p>';
						?>
						<form method="post" style="margin-top: 1em;">
							<?php
							wp_nonce_field( 'woi_cleanup_action', 'woi_cleanup_nonce' );
							submit_button(
								__( 'Delete Orphaned Files', 'woo-order-images' ),
								'delete',
								'woi_cleanup_action',
								false
							);
							?>
						</form>
						<?php
					} else {
						echo '<p><em>' . esc_html__( 'No orphaned files found.', 'woo-order-images' ) . '</em></p>';
					}
					?>
				</div>
			</div>
		</div>
		<?php
	}

	public function sanitize_float( $input ) {
		return is_numeric( $input ) ? (float) $input : 0;
	}

	public function sanitize_page_size( $input ) {
		return isset( self::$PAGE_SIZES[ $input ] ) ? $input : self::DEFAULT_PRINT_PAGE_SIZE;
	}

	public static function get_watermark_text() {
		$value = get_option( self::OPTION_WATERMARK_TEXT, self::DEFAULT_WATERMARK_TEXT );
		$value = is_string( $value ) ? trim( $value ) : '';

		return '' !== $value ? $value : self::DEFAULT_WATERMARK_TEXT;
	}

	public static function get_print_margin_top() {
		return (float) get_option( self::OPTION_PRINT_MARGIN_TOP, self::DEFAULT_PRINT_MARGIN_TOP );
	}

	public static function get_print_margin_right() {
		return (float) get_option( self::OPTION_PRINT_MARGIN_RIGHT, self::DEFAULT_PRINT_MARGIN_RIGHT );
	}

	public static function get_print_margin_bottom() {
		return (float) get_option( self::OPTION_PRINT_MARGIN_BOTTOM, self::DEFAULT_PRINT_MARGIN_BOTTOM );
	}

	public static function get_print_margin_left() {
		return (float) get_option( self::OPTION_PRINT_MARGIN_LEFT, self::DEFAULT_PRINT_MARGIN_LEFT );
	}

	public static function get_print_gap() {
		return (float) get_option( self::OPTION_PRINT_GAP, self::DEFAULT_PRINT_GAP );
	}

	public static function get_print_page_size() {
		return get_option( self::OPTION_PRINT_PAGE_SIZE, self::DEFAULT_PRINT_PAGE_SIZE );
	}

	/**
	 * Get page dimensions for a given size.
	 *
	 * @param string $size Page size key (letter, legal, a4, a5).
	 * @return array Array with 'width' and 'height' in inches, or null if not found.
	 */
	public static function get_page_size_dimensions( $size ) {
		return isset( self::$PAGE_SIZES[ $size ] ) ? array(
			'width'  => self::$PAGE_SIZES[ $size ]['width'],
			'height' => self::$PAGE_SIZES[ $size ]['height'],
		) : null;
	}

	/**
	 * Handle cleanup request from admin form.
	 */
	public function handle_cleanup_request() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST['woi_cleanup_action'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( ! isset( $_POST['woi_cleanup_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woi_cleanup_nonce'] ) ), 'woi_cleanup_action' ) ) {
			return;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$deleted = $this->cleanup_orphaned_images();

		// Redirect with result message.
		$redirect_url = add_query_arg(
			array(
				'page'      => 'woi-settings',
				'woi_cleanup_result' => $deleted,
			),
			admin_url( 'admin.php' )
		);

		wp_safe_remote_post( wp_nonce_url( $redirect_url, 'wp_http_nonce' ) );
		wp_redirect( $redirect_url );
		exit;
	}

	/**
	 * Count orphaned image files (on disk but not in any order).
	 *
	 * @return int Number of orphaned files.
	 */
	public function count_orphaned_images() {
		$image_counts = $this->get_image_cleanup_counts();

		return $image_counts['orphaned_count'];
	}

	/**
	 * Get referenced and orphaned image counts for the cleanup UI.
	 *
	 * @return array{in_use_count:int, orphaned_count:int}
	 */
	private function get_image_cleanup_counts() {
		$uploads_dir = wp_upload_dir();
		$woi_dir     = trailingslashit( $uploads_dir['basedir'] ) . 'woo-order-images/';
		$db_paths    = $this->get_referenced_image_relative_paths();

		if ( ! is_dir( $woi_dir ) ) {
			return array(
				'in_use_count'   => count( $db_paths ),
				'orphaned_count' => 0,
			);
		}

		$disk_files = $this->get_disk_image_paths( $woi_dir );
		$orphaned   = 0;

		foreach ( array_keys( $disk_files ) as $disk_path ) {
			$rel_path = $this->normalize_upload_relative_path( str_replace( $woi_dir, '', $disk_path ) );

			if ( '' === $rel_path || ! isset( $db_paths[ $rel_path ] ) ) {
				$orphaned++;
			}
		}

		return array(
			'in_use_count'   => count( $db_paths ),
			'orphaned_count' => $orphaned,
		);
	}

	/**
	 * Get unique referenced image relative paths from order item metadata.
	 *
	 * @return array<string, bool>
	 */
	private function get_referenced_image_relative_paths() {
		global $wpdb;

		$results = $wpdb->get_results(
			"SELECT meta_key, meta_value FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE meta_key IN ('_woi_images','_woi_image_urls')"
		);

		$db_paths = array();
		foreach ( $results as $row ) {
			$meta_value = maybe_unserialize( $row->meta_value );
			if ( ! is_array( $meta_value ) ) {
				continue;
			}

			if ( '_woi_images' === $row->meta_key ) {
				foreach ( $meta_value as $image ) {
					$url  = is_array( $image ) && ! empty( $image['url'] ) ? (string) $image['url'] : '';
					$path = $this->url_to_upload_relative_path( $url );
					if ( '' !== $path ) {
						$db_paths[ $path ] = true;
					}
				}
				continue;
			}

			foreach ( $meta_value as $legacy_url ) {
				$path = $this->url_to_upload_relative_path( is_string( $legacy_url ) ? $legacy_url : '' );
				if ( '' !== $path ) {
					$db_paths[ $path ] = true;
				}
			}
		}

		return $db_paths;
	}

	/**
	 * Get image file paths currently present on disk.
	 *
	 * @param string $woi_dir Base WOI upload directory.
	 * @return array<string, bool>
	 */
	private function get_disk_image_paths( $woi_dir ) {
		$disk_files = array();

		try {
			foreach ( new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $woi_dir, RecursiveDirectoryIterator::SKIP_DOTS )
			) as $file ) {
				if ( $file->isFile() ) {
					$disk_files[ $file->getRealPath() ] = true;
				}
			}
		} catch ( Exception $e ) {
			return array();
		}

		return $disk_files;
	}

	/**
	 * Delete orphaned image files (on disk but not in any order).
	 *
	 * @return int Number of files deleted.
	 */
	public function cleanup_orphaned_images() {
		$db_paths = $this->get_referenced_image_relative_paths();

		// Get all files from disk.
		$uploads_dir = wp_upload_dir();
		$woi_dir     = trailingslashit( $uploads_dir['basedir'] ) . 'woo-order-images/';

		if ( ! is_dir( $woi_dir ) ) {
			return 0;
		}

		$disk_files = $this->get_disk_image_paths( $woi_dir );

		// Delete orphaned files.
		$deleted = 0;
		foreach ( array_keys( $disk_files ) as $disk_path ) {
			$rel_path = $this->normalize_upload_relative_path( str_replace( $woi_dir, '', $disk_path ) );

			if ( '' !== $rel_path && ! isset( $db_paths[ $rel_path ] ) && is_file( $disk_path ) ) {
				if ( @unlink( $disk_path ) ) {
					$deleted++;
				}
			}
		}

		return $deleted;
	}

	/**
	 * Convert an uploads URL into an uploads-relative path.
	 *
	 * @param string $url Uploaded file URL.
	 * @return string
	 */
	private function url_to_upload_relative_path( $url ) {
		if ( '' === $url ) {
			return '';
		}

		$uploads = wp_upload_dir();
		$baseurl = isset( $uploads['baseurl'] ) ? (string) $uploads['baseurl'] : '';
		if ( '' === $baseurl ) {
			return '';
		}

		$normalized_url     = untrailingslashit( strtolower( (string) $url ) );
		$normalized_baseurl = untrailingslashit( strtolower( $baseurl ) );
		if ( 0 !== strpos( $normalized_url, $normalized_baseurl . '/' ) ) {
			return '';
		}

		$relative = substr( (string) $url, strlen( $baseurl ) + 1 );

		return $this->normalize_upload_relative_path( $relative );
	}

	/**
	 * Normalize uploads-relative path format for stable comparisons.
	 *
	 * @param string $relative_path Relative path under uploads.
	 * @return string
	 */
	private function normalize_upload_relative_path( $relative_path ) {
		$path = str_replace( '\\', '/', trim( (string) $relative_path ) );
		$path = ltrim( $path, '/' );

		return $path;
	}
}