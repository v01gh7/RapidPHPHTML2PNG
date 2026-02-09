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

// Parse input data
$input = parseInput();

// Extract and validate parameters
$htmlBlocks = validateHtmlBlocks($input['html_blocks'] ?? null);
$cssUrl = validateCssUrl($input['css_url'] ?? null);

// Return successful parsing response (for now, until conversion is implemented)
sendSuccess([
    'status' => 'Parameters validated successfully',
    'html_blocks_count' => count($htmlBlocks),
    'html_blocks_preview' => array_map(function($block) {
        return substr($block, 0, 100) . (strlen($block) > 100 ? '...' : '');
    }, $htmlBlocks),
    'css_url' => $cssUrl,
    'note' => 'Conversion logic will be implemented in subsequent features'
], 'RapidHTML2PNG API - Parameters accepted');
