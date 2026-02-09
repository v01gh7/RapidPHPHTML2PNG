<?php
/**
 * Feature #15 Test: Handles CSS loading errors
 *
 * This script tests that the API gracefully handles CSS loading failures:
 * 1. Invalid CSS URLs (404 errors)
 * 2. Unreachable hosts (DNS failures)
 * 3. Network timeouts
 * 4. Invalid URL schemes
 * 5. cURL errors
 */

$apiUrl = (getenv('DOCKER_ENV') === 'true')
    ? 'http://localhost/convert.php'
    : 'http://127.0.0.1:8080/convert.php';
$testsPassed = 0;
$testsFailed = 0;
$testResults = [];

/**
 * Test CSS loading error scenario
 */
function testCssError($testName, $cssUrl, $expectedErrorPatterns = []) {
    global $apiUrl, $testsPassed, $testsFailed, $testResults;

    echo "\n" . str_repeat("=", 80) . "\n";
    echo "TEST: $testName\n";
    echo str_repeat("=", 80) . "\n";

    $postData = json_encode([
        'html_blocks' => ['<div>Test content</div>'],
        'css_url' => $cssUrl
    ]);

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n" .
                        "Content-Length: " . strlen($postData) . "\r\n",
            'content' => $postData,
            'ignore_errors' => true,
            'timeout' => 35
        ]
    ]);

    $response = @file_get_contents($apiUrl, false, $context);

    if ($response === false) {
        echo "❌ FAILED: No response from API\n";
        $testsFailed++;
        $testResults[] = ['name' => $testName, 'status' => 'FAILED', 'error' => 'No response'];
        return false;
    }

    // Parse response
    $responseData = json_decode($response, true);
    $httpCode = substr($http_response_header[0], 9, 3);

    echo "HTTP Status Code: $httpCode\n";
    echo "CSS URL: $cssUrl\n";
    echo "Response:\n" . json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

    // Verify error response structure
    $testPassed = true;
    $errors = [];

    // Check that response has proper error structure
    if (!isset($responseData['success'])) {
        $errors[] = "Missing 'success' field in response";
        $testPassed = false;
    }

    if (!isset($responseData['error']) && !isset($responseData['message'])) {
        $errors[] = "Missing 'error' or 'message' field in response";
        $testPassed = false;
    }

    // Check that success is false (or true with error details)
    if (isset($responseData['success']) && $responseData['success'] !== false) {
        // If success is true, it should still indicate the CSS loading failed
        if (!isset($responseData['data']['css_error']) &&
            !isset($responseData['data']['css_loaded']) &&
            strpos($response, 'CSS') === false) {
            $errors[] = "Response indicates success but should indicate CSS loading failure";
            $testPassed = false;
        }
    }

    // Check for expected error patterns (case-insensitive)
    foreach ($expectedErrorPatterns as $pattern) {
        if (stripos($response, $pattern) === false) {
            $errors[] = "Response missing expected error pattern: '$pattern'";
            $testPassed = false;
        }
    }

    // Verify HTTP status code is appropriate (4xx or 5xx)
    if ($httpCode < 400 || $httpCode >= 600) {
        $errors[] = "Expected HTTP status code 4xx or 5xx, got $httpCode";
        $testPassed = false;
    }

    // Verify response is valid JSON
    if (json_last_error() !== JSON_ERROR_NONE) {
        $errors[] = "Response is not valid JSON: " . json_last_error_msg();
        $testPassed = false;
    }

    if ($testPassed) {
        echo "✅ PASSED: Error handled gracefully\n";
        $testsPassed++;
        $testResults[] = ['name' => $testName, 'status' => 'PASSED'];
    } else {
        echo "❌ FAILED:\n";
        foreach ($errors as $error) {
            echo "  - $error\n";
        }
        $testsFailed++;
        $testResults[] = ['name' => $testName, 'status' => 'FAILED', 'errors' => $errors];
    }

    return $testPassed;
}

echo "\n";
echo "╔══════════════════════════════════════════════════════════════════════════════╗\n";
echo "║  Feature #15 Test: Handles CSS loading errors                                ║\n";
echo "║  Testing graceful error handling for CSS loading failures                    ║\n";
echo "╚══════════════════════════════════════════════════════════════════════════════╝\n";

// Test 1: 404 Not Found (CSS file doesn't exist)
testCssError(
    'Test 1: CSS file returns 404 Not Found',
    'http://httpbin.org/status/404',
    ['CSS', '404', 'error']
);

// Test 2: Unreachable host (invalid DNS)
testCssError(
    'Test 2: CSS URL with invalid hostname (DNS failure)',
    'http://this-domain-definitely-does-not-exist-12345.com/styles.css',
    ['CSS', 'error', 'curl']
);

// Test 3: Connection timeout (unreachable IP)
testCssError(
    'Test 3: CSS URL with connection timeout (unreachable IP)',
    'http://192.0.2.1/styles.css', // TEST-NET-1 (documentation IP, should timeout)
    ['CSS', 'error', 'timed', 'curl']
);

// Test 4: Invalid URL scheme
testCssError(
    'Test 4: CSS URL with invalid scheme (ftp://)',
    'ftp://example.com/styles.css',
    ['scheme', 'http', 'https']
);

// Test 5: Malformed URL
testCssError(
    'Test 5: Malformed CSS URL',
    'not-a-valid-url',
    ['valid URL', 'URL']
);

// Test 6: HTTP error (500 from server)
testCssError(
    'Test 6: CSS URL that returns 500 error',
    'http://httpbin.org/status/500',
    ['CSS', '500', 'error']
);

// Test 7: HTTP error (403 Forbidden)
testCssError(
    'Test 7: CSS URL that returns 403 Forbidden',
    'http://httpbin.org/status/403',
    ['CSS', '403', 'error']
);

// Test 8: Redirect loop (should fail)
testCssError(
    'Test 8: CSS URL with redirect loop',
    'http://httpbin.org/redirect/10', // Too many redirects
    ['CSS', 'error', 'redirect']
);

// Test 9: Empty CSS file
testCssError(
    'Test 9: CSS URL that returns empty content',
    'http://httpbin.org/bytes/0',
    ['CSS', 'empty']
);

// Test 10: SSL certificate error (invalid cert)
testCssError(
    'Test 10: CSS URL with SSL certificate error',
    'https://expired.badssl.com/',
    ['CSS', 'error', 'SSL', 'certificate']
);

// Summary
echo "\n";
echo str_repeat("═", 80) . "\n";
echo "SUMMARY\n";
echo str_repeat("═", 80) . "\n";
echo "Total Tests: " . ($testsPassed + $testsFailed) . "\n";
echo "Passed: $testsPassed ✅\n";
echo "Failed: $testsFailed ❌\n";
echo "Success Rate: " . round(($testsPassed / ($testsPassed + $testsFailed)) * 100, 1) . "%\n";
echo str_repeat("═", 80) . "\n";

// Detailed results
echo "\nDETAILED RESULTS:\n";
foreach ($testResults as $result) {
    $status = $result['status'] === 'PASSED' ? '✅' : '❌';
    echo "$status {$result['name']}\n";
    if (isset($result['errors'])) {
        foreach ($result['errors'] as $error) {
            echo "    - $error\n";
        }
    }
}

// Exit with appropriate code
exit($testsFailed > 0 ? 1 : 0);
