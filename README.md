# Woo Order Images (WOI)

A WooCommerce plugin for collecting customer photos on WooCommerce products, storing crops as metadata, and generating consolidated order-level print sheets with bleed, watermark tabs, and puzzle layouts.

## Features

### Customer-Facing (Frontend)
- **Product Image Upload**: Add photo images to WOI-enabled products from the product page
- **Interactive Crop Modal**: Cropper.js-based interface with zoom slider, rotation, orientation swap, and live preview
- **Edit Mode**: Pre-loads previously uploaded images if replacing items in cart
- **Save Once, Crop as Metadata**: Stores source image once and saves crop geometry (`x/y/width/height`) instead of rewriting cropped files on every edit
- **Flexible Add-to-Cart Flow**: Items can be added with or without images, but checkout enforces required image completion
- **Progress Tracking**: "x of y images" labels and visual "Edit Images" links in cart
- **Puzzle Orientation Support**: Puzzle crops can swap between portrait and landscape puzzle layouts

### Admin (Backend)
- **Order-Level Print Sheet**: Opens a full print preview with all order images consolidated onto printable pages
- **Print-Time Crop Application**: Applies saved crop geometry at render time for both single magnets and puzzle tiles
- **Admin Re-Crop**: Hover thumbnails on the order screen to reopen and adjust saved crops
- **Automatic Packing**: Bin-packs tiles across pages, respecting printer margins and configurable gaps
- **Mixed Spec Sources**: Each product defines visible area and puzzle grid, while global plugin settings define bleed, paper size, margins, and watermark text
- **Auto-Orientation**: Detects image landscape/portrait vs. product aspect ratio and swaps template orientation automatically
- **Cached Print Tiles**: Print sheets load tile images from dedicated versioned URLs instead of embedding large base64 blobs in the page HTML
- **Bundled Cropper Assets**: Cropper.js is shipped locally with the plugin instead of loaded from a CDN
- **Corner Die-Cut**: Clip-path polygon corners tie to wrap margin dimensions
- **Hidden Order Meta**: Internal `_woi_*` metadata keys suppressed from customer view
- **Auto-Cleanup**: Deletes uploaded WOI images when an order is trashed

## Installation

1. Copy or clone the plugin into `wp-content/plugins/woo-order-images/`
2. Activate it from WordPress admin > Plugins
3. Configure global settings at **WooCommerce > Order Images**
4. Set per-product image specs in the product edit screen

## Configuration

### Plugin Settings (WooCommerce > Order Images)

| Setting | Default | Unit | Purpose |
|---------|---------|------|---------|
| Watermark text | BestLifeMagnets.com | text | Text printed on image bleed tabs |
| Bleed area | 0.4 | inches | Global bleed edge distance used in previews and print output |
| Top margin | 0.25 | inches | Printer safe zone top |
| Right margin | 0.25 | inches | Printer safe zone right |
| Bottom margin | 0.5 | inches | Printer safe zone bottom |
| Left margin | 0.25 | inches | Printer safe zone left |
| Page size | Letter | preset | Print sheet paper size |
| Tile gap | 0.1 | inches | Space between packed print tiles |

### Product Settings (Product Edit > Woo Order Images tab)

| Field | Default | Example | Purpose |
|-------|---------|---------|---------|
| Enable for this product | — | yes | Activate WOI for product |
| Visible width | 2.0 | 2.5 | Magnet face width in inches |
| Visible height | 2.0 | 3.5 | Magnet face height in inches |
| Image limit | 1 | 4 | Max images per line item |
| Puzzle layout | Off | 3×4 | Split one image across a puzzle grid |

## Architecture

### Core Classes

- **WOI_Order_Images** (`includes/class-woi-order-images.php`)
  - Cart validation and item meta storage
  - Add-to-cart/update-cart flow with image preloading
  - Hidden meta key filtering
  - Upload staging and order cleanup hooks

- **WOI_Frontend** (`includes/class-woi-frontend.php`)
  - Product page modal UI binding
  - Crop modal state and export logic
  - Rotation, orientation swap, and zoom controls

- **WOI_Admin_Order_Images** (`includes/class-woi-admin-order-images.php`)
  - Admin order details rendering
  - `render_print_page()`: order-level print sheet generation
  - `render_print_image()`: per-tile print image endpoint
  - Tile packing, page break logic, and watermark rendering
  - Admin re-crop modal and crop save endpoint

- **WOI_Admin_Product_Settings** (`includes/class-woi-admin-product-settings.php`)
  - Product metabox UI for visible area, puzzle configuration, image limit, and admin list columns

- **WOI_Settings** (`includes/class-woi-settings.php`)
  - Global plugin options for watermark text, bleed, print margins, page size, and cleanup actions

## Print Rendering Pipeline

1. **Collect**: Gather structured image entries (`url` + `crop`) from order items
2. **Spec**: Resolve each item's visible area and apply the current global bleed setting
3. **Orient**: Detect image ratio vs. product aspect and auto-swap orientation if needed
4. **Crop**: Apply saved crop geometry to source image at render time
5. **Pack**: Bin-pack tiles left-to-right, then top-to-bottom, respecting page bounds and gaps
6. **Render**: Output absolute-positioned tiles with:
   - Versioned print-image URLs for each rendered tile
   - Visible-area window for the final product face
   - Bleed-capable tile images behind it
   - Watermark bands positioned in the bleed area
   - Cut outline polygons from the configured wrap geometry

## Asset Organization

- **assets/js/frontend.js**: Cropper.js integration, image rotation, crop metadata capture, and puzzle orientation swap
- **assets/js/admin-order-crop.js**: Admin re-crop modal behavior for order images
- **assets/css/frontend.css**: Product-page modal styles, zoom slider, and button layout
- **assets/css/admin-order-crop.css**: Admin hover and crop modal styles
- **assets/css/print-sheet.css**: Print-specific styles, watermark positioning, and color preservation hints

## Development Notes

### Key Decisions
- **Canonical source persistence**: Source images are written once; edits update crop metadata only
- **Order-level print**: Consolidated output reduces paper waste and print jobs
- **Global bleed**: Bleed is a plugin-level print setting, not historical order data
- **Versioned print tiles**: Print images are requested separately and include cache-busting keys derived from crop/spec state
- **Source-pixel bleed first**: Bleed rendering prefers real surrounding source pixels and only stretches edges at image boundaries
- **Canvas-based rotation**: Frontend uses bitmap rotation rather than CSS-only transforms for crop accuracy

### Common Tasks

#### Adding a new product setting
1. Add the metabox field in `WOI_Admin_Product_Settings`
2. Save it with product meta
3. Retrieve it in item/spec resolution
4. Apply it in frontend or print rendering as needed

#### Adjusting print margins or bleed
Use `WOI_Settings` getters and the admin settings page rather than hard-coded values.

#### Changing watermark placement
Modify the band geometry in `render_print_page()` and the supporting styles in `assets/css/print-sheet.css`.

#### Debugging admin orders
Prefer REST, CLI, and direct print URLs before opening WooCommerce order edit screens. If you do open an order in admin, do not leave it locked/open when you are done.

#### Preparing a release
1. Update `woo-order-images.php` version metadata
2. Update `readme.txt` `Stable tag`
3. Sync `readme.txt` `Tested up to` with the WordPress version used for release testing
4. Update `CHANGELOG.md` before tagging so GitHub release notes stay in sync
5. Tag and release from Git

## Testing

- **Cart flow**: Add WOI items with and without images; confirm checkout enforces required completion
- **Integration**: Cart add/update/edit flow with preload, rotate, and puzzle orientation swap
- **Admin**: Re-crop an existing order image from the hover control and verify the print view updates
- **Print**: Export PDF from browser print dialog; verify spacing, watermark size, page breaks, and bleed behavior

## Changelog

Release history is maintained in [CHANGELOG.md](CHANGELOG.md).

## License

This project is licensed under **GPL-2.0-or-later**.

Copyright (C) Tim Riker <timriker@gmail.com>

See [LICENSE](LICENSE) for the repository license notice.

This plugin bundles **Cropper.js 1.6.2** under the MIT license. See [assets/vendor/cropper/NOTICE.txt](assets/vendor/cropper/NOTICE.txt).

This plugin also bundles **plugin-update-checker 5.6** under the MIT license for GitHub-based plugin updates. See [vendor/plugin-update-checker/license.txt](vendor/plugin-update-checker/license.txt).

## Support

For issues or feature requests, contact the development team or open an issue on GitHub.
