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
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

// Input size limits (in bytes)
define('MAX_HTML_BLOCK_SIZE', 1048576); // 1MB per HTML block
define('MAX_TOTAL_INPUT_SIZE', 5242880); // 5MB total input size
define('MAX_CSS_SIZE', 1048576); // 1MB for CSS content

// Abuse protection settings
define('REQUEST_MAX_RUNTIME_SECONDS', 300); // 5 minutes
define('REQUEST_LOCK_PATH', __DIR__ . '/logs/convert_runtime.lock');
define('API_KEY_ENV_NAME', 'RAPIDHTML2PNG_API_KEY');

// Enforce UTF-8 processing for all text operations.
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}
if (function_exists('mb_regex_encoding')) {
    mb_regex_encoding('UTF-8');
}

$GLOBALS['request_guard'] = [
    'started_at' => microtime(true),
    'lock_handle' => null
];

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

    // Log the error for debugging
    logError($code, $message, $data);

    echo json_encode($response, JSON_PRETTY_PRINT);
    if (!defined('TEST_MODE')) {
        exit;
    }
}

/**
 * Log error to application error log
 *
 * Creates structured log entries with timestamp, HTTP status code,
 * error message, and sanitized context data. Sensitive information
 * is filtered out before logging.
 *
 * @param int $code HTTP status code
 * @param string $message Error message
 * @param mixed $data Additional data (will be sanitized)
 */
function logError($code, $message, $data = null) {
    $logPath = __DIR__ . '/logs/application_errors.log';
    $logDir = dirname($logPath);

    // Create log directory if it doesn't exist
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    // Sanitize data to remove sensitive information
    $safeData = sanitizeLogData($data);

    // Build log entry
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] HTTP $code - $message\n";

    // Add request context
    if (isset($_SERVER['REQUEST_METHOD'])) {
        $logEntry .= "  Method: {$_SERVER['REQUEST_METHOD']}\n";
    }
    if (isset($_SERVER['REQUEST_URI'])) {
        // Sanitize URI to remove query string with potential sensitive data
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $logEntry .= "  URI: $uri\n";
    }
    if (isset($_SERVER['REMOTE_ADDR'])) {
        // Mask IP address for privacy (keep first 2 octets)
        $ip = $_SERVER['REMOTE_ADDR'];
        $maskedIp = preg_replace('/(\d+\.\d+)\.\d+\.\d+/', '$1.0.0.0', $ip);
        $logEntry .= "  Client IP: $maskedIp\n";
    }

    // Add sanitized context data
    if ($safeData !== null) {
        $logEntry .= "  Context: " . json_encode($safeData, JSON_UNESCAPED_SLASHES) . "\n";
    }

    $logEntry .= "\n";

    // Append to log file
    file_put_contents($logPath, $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * Sanitize data for logging
 *
 * Removes or masks sensitive information before logging.
 * Filters out passwords, API keys, tokens, etc.
 *
 * @param mixed $data Data to sanitize
 * @return mixed Sanitized data
 */
function sanitizeLogData($data) {
    if ($data === null) {
        return null;
    }

    if (!is_array($data)) {
        // For non-array data, just return as string if it's safe
        return (string)$data;
    }

    $sensitiveKeys = [
        'password', 'passwd', 'secret', 'api_key', 'apikey', 'api-key',
        'token', 'authorization', 'auth', 'session', 'cookie',
        'private_key', 'privatekey', 'access_token', 'accesstoken'
    ];

    $sanitized = [];

    foreach ($data as $key => $value) {
        $lowerKey = strtolower(str_replace(['-', '_', ' '], '', $key));

        // Check if this key contains sensitive information
        $isSensitive = false;
        foreach ($sensitiveKeys as $sensitive) {
            if (strpos($lowerKey, $sensitive) !== false) {
                $isSensitive = true;
                break;
            }
        }

        if ($isSensitive) {
            // Mask sensitive values
            $sanitized[$key] = '[REDACTED]';
        } elseif (is_array($value)) {
            // Recursively sanitize nested arrays
            $sanitized[$key] = sanitizeLogData($value);
        } else {
            // Keep non-sensitive values as-is
            $sanitized[$key] = $value;
        }
    }

    return $sanitized;
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

/**
 * Normalize string to UTF-8.
 *
 * @param mixed $text Input value
 * @param string $fieldName Field name for error context
 * @return mixed UTF-8 string or original non-string value
 */
function normalizeToUtf8($text, $fieldName = 'input') {
    if (!is_string($text)) {
        return $text;
    }

    // Remove UTF-8 BOM if present.
    $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
    if ($text === '') {
        return $text;
    }

    if (function_exists('mb_check_encoding') && mb_check_encoding($text, 'UTF-8')) {
        return $text;
    }

    if (!function_exists('mb_convert_encoding') || !function_exists('mb_check_encoding')) {
        sendError(500, 'mbstring extension is required for UTF-8 normalization', [
            'field' => $fieldName,
            'required_extension' => 'mbstring'
        ]);
    }

    $sourceEncodings = ['UTF-8', 'Windows-1251', 'CP1251', 'KOI8-R', 'ISO-8859-1'];
    foreach ($sourceEncodings as $sourceEncoding) {
        $converted = @mb_convert_encoding($text, 'UTF-8', $sourceEncoding);
        if ($converted !== false && mb_check_encoding($converted, 'UTF-8')) {
            return $converted;
        }
    }

    sendError(400, 'Invalid text encoding detected', [
        'field' => $fieldName,
        'expected_encoding' => 'UTF-8'
    ]);
}

/**
 * Recursively normalize request payload to UTF-8.
 *
 * @param mixed $value Input payload
 * @param string $path Current path for error context
 * @return mixed Normalized payload
 */
function normalizePayloadUtf8($value, $path = 'input') {
    if (is_array($value)) {
        $normalized = [];
        foreach ($value as $key => $item) {
            $itemPath = $path . '[' . $key . ']';
            $normalized[$key] = normalizePayloadUtf8($item, $itemPath);
        }
        return $normalized;
    }

    if (is_string($value)) {
        return normalizeToUtf8($value, $path);
    }

    return $value;
}

/**
 * Get raw request body with in-memory caching.
 *
 * @return string
 */
function getRawRequestBody() {
    static $rawBody = null;
    if ($rawBody === null) {
        $rawBody = file_get_contents('php://input');
        if ($rawBody === false) {
            $rawBody = '';
        }
    }
    return $rawBody;
}

/**
 * Emit compact JSON response without error logging helper.
 *
 * @param int $code HTTP status code
 * @param array $payload Response payload
 * @return void
 */
function emitSimpleJson($code, $payload) {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

/**
 * Get configured API key from environment.
 *
 * @return string|null
 */
function getConfiguredApiKey() {
    $apiKey = getenv(API_KEY_ENV_NAME);
    if ($apiKey === false || $apiKey === null) {
        return null;
    }

    $apiKey = trim((string)$apiKey);
    return $apiKey === '' ? null : $apiKey;
}

/**
 * Extract API key from headers or request payload.
 *
 * @return string|null
 */
function extractApiKeyFromRequest() {
    $headerCandidates = [
        'HTTP_X_API_KEY',
        'HTTP_X_APIKEY',
        'REDIRECT_HTTP_X_API_KEY'
    ];

    foreach ($headerCandidates as $headerName) {
        if (!empty($_SERVER[$headerName])) {
            return trim((string)$_SERVER[$headerName]);
        }
    }

    if (isset($_POST['api_key'])) {
        return trim((string)$_POST['api_key']);
    }

    if (isset($_GET['api_key'])) {
        return trim((string)$_GET['api_key']);
    }

    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        $jsonFlags = defined('JSON_INVALID_UTF8_SUBSTITUTE') ? JSON_INVALID_UTF8_SUBSTITUTE : 0;
        $payload = json_decode(getRawRequestBody(), true, 512, $jsonFlags);
        if (is_array($payload) && isset($payload['api_key'])) {
            return trim((string)$payload['api_key']);
        }
    }

    return null;
}

/**
 * Release single-instance lock handle.
 *
 * @return void
 */
function releaseRequestLock() {
    $lockHandle = $GLOBALS['request_guard']['lock_handle'] ?? null;
    if (!is_resource($lockHandle)) {
        return;
    }

    @flock($lockHandle, LOCK_UN);
    @fclose($lockHandle);
    $GLOBALS['request_guard']['lock_handle'] = null;
}

/**
 * Acquire non-blocking single-instance lock.
 *
 * @return array
 */
function acquireRequestLock() {
    $lockDir = dirname(REQUEST_LOCK_PATH);
    if (!is_dir($lockDir)) {
        @mkdir($lockDir, 0755, true);
    }

    $lockHandle = @fopen(REQUEST_LOCK_PATH, 'c+');
    if ($lockHandle === false) {
        return [
            'acquired' => false,
            'reason' => 'lock_open_failed'
        ];
    }

    if (!@flock($lockHandle, LOCK_EX | LOCK_NB)) {
        @fclose($lockHandle);
        $runningMeta = json_decode((string)@file_get_contents(REQUEST_LOCK_PATH), true);
        if (!is_array($runningMeta)) {
            $runningMeta = [];
        }

        return [
            'acquired' => false,
            'reason' => 'already_running',
            'running_meta' => $runningMeta
        ];
    }

    $lockMeta = [
        'started_at' => time(),
        'pid' => function_exists('getmypid') ? getmypid() : null,
        'uri' => $_SERVER['REQUEST_URI'] ?? '/convert.php'
    ];

    ftruncate($lockHandle, 0);
    rewind($lockHandle);
    fwrite($lockHandle, json_encode($lockMeta, JSON_UNESCAPED_SLASHES));
    fflush($lockHandle);

    $GLOBALS['request_guard']['lock_handle'] = $lockHandle;
    register_shutdown_function('releaseRequestLock');

    return [
        'acquired' => true,
        'meta' => $lockMeta
    ];
}

/**
 * Abort request if runtime exceeded configured max duration.
 *
 * @param string $stage Runtime stage marker
 * @return void
 */
function enforceRuntimeLimit($stage = 'runtime') {
    $startedAt = $GLOBALS['request_guard']['started_at'] ?? microtime(true);
    $elapsed = microtime(true) - $startedAt;
    if ($elapsed <= REQUEST_MAX_RUNTIME_SECONDS) {
        return;
    }

    releaseRequestLock();
    emitSimpleJson(408, [
        'success' => false,
        'error' => 'Request timed out',
        'stage' => $stage,
        'max_runtime_seconds' => REQUEST_MAX_RUNTIME_SECONDS,
        'elapsed_seconds' => (int)round($elapsed)
    ]);
    exit;
}

// Only allow POST requests for actual conversion (unless in test mode)
if (!defined('TEST_MODE') && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError(405, 'Method Not Allowed', [
        'allowed_methods' => ['POST'],
        'endpoint' => '/convert.php',
        'documentation' => 'This endpoint accepts POST requests with html_blocks and optional css_url/render_engine for conversion to PNG'
    ]);
}

// Configure script hard timeout for long-running executions.
if (!defined('TEST_MODE') && function_exists('set_time_limit')) {
    @set_time_limit(REQUEST_MAX_RUNTIME_SECONDS);
}

// Require API key without invoking structured error logger.
if (!defined('TEST_MODE')) {
    $configuredApiKey = getConfiguredApiKey();
    if ($configuredApiKey === null) {
        emitSimpleJson(503, [
            'success' => false,
            'error' => 'API key is not configured',
            'config_env' => API_KEY_ENV_NAME
        ]);
        return;
    }

    $providedApiKey = extractApiKeyFromRequest();
    if ($providedApiKey === null || $providedApiKey === '') {
        emitSimpleJson(401, [
            'success' => false,
            'error' => 'API key is required'
        ]);
        return;
    }

    if (!hash_equals($configuredApiKey, $providedApiKey)) {
        emitSimpleJson(403, [
            'success' => false,
            'error' => 'Invalid API key'
        ]);
        return;
    }

    $lockResult = acquireRequestLock();
    if (!$lockResult['acquired']) {
        if (($lockResult['reason'] ?? '') === 'already_running') {
            $runningStartedAt = (int)($lockResult['running_meta']['started_at'] ?? 0);
            $runningFor = $runningStartedAt > 0 ? max(0, time() - $runningStartedAt) : null;
            emitSimpleJson(429, [
                'success' => false,
                'error' => 'Work already in progress, please wait',
                'running_for_seconds' => $runningFor,
                'max_runtime_seconds' => REQUEST_MAX_RUNTIME_SECONDS,
                'running_over_timeout' => $runningFor !== null && $runningFor > REQUEST_MAX_RUNTIME_SECONDS
            ]);
            return;
        }

        emitSimpleJson(503, [
            'success' => false,
            'error' => 'Failed to acquire execution lock'
        ]);
        return;
    }
}

enforceRuntimeLimit('startup');

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
        $json = getRawRequestBody();
        $jsonFlags = defined('JSON_INVALID_UTF8_SUBSTITUTE') ? JSON_INVALID_UTF8_SUBSTITUTE : 0;
        $data = json_decode($json, true, 512, $jsonFlags);

        if (json_last_error() !== JSON_ERROR_NONE) {
            sendError(400, 'Invalid JSON', [
                'json_error' => json_last_error_msg()
            ]);
        }

        return normalizePayloadUtf8($data ?? []);
    }

    // Handle multipart/form-data or form-urlencoded
    if (strpos($contentType, 'multipart/form-data') !== false ||
        strpos($contentType, 'application/x-www-form-urlencoded') !== false) {
        return normalizePayloadUtf8($_POST);
    }

    // Default to $_POST if no content type specified
    return normalizePayloadUtf8($_POST);
}

/**
 * Check total input size from request body
 *
 * @return void Sends error if size exceeds limit
 */
function checkTotalInputSize() {
    $contentLength = $_SERVER['CONTENT_LENGTH'] ?? 0;

    if ($contentLength > MAX_TOTAL_INPUT_SIZE) {
        sendError(413, 'Request body too large', [
            'content_length' => $contentLength,
            'max_allowed' => MAX_TOTAL_INPUT_SIZE,
            'max_allowed_mb' => round(MAX_TOTAL_INPUT_SIZE / 1048576, 2),
            'exceeded_by' => $contentLength - MAX_TOTAL_INPUT_SIZE
        ]);
    }
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
            'optional_parameters' => ['css_url', 'render_engine']
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

        $block = normalizeToUtf8($block, "html_blocks[$index]");

        // Check individual block size
        $blockSize = strlen($block);
        if ($blockSize > MAX_HTML_BLOCK_SIZE) {
            sendError(413, "html_blocks[$index] exceeds maximum size", [
                'invalid_index' => $index,
                'block_size' => $blockSize,
                'max_allowed_size' => MAX_HTML_BLOCK_SIZE,
                'max_allowed_mb' => round(MAX_HTML_BLOCK_SIZE / 1048576, 2),
                'exceeded_by' => $blockSize - MAX_HTML_BLOCK_SIZE
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

    $cssUrl = normalizeToUtf8($cssUrl, 'css_url');

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
 * Validate requested render engine (optional).
 *
 * Supported aliases:
 * - imagick => imagemagick
 * - auto => null (default fallback mode)
 *
 * @param mixed $renderEngine Requested engine value
 * @return string|null Canonical engine name or null for auto mode
 */
function validateRenderEngine($renderEngine) {
    if ($renderEngine === null || $renderEngine === '') {
        return null;
    }

    if (!is_string($renderEngine)) {
        sendError(400, 'render_engine must be a string', [
            'received_type' => gettype($renderEngine),
            'allowed_values' => ['auto', 'wkhtmltoimage', 'gd', 'imagick', 'imagemagick']
        ]);
    }

    $renderEngine = strtolower(trim(normalizeToUtf8($renderEngine, 'render_engine')));

    $aliases = [
        'auto' => null,
        'imagick' => 'imagemagick',
        'imagemagick' => 'imagemagick',
        'wkhtmltoimage' => 'wkhtmltoimage',
        'gd' => 'gd'
    ];

    if (!array_key_exists($renderEngine, $aliases)) {
        sendError(400, 'Invalid render_engine value', [
            'provided_value' => $renderEngine,
            'allowed_values' => ['auto', 'wkhtmltoimage', 'gd', 'imagick', 'imagemagick']
        ]);
    }

    return $aliases[$renderEngine];
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

    return normalizeToUtf8(trim($html), 'html_sanitized');
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
 * Check if cached CSS is still valid using conditional HTTP request
 *
 * This function makes a conditional HTTP request (HEAD or GET with If-None-Match/If-Modified-Since)
 * to check if the cached CSS is still fresh without downloading the entire file.
 *
 * @param string $cssUrl The CSS URL
 * @return array Result with 'valid' bool and 'should_refresh' bool
 */
function checkCssCacheFreshness($cssUrl) {
    $cachePath = getCssCachePath($cssUrl);

    // Check if cached file exists
    if (!file_exists($cachePath)) {
        return ['valid' => false, 'should_refresh' => true];
    }

    // Load metadata to get ETag and Last-Modified
    $metadata = loadCssMetadata($cssUrl);
    if ($metadata === null) {
        // No metadata exists, cache is invalid
        return ['valid' => false, 'should_refresh' => true];
    }

    // Check if cURL is available
    if (!extension_loaded('curl')) {
        // Can't check, use TTL fallback
        $cacheFilemtime = filemtime($cachePath);
        $cacheAge = time() - $cacheFilemtime;
        $cacheTTL = 3600; // 1 hour
        return [
            'valid' => $cacheAge <= $cacheTTL,
            'should_refresh' => $cacheAge > $cacheTTL,
            'method' => 'ttl_fallback'
        ];
    }

    // Make conditional HTTP request to check if CSS has changed
    $ch = curl_init($cssUrl);
    if ($ch === false) {
        // cURL init failed, use TTL fallback
        $cacheFilemtime = filemtime($cachePath);
        $cacheAge = time() - $cacheFilemtime;
        $cacheTTL = 3600;
        return [
            'valid' => $cacheAge <= $cacheTTL,
            'should_refresh' => $cacheAge > $cacheTTL,
            'method' => 'ttl_fallback'
        ];
    }

    // Set cURL options for conditional request
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_USERAGENT, 'RapidHTML2PNG/1.0');
    curl_setopt($ch, CURLOPT_NOBODY, true); // HEAD request

    // Add conditional headers if we have ETag or Last-Modified
    $headers = [];
    if (!empty($metadata['etag'])) {
        $headers[] = 'If-None-Match: ' . $metadata['etag'];
    }
    if (!empty($metadata['last_modified'])) {
        $headers[] = 'If-Modified-Since: ' . gmdate('D, d M Y H:i:s T', $metadata['last_modified']) . ' GMT';
    }
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    // Execute conditional request
    curl_exec($ch);

    // Get HTTP status code
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // HTTP 304 = Not Modified (cache is still valid)
    // HTTP 200 = OK (CSS has changed, we should refresh)
    // HTTP 4xx/5xx = Error (use cache as fallback)
    $cacheValid = ($httpCode === 304);
    $shouldRefresh = ($httpCode === 200);

    return [
        'valid' => $cacheValid,
        'should_refresh' => $shouldRefresh,
        'http_code' => $httpCode,
        'method' => 'conditional_request'
    ];
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
    $priority = ['wkhtmltoimage', 'gd', 'imagemagick'];
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
        $text = extractTextFromHtml($html);
        $text = normalizeToUtf8($text, 'imagemagick_text');

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
        $draw->setTextEncoding('UTF-8');
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
 * Find a Unicode-capable TrueType font for GD rendering.
 *
 * @return string|null Font path or null if not found
 */
function findUnicodeFontPath() {
    $fontCandidates = [
        __DIR__ . '/assets/fonts/DejaVuSans.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
        'C:/Windows/Fonts/arial.ttf'
    ];

    foreach ($fontCandidates as $fontPath) {
        if (is_file($fontPath) && is_readable($fontPath)) {
            return $fontPath;
        }
    }

    return null;
}

/**
 * UTF-8 safe string length helper.
 *
 * @param string $text Text to measure
 * @return int Length
 */
function utf8Length($text) {
    if (function_exists('mb_strlen')) {
        return mb_strlen($text, 'UTF-8');
    }
    return strlen($text);
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

    // Normalize lines for rendering.
    $lines = preg_split('/\R/u', $text);
    $lines = array_values(array_filter(array_map('trim', $lines), function($line) {
        return $line !== '';
    }));
    if (empty($lines)) {
        $lines = [' '];
    }

    $padding = 10;
    $fontPath = findUnicodeFontPath();
    $useTtf = $fontPath !== null && function_exists('imagettfbbox') && function_exists('imagettftext');

    // Defaults for built-in GD font fallback.
    $font = 5;
    $fontWidth = imagefontwidth($font);
    $lineHeight = imagefontheight($font);
    $lineSpacing = 2;
    $maxWidth = 1;

    if ($useTtf) {
        $lineHeight = max(1, (int)ceil($fontSize * 1.4));
        $lineSpacing = max(2, (int)ceil($fontSize * 0.35));
        $fallbackCharWidth = max(7, (int)ceil($fontSize * 0.6));

        foreach ($lines as $line) {
            $bbox = imagettfbbox($fontSize, 0, $fontPath, $line);
            if ($bbox !== false) {
                $lineWidth = max(1, abs($bbox[2] - $bbox[0]));
                $bboxHeight = max(1, abs($bbox[7] - $bbox[1]));
                if ($bboxHeight > $lineHeight) {
                    $lineHeight = $bboxHeight;
                }
            } else {
                $lineWidth = max(1, utf8Length($line) * $fallbackCharWidth);
            }

            if ($lineWidth > $maxWidth) {
                $maxWidth = $lineWidth;
            }
        }
    } else {
        foreach ($lines as $line) {
            $lineWidth = max(1, utf8Length($line) * $fontWidth);
            if ($lineWidth > $maxWidth) {
                $maxWidth = $lineWidth;
            }
        }
    }

    $imageWidth = max(1, $maxWidth + ($padding * 2));
    $imageHeight = max(1, (count($lines) * $lineHeight) + (max(0, count($lines) - 1) * $lineSpacing) + ($padding * 2));

    // Create image
    $image = imagecreatetruecolor($imageWidth, $imageHeight);

    // Keep output transparent regardless of source CSS background declarations.
    imagealphablending($image, false);
    imagesavealpha($image, true);
    $transparent = imagecolorallocatealpha($image, 255, 255, 255, 127);
    imagefill($image, 0, 0, $transparent);
    imagealphablending($image, true);

    // Allocate text color
    $textRgb = hexColorToRgb($fontColor);
    $textColor = imagecolorallocate($image, $textRgb['r'], $textRgb['g'], $textRgb['b']);

    // Draw text line by line.
    if ($useTtf) {
        $y = $padding + $lineHeight;
        foreach ($lines as $line) {
            imagettftext($image, $fontSize, 0, $padding, $y, $textColor, $fontPath, $line);
            $y += $lineHeight + $lineSpacing;
        }
    } else {
        $y = $padding;
        foreach ($lines as $line) {
            imagestring($image, $font, $padding, $y, $line, $textColor);
            $y += $lineHeight;
        }
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
    $html = normalizeToUtf8($html, 'html_extract');

    // Preserve common block separators before stripping tags.
    $html = preg_replace('#<\s*br\s*/?\s*>#i', "\n", $html);
    $html = preg_replace('#</\s*p\s*>#i', "\n", $html);
    $html = preg_replace('#</\s*div\s*>#i', "\n", $html);

    // Decode entities and strip tags.
    $text = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = strip_tags($text);

    // Normalize line endings and whitespace.
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = preg_replace('/[ \t]+/u', ' ', $text);
    $text = preg_replace('/\n{2,}/', "\n", $text);
    $text = trim($text);

    return normalizeToUtf8($text, 'html_extract_result');
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
 * @param string|null $preferredEngine Optional forced engine (wkhtmltoimage|gd|imagemagick)
 * @return array Result with success status and file path
 */
function convertHtmlToPng($htmlBlocks, $cssContent, $contentHash, $preferredEngine = null) {
    enforceRuntimeLimit('convert_start');

    // Get output directory
    $outputDir = getOutputDirectory();
    $cacheFileKey = $contentHash;
    if ($preferredEngine !== null) {
        $cacheFileKey .= '_' . $preferredEngine;
    }
    $outputPath = $outputDir . '/' . $cacheFileKey . '.png';

    // Check if file already exists (cache hit).
    if (file_exists($outputPath)) {
        return [
            'success' => true,
            'cached' => true,
            'engine' => $preferredEngine ?? null,
            'output_path' => $outputPath,
            'file_size' => filesize($outputPath)
        ];
    }

    // Detect available libraries
    $detection = detectAvailableLibraries();
    $priority = ['wkhtmltoimage', 'gd', 'imagemagick'];
    $availableLibraries = [];
    foreach ($priority as $libraryName) {
        if (!empty($detection['detected_libraries'][$libraryName]['available'])) {
            $availableLibraries[] = $libraryName;
        }
    }

    if ($preferredEngine !== null) {
        if (!in_array($preferredEngine, $priority, true)) {
            sendError(500, 'Unknown requested rendering engine', [
                'requested_engine' => $preferredEngine
            ]);
        }

        if (!in_array($preferredEngine, $availableLibraries, true)) {
            sendError(500, 'Requested rendering engine is not available', [
                'requested_engine' => $preferredEngine,
                'available_libraries' => $availableLibraries,
                'detected_libraries' => $detection['detected_libraries']
            ]);
        }

        $availableLibraries = [$preferredEngine];
    }

    if (empty($availableLibraries)) {
        sendError(500, 'No rendering libraries available', [
            'detected_libraries' => $detection
        ]);
    }

    // Render using available libraries in priority order with fallback.
    $failedAttempts = [];
    foreach ($availableLibraries as $libraryName) {
        enforceRuntimeLimit('convert_render_' . $libraryName);
        $result = null;
        switch ($libraryName) {
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
                $failedAttempts[$libraryName] = [
                    'error' => 'Unknown library selected'
                ];
                continue 2;
        }

        if (!empty($result['success'])) {
            // Add cache flag for new renders
            $result['cached'] = false;
            return $result;
        }

        $failedAttempts[$libraryName] = [
            'error' => $result['error'] ?? 'Unknown error',
            'details' => $result
        ];
    }

    sendError(500, 'Rendering failed with all available libraries', [
        'attempted_libraries' => $availableLibraries,
        'failures' => $failedAttempts
    ]);
}

/**
 * Load CSS content from URL via cURL with caching and invalidation
 *
 * This function implements proper cache invalidation using HTTP conditional requests.
 * When a CSS file changes on the server, the cache is automatically invalidated and
 * the new CSS is fetched, which results in a new content hash and PNG regeneration.
 *
 * @param string $cssUrl The CSS URL to load
 * @return string CSS content
 * @throws Exception If cURL request fails
 */
function loadCssContent($cssUrl) {
    enforceRuntimeLimit('load_css_start');

    // Check if cURL is available
    if (!extension_loaded('curl')) {
        sendError(500, 'cURL extension is not available', [
            'required_extension' => 'curl',
            'css_url' => $cssUrl
        ]);
    }

    // Check cache freshness using conditional HTTP request
    $freshnessCheck = checkCssCacheFreshness($cssUrl);
    $cachePath = getCssCachePath($cssUrl);

    // If cache is valid (HTTP 304), return cached content
    if ($freshnessCheck['valid']) {
        $cssContent = normalizeToUtf8(file_get_contents($cachePath), 'css_cache_content');
        $metadata = loadCssMetadata($cssUrl);

        return [
            'content' => $cssContent,
            'cached' => true,
            'cache_filemtime' => filemtime($cachePath),
            'cache_age' => time() - filemtime($cachePath),
            'metadata' => $metadata,
            'cache_status' => 'hit',
            'freshness_check' => $freshnessCheck
        ];
    }

    // Need to fetch from remote URL (cache miss or expired)
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

    // Add conditional headers if we have cached metadata
    $metadata = loadCssMetadata($cssUrl);
    if ($metadata !== null) {
        $headers = [];
        if (!empty($metadata['etag'])) {
            $headers[] = 'If-None-Match: ' . $metadata['etag'];
        }
        if (!empty($metadata['last_modified'])) {
            $headers[] = 'If-Modified-Since: ' . gmdate('D, d M Y H:i:s T', $metadata['last_modified']) . ' GMT';
        }
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
    }

    // Request headers to capture ETag and Last-Modified
    $responseHeaders = [];
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $header) use (&$responseHeaders) {
        $len = strlen($header);
        $header = explode(':', $header, 2);
        if (count($header) < 2) {
            return $len;
        }
        $responseHeaders[strtolower(trim($header[0]))][] = trim($header[1]);
        return $len;
    });

    // Execute cURL request
    enforceRuntimeLimit('load_css_before_curl_exec');
    $cssContent = curl_exec($ch);

    // Get HTTP status code
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    enforceRuntimeLimit('load_css_after_curl_exec');

    // Check for cURL errors
    if ($cssContent === false) {
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        sendError(500, 'Failed to load CSS file via cURL', [
            'css_url' => $cssUrl,
            'curl_error' => $error,
            'curl_errno' => $errno
        ]);
    }

    // Handle HTTP 304 Not Modified (cache is still valid)
    if ($httpCode === 304) {
        // Server confirms cache is still valid
        $cssContent = normalizeToUtf8(file_get_contents($cachePath), 'css_cache_content');
        $metadata = loadCssMetadata($cssUrl);

        // Update cache filemtime to now
        touch($cachePath);

        return [
            'content' => $cssContent,
            'cached' => true,
            'cache_filemtime' => filemtime($cachePath),
            'cache_age' => time() - filemtime($cachePath),
            'metadata' => $metadata,
            'cache_status' => 'validated',
            'http_code' => 304
        ];
    }

    // Check HTTP status code for errors
    if ($httpCode !== 200) {
        // If we have cached content and server is unreachable, use cache as fallback
        if (file_exists($cachePath) && $httpCode >= 500) {
            $cssContent = normalizeToUtf8(file_get_contents($cachePath), 'css_cache_content');
            $metadata = loadCssMetadata($cssUrl);

            return [
                'content' => $cssContent,
                'cached' => true,
                'cache_filemtime' => filemtime($cachePath),
                'cache_age' => time() - filemtime($cachePath),
                'metadata' => $metadata,
                'cache_status' => 'fallback',
                'http_code' => $httpCode,
                'warning' => 'Using cached content due to server error'
            ];
        }

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

    $cssContent = normalizeToUtf8($cssContent, 'css_content');

    // Check CSS content size
    $cssSize = strlen($cssContent);
    if ($cssSize > MAX_CSS_SIZE) {
        sendError(413, 'CSS file exceeds maximum size', [
            'css_url' => $cssUrl,
            'css_size' => $cssSize,
            'max_allowed_size' => MAX_CSS_SIZE,
            'max_allowed_mb' => round(MAX_CSS_SIZE / 1048576, 2),
            'exceeded_by' => $cssSize - MAX_CSS_SIZE
        ]);
    }

    // Extract ETag and Last-Modified from headers
    $etag = $responseHeaders['etag'][0] ?? null;
    $lastModified = isset($responseHeaders['last-modified'][0]) ? strtotime($responseHeaders['last-modified'][0]) : null;

    // Save to cache (this will overwrite old cache if CSS changed)
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
        'cache_filemtime' => filemtime($cachePath),
        'cache_status' => 'fresh',
        'http_code' => $httpCode
    ];
}

// Check total input size before parsing
enforceRuntimeLimit('before_input_size_check');
checkTotalInputSize();

// Parse input data
enforceRuntimeLimit('before_parse_input');
$input = parseInput();

// Extract and validate parameters
enforceRuntimeLimit('before_validate_input');
$htmlBlocks = validateHtmlBlocks($input['html_blocks'] ?? null);
$cssUrl = validateCssUrl($input['css_url'] ?? null);
$requestedRenderEngine = validateRenderEngine(
    $input['render_engine'] ?? $input['renderer'] ?? $input['engine'] ?? null
);

// Load CSS content if URL is provided
$cssResult = null;
$cssContent = null;
if ($cssUrl !== null) {
    enforceRuntimeLimit('before_css_load');
    $cssResult = loadCssContent($cssUrl);
    $cssContent = $cssResult['content'];
}

// Detect available rendering libraries
enforceRuntimeLimit('before_library_detection');
$libraryDetection = detectAvailableLibraries();

// Log the library selection for debugging
$selectedLibrary = $requestedRenderEngine ?? ($libraryDetection['best_library'] ?? null);
$selectionReason = '';
if ($requestedRenderEngine !== null) {
    if (!empty($libraryDetection['detected_libraries'][$requestedRenderEngine]['available'])) {
        $selectionReason = sprintf(
            'Selected by request parameter render_engine=%s',
            strtoupper($requestedRenderEngine)
        );
    } else {
        $selectionReason = sprintf(
            'Requested render_engine=%s is not available',
            strtoupper($requestedRenderEngine)
        );
    }
} elseif ($selectedLibrary) {
    $priorityOrder = ['wkhtmltoimage' => 1, 'gd' => 2, 'imagemagick' => 3];
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
enforceRuntimeLimit('before_hash_generation');
$contentHash = generateContentHash($htmlBlocks, $cssContent);

// Return successful parsing response (for now, until conversion is implemented)
$responseData = [
    'status' => 'Parameters validated successfully',
    'html_blocks_count' => count($htmlBlocks),
    'html_blocks_preview' => array_map(function($block) {
        return substr($block, 0, 100) . (strlen($block) > 100 ? '...' : '');
    }, $htmlBlocks),
    'css_url' => $cssUrl,
    'render_engine_requested' => $requestedRenderEngine ?? 'auto',
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
enforceRuntimeLimit('before_render');
$renderResult = convertHtmlToPng($htmlBlocks, $cssContent, $contentHash, $requestedRenderEngine);

// Add rendering results to response
$responseData['rendering'] = [
    'engine' => $renderResult['engine'] ?? 'unknown',
    'selection_mode' => $requestedRenderEngine === null ? 'auto' : 'forced',
    'requested_engine' => $requestedRenderEngine ?? 'auto',
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

enforceRuntimeLimit('before_success_response');
sendSuccess($responseData, 'HTML converted to PNG successfully');
