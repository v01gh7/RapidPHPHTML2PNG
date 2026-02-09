<?php
/**
 * Feature #40: Returns HTTP 400 for invalid input
 *
 * Tests that the API returns proper HTTP status codes for invalid requests
 */

// Test configuration
define('TEST_MODE', true);
$apiUrl = 'http://localhost/convert.php';

$tests = [];
$results = [];

/**
 * Test API response for invalid input
 */
function testInvalidInput($name, $payload) {
    global $apiUrl, $tests, $results;

    $tests[] = $name;

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_HEADER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Parse response
    $headerSize = strpos($response, "\r\n\r\n");
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize + 4);
    $data = json_decode($body, true);

    // Check if response is correct
    $passed = ($httpCode === 400) &&
              isset($data['success']) &&
              ($data['success'] === false) &&
              isset($data['error']) &&
              !empty($data['error']) &&
              isset($data['timestamp']);

    $results[$name] = [
        'passed' => $passed,
        'http_code' => $httpCode,
        'has_success' => isset($data['success']),
        'success_value' => $data['success'] ?? null,
        'has_error' => isset($data['error']),
        'error_message' => $data['error'] ?? null,
        'has_timestamp' => isset($data['timestamp']),
        'has_data' => isset($data['data']),
        'response' => $data
    ];

    return $passed;
}

echo "=== Feature #40: HTTP 400 Error Response Tests ===\n\n";

// Test 1: Missing html_blocks parameter
testInvalidInput(
    'Missing html_blocks parameter',
    []
);

// Test 2: Empty html_blocks array
testInvalidInput(
    'Empty html_blocks array',
    ['html_blocks' => []]
);

// Test 3: html_blocks is not an array
testInvalidInput(
    'html_blocks is not an array',
    ['html_blocks' => 'invalid']
);

// Test 4: html_blocks contains non-string value
testInvalidInput(
    'html_blocks contains non-string value',
    ['html_blocks' => [123]]
);

// Test 5: html_blocks contains empty string
testInvalidInput(
    'html_blocks contains empty string',
    ['html_blocks' => ['']]
);

// Test 6: html_blocks contains only whitespace
testInvalidInput(
    'html_blocks contains only whitespace',
    ['html_blocks' => ['   ']]
);

// Test 7: Invalid JSON
$tests[] = 'Invalid JSON format';
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, '{invalid json}');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_HEADER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$headerSize = strpos($response, "\r\n\r\n");
$headers = substr($response, 0, $headerSize);
$body = substr($response, $headerSize + 4);
$data = json_decode($body, true);

$passed = ($httpCode === 400) &&
          isset($data['success']) &&
          ($data['success'] === false) &&
          isset($data['error']) &&
          !empty($data['error']);

$results['Invalid JSON format'] = [
    'passed' => $passed,
    'http_code' => $httpCode,
    'has_success' => isset($data['success']),
    'success_value' => $data['success'] ?? null,
    'has_error' => isset($data['error']),
    'error_message' => $data['error'] ?? null,
    'response' => $data
];

// Test 8: Invalid css_url (not a string)
testInvalidInput(
    'Invalid css_url type',
    ['html_blocks' => ['<div>test</div>'], 'css_url' => 123]
);

// Test 9: Invalid css_url (not a valid URL)
testInvalidInput(
    'Invalid css_url format',
    ['html_blocks' => ['<div>test</div>'], 'css_url' => 'not-a-url']
);

// Test 10: Invalid css_url scheme
testInvalidInput(
    'Invalid css_url scheme',
    ['html_blocks' => ['<div>test</div>'], 'css_url' => 'file:///etc/passwd']
);

// Test 11: Only dangerous HTML (after sanitization)
testInvalidInput(
    'Only dangerous HTML (sanitized to empty)',
    ['html_blocks' => ['<script>alert("xss")</script>']]
);

// Test 12: Malformed HTML structure
testInvalidInput(
    'Malformed HTML',
    ['html_blocks' => ['<div>unclosed']]
);

// Display results
echo "Test Results:\n";
echo str_repeat("=", 80) . "\n";

$passCount = 0;
$failCount = 0;

foreach ($tests as $testName) {
    $result = $results[$testName];
    $status = $result['passed'] ? '✓ PASS' : '✗ FAIL';
    $httpStatus = $result['http_code'] ?? 'N/A';

    if ($result['passed']) {
        $passCount++;
        echo "\033[32m{$status}\033[0m";
    } else {
        $failCount++;
        echo "\033[31m{$status}\033[0m";
    }

    echo " - {$testName}\n";
    echo "    HTTP Status: {$httpStatus} ";

    if ($httpStatus === 400) {
        echo "\033[32m✓\033[0m";
    } else {
        echo "\033[31m✗ (expected 400)\033[0m";
    }

    echo "\n";

    if (isset($result['has_success']) && $result['has_success']) {
        $successValue = $result['success_value'] ? 'true' : 'false';
        $successCheck = ($result['success_value'] === false) ? "\033[32m✓\033[0m" : "\033[31m✗\033[0m";
        echo "    Success: {$successValue} {$successCheck}\n";
    }

    if (isset($result['has_error']) && $result['has_error']) {
        $errorMsg = $result['error_message'];
        $errorCheck = !empty($errorMsg) ? "\033[32m✓\033[0m" : "\033[31m✗\033[0m";
        echo "    Error: {$errorMsg} {$errorCheck}\n";
    }

    if (isset($result['has_timestamp']) && $result['has_timestamp']) {
        echo "    Timestamp: present \033[32m✓\033[0m\n";
    }

    if (isset($result['has_data'])) {
        $hasData = $result['has_data'] ? 'yes' : 'no';
        echo "    Additional Data: {$hasData}\n";
    }

    echo "\n";
}

echo str_repeat("=", 80) . "\n";
$total = count($tests);
$percentage = round(($passCount / $total) * 100, 1);

echo "\n\033[1mSummary: {$passCount}/{$total} tests passed ({$percentage}%)\033[0m\n";

if ($passCount === $total) {
    echo "\033[32m✓ All tests passed!\033[0m\n";
    exit(0);
} else {
    echo "\033[31m✗ Some tests failed\033[0m\n";
    exit(1);
}
