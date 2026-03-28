=== Woo Order Images ===
Contributors: timriker
Tags: woocommerce, product images, image upload, printing, puzzle
Requires at least: 6.8
Tested up to: 6.9.4
Requires PHP: 7.4
Stable tag: 0.7.1
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

== Upgrades ==

Sites using the bundled GitHub update checker can upgrade from the WordPress `Plugins` screen when a newer GitHub release is available.
Manual upgrades by uploading the release zip are still supported if needed.

== Frequently Asked Questions ==

= Are uploaded images cropped into new files? =

No. The original uploaded image is stored once, and crop geometry is saved as metadata. Print output is generated from the source image at render time.

= Do customers have to upload images before adding to cart? =

No. WOI products can be added to cart with or without images, but checkout can enforce that required image slots are completed first.

= Does the plugin support puzzle products? =

Yes. Puzzle products can split one source image across a configured grid and support portrait or landscape puzzle orientation where appropriate.

= Can admins re-crop after checkout? =

Yes. Admins can hover order-image thumbnails and reopen the crop editor without replacing the original uploaded image.

= Does the plugin support updates from GitHub releases? =

Yes. The plugin bundles a GitHub update checker so installed sites can receive in-dashboard updates from tagged GitHub releases.
Manual zip installation is still supported for first-time installs or fallback upgrades.

== Third-Party Assets ==

This plugin bundles the following third-party library:

* Cropper.js 1.6.2 — MIT License
  * https://github.com/fengyuanchen/cropperjs
* plugin-update-checker 5.6 — MIT License
  * https://github.com/YahnisElsts/plugin-update-checker

== Changelog ==

= 0.7.1 =
* Added a `Print Sheet` action in the WooCommerce admin Orders list `Actions` column for orders with WOI images, with compatibility across both order-action hook paths.
* Added legacy `post_row_actions` fallback support for non-HPOS order tables.
* Styled the product-page `Choose Files` control to render as a visible button across themes.

= 0.7.0 =
* Removed SVG grid overlay from shared thumbnail renderer — grid is now baked once as raster in the JPEG thumbnail only, eliminating the double-grid rendering issue on cart and admin views.
* Unified order history thumbnail rendering to use the same shared renderer as cart and admin (`render_thumbnail_html_static()`).
* Fixed admin order image frames for puzzle products — frame aspect ratio now reflects the full puzzle grid dimensions (cols × tile width : rows × tile height) rather than the single-tile aspect ratio, preventing square frames and row clipping on portrait puzzles.
* Fixed AJAX crop-save response to return the correct puzzle-aware aspect ratio for live admin frame updates.
* Non-puzzle items now always store `puzzle_cols=0` and `puzzle_rows=0`, preventing grid overlays from appearing on non-puzzle magnets.
* Added `get_thumbnail_frame_aspect_ratio()` helper for consistent puzzle-vs-non-puzzle frame sizing.
* Added daily scheduled orphan-image cleanup with 24-hour protection for recently uploaded files.
* Added multi-slot image assignment in the frontend crop modal with per-slot apply count display.
* Added orientation and rotation persistence across cart sessions.

= 0.6.10 =
* Fixed GitHub repository link in plugin details from `bestlifemagnets/woo-order-images` to `timriker/woo-order-images`.

= 0.6.9 =
* Added a release check that requires the current version to appear in the WordPress-facing `readme.txt` changelog.
* Updated release guidance so both `CHANGELOG.md` and `readme.txt` changelog entries are required before tagging.

= 0.6.8 =
* Added release workflow validation for plugin version, `WOI_VERSION`, `Stable tag`, and changelog alignment.
* Tightened release guidance so changelog updates happen before tagging.

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
