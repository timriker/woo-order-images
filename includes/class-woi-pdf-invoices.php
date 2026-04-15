<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Integrates Woo Order Images thumbnails into PDF Invoices & Packing Slips for WooCommerce
 * (plugin slug: woocommerce-pdf-invoices-packing-slips, class: WPO_WCPDF).
 *
 * Hooks into wpo_wcpdf_after_item_meta to inject a small cropped JPEG thumbnail,
 * encoded as a data URI, directly into the PDF HTML before the renderer sees it.
 * This avoids any HTTP requests from the PDF engine (mPDF/Dompdf), so it works
 * regardless of authentication context (admin-triggered or automated email).
 */
class WOI_PDF_Invoices {

	public function init() {
		// Register the hook unconditionally — it will never fire if WPO WCPDF is absent.
		add_action( 'wpo_wcpdf_after_item_meta', array( $this, 'inject_thumbnails' ), 10, 3 );
	}

	/**
	 * Output thumbnail(s) for a line item's WOI images into the PDF document.
	 *
	 * @param string   $document_type WPO document type slug ('invoice', 'packing-slip', etc.).
	 * @param array    $item          WPO-processed item data array.  Key 'item' holds the
	 *                                underlying WC_Order_Item_Product object.
	 * @param WC_Order $order         The WooCommerce order.
	 */
	public function inject_thumbnails( $document_type, $item, $order ) {
		if ( ! is_array( $item ) || ! isset( $item['item'] ) ) {
			return;
		}

		$wc_item = $item['item'];
		if ( ! $wc_item instanceof WC_Order_Item_Product ) {
			return;
		}

		$images = $wc_item->get_meta( WOI_Order_Images::ORDER_META_IMAGES, true );
		if ( empty( $images ) || ! is_array( $images ) ) {
			return;
		}

		$thumb_html = '';
		foreach ( $images as $image_entry ) {
			if ( empty( $image_entry['url'] ) ) {
				continue;
			}

			$data_uri = WOI_Admin_Order_Images::generate_pdf_thumbnail_data_uri( $wc_item, $image_entry );
			if ( '' === $data_uri ) {
				continue;
			}

			// Inline style only — mPDF/Dompdf have limited CSS support.
			// max-height drives the row height; width:auto preserves aspect ratio.
			$thumb_html .= '<img src="' . esc_attr( $data_uri ) . '" '
				. 'style="max-height:60px;max-width:60px;width:auto;height:auto;'
				. 'display:inline-block;vertical-align:middle;margin:2px;" alt="">';
		}

		if ( '' === $thumb_html ) {
			return;
		}

		echo '<div style="margin-top:4px;line-height:0;">' . $thumb_html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
