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
    exit;
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
    exit;
}

// Only allow POST requests for actual conversion
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

    // Validate each block is a non-empty string
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

$responseData['note'] = 'Conversion logic will be implemented in subsequent features';

sendSuccess($responseData, 'RapidHTML2PNG API - Parameters accepted and CSS loaded');
