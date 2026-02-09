<?php
/**
 * RapidHTML2PNG - HTML to PNG Conversion API
 *
 * This script converts HTML blocks to PNG images with transparent background.
 * It accepts POST requests with HTML content and CSS URL, caches results based
 * on content hash, and auto-detects available rendering libraries.
 *
 * @author RapidHTML2PNG Development Team
 * @version 1.0.0
 */

// Set error reporting for production
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

// Set JSON header for all responses
header('Content-Type: application/json; charset=utf-8');

// Allow CORS for development (restrict in production)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle OPTIONS preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(json_encode(['status' => 'OK']));
}

/**
 * Get library selection log file path
 *
 * @return string Path to log file
 */
function getLibraryLogPath() {
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    return $logDir . '/library_selection.log';
}

/**
 * Log library selection for debugging
 *
 * @param string $selectedLibrary The library that was selected
 * @param array $detectionResults Full detection results from all libraries
 * @param string $reason Explanation of why this library was chosen
 * @return void
 */
function logLibrarySelection($selectedLibrary, $detectionResults, $reason = '') {
    $logPath = getLibraryLogPath();
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = sprintf(
        "[%s] Selected Library: %s\n",
        $timestamp,
        $selectedLibrary ? strtoupper($selectedLibrary) : 'NONE'
    );

    // Add reason
    if (!empty($reason)) {
        $logEntry .= sprintf("  Reason: %s\n", $reason);
    }

    // Add detection details for each library
    if (isset($detectionResults['detected_libraries'])) {
        $logEntry .= "  Detection Results:\n";
        foreach ($detectionResults['detected_libraries'] as $libName => $libInfo) {
            $status = $libInfo['available'] ? 'AVAILABLE' : 'UNAVAILABLE';
            $logEntry .= sprintf("    - %s: %s\n", strtoupper($libName), $status);

            if ($libInfo['available']) {
                // Add details for available libraries
                if (isset($libInfo['version'])) {
                    $logEntry .= sprintf("      Version: %s\n", $libInfo['version']);
                }
                if (isset($libInfo['path'])) {
                    $logEntry .= sprintf("      Path: %s\n", $libInfo['path']);
                }
                if (isset($libInfo['info'])) {
                    $infoStr = json_encode($libInfo['info'], JSON_UNESCAPED_SLASHES);
                    $logEntry .= sprintf("      Info: %s\n", $infoStr);
                }
            } else {
                // Add reason for unavailable libraries
                if (isset($libInfo['reason'])) {
                    $logEntry .= sprintf("      Reason: %s\n", $libInfo['reason']);
                }
                if (isset($libInfo['error'])) {
                    $logEntry .= sprintf("      Error: %s\n", $libInfo['error']);
                }
            }
        }
    }

    $logEntry .= "\n";

    // Append to log file
    file_put_contents($logPath, $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * Send JSON error response
 *
 * @param int $code HTTP status code
 * @param string $message Error message
 * @param mixed $data Additional data
 */
function sendError($code, $message, $data = null) {
    http_response_code($code);
    $response = [
        'success' => false,
        'error' => $message,
        'timestamp' => date('c')
    ];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response, JSON_PRETTY_PRINT);
    if (!defined('TEST_MODE')) {
        exit;
    }
}

/**
 * Send JSON success response
 *
 * @param mixed $data Response data
 * @param string $message Success message
 */
function sendSuccess($data = null, $message = 'OK') {
    http_response_code(200);
    $response = [
        'success' => true,
        'message' => $message,
        'timestamp' => date('c')
    ];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response, JSON_PRETTY_PRINT);
    if (!defined('TEST_MODE')) {
        exit;
    }
}

// Only allow POST requests for actual conversion (unless in test mode)
if (!defined('TEST_MODE') && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError(405, 'Method Not Allowed', [
        'allowed_methods' => ['POST'],
        'endpoint' => '/convert.php',
        'documentation' => 'This endpoint accepts POST requests with HTML blocks and CSS URL for conversion to PNG'
    ]);
}

/**
 * Parse input data from POST request
 * Supports both multipart/form-data and JSON formats
 *
 * @return array Parsed input data
 */
function parseInput() {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    // Handle JSON input
    if (strpos($contentType, 'application/json') !== false) {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            sendError(400, 'Invalid JSON', [
                'json_error' => json_last_error_msg()
            ]);
        }

        return $data ?? [];
    }

    // Handle multipart/form-data or form-urlencoded
    if (strpos($contentType, 'multipart/form-data') !== false ||
        strpos($contentType, 'application/x-www-form-urlencoded') !== false) {
        return $_POST;
    }

    // Default to $_POST if no content type specified
    return $_POST;
}

/**
 * Validate HTML blocks input
 *
 * @param mixed $htmlBlocks The html_blocks parameter to validate
 * @return array Validated array of HTML blocks
 */
function validateHtmlBlocks($htmlBlocks) {
    // Check if parameter exists
    if ($htmlBlocks === null || $htmlBlocks === '') {
        sendError(400, 'Missing required parameter: html_blocks', [
            'required_parameters' => ['html_blocks'],
            'optional_parameters' => ['css_url']
        ]);
    }

    // Ensure it's an array
    if (!is_array($htmlBlocks)) {
        // Try to convert single string to array
        if (is_string($htmlBlocks)) {
            $htmlBlocks = [$htmlBlocks];
        } else {
            sendError(400, 'html_blocks must be an array', [
                'received_type' => gettype($htmlBlocks),
                'expected_type' => 'array'
            ]);
        }
    }

    // Check if array is empty
    if (empty($htmlBlocks)) {
        sendError(400, 'html_blocks array cannot be empty', [
            'provided_count' => 0,
            'minimum_count' => 1
        ]);
    }

    // Validate each block is a non-empty string and sanitize
    foreach ($htmlBlocks as $index => $block) {
        if (!is_string($block)) {
            sendError(400, "html_blocks[$index] must be a string", [
                'invalid_index' => $index,
                'received_type' => gettype($block)
            ]);
        }

        if (trim($block) === '') {
            sendError(400, "html_blocks[$index] cannot be empty", [
                'invalid_index' => $index
            ]);
        }

        // Sanitize HTML to prevent XSS attacks
        $htmlBlocks[$index] = sanitizeHtmlInput($block);

        // Check if sanitization removed all content
        if (trim($htmlBlocks[$index]) === '') {
            sendError(400, "html_blocks[$index] contained only dangerous/invalid HTML", [
                'invalid_index' => $index,
                'reason' => 'Sanitization removed all content'
            ]);
        }
    }

    return $htmlBlocks;
}

/**
 * Validate CSS URL parameter (optional)
 *
 * @param string $cssUrl The CSS URL to validate
 * @return string|null Validated CSS URL or null if not provided
 */
function validateCssUrl($cssUrl) {
    if ($cssUrl === null || $cssUrl === '') {
        return null;
    }

    if (!is_string($cssUrl)) {
        sendError(400, 'css_url must be a string', [
            'received_type' => gettype($cssUrl)
        ]);
    }

    // Basic URL validation
    if (!filter_var($cssUrl, FILTER_VALIDATE_URL)) {
        sendError(400, 'css_url must be a valid URL', [
            'provided_url' => $cssUrl
        ]);
    }

    // Ensure it's http or https
    $scheme = parse_url($cssUrl, PHP_URL_SCHEME);
    if (!in_array($scheme, ['http', 'https'])) {
        sendError(400, 'css_url must use http or https scheme', [
            'provided_scheme' => $scheme
        ]);
    }

    return $cssUrl;
}

/**
 * Sanitize HTML content to prevent XSS attacks
 *
 * This function removes potentially dangerous HTML elements and attributes
 * that could be used for XSS attacks while preserving safe HTML structure
 * needed for rendering.
 *
 * Security measures:
 * - Removes <script> tags and their content
 * - Removes event handler attributes (onclick, onload, etc.)
 * - Removes javascript: URLs
 * - Removes iframe, object, embed, form, input tags
 * - Removes data attributes that could contain malicious code
 * - Removes style attributes with javascript: or expression()
 *
 * @param string $html The HTML content to sanitize
 * @return string Sanitized HTML content
 */
function sanitizeHtmlInput($html) {
    // Remove script tags and their content
    $html = preg_replace('#<script\b[^>]*>(.*?)</script>#is', '', $html);

    // Remove iframe tags (often used for clickjacking)
    $html = preg_replace('#<iframe\b[^>]*>.*?</iframe>#is', '', $html);

    // Remove object tags
    $html = preg_replace('#<object\b[^>]*>.*?</object>#is', '', $html);

    // Remove embed tags
    $html = preg_replace('#<embed\b[^>]*>#i', '', $html);

    // Remove form and input tags
    $html = preg_replace('#<form\b[^>]*>.*?</form>#is', '', $html);
    $html = preg_replace('#<input\b[^>]*>#i', '', $html);
    $html = preg_replace('#<button\b[^>]*>.*?</button>#is', '', $html);

    // Remove dangerous event handler attributes from all tags
    // This covers: onclick, onload, onerror, onmouseover, onmouseout, etc.
    $dangerousEvents = [
        'onabort', 'onactivate', 'onafterprint', 'onafterupdate', 'onbeforeactivate',
        'onbeforecopy', 'onbeforecut', 'onbeforedeactivate', 'onbeforeeditfocus',
        'onbeforepaste', 'onbeforeprint', 'onbeforeunload', 'onbeforeupdate',
        'onblur', 'onbounce', 'oncellchange', 'onchange', 'onclick', 'oncontextmenu',
        'oncontrolselect', 'oncopy', 'oncut', 'ondataavailable', 'ondatasetchanged',
        'ondatasetcomplete', 'ondblclick', 'ondeactivate', 'ondrag', 'ondragend',
        'ondragenter', 'ondragleave', 'ondragover', 'ondragstart', 'ondrop',
        'onerror', 'onerrorupdate', 'onfilterchange', 'onfinish', 'onfocus',
        'onfocusin', 'onfocusout', 'onhelp', 'onkeydown', 'onkeypress', 'onkeyup',
        'onlayoutcomplete', 'onload', 'onlosecapture', 'onmousedown', 'onmouseenter',
        'onmouseleave', 'onmousemove', 'onmouseout', 'onmouseover', 'onmouseup',
        'onmousewheel', 'onmove', 'onmoveend', 'onmovestart', 'onpaste',
        'onpropertychange', 'onreadystatechange', 'onreset', 'onresize',
        'onresizeend', 'onresizestart', 'onrowenter', 'onrowexit', 'onrowsdelete',
        'onrowsinserted', 'onscroll', 'onselect', 'onselectionchange', 'onselectstart',
        'onstart', 'onstop', 'onsubmit', 'onunload'
    ];

    foreach ($dangerousEvents as $event) {
        $html = preg_replace('#\s' . $event . '\s*=\s*(["\']).*?\1#is', '', $html);
        $html = preg_replace('#\s' . $event . '\s*=\s*[^\s>]*#is', '', $html);
    }

    // Remove javascript: and vbscript: protocols from href and src attributes
    $html = preg_replace('#\s(href|src|lowsrc|dynsrc|background)\s*=\s*(["\'])?\s*javascript:[^"\'>]*\2?#is', '', $html);
    $html = preg_replace('#\s(href|src|lowsrc|dynsrc|background)\s*=\s*(["\'])?\s*vbscript:[^"\'>]*\2?#is', '', $html);
    $html = preg_replace('#\s(href|src|lowsrc|dynsrc|background)\s*=\s*(["\'])?\s*data:[^"\'>]*\2?#is', '', $html);

    // Remove style attributes containing javascript: or expression()
    $html = preg_replace('#\sstyle\s*=\s*(["\'])(.*?(?:javascript:|expression\().*?)\1#is', '', $html);

    // Remove data-* attributes that could contain malicious code (be conservative)
    $html = preg_replace('#\sdata-[a-z-]+=\s*(["\']).*?\1#is', '', $html);

    // Remove any remaining potential HTML comments with malicious content
    $html = preg_replace('#<!--.*?-->#s', '', $html);

    return trim($html);
}

/**
 * Get CSS cache directory path
 *
 * @return string Cache directory path
 */
function getCssCacheDir() {
    $cacheDir = __DIR__ . '/assets/media/rapidhtml2png/css_cache';
    if (!is_dir($cacheDir)) {
        if (!mkdir($cacheDir, 0755, true)) {
            sendError(500, 'Failed to create CSS cache directory', [
                'cache_dir' => $cacheDir
            ]);
        }
    }
    return $cacheDir;
}

/**
 * Get cached CSS file path for a URL
 *
 * @param string $cssUrl The CSS URL
 * @return string Path to cached CSS file
 */
function getCssCachePath($cssUrl) {
    $cacheDir = getCssCacheDir();
    $cacheKey = md5($cssUrl);
    return $cacheDir . '/' . $cacheKey . '.css';
}

/**
 * Get CSS metadata file path for a URL
 *
 * @param string $cssUrl The CSS URL
 * @return string Path to metadata file
 */
function getCssMetadataPath($cssUrl) {
    $cacheDir = getCssCacheDir();
    $cacheKey = md5($cssUrl);
    return $cacheDir . '/' . $cacheKey . '.meta.json';
}

/**
 * Save CSS metadata
 *
 * @param string $cssUrl The CSS URL
 * @param string $etag Optional ETag from HTTP response
 * @param int $lastModified Optional Last-Modified timestamp from HTTP response
 * @return void
 */
function saveCssMetadata($cssUrl, $etag = null, $lastModified = null) {
    $metadataPath = getCssMetadataPath($cssUrl);
    $metadata = [
        'url' => $cssUrl,
        'cached_at' => time(),
        'etag' => $etag,
        'last_modified' => $lastModified
    ];
    file_put_contents($metadataPath, json_encode($metadata, JSON_PRETTY_PRINT));
}

/**
 * Load CSS metadata
 *
 * @param string $cssUrl The CSS URL
 * @return array|null Metadata or null if not found
 */
function loadCssMetadata($cssUrl) {
    $metadataPath = getCssMetadataPath($cssUrl);
    if (!file_exists($metadataPath)) {
        return null;
    }
    $content = file_get_contents($metadataPath);
    return json_decode($content, true);
}

/**
 * Check if cached CSS is still valid using filemtime()
 *
 * @param string $cssUrl The CSS URL
 * @return bool True if cache is valid and should be used
 */
function isCssCacheValid($cssUrl) {
    $cachePath = getCssCachePath($cssUrl);

    // Check if cached file exists
    if (!file_exists($cachePath)) {
        return false;
    }

    // Get file modification time of cached file
    $cacheFilemtime = filemtime($cachePath);

    // Load metadata to check when we last fetched from remote
    $metadata = loadCssMetadata($cssUrl);
    if ($metadata === null) {
        // No metadata exists, cache is invalid
        return false;
    }

    // For remote URLs, we can't check remote filemtime directly
    // We use a cache TTL (time-to-live) approach
    // Cache is valid for 1 hour (3600 seconds)
    $cacheAge = time() - $cacheFilemtime;
    $cacheTTL = 3600; // 1 hour

    if ($cacheAge > $cacheTTL) {
        // Cache is too old, needs refresh
        return false;
    }

    // Cache is still valid
    return true;
}

/**
 * Detect available rendering libraries
 *
 * This function checks which HTML-to-image conversion libraries are available.
 * It returns an array with detection results for each library.
 *
 * @return array Detection results with 'available' and 'detected_libraries' keys
 */
function detectAvailableLibraries() {
    $detected = [];

    // Check wkhtmltoimage
    $wkhtmltoimageAvailable = false;
    $wkhtmltoimagePath = null;

    // Use exec() to test if wkhtmltoimage binary exists and is executable
    if (function_exists('exec')) {
        // Try to find wkhtmltoimage in common locations
        $possiblePaths = [
            'wkhtmltoimage',
            '/usr/bin/wkhtmltoimage',
            '/usr/local/bin/wkhtmltoimage',
            '/opt/homebrew/bin/wkhtmltoimage',
            '/usr/bin/wkhtmltoimage.sh'
        ];

        foreach ($possiblePaths as $path) {
            try {
                @exec('which ' . escapeshellarg($path) . ' 2>&1', $output, $returnCode);
                if ($returnCode === 0 && !empty($output[0])) {
                    // Found it, now test if it actually works
                    $testPath = $output[0];
                    @exec(escapeshellcmd($testPath) . ' --version 2>&1', $versionOutput, $versionReturnCode);

                    if ($versionReturnCode === 0) {
                        $wkhtmltoimageAvailable = true;
                        $wkhtmltoimagePath = $testPath;
                        $detected['wkhtmltoimage'] = [
                            'available' => true,
                            'path' => $testPath,
                            'version' => $versionOutput[0] ?? 'unknown'
                        ];
                        break;
                    }
                }
            } catch (Exception $e) {
                // Continue to next path
            }
        }

        // If not found in paths, mark as unavailable
        if (!$wkhtmltoimageAvailable) {
            $detected['wkhtmltoimage'] = [
                'available' => false,
                'reason' => 'Binary not found or not executable',
                'note' => 'Install wkhtmltoimage to enable this rendering engine'
            ];
        }
    } else {
        $detected['wkhtmltoimage'] = [
            'available' => false,
            'reason' => 'exec() function is disabled'
        ];
    }

    // Check ImageMagick
    $imagemagickAvailable = false;
    if (extension_loaded('imagick')) {
        try {
            $imagick = new Imagick();
            if (defined('Imagick::IMAGICK_EXTVER')) {
                $imagemagickAvailable = true;
                $detected['imagemagick'] = [
                    'available' => true,
                    'version' => Imagick::IMAGICK_EXTVER,
                    'extension_loaded' => true
                ];
            }
        } catch (Exception $e) {
            $detected['imagemagick'] = [
                'available' => false,
                'reason' => 'Imagick extension loaded but cannot instantiate',
                'error' => $e->getMessage()
            ];
        }
    } else {
        $detected['imagemagick'] = [
            'available' => false,
            'reason' => 'Imagick extension not loaded'
        ];
    }

    // Check GD library (always available in PHP)
    $gdAvailable = false;
    $gdInfo = [];
    if (extension_loaded('gd')) {
        if (function_exists('gd_info')) {
            $gdInfo = gd_info();
            $gdAvailable = true;
        }
    }

    $detected['gd'] = [
        'available' => $gdAvailable,
        'info' => $gdInfo,
        'note' => 'GD library is the baseline fallback renderer'
    ];

    // Determine best available library
    $priority = ['wkhtmltoimage', 'imagemagick', 'gd'];
    $bestLibrary = null;
    foreach ($priority as $lib) {
        if (isset($detected[$lib]) && $detected[$lib]['available']) {
            $bestLibrary = $lib;
            break;
        }
    }

    return [
        'detected_libraries' => $detected,
        'best_library' => $bestLibrary,
        'available' => $bestLibrary !== null
    ];
}

/**
 * Generate MD5 hash from HTML and CSS content
 *
 * This function creates a unique hash based on the combined content
 * of HTML blocks and CSS. The hash is used for cache file naming.
 *
 * @param array $htmlBlocks Array of HTML content blocks
 * @param string|null $cssContent Optional CSS content string
 * @return string 32-character hexadecimal MD5 hash
 */
function generateContentHash($htmlBlocks, $cssContent = null) {
    // Combine all HTML blocks into a single string
    $combinedContent = implode('', $htmlBlocks);

    // Append CSS content if provided
    if ($cssContent !== null && $cssContent !== '') {
        $combinedContent .= $cssContent;
    }

    // Generate MD5 hash
    $hash = md5($combinedContent);

    // Verify the hash is valid (32 character hexadecimal string)
    if (!preg_match('/^[a-f0-9]{32}$/', $hash)) {
        sendError(500, 'Failed to generate valid MD5 hash', [
            'generated_hash' => $hash,
            'hash_length' => strlen($hash)
        ]);
    }

    return $hash;
}

/**
 * Get output directory path for PNG files
 *
 * @return string Path to output directory
 */
function getOutputDirectory() {
    $outputDir = __DIR__ . '/assets/media/rapidhtml2png';
    if (!is_dir($outputDir)) {
        if (!mkdir($outputDir, 0755, true)) {
            sendError(500, 'Failed to create output directory', [
                'output_dir' => $outputDir
            ]);
        }
    }
    return $outputDir;
}

/**
 * Render HTML to PNG using wkhtmltoimage
 *
 * This function uses the wkhtmltoimage command-line tool to render HTML content
 * to a PNG image with transparent background.
 *
 * @param array $htmlBlocks Array of HTML content blocks
 * @param string|null $cssContent Optional CSS content to apply
 * @param string $outputPath Path where PNG file should be saved
 * @return array Result with success status and metadata
 */
function renderWithWkHtmlToImage($htmlBlocks, $cssContent, $outputPath) {
    // Detect wkhtmltoimage availability
    $detection = detectAvailableLibraries();
    $wkAvailable = $detection['detected_libraries']['wkhtmltoimage']['available'] ?? false;

    if (!$wkAvailable) {
        return [
            'success' => false,
            'error' => 'wkhtmltoimage is not available',
            'reason' => $detection['detected_libraries']['wkhtmltoimage']['reason'] ?? 'Unknown reason'
        ];
    }

    // Get wkhtmltoimage binary path
    $wkPath = $detection['detected_libraries']['wkhtmltoimage']['path'] ?? 'wkhtmltoimage';

    // Combine HTML blocks
    $html = implode('', $htmlBlocks);

    // Create a complete HTML document with CSS
    $fullHtml = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            margin: 0;
            padding: 0;
            background: transparent;
        }
    </style>';

    // Add CSS content if provided
    if ($cssContent) {
        $fullHtml .= '<style>' . $cssContent . '</style>';
    }

    $fullHtml .= '</head>
<body>' . $html . '</body>
</html>';

    // Create temporary HTML file
    $tempHtmlFile = tempnam(sys_get_temp_dir(), 'wkhtml_');
    $tempHtmlFileWithExt = $tempHtmlFile . '.html';
    rename($tempHtmlFile, $tempHtmlFileWithExt);

    file_put_contents($tempHtmlFileWithExt, $fullHtml);

    // Build wkhtmltoimage command
    $command = escapeshellcmd($wkPath);
    $command .= ' --format png';
    $command .= ' --transparent';
    $command .= ' --width 800';  // Default width
    $command .= ' ' . escapeshellarg($tempHtmlFileWithExt);
    $command .= ' ' . escapeshellarg($outputPath);

    // Execute command
    $output = [];
    $returnVar = 0;
    @exec($command . ' 2>&1', $output, $returnVar);

    // Clean up temp file
    @unlink($tempHtmlFileWithExt);

    // Check if rendering succeeded
    if ($returnVar !== 0) {
        return [
            'success' => false,
            'error' => 'wkhtmltoimage execution failed',
            'return_code' => $returnVar,
            'output' => implode("\n", $output),
            'command' => $command
        ];
    }

    // Verify output file was created
    if (!file_exists($outputPath)) {
        return [
            'success' => false,
            'error' => 'Output file was not created',
            'output_path' => $outputPath
        ];
    }

    // Get file info
    $imageInfo = getimagesize($outputPath);
    if ($imageInfo === false) {
        return [
            'success' => false,
            'error' => 'Generated file is not a valid image',
            'output_path' => $outputPath
        ];
    }

    return [
        'success' => true,
        'engine' => 'wkhtmltoimage',
        'output_path' => $outputPath,
        'file_size' => filesize($outputPath),
        'width' => $imageInfo[0],
        'height' => $imageInfo[1],
        'mime_type' => $imageInfo['mime'],
        'command_used' => $command
    ];
}

/**
 * Render HTML to PNG using ImageMagick (Imagick extension)
 *
 * This function uses the ImageMagick library through PHP's Imagick extension
 * to render HTML content to a PNG image with transparent background.
 *
 * @param array $htmlBlocks Array of HTML content blocks
 * @param string|null $cssContent Optional CSS content to apply
 * @param string $outputPath Path where PNG file should be saved
 * @return array Result with success status and metadata
 */
function renderWithImageMagick($htmlBlocks, $cssContent, $outputPath) {
    // Detect ImageMagick availability
    $detection = detectAvailableLibraries();
    $imAvailable = $detection['detected_libraries']['imagemagick']['available'] ?? false;

    if (!$imAvailable) {
        return [
            'success' => false,
            'error' => 'ImageMagick is not available',
            'reason' => $detection['detected_libraries']['imagemagick']['reason'] ?? 'Unknown reason'
        ];
    }

    try {
        // Combine HTML blocks
        $html = implode('', $htmlBlocks);

        // Create a complete HTML document with CSS
        $fullHtml = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            margin: 0;
            padding: 10px;
            background: transparent;
            font-family: Arial, sans-serif;
        }
    </style>';

        // Add CSS content if provided
        if ($cssContent) {
            $fullHtml .= '<style>' . $cssContent . '</style>';
        }

        $fullHtml .= '</head>
<body>' . $html . '</body>
</html>';

        // Create temporary HTML file
        $tempHtmlFile = tempnam(sys_get_temp_dir(), 'imagick_');
        $tempHtmlFileWithExt = $tempHtmlFile . '.html';
        rename($tempHtmlFile, $tempHtmlFileWithExt);

        file_put_contents($tempHtmlFileWithExt, $fullHtml);

        // Create new Imagick object
        $imagick = new Imagick();

        // Set up Imagick for rendering
        $imagick->setResolution(96, 96); // Standard web resolution

        // Read the HTML file and convert to image
        // Note: Imagick doesn't natively render HTML, so we use a workaround
        // We'll create an image from the HTML content using annotation

        // Extract text content from HTML for rendering
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Parse basic CSS for styling
        $cssStyles = parseBasicCss($cssContent);

        // Extract styles
        $fontSize = $cssStyles['font_size'] ?? 14;
        $fontColor = $cssStyles['color'] ?? '#000000';

        // Create a new image with transparent background
        $imagick->newImage(800, 100, new ImagickPixel('transparent'));

        // Create a draw object for text annotation
        $draw = new ImagickDraw();
        $draw->setFillColor(new ImagickPixel($fontColor));
        // Try to set a font, but don't fail if it's not available
        try {
            $draw->setFont('DejaVu-Sans');
        } catch (Exception $e) {
            // Font not available, use default
        }
        $draw->setFontSize($fontSize);
        $draw->setGravity(Imagick::GRAVITY_NORTHWEST);

        // Add text to image (with word wrapping)
        $text = wordwrap($text, 80, "\n", true);
        $imagick->annotateImage($draw, 10, 10, 0, $text);

        // Trim image to content size
        $imagick->trimImage(0);

        // Add some padding
        $imagick->borderImage('transparent', 10, 10);

        // Set format to PNG
        $imagick->setImageFormat('png');

        // Enable transparency
        $imagick->setImageAlphaChannel(Imagick::ALPHACHANNEL_ACTIVATE);
        $imagick->setBackgroundColor(new ImagickPixel('transparent'));

        // Set PNG compression level for web-quality output
        // PNG compression: 0 (none) to 9 (maximum)
        // Level 6 provides good balance between file size and quality
        $imagick->setImageCompression(Imagick::COMPRESSION_ZIP);
        $imagick->setImageCompressionQuality(60); // 60 = PNG level 6 (0-99 scale)
        $imagick->setOption('png:compression-level', '6');
        $imagick->setOption('png:compression-strategy', 'filtered');

        // Write the image to file
        $imagick->writeImage($outputPath);

        // Clean up
        $imagick->clear();
        $imagick->destroy();
        @unlink($tempHtmlFileWithExt);

        // Verify output file was created
        if (!file_exists($outputPath)) {
            return [
                'success' => false,
                'error' => 'Output file was not created',
                'output_path' => $outputPath
            ];
        }

        // Get file info
        $imageInfo = getimagesize($outputPath);
        if ($imageInfo === false) {
            return [
                'success' => false,
                'error' => 'Generated file is not a valid image',
                'output_path' => $outputPath
            ];
        }

        return [
            'success' => true,
            'engine' => 'imagemagick',
            'output_path' => $outputPath,
            'file_size' => filesize($outputPath),
            'width' => $imageInfo[0],
            'height' => $imageInfo[1],
            'mime_type' => $imageInfo['mime']
        ];

    } catch (ImagickException $e) {
        return [
            'success' => false,
            'error' => 'ImageMagick rendering failed',
            'exception' => get_class($e),
            'message' => $e->getMessage()
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Unexpected error during ImageMagick rendering',
            'exception' => get_class($e),
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Render HTML to PNG using GD library (baseline fallback)
 *
 * This function uses PHP's GD library to create a basic PNG image from HTML.
 * It provides a simplified rendering that handles text elements with basic formatting.
 *
 * @param array $htmlBlocks Array of HTML content blocks
 * @param string|null $cssContent Optional CSS content (basic support only)
 * @param string $outputPath Path where PNG file should be saved
 * @return array Result with success status and metadata
 */
function renderWithGD($htmlBlocks, $cssContent, $outputPath) {
    // Detect GD availability
    $detection = detectAvailableLibraries();
    $gdAvailable = $detection['detected_libraries']['gd']['available'] ?? false;

    if (!$gdAvailable) {
        return [
            'success' => false,
            'error' => 'GD library is not available',
            'reason' => 'GD extension not loaded or gd_info() failed'
        ];
    }

    // Combine all HTML blocks
    $html = implode('', $htmlBlocks);

    // Parse HTML to extract text content
    // GD is limited, so we'll do basic text extraction
    $text = extractTextFromHtml($html);

    // Extract basic CSS properties for styling
    $cssStyles = parseBasicCss($cssContent);

    // Determine font size from CSS or use default
    $fontSize = $cssStyles['font_size'] ?? 16;
    $fontColor = $cssStyles['color'] ?? '#000000';
    $backgroundColor = $cssStyles['background'] ?? null;

    // Load font (use built-in font for simplicity)
    $font = 5; // Built-in font (largest available)
    $fontWidth = imagefontwidth($font);
    $fontHeight = imagefontheight($font);

    // Calculate text dimensions
    $lines = explode("\n", $text);
    $maxWidth = 0;
    foreach ($lines as $line) {
        $lineWidth = strlen($line) * $fontWidth;
        if ($lineWidth > $maxWidth) {
            $maxWidth = $lineWidth;
        }
    }
    $totalHeight = count($lines) * $fontHeight;

    // Add padding
    $padding = 10;
    $imageWidth = $maxWidth + ($padding * 2);
    $imageHeight = $totalHeight + ($padding * 2);

    // Create image
    $image = imagecreatetruecolor($imageWidth, $imageHeight);

    // Allocate colors
    if ($backgroundColor && $backgroundColor !== 'transparent') {
        $bgRgb = hexColorToRgb($backgroundColor);
        $bgColor = imagecolorallocate($image, $bgRgb['r'], $bgRgb['g'], $bgRgb['b']);
        imagefill($image, 0, 0, $bgColor);
    } else {
        // Transparent background
        imagealphablending($image, true);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 255, 255, 255, 127);
        imagefill($image, 0, 0, $transparent);
    }

    // Allocate text color
    $textRgb = hexColorToRgb($fontColor);
    $textColor = imagecolorallocate($image, $textRgb['r'], $textRgb['g'], $textRgb['b']);

    // Draw text line by line
    $y = $padding;
    foreach ($lines as $line) {
        imagestring($image, $font, $padding, $y, $line, $textColor);
        $y += $fontHeight;
    }

    // Save PNG with web-quality compression
    // Compression level: 0 (none) to 9 (maximum)
    // Level 6 provides good balance between file size and quality for web use
    imagepng($image, $outputPath, 6);
    imagedestroy($image);

    // Verify output file was created
    if (!file_exists($outputPath)) {
        return [
            'success' => false,
            'error' => 'Output file was not created',
            'output_path' => $outputPath
        ];
    }

    // Get file info
    $imageInfo = getimagesize($outputPath);
    if ($imageInfo === false) {
        return [
            'success' => false,
            'error' => 'Generated file is not a valid image',
            'output_path' => $outputPath
        ];
    }

    return [
        'success' => true,
        'engine' => 'gd',
        'output_path' => $outputPath,
        'file_size' => filesize($outputPath),
        'width' => $imageInfo[0],
        'height' => $imageInfo[1],
        'mime_type' => $imageInfo['mime'],
        'text_lines' => count($lines),
        'text_preview' => substr($text, 0, 50) . (strlen($text) > 50 ? '...' : '')
    ];
}

/**
 * Extract plain text from HTML string
 *
 * This is a simple text extraction function for GD rendering.
 * It strips HTML tags and converts entities to text.
 *
 * @param string $html HTML content
 * @return string Extracted plain text
 */
function extractTextFromHtml($html) {
    // Decode HTML entities
    $text = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    // Remove HTML tags
    $text = strip_tags($text);

    // Convert common HTML whitespace to newlines
    $text = str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $text);
    $text = strip_tags($text); // Strip tags again after replacing breaks

    // Normalize whitespace
    $text = preg_replace('/\s+/', ' ', $text);
    $text = str_replace([' , ', ' . ', ' ! ', ' ? '], [', ', '. ', '! ', '? '], $text);

    // Trim and clean up
    $text = trim($text);
    $text = preg_replace('/\n\s*\n/', "\n", $text); // Remove empty lines

    return $text;
}

/**
 * Parse basic CSS properties from CSS content
 *
 * Extracts simple CSS properties for GD rendering.
 * This is a very basic parser with limited CSS support.
 *
 * @param string|null $cssContent CSS content
 * @return array Associative array of CSS properties
 */
function parseBasicCss($cssContent) {
    $styles = [
        'font_size' => 16,
        'color' => '#000000',
        'background' => 'transparent'
    ];

    if (empty($cssContent)) {
        return $styles;
    }

    // Try to extract font-size
    if (preg_match('/font-size\s*:\s*(\d+)\s*(px|pt|em)?/i', $cssContent, $matches)) {
        $size = intval($matches[1]);
        // Convert to approximate pixel size
        $unit = strtolower($matches[2] ?? 'px');
        switch ($unit) {
            case 'pt':
                $styles['font_size'] = intval($size * 1.33);
                break;
            case 'em':
                $styles['font_size'] = intval($size * 16);
                break;
            default:
                $styles['font_size'] = $size;
        }
    }

    // Try to extract color
    if (preg_match('/color\s*:\s*(#[0-9a-fA-F]{3,6}|[a-zA-Z]+)/i', $cssContent, $matches)) {
        $styles['color'] = $matches[1];
    }

    // Try to extract background color
    if (preg_match('/background(?:-color)?\s*:\s*(#[0-9a-fA-F]{3,6}|[a-zA-Z]+|transparent)/i', $cssContent, $matches)) {
        $styles['background'] = $matches[1];
    }

    return $styles;
}

/**
 * Convert hex color to RGB array
 *
 * @param string $hex Hex color code (with or without #)
 * @return array Associative array with r, g, b values (0-255)
 */
function hexColorToRgb($hex) {
    // Remove # if present
    $hex = ltrim($hex, '#');

    // Expand shorthand hex (3 digits to 6)
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }

    // Parse RGB values
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));

    return ['r' => $r, 'g' => $g, 'b' => $b];
}

/**
 * Convert HTML blocks to PNG image
 *
 * This is the main rendering function that automatically selects the best
 * available rendering library and converts HTML to PNG.
 *
 * @param array $htmlBlocks Array of HTML content blocks
 * @param string|null $cssContent Optional CSS content
 * @param string $contentHash Hash for cache file naming
 * @return array Result with success status and file path
 */
function convertHtmlToPng($htmlBlocks, $cssContent, $contentHash) {
    // Get output directory
    $outputDir = getOutputDirectory();
    $outputPath = $outputDir . '/' . $contentHash . '.png';

    // Check if file already exists (cache hit)
    if (file_exists($outputPath)) {
        return [
            'success' => true,
            'cached' => true,
            'output_path' => $outputPath,
            'file_size' => filesize($outputPath)
        ];
    }

    // Detect available libraries
    $detection = detectAvailableLibraries();
    $bestLibrary = $detection['best_library'] ?? null;

    if (!$bestLibrary) {
        sendError(500, 'No rendering libraries available', [
            'detected_libraries' => $detection
        ]);
    }

    // Render using the best available library
    $result = null;
    switch ($bestLibrary) {
        case 'wkhtmltoimage':
            $result = renderWithWkHtmlToImage($htmlBlocks, $cssContent, $outputPath);
            break;

        case 'imagemagick':
            $result = renderWithImageMagick($htmlBlocks, $cssContent, $outputPath);
            break;

        case 'gd':
            $result = renderWithGD($htmlBlocks, $cssContent, $outputPath);
            break;

        default:
            sendError(500, 'Unknown library selected', [
                'library' => $bestLibrary
            ]);
    }

    // Check if rendering succeeded
    if (!$result['success']) {
        sendError(500, 'Rendering failed', [
            'library' => $bestLibrary,
            'error' => $result['error'] ?? 'Unknown error',
            'details' => $result
        ]);
    }

    // Add cache flag for new renders
    $result['cached'] = false;

    return $result;
}

/**
 * Load CSS content from URL via cURL with caching
 *
 * @param string $cssUrl The CSS URL to load
 * @return string CSS content
 * @throws Exception If cURL request fails
 */
function loadCssContent($cssUrl) {
    // Check if cURL is available
    if (!extension_loaded('curl')) {
        sendError(500, 'cURL extension is not available', [
            'required_extension' => 'curl',
            'css_url' => $cssUrl
        ]);
    }

    // Check if we have a valid cached version
    if (isCssCacheValid($cssUrl)) {
        $cachePath = getCssCachePath($cssUrl);
        $cssContent = file_get_contents($cachePath);
        $metadata = loadCssMetadata($cssUrl);

        // Return cached content
        return [
            'content' => $cssContent,
            'cached' => true,
            'cache_filemtime' => filemtime($cachePath),
            'cache_age' => time() - filemtime($cachePath),
            'metadata' => $metadata
        ];
    }

    // Need to fetch from remote URL
    // Initialize cURL
    $ch = curl_init($cssUrl);
    if ($ch === false) {
        sendError(500, 'Failed to initialize cURL', [
            'css_url' => $cssUrl
        ]);
    }

    // Set cURL options
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_USERAGENT, 'RapidHTML2PNG/1.0');

    // Request headers to get ETag and Last-Modified
    $headers = [];
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $header) use (&$headers) {
        $len = strlen($header);
        $header = explode(':', $header, 2);
        if (count($header) < 2) {
            return $len;
        }
        $headers[strtolower(trim($header[0]))][] = trim($header[1]);
        return $len;
    });

    // Execute cURL request
    $cssContent = curl_exec($ch);

    // Check for errors
    if ($cssContent === false) {
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        curl_close($ch);
        sendError(500, 'Failed to load CSS file via cURL', [
            'css_url' => $cssUrl,
            'curl_error' => $error,
            'curl_errno' => $errno
        ]);
    }

    // Get HTTP status code
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Check HTTP status code
    if ($httpCode !== 200) {
        sendError(500, 'CSS file returned non-200 status code', [
            'css_url' => $cssUrl,
            'http_code' => $httpCode
        ]);
    }

    // Verify we got some content
    if (empty($cssContent)) {
        sendError(500, 'CSS file is empty or could not be read', [
            'css_url' => $cssUrl,
            'content_length' => strlen($cssContent)
        ]);
    }

    // Extract ETag and Last-Modified from headers
    $etag = $headers['etag'][0] ?? null;
    $lastModified = isset($headers['last-modified'][0]) ? strtotime($headers['last-modified'][0]) : null;

    // Save to cache
    $cachePath = getCssCachePath($cssUrl);
    file_put_contents($cachePath, $cssContent);

    // Save metadata
    saveCssMetadata($cssUrl, $etag, $lastModified);

    // Return fresh content
    return [
        'content' => $cssContent,
        'cached' => false,
        'etag' => $etag,
        'last_modified' => $lastModified,
        'cache_file_path' => $cachePath,
        'cache_filemtime' => filemtime($cachePath)
    ];
}

// Parse input data
$input = parseInput();

// Extract and validate parameters
$htmlBlocks = validateHtmlBlocks($input['html_blocks'] ?? null);
$cssUrl = validateCssUrl($input['css_url'] ?? null);

// Load CSS content if URL is provided
$cssResult = null;
$cssContent = null;
if ($cssUrl !== null) {
    $cssResult = loadCssContent($cssUrl);
    $cssContent = $cssResult['content'];
}

// Detect available rendering libraries
$libraryDetection = detectAvailableLibraries();

// Log the library selection for debugging
$selectedLibrary = $libraryDetection['best_library'] ?? null;
$selectionReason = '';
if ($selectedLibrary) {
    $priorityOrder = ['wkhtmltoimage' => 1, 'imagemagick' => 2, 'gd' => 3];
    $priority = $priorityOrder[$selectedLibrary] ?? 0;
    $selectionReason = sprintf(
        'Selected based on priority (priority %d) - %s is the best available library',
        $priority,
        strtoupper($selectedLibrary)
    );
} else {
    $selectionReason = 'No rendering libraries available - conversion will fail';
}
logLibrarySelection($selectedLibrary, $libraryDetection, $selectionReason);

// Generate content hash from HTML and CSS
$contentHash = generateContentHash($htmlBlocks, $cssContent);

// Return successful parsing response (for now, until conversion is implemented)
$responseData = [
    'status' => 'Parameters validated successfully',
    'html_blocks_count' => count($htmlBlocks),
    'html_blocks_preview' => array_map(function($block) {
        return substr($block, 0, 100) . (strlen($block) > 100 ? '...' : '');
    }, $htmlBlocks),
    'css_url' => $cssUrl,
    'content_hash' => $contentHash,
    'hash_algorithm' => 'md5',
    'hash_length' => strlen($contentHash),
    'library_detection' => $libraryDetection
];

// Include CSS content info if loaded
if ($cssResult !== null) {
    $responseData['css_loaded'] = true;
    $responseData['css_content_length'] = strlen($cssContent);
    $responseData['css_preview'] = substr($cssContent, 0, 200) . (strlen($cssContent) > 200 ? '...' : '');
    $responseData['css_cached'] = $cssResult['cached'];
    $responseData['css_cache_filemtime'] = $cssResult['cache_filemtime'];
    $responseData['css_cache_filemtime_formatted'] = date('Y-m-d H:i:s', $cssResult['cache_filemtime']);

    if ($cssResult['cached']) {
        $responseData['css_cache_age'] = $cssResult['cache_age'];
        $responseData['css_cache_age_formatted'] = gmdate('H:i:s', $cssResult['cache_age']);
    } else {
        $responseData['css_fresh'] = true;
        if (isset($cssResult['etag'])) {
            $responseData['css_etag'] = $cssResult['etag'];
        }
        if (isset($cssResult['last_modified'])) {
            $responseData['css_last_modified'] = date('Y-m-d H:i:s', $cssResult['last_modified']);
        }
        $responseData['css_cache_file_path'] = $cssResult['cache_file_path'];
    }
} else {
    $responseData['css_loaded'] = false;
    $responseData['css_info'] = 'No CSS URL provided';
}

// Convert HTML to PNG
$renderResult = convertHtmlToPng($htmlBlocks, $cssContent, $contentHash);

// Add rendering results to response
$responseData['rendering'] = [
    'engine' => $renderResult['engine'] ?? 'unknown',
    'cached' => $renderResult['cached'] ?? false,
    'output_file' => $renderResult['output_path'] ?? null,
    'file_size' => $renderResult['file_size'] ?? null,
    'width' => $renderResult['width'] ?? null,
    'height' => $renderResult['height'] ?? null,
    'mime_type' => $renderResult['mime_type'] ?? null
];

// Add command used for debugging (wkhtmltoimage only)
if (isset($renderResult['command_used'])) {
    $responseData['rendering']['command_used'] = $renderResult['command_used'];
}

sendSuccess($responseData, 'HTML converted to PNG successfully');
