# RapidHTML2PNG MODX Ready Package (`ready_pocket`)

This folder contains a buildable MODX transport package source for Revo 2.8+.

Package installs:
- plugin `RapidHTML2PNGHeaderButton`
- snippet `RapidHTML2PNGBulkRender`
- system settings `rapidhtml2png_*`
- files:
  - `assets/components/rapidhtml2png/...`
  - `core/components/rapidhtml2png/...`
  - `connectors/rapidhtml2png.php`

## Build

1. Open terminal in this folder:
   - `integrations/modx/ready_pocket`
2. Install dependencies:
   - `composer install`
3. Build transport package:
   - `composer run build`

Expected package name:
- `rapidhtml2png-1.0.0-pl.transport.zip`

## Install in MODX

1. Open MODX manager.
2. `Extras -> Installer`.
3. Upload `rapidhtml2png-1.0.0-pl.transport.zip`.
4. Install package.
5. Set required settings:
   - `rapidhtml2png_convert_url`
   - `rapidhtml2png_convert_api_key`
6. Clear MODX cache and reload manager.

## Notes

- Build script is based on transport-package workflow used in MODX docs/community guides.
- File copying is handled by `file` resolvers in `_build/build.transport.php`.
- Version checks are in `_build/validators`.
