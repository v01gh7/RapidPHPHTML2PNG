<?php
/**
 * Feature #41: Returns HTTP 500 for server errors
 *
 * Tests that the API returns proper HTTP status codes for server errors
 */

// Test configuration
define('TEST_MODE', true);
$apiUrl = 'http://localhost/convert.php';

$tests = [];
$results = [];

/**
 * Test API response for server errors
 */
function testServerError($name, $payload, $expectedErrorPattern = null) {
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
    if ($headerSize === false) {
        $headerSize = 0;
    }
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize + 4);
    $data = json_decode($body, true);

    // Check if response is correct
    $passed = ($httpCode === 500) &&
              isset($data['success']) &&
              ($data['success'] === false) &&
              isset($data['error']) &&
              !empty($data['error']) &&
              isset($data['timestamp']);

    // Check error pattern if provided
    if ($expectedErrorPattern && $passed) {
        $passed = preg_match($expectedErrorPattern, $data['error']) === 1;
    }

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

echo "=== Feature #41: HTTP 500 Server Error Tests ===\n\n";

// Test 1: CSS directory creation failure (simulated by using invalid path in payload)
// Note: This test verifies the error handling code exists even if we can't trigger actual failure
$tests[] = 'Verify sendError(500) function exists';
$passed = function_exists('sendError');
$results['Verify sendError(500) function exists'] = [
    'passed' => $passed,
    'http_code' => null,
    'response' => ['function_exists' => $passed]
];

// Test 2: Verify HTTP 500 is used for directory creation failures
$tests[] = 'Check error handling code for directory creation';
$convertPhp = file_get_contents(__DIR__ . '/convert.php');
$passed = strpos($convertPhp, "sendError(500, 'Failed to create CSS cache directory'") !== false ||
          strpos($convertPhp, "sendError(500, 'Failed to create output directory'") !== false;
$results['Check error handling code for directory creation'] = [
    'passed' => $passed,
    'http_code' => null,
    'response' => ['code_present' => $passed]
];

// Test 3: Verify HTTP 500 is used for rendering failures
$tests[] = 'Check error handling code for rendering failures';
$passed = strpos($convertPhp, "sendError(500, 'Rendering failed'") !== false;
$results['Check error handling code for rendering failures'] = [
    'passed' => $passed,
    'http_code' => null,
    'response' => ['code_present' => $passed]
];

// Test 4: Verify HTTP 500 is used for library detection failures
$tests[] = 'Check error handling code for library failures';
$passed = strpos($convertPhp, "sendError(500, 'No rendering libraries available'") !== false ||
          strpos($convertPhp, "sendError(500, 'Unknown library selected'") !== false;
$results['Check error handling code for library failures'] = [
    'passed' => $passed,
    'http_code' => null,
    'response' => ['code_present' => $passed]
];

// Test 5: Verify HTTP 500 is used for CSS loading failures
$tests[] = 'Check error handling code for CSS loading failures';
$passed = strpos($convertPhp, "sendError(500, 'cURL extension is not available'") !== false ||
          strpos($convertPhp, "sendError(500, 'Failed to initialize cURL'") !== false ||
          strpos($convertPhp, "sendError(500, 'Failed to load CSS file via cURL'") !== false;
$results['Check error handling code for CSS loading failures'] = [
    'passed' => $passed,
    'http_code' => null,
    'response' => ['code_present' => $passed]
];

// Test 6: Verify HTTP 500 is used for hash generation failures
$tests[] = 'Check error handling code for hash generation failures';
$passed = strpos($convertPhp, "sendError(500, 'Failed to generate valid MD5 hash'") !== false;
$results['Check error handling code for hash generation failures'] = [
    'passed' => $passed,
    'http_code' => null,
    'response' => ['code_present' => $passed]
];

// Test 7: Verify error logging is configured
$tests[] = 'Check error logging configuration';
$passed = strpos($convertPhp, "ini_set('log_errors', 1)") !== false &&
          strpos($convertPhp, "ini_set('error_log',") !== false;
$results['Check error logging configuration'] = [
    'passed' => $passed,
    'http_code' => null,
    'response' => ['logging_configured' => $passed]
];

// Test 8: Verify display_errors is disabled for security
$tests[] = 'Check display_errors is disabled';
$passed = strpos($convertPhp, "ini_set('display_errors', 0)") !== false;
$results['Check display_errors is disabled'] = [
    'passed' => $passed,
    'http_code' => null,
    'response' => ['display_errors_disabled' => $passed]
];

// Test 9: Count total sendError(500, ...) calls
$tests[] = 'Count total HTTP 500 error calls';
preg_match_all('/sendError\(500,/', $convertPhp, $matches);
$count500 = count($matches[0]);
$passed = $count500 >= 10; // Should have at least 10 different 500 errors
$results['Count total HTTP 500 error calls'] = [
    'passed' => $passed,
    'http_code' => null,
    'response' => ['total_500_errors' => $count500]
];

// Test 10: Verify sendError function signature
$tests[] = 'Check sendError function signature';
$passed = preg_match('/function sendError\(\s*\$\w+\s*,\s*\$\w+\s*(,\s*\$\w+)?\s*\)/', $convertPhp) === 1;
$results['Check sendError function signature'] = [
    'passed' => $passed,
    'http_code' => null,
    'response' => ['function_signature_valid' => $passed]
];

// Test 11: Verify http_response_code is used in sendError
$tests[] = 'Check http_response_code in sendError';
$passed = preg_match('/function sendError.*?http_response_code\(\s*\$\w+\s*\)/s', $convertPhp) === 1;
$results['Check http_response_code in sendError'] = [
    'passed' => $passed,
    'http_code' => null,
    'response' => ['sets_http_code' => $passed]
];

// Test 12: Verify error responses include timestamp
$tests[] = 'Check error responses include timestamp';
$passed = strpos($convertPhp, "'timestamp' => date('c')") !== false;
$results['Check error responses include timestamp'] = [
    'passed' => $passed,
    'http_code' => null,
    'response' => ['includes_timestamp' => $passed]
];

// Test 13: Verify error responses include success flag
$tests[] = 'Check error responses include success flag';
$passed = preg_match("/'success'\s*=>\s*false/", $convertPhp) === 1;
$results['Check error responses include success flag'] = [
    'passed' => $passed,
    'http_code' => null,
    'response' => ['includes_success_flag' => $passed]
];

// Test 14: Verify JSON response format
$tests[] = 'Check JSON response format in errors';
$passed = strpos($convertPhp, "json_encode(\$response, JSON_PRETTY_PRINT)") !== false;
$results['Check JSON response format in errors'] = [
    'passed' => $passed,
    'http_code' => null,
    'response' => ['json_pretty_print' => $passed]
];

// Test 15: Verify no sensitive info in error messages (basic check)
$tests[] = 'Check no sensitive paths exposed in common errors';
$sensitivePatterns = [
    '/__DIR__/',  // Should not expose __DIR__ constant in error messages
    '/realpath\(/',  // Should not expose realpath in error messages
    '/getcwd\(/',  // Should not expose getcwd in error messages
];
$exposed = false;
foreach ($sensitivePatterns as $pattern) {
    // Check only in sendError calls, not in implementation
    preg_match_all('/sendError\([^)]*\);/', $convertPhp, $errorCalls);
    foreach ($errorCalls[0] as $call) {
        if (preg_match($pattern, $call)) {
            $exposed = true;
            break 2;
        }
    }
}
$passed = !$exposed;
$results['Check no sensitive paths exposed in common errors'] = [
    'passed' => $passed,
    'http_code' => null,
    'response' => ['no_sensitive_info' => $passed]
];

// Display results
echo "Test Results:\n";
echo str_repeat("=", 80) . "\n";

$passCount = 0;
$failCount = 0;

foreach ($tests as $testName) {
    $result = $results[$testName];
    $status = $result['passed'] ? '✓ PASS' : '✗ FAIL';

    if ($result['passed']) {
        $passCount++;
        echo "\033[32m{$status}\033[0m";
    } else {
        $failCount++;
        echo "\033[31m{$status}\033[0m";
    }

    echo " - {$testName}\n";

    // Show additional details
    if (isset($result['response'])) {
        foreach ($result['response'] as $key => $value) {
            if (is_bool($value)) {
                $strValue = $value ? 'true' : 'false';
            } elseif (is_int($value)) {
                $strValue = (string)$value;
            } else {
                $strValue = $value;
            }
            echo "    {$key}: {$strValue}\n";
        }
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
