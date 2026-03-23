# WOO Order Images (WOI)

A WooCommerce plugin for managing custom product images in orders. Customers upload and crop their photos during checkout, then admins generate consolidated, print-ready order sheets with watermarked image tiles.

## Features

### Customer-Facing (Frontend)
- **Product Image Upload**: Add photo images to products at add-to-cart stage on product pages
- **Interactive Crop Modal**: Cropper.js-based interface with zoom slider, rotation, and live preview
- **Edit Mode**: Pre-loads previously uploaded images if replacing items in cart
- **Save Once, Crop as Metadata**: Stores source image once and saves crop geometry (x/y/width/height) instead of rewriting cropped files on every edit
- **Progress Tracking**: "x of y images" labels and visual "Edit Images" links in cart
- **Validation**: Requires all images before checkout; cart respects image completeness

### Admin (Backend)
- **Order-Level Print Sheet**: Opens a full print preview with all order images consolidated onto 8.5×11 pages
- **Print-Time Crop Application**: Applies saved crop geometry at render time for both single magnets and puzzle tiles
- **Automatic Packing**: Bin-packs tiles across pages, respecting printer margins (0.25" top/left/right, 0.5" bottom by default)
- **Mixed Spec Sources**: Each product defines visible area and puzzle grid, while global plugin settings define bleed, paper size, margins, and watermark text
- **Auto-Orientation**: Detects image landscape/portrait vs. product aspect ratio; swaps template orientation automatically
- **Watermark Labels**: Small text bands on bleed edges (1/3 of visible area height), configurable text and per-tile sizing
- **Corner Die-Cut**: Clip-path polygon corners tie to wrap margin dimensions
- **Hidden Order Meta**: Internal `_woi_*` metadata keys suppressed from customer view
- **Auto-Cleanup**: Deletes uploaded WOI images when order is trashed

## Installation

1. Copy/clone plugin into `wp-content/plugins/woo-order-images/`
2. Activate via WordPress admin > Plugins
3. Configure at **WooCommerce > Order Images** (watermark text, bleed, print margins)
4. Set per-product image specs in product edit screen

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

### Product Settings (Product Edit > Woo Order Images tab)

| Field | Default | Example | Purpose |
|-------|---------|---------|---------|
| Enable for this product | — | ✓ | Activate WOI for product |
| Visible width | 2.0 | 2.5 | Magnet face width (inches) |
| Visible height | 2.0 | 3.5 | Magnet face height (inches) |
| Image limit | 1 | 4 | Max images per line item |

## Architecture

### Core Classes

- **WOI_Order_Images** (`includes/class-woi-order-images.php`)
  - Cart validation and item meta storage
  - Add-to-cart/update-cart flow with image preloading
  - Hidden meta key filtering
  - Order cleanup hooks

- **WOI_Frontend** (`includes/class-woi-frontend.php`)
  - Product page modal UI binding
  - Crop modal state and export logic
  - Rotation and zoom controls

- **WOI_Admin_Order_Images** (`includes/class-woi-admin-order-images.php`)
  - Admin order details rendering
  - **render_print_page()**: Order-level print sheet generation
  - Tile packing & page break logic
  - Watermark rendering with per-tile font sizing

- **WOI_Admin_Product_Settings** (`includes/class-woi-admin-product-settings.php`)
  - Product metabox UI for visible area, puzzle configuration, image limit

- **WOI_Settings** (`includes/class-woi-settings.php`)
  - Global plugin options (watermark text, bleed, print margins)
  - Settings page registration and sanitization

### Print Rendering Pipeline

1. **Collect**: Gather structured image entries (`url` + `crop`) from order items
2. **Spec**: Resolve each item's visible area (width/height) from the order and apply the current global bleed setting
3. **Orient**: Detect image ratio vs. product aspect; auto-swap orientation if mismatch
4. **Crop**: Apply saved crop geometry to source image at render time
5. **Pack**: Bin-pack tiles left-to-right, then top-to-bottom, respecting page bounds and gaps
6. **Render**: For each page, output absolute-positioned tiles with:
   - Visible-area window (centered crop region)
   - Bleed area behind (padded by wrap margin)
   - Watermark bands (positioned in bleed, avoiding visible window)
   - Cut outline (SVG polygon from corner geometry)

### Asset Organization

- **assets/js/frontend.js**: Cropper.js integration, image rotation (canvas-based bitmap), crop metadata capture
- **assets/css/frontend.css**: Modal styles, zoom slider, button layout
- **assets/css/print-sheet.css**: Print-specific styles, watermark positioning, color preservation hints

## Development Notes

### Code Style
- No noisy HTML (data-* attributes kept minimal—runtime-computed geometry not hard-coded)
- Hooks over filters where possible
- Favor explicit static methods in settings class over global getters

### Key Decisions
- **Order-level print**: Consolidated output reduces paper waste and print jobs
- **Canonical source persistence**: Source images are written once; edits update crop metadata only
- **Development-mode schema agility**: Non-backward-compatible schema updates are acceptable; test/legacy orders can be removed as needed
- **Per-tile watermark sizing**: `font_pt = wrap_margin × 24`, so labels scale with the current global bleed
- **Canvas-based rotation**: Bitmap rotation in frontend (not CSS transform) for accurate crop geometry
- **Bin-packing algorithm**: Left-to-right rows with gap enforcement, new page when overflow
- **Hard page height**: `height:11in` + `overflow:hidden` prevents blank-page artifacts

### Common Tasks

#### Adding a new product setting
1. Add metabox field in `WOI_Admin_Product_Settings::render_metabox()`
2. Create getter in `WOI_Admin_Product_Settings::get_product_spec()`
3. Use in `WOI_Admin_Order_Images::get_oriented_spec()` if it affects geometry

#### Adjusting print margins
Edit `WOI_Settings` constants or use the admin settings page (no code change needed).

#### Changing watermark placement
Modify band height/position logic in `render_print_page()` (search `top_safe`, `bottom_safe`, etc.).

## Testing

- **Unit**: E2E test order creation with mixed 2×2 and 2.5×3.5 items, coupon application
- **Integration**: Cart add/update/edit flow with preload and rotate
- **Print**: Export PDF from browser print dialog; verify spacing, watermark size, page breaks

## License

[Your License Here]

## Support

For issues or feature requests, contact the development team or open an issue on GitHub.
