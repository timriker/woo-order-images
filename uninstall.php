<?php
/**
 * Woo Order Images Uninstall Script
 *
 * Handles cleanup when the plugin is deleted via WordPress admin.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete all WOI settings options.
$options_to_delete = array(
	'woi_watermark_text',
	'woi_print_bleed',
	'woi_print_margin_top',
	'woi_print_margin_right',
	'woi_print_margin_bottom',
	'woi_print_margin_left',
	'woi_print_gap',
	'woi_print_page_size',
);

foreach ( $options_to_delete as $option ) {
	delete_option( $option );
}

// Delete uploaded WOI images (optional - only delete if you want to free up disk space).
// Comment this block if you prefer to keep images after uninstall.
$uploads_dir = wp_upload_dir();
if ( ! empty( $uploads_dir['basedir'] ) ) {
	$woi_dir = trailingslashit( $uploads_dir['basedir'] ) . 'woo-order-images/';
	if ( is_dir( $woi_dir ) ) {
		// Recursively delete all files in the woo-order-images directory.
		_woi_delete_directory_recursive( $woi_dir );
	}
}

/**
 * Recursively delete a directory and all its contents.
 *
 * @param string $path Directory path to delete.
 * @return bool True on success, false on failure.
 */
function _woi_delete_directory_recursive( $path ) {
	if ( ! is_dir( $path ) ) {
		return false;
	}

	$files = @scandir( $path );
	if ( ! is_array( $files ) ) {
		return false;
	}

	foreach ( $files as $file ) {
		if ( '.' === $file || '..' === $file ) {
			continue;
		}

		$full_path = $path . $file;
		if ( is_dir( $full_path ) ) {
			_woi_delete_directory_recursive( trailingslashit( $full_path ) );
		} else {
			@unlink( $full_path );
		}
	}

	return @rmdir( $path );
}
