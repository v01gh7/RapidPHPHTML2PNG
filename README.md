# RapidHTML2PNG

RapidHTML2PNG is a PHP API endpoint that converts one or more HTML blocks into a PNG file with a transparent background.

The project is implemented as a single script (`convert.php`) with built-in:
- input validation and HTML sanitization,
- CSS loading by URL with cache invalidation,
- content-hash based PNG caching,
- automatic renderer detection and fallback,
- optional forced renderer selection via request flag.

## Real Rendering Behavior

Renderer priority:
1. `wkhtmltoimage` (best fidelity, full HTML/CSS rendering)
2. `gd` (stable fallback, simplified text-based rendering)
3. `imagick` (secondary fallback, simplified text-based rendering)

Important detail:
- `imagick` and `gd` fallback modes do not render full browser layout. They extract text and apply only basic style hints.
- Output PNG is saved with a transparent background.
- GD fallback uses a Unicode TrueType font (`DejaVuSans` when available), so Cyrillic text is rendered in UTF-8 correctly.
- Optional request flag `render_engine` allows forced engine selection (`wkhtmltoimage|gd|imagick|imagemagick`); without it, auto-fallback is used.

## Encoding (UTF-8)

The endpoint normalizes text to UTF-8 in all critical data paths:
- Request payload parsing (`application/json`, `multipart/form-data`, `x-www-form-urlencoded`)
- `html_blocks` validation/sanitization
- `css_url` validation input
- CSS content loaded from remote URL and CSS cache files
- HTML text extraction used by fallback renderers
- Temporary HTML files for rendering include `<meta charset="UTF-8">`

If non-UTF-8 text is detected, the API attempts conversion from common legacy encodings (`Windows-1251`, `CP1251`, `KOI8-R`, `ISO-8859-1`) before returning an error.

## Requirements

Minimum:
- PHP 7.4+
- Extensions: `curl`, `gd`, `mbstring`
- Writable directories:
  - `assets/media/rapidhtml2png`
  - `logs`

Optional (for better rendering quality):
- `wkhtmltoimage`
- `imagick` extension

## Project Structure (Current)

```text
RapidHTML2PNG/
├── convert.php
├── main.css
├── test_html_to_render.html
├── test_28_minimal.html
├── test_40.html
├── Dockerfile
├── docker-compose.yml
├── init.sh
├── start_server.bat
├── RUN_E2E_TEST.md
├── check_dimensions.php
├── debug_imagemagick.php
├── assets/
│   └── media/
│       └── rapidhtml2png/
└── logs/
```

## Quick Start

### Option 1: Docker (recommended)

```bash
docker compose up -d
```

API endpoint:
- `http://localhost:8080/convert.php`

### Option 2: Local PHP server

```bash
php -S localhost:8080
```

Windows helper:
```bat
start_server.bat
```

## API

### Endpoint

`POST /convert.php`

Supported content types:
- `application/json`
- `multipart/form-data`
- `application/x-www-form-urlencoded`

### Request fields

- `html_blocks` (required): array of HTML strings
- `css_url` (optional): `http`/`https` URL to CSS file
- `render_engine` (optional): renderer override
  - `auto` (default)
  - `wkhtmltoimage`
  - `gd`
  - `imagick` or `imagemagick` (alias)

Compatibility aliases for the same option:
- `renderer`
- `engine`

### cURL example (JSON)

```bash
curl -X POST http://localhost:8080/convert.php \
  -H "Content-Type: application/json" \
  -d '{
    "html_blocks": ["<div><p>Hello</p></div>"],
    "css_url": "http://localhost/main.css",
    "render_engine": "auto"
  }'
```

### PowerShell example (form-urlencoded)

```powershell
$html = Get-Content -Raw test_html_to_render.html
$body = @{
  'html_blocks[]' = $html
  'css_url' = 'http://localhost/main.css'
  'render_engine' = 'gd'
}
Invoke-RestMethod -Method Post -Uri 'http://localhost:8080/convert.php' -Body $body
```

## Response Shape

Success response:

```json
{
  "success": true,
  "message": "HTML converted to PNG successfully",
  "data": {
    "content_hash": "<md5>",
    "render_engine_requested": "auto|wkhtmltoimage|gd|imagemagick",
    "library_detection": { ... },
    "rendering": {
      "engine": "wkhtmltoimage|imagemagick|gd",
      "selection_mode": "auto|forced",
      "requested_engine": "auto|wkhtmltoimage|gd|imagemagick",
      "cached": false,
      "output_file": ".../assets/media/rapidhtml2png/<md5>.png",
      "file_size": 12345,
      "width": 800,
      "height": 200,
      "mime_type": "image/png"
    }
  }
}
```

Error response:

```json
{
  "success": false,
  "error": "...",
  "timestamp": "..."
}
```

## Caching

Two cache layers are used:

1. CSS cache:
- Stored in `assets/media/rapidhtml2png/css_cache`
- Uses conditional requests (`ETag`, `Last-Modified`) when available
- Falls back to TTL logic if required

2. PNG cache:
- Output file name is MD5 of `html_blocks + css_content`
- If the same hash already exists, the existing PNG is returned
- In forced mode (`render_engine` set), engine-specific cache files are used: `<hash>_<engine>.png`

## Notes for Docker CSS URLs

`css_url` is fetched by PHP inside the container.

When using Docker, this works reliably:
- `http://localhost/main.css`

This usually fails from inside container context:
- `http://localhost:8080/main.css`

## Troubleshooting

- `405 Method Not Allowed`: use `POST`, not `GET`.
- `Missing required parameter: html_blocks`: send at least one HTML block.
- `No rendering libraries available`: install/enable required extensions and tools.
- `Requested rendering engine is not available`: remove `render_engine` or pick an available engine from `library_detection`.
- CSS fetch errors: verify `css_url` is reachable from the PHP runtime environment.
- `WriteBlob Failed` or `Output file was not created`: fix output permissions inside container:
  - `docker compose exec -T app sh -lc "chown -R www-data:www-data /var/www/html/assets/media/rapidhtml2png && chmod -R 775 /var/www/html/assets/media/rapidhtml2png"`

## License

Set your license in this repository.
