# Changelog

All notable changes to `woo-order-images` will be documented in this file.

This project follows a pragmatic release history rather than a strict changelog format for older versions. Recent releases are documented in more detail.

## [0.6.8] - 2026-03-24

### Changed
- Added a release workflow validation step that checks plugin version, `WOI_VERSION`, `readme.txt` `Stable tag`, and the matching `CHANGELOG.md` entry.
- Tightened release guidance in project docs so changelog and metadata updates are part of the normal release routine.

## [0.6.7] - 2026-03-24

### Added
- Bundled `plugin-update-checker 5.6` for GitHub-based plugin update checks.
- Added updater notices for bundled third-party dependencies.

### Changed
- Wired the plugin bootstrap to check GitHub releases and release zip assets for updates.
- Updated release-process docs so `Tested up to`, `Stable tag`, and changelog updates are part of the normal release flow.
- Updated `readme.txt` metadata to match the tested WordPress environment (`6.9.4`) and the current stable tag.

## [0.6.6] - 2026-03-24

### Added
- Added a WordPress-style `readme.txt`.
- Added a full GPL license file and explicit plugin license metadata.
- Bundled local Cropper.js assets and vendor notices.

### Changed
- Updated project documentation and AI development guidance to match the current plugin workflow.
- Updated the GitHub Actions workflow to lint on pushes to `main` and to pull release notes from `CHANGELOG.md`.
- Normalized remaining `Woo` branding in docs and workflow text.

## [0.6.5] - 2026-03-24

### Changed
- Added cache-busting `v=` tokens to print-image URLs so browser caching stays fresh after crop or layout changes.
- Added cache-friendly image response headers for print-image endpoints.
- Updated bleed generation to use real source-image pixels where available before falling back to edge extension.
- Improved puzzle tile bleed generation so interior pieces use adjacent source pixels instead of repeated edge pixels.

### Removed
- Removed the temporary `/tmp` print debug logger and returned to normal PHP log handling.
- Removed the unnecessary settings-page self-POST during orphan-image cleanup.

## [0.6.4] - 2026-03-24

### Changed
- Refactored print sheets to load tile images from dedicated URLs instead of embedding large inline base64 images in the page HTML.
- Added a dedicated admin print-image endpoint for single tiles and puzzle pieces.
- Fixed the fatal error in the new print-image path by replacing the unavailable `wc_get_order_item()` call with order-backed item lookup.

### Fixed
- Restored successful rendering for large dev print sheets such as order `#108`.
- Reduced print-sheet memory pressure significantly by moving image generation to per-image requests.

## [0.6.3] - 2026-03-24

### Changed
- Updated the admin `WOI` product-list badge to use exact configured dimensions instead of rounded shorthand.

### Fixed
- A `2.5x3.5` single-image product now displays as `1-2.5x3.5` instead of `1-3x4`.

## [0.6.2] - 2026-03-24

### Added
- Added admin-side hover re-crop controls for order-image thumbnails.
- Added an admin crop modal for updating saved crop metadata after checkout.

### Fixed
- Fixed admin re-crop orientation reopening so saved landscape and portrait crops reopen correctly.
- Improved orientation handling for non-square items and puzzle crops in the admin editor.

## [0.6.1] - 2026-03-24

### Fixed
- Reverted the right-side watermark band container offset after it caused overlap on some prints.

## [0.6.0] - 2026-03-24

### Changed
- Reduced watermark text size by about 20 percent.
- Moved watermark text closer to the image, especially on the top and bottom tabs.
- Adjusted left and right watermark spacing for better visual balance.

## [0.5.1] - 2026-03-23

### Fixed
- Preserved original image aspect ratio during print rendering instead of stretching malformed or API-generated crops.

## [0.5.0] - 2026-03-23

### Added
- Moved bleed area to a global plugin setting instead of storing it per product and per order item.

### Changed
- Print rendering now always uses the current global bleed setting.
- Existing orders automatically reflect updated bleed settings at print time.

## [0.4.0] - 2026-03-23

### Changed
- Established the current print-sheet workflow and release packaging structure.

## [0.3.0] - 2026-03-23

### Changed
- Expanded the plugin’s WooCommerce order-image workflow and admin tooling.

## [0.2.0] - 2026-03-23

### Added
- Initial tagged release of the Woo Order Images plugin.
