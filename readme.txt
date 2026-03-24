=== Woo Order Images ===
Contributors: timriker
Tags: woocommerce, product images, image upload, printing, puzzle
Requires at least: 6.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.6.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Collect customer-uploaded product photos for WooCommerce, store crops as metadata, and generate order-level print sheets with bleed, watermark tabs, and puzzle layouts.

== Description ==

Woo Order Images extends WooCommerce products with image upload and crop support, then renders print-ready order sheets from the saved source image plus crop metadata.

Key capabilities include:

* Product-page image upload and crop flow
* Optional add-to-cart, required image completion before checkout
* Canonical source image storage with crop metadata persistence
* Admin hover re-crop on order images
* Order-level print-sheet generation
* Puzzle layouts with portrait and landscape grid support
* Global print bleed, margins, watermark text, and page size settings

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/woo-order-images/`, or upload the release zip from WordPress admin.
2. Activate the plugin through the `Plugins` screen in WordPress.
3. Visit `WooCommerce > Order Images` to configure global settings.
4. Edit WooCommerce products and enable Woo Order Images where needed.

== Frequently Asked Questions ==

= Are uploaded images cropped into new files? =

No. The original uploaded image is stored once, and crop geometry is saved as metadata. Print output is generated from the source image at render time.

= Do customers have to upload images before adding to cart? =

No. WOI products can be added to cart with or without images, but checkout can enforce that required image slots are completed first.

= Does the plugin support puzzle products? =

Yes. Puzzle products can split one source image across a configured grid and support portrait or landscape puzzle orientation where appropriate.

= Can admins re-crop after checkout? =

Yes. Admins can hover order-image thumbnails and reopen the crop editor without replacing the original uploaded image.

== Third-Party Assets ==

This plugin bundles the following third-party library:

* Cropper.js 1.6.2 — MIT License
  * https://github.com/fengyuanchen/cropperjs

== Changelog ==

= 0.6.5 =
* Added cache-busting print-image URLs and cache-friendly response headers.
* Updated bleed generation to prefer real source-image pixels before edge extension.
* Improved puzzle tile bleed generation for interior pieces.
* Removed the temporary print debug logger.
* Removed the unnecessary cleanup self-POST.

= 0.6.4 =
* Refactored print sheets to load tile images from dedicated URLs instead of inline base64 blobs.
* Added a dedicated admin print-image endpoint.
* Fixed the fatal in the new print-image path.

= 0.6.3 =
* Updated the admin WOI badge to use exact configured dimensions.

= 0.6.2 =
* Added admin hover re-crop controls and orientation reopen fixes.

= 0.6.1 =
* Reverted the right-side watermark band offset after overlap issues.

= 0.6.0 =
* Reduced and repositioned watermark text.
