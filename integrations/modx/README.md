# MODX Revo 2.8 Integration (RapidHTML2PNG)

This integration adds a `Render` button to the MODX manager header and opens a modal window with two modes:
- `Render all resources`
- `Render by IDs`

Pipeline:
1. The resource template is loaded.
2. The template is rendered through `pdoTools` (resource fields and TVs are injected).
3. Text blocks are extracted from the final HTML.
4. The blocks are sent in batches to `convert.php` (`html_blocks[]`).

## 0. Ready Transport Package

Prebuilt package in repository:
- `integrations/modx/rapidhtml2png-1.0.0-pl.transport.zip`

You can upload this file directly in MODX:
- `Extras -> Installer -> Upload a package`

## Docker Build (PHP 7.4)

Package builder Dockerfile:
- `integrations/modx/ready_pocket/Dockerfile.package`

PowerShell commands from project root:

```powershell
docker build -f .\integrations\modx\ready_pocket\Dockerfile.package -t rapidhtml2png-modx-packager .\integrations\modx\ready_pocket
$src = (Resolve-Path .\integrations\modx\ready_pocket).Path
docker run --rm -v "${src}:/work" -w /work rapidhtml2png-modx-packager bash -lc "composer config --global audit.block-insecure false && composer install --no-interaction --no-progress && composer run build"
Copy-Item .\integrations\modx\ready_pocket\_packages\rapidhtml2png-1.0.0-pl.transport.zip .\integrations\modx\rapidhtml2png-1.0.0-pl.transport.zip -Force
```

## 1. Prerequisites

- MODX Revo `2.8.x`
- Installed `pdoTools`
- Working RapidHTML2PNG converter endpoint (for example: `https://example.com/convert.php`)

## 2. File Copy

Copy files from this folder to your MODX root:

- `integrations/modx/assets/components/rapidhtml2png/js/mgr/rapidhtml2png.js`
  -> `assets/components/rapidhtml2png/js/mgr/rapidhtml2png.js`
- `integrations/modx/core/components/rapidhtml2png/elements/plugins/rapidhtml2png_header_button.plugin.php`
  -> `core/components/rapidhtml2png/elements/plugins/rapidhtml2png_header_button.plugin.php`
- `integrations/modx/core/components/rapidhtml2png/model/rapidhtml2png/RendererService.php`
  -> `core/components/rapidhtml2png/model/rapidhtml2png/RendererService.php`
- `integrations/modx/connectors/rapidhtml2png.php`
  -> `connectors/rapidhtml2png.php`

## 3. MODX System Settings

Create system settings (`System Settings`) in namespace `core` (or your custom namespace):

- `rapidhtml2png_convert_url` = `https://example.com/convert.php`
- `rapidhtml2png_convert_api_key` = `YOUR_CONVERTER_API_KEY`
- `rapidhtml2png_css_url` = `https://example.com/main.css` (optional)
- `rapidhtml2png_render_engine` = `auto` (or `gd`, `imagick`, `wkhtmltoimage`)
- `rapidhtml2png_request_timeout` = `120`
- `rapidhtml2png_batch_max_bytes` = `4500000`
- `rapidhtml2png_batch_max_blocks` = `75`
- `rapidhtml2png_default_skip_classes` = `no-render,skip-export` (optional)

## 4. Create MODX Plugin

1. Go to `Elements -> Plugins -> New Plugin`
2. Name: `RapidHTML2PNGHeaderButton`
3. Plugin code: paste code from file  
   `core/components/rapidhtml2png/elements/plugins/rapidhtml2png_header_button.plugin.php`
4. In `System Events`, enable:
   - `OnManagerPageBeforeRender`
5. Save the plugin.

## 5. Usage

1. Open MODX manager and refresh the page.
2. A `Render` button will appear in the header.
3. Click the button:
   - `Render all resources` -> processes all non-deleted resources.
   - `Render by IDs` -> processes only IDs from the field (`1,2,10`).
4. `Skip CSS classes` field accepts comma-separated class names.  
   Any HTML node with one of these classes (or inside such node) is skipped.

## 6. Connector Response

Connector `connectors/rapidhtml2png.php` returns JSON:

- `success` (bool)
- `message` (summary)
- `data`:
  - `rendered_resources`
  - `total_blocks`
  - `batches_total`
  - `batches_success`
  - `batches_failed`
  - `output_files`
  - `resource_stats`
  - `resource_errors`
  - `batch_results`

## 7. Current Implementation Limits

- Text DOM blocks are extracted after template rendering (not from raw content).
- Blocks are deduplicated by `md5`.
- For large payloads, block data is sent in size/count-based batches.
- `all` mode loads all resources with `deleted = 0`.
