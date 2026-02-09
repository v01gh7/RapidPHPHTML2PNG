<?php
/**
 * Test Feature #10: Returns valid JSON response
 *
 * This script verifies that the API returns properly formatted JSON responses
 * with correct Content-Type headers and valid JSON structure.
 */

// Use 127.0.0.1 for direct access from within container
$baseUrl = 'http://127.0.0.1';
$endpoint = '/convert.php';
$url = $baseUrl . $endpoint;

echo "=== Feature #10: Returns valid JSON response ===\n\n";

// Test 1: POST with valid parameters should return 200 with valid JSON
echo "Test 1: POST with valid parameters - 200 OK with valid JSON\n";
$data = [
    'html_blocks' => ['<div>Hello World</div>'],
    'css_url' => 'http://localhost:8080/main.css'
];
$options = [
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\n",
        'content' => json_encode($data),
        'ignore_errors' => true
    ]
];
$context = stream_context_create($options);
$response = file_get_contents($url, false, $context);

// Check Content-Type header
$headers = $http_response_header ?? [];
$foundContentType = false;
foreach ($headers as $header) {
    if (stripos($header, 'Content-Type') !== false) {
        echo "  Header: $header\n";
        if (stripos($header, 'application/json') !== false) {
            $foundContentType = true;
        }
    }
}

// Verify JSON is valid
$decoded = json_decode($response, true);
if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
    echo "  ❌ FAILED: Invalid JSON - " . json_last_error_msg() . "\n";
    echo "  Response: $response\n";
} else {
    echo "  ✅ PASSED: Valid JSON returned\n";
}

// Check for expected fields
if (isset($decoded['success'])) {
    echo "  ✅ PASSED: Contains 'success' field (value: " . ($decoded['success'] ? 'true' : 'false') . ")\n";
} else {
    echo "  ❌ FAILED: Missing 'success' field\n";
}

if (isset($decoded['message'])) {
    echo "  ✅ PASSED: Contains 'message' field\n";
} else {
    echo "  ❌ FAILED: Missing 'message' field\n";
}

if (isset($decoded['timestamp'])) {
    echo "  ✅ PASSED: Contains 'timestamp' field\n";
} else {
    echo "  ❌ FAILED: Missing 'timestamp' field\n";
}

if (isset($decoded['data'])) {
    echo "  ✅ PASSED: Contains 'data' field\n";
} else {
    echo "  ⚠️  WARNING: Missing 'data' field (may not be required for all responses)\n";
}

if ($foundContentType) {
    echo "  ✅ PASSED: Content-Type is application/json\n";
} else {
    echo "  ❌ FAILED: Content-Type is not application/json\n";
}

echo "\n";

// Test 2: POST with missing parameters should return 400 with valid JSON
echo "Test 2: POST with missing parameters - 400 error with valid JSON\n";
$data = ['invalid_param' => 'test'];
$options = [
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\n",
        'content' => json_encode($data),
        'ignore_errors' => true
    ]
];
$context = stream_context_create($options);
$response = file_get_contents($url, false, $context);

$decoded = json_decode($response, true);
if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
    echo "  ❌ FAILED: Invalid JSON - " . json_last_error_msg() . "\n";
    echo "  Response: $response\n";
} else {
    echo "  ✅ PASSED: Valid JSON returned for error\n";
}

if (isset($decoded['success']) && $decoded['success'] === false) {
    echo "  ✅ PASSED: Error response has success=false\n";
} else {
    echo "  ❌ FAILED: Error response should have success=false\n";
}

if (isset($decoded['error'])) {
    echo "  ✅ PASSED: Error response contains 'error' field\n";
} else {
    echo "  ❌ FAILED: Error response missing 'error' field\n";
}

echo "\n";

// Test 3: GET request should return 405 with valid JSON
echo "Test 3: GET request - 405 error with valid JSON\n";
$options = [
    'http' => [
        'method' => 'GET',
        'ignore_errors' => true
    ]
];
$context = stream_context_create($options);
$response = file_get_contents($url, false, $context);

$decoded = json_decode($response, true);
if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
    echo "  ❌ FAILED: Invalid JSON - " . json_last_error_msg() . "\n";
} else {
    echo "  ✅ PASSED: Valid JSON returned for 405 error\n";
}

if (isset($decoded['success']) && $decoded['success'] === false) {
    echo "  ✅ PASSED: 405 response has success=false\n";
} else {
    echo "  ❌ FAILED: 405 response should have success=false\n";
}

echo "\n";

// Test 4: POST with invalid JSON should return 400 with valid JSON
echo "Test 4: POST with invalid JSON body - 400 error with valid JSON\n";
$options = [
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\n",
        'content' => '{invalid json}',
        'ignore_errors' => true
    ]
];
$context = stream_context_create($options);
$response = file_get_contents($url, false, $context);

$decoded = json_decode($response, true);
if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
    echo "  ❌ FAILED: Invalid JSON - " . json_last_error_msg() . "\n";
} else {
    echo "  ✅ PASSED: Valid JSON returned for JSON parse error\n";
}

if (isset($decoded['error'])) {
    echo "  ✅ PASSED: Response contains error message\n";
} else {
    echo "  ❌ FAILED: Response should contain error message\n";
}

echo "\n";

// Test 5: POST with empty html_blocks should return 400 with valid JSON
echo "Test 5: POST with empty html_blocks array - 400 error with valid JSON\n";
$data = ['html_blocks' => []];
$options = [
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\n",
        'content' => json_encode($data),
        'ignore_errors' => true
    ]
];
$context = stream_context_create($options);
$response = file_get_contents($url, false, $context);

$decoded = json_decode($response, true);
if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
    echo "  ❌ FAILED: Invalid JSON - " . json_last_error_msg() . "\n";
} else {
    echo "  ✅ PASSED: Valid JSON returned for empty array\n";
}

if (isset($decoded['success']) && $decoded['success'] === false) {
    echo "  ✅ PASSED: Error response has success=false\n";
} else {
    echo "  ❌ FAILED: Error response should have success=false\n";
}

echo "\n";

// Test 6: Verify JSON_PRETTY_PRINT formatting
echo "Test 6: Check JSON formatting (pretty print)\n";
$data = ['html_blocks' => ['<div>Test</div>']];
$options = [
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\n",
        'content' => json_encode($data),
        'ignore_errors' => true
    ]
];
$context = stream_context_create($options);
$response = file_get_contents($url, false, $context);

// Check if response is pretty-printed (has newlines and indentation)
if (strpos($response, "\n") !== false && strpos($response, "  ") !== false) {
    echo "  ✅ PASSED: JSON is pretty-printed with indentation\n";
} else {
    echo "  ⚠️  INFO: JSON may not be pretty-printed (not critical)\n";
}

echo "\n";
echo "=== Feature #10 Test Summary ===\n";
echo "All tests verify that the API returns properly formatted JSON responses\n";
echo "with correct Content-Type headers and expected JSON structure.\n";
