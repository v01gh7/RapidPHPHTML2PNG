<?php
/**
 * Test Feature #8: Endpoint accepts css_url parameter
 *
 * This script verifies that the API properly accepts, validates, and parses
 * the css_url parameter in various formats and scenarios.
 */

echo "=== Feature #8: css_url Parameter Acceptance Test ===\n\n";

$baseUrl = 'http://localhost:8080/convert.php';
$testResults = [];
$totalTests = 0;
$passedTests = 0;

/**
 * Make a POST request to the API
 */
function makeRequest($url, $data) {
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
    return json_decode($response, true);
}

/**
 * Test helper function
 */
function runTest($testName, $testFn, &$totalTests, &$passedTests, &$testResults) {
    $totalTests++;
    echo "Test $totalTests: $testName\n";
    try {
        $result = $testFn();
        if ($result['passed']) {
            $passedTests++;
            echo "  ✅ PASSED: " . $result['message'] . "\n\n";
        } else {
            echo "  ❌ FAILED: " . $result['message'] . "\n\n";
        }
        $testResults[] = [
            'name' => $testName,
            'passed' => $result['passed'],
            'message' => $result['message']
        ];
    } catch (Exception $e) {
        echo "  ❌ EXCEPTION: " . $e->getMessage() . "\n\n";
        $testResults[] = [
            'name' => $testName,
            'passed' => false,
            'message' => 'Exception: ' . $e->getMessage()
        ];
    }
}

// Test 1: Valid http CSS URL
runTest(
    'Valid http CSS URL is accepted',
    function() use ($baseUrl) {
        $response = makeRequest($baseUrl, [
            'html_blocks' => ['<div>Test</div>'],
            'css_url' => 'http://example.com/style.css'
        ]);

        if (!$response['success']) {
            return ['passed' => false, 'message' => 'Request was not successful: ' . ($response['error'] ?? 'Unknown error')];
        }

        if (!isset($response['data']['css_url'])) {
            return ['passed' => false, 'message' => 'Response does not contain css_url field'];
        }

        if ($response['data']['css_url'] !== 'http://example.com/style.css') {
            return ['passed' => false, 'message' => 'css_url value mismatch: ' . $response['data']['css_url']];
        }

        return ['passed' => true, 'message' => 'Valid http CSS URL accepted and parsed correctly'];
    },
    $totalTests,
    $passedTests,
    $testResults
);

// Test 2: Valid https CSS URL
runTest(
    'Valid https CSS URL is accepted',
    function() use ($baseUrl) {
        $response = makeRequest($baseUrl, [
            'html_blocks' => ['<div>Test</div>'],
            'css_url' => 'https://cdn.example.com/theme.css'
        ]);

        if (!$response['success']) {
            return ['passed' => false, 'message' => 'Request was not successful: ' . ($response['error'] ?? 'Unknown error')];
        }

        if ($response['data']['css_url'] !== 'https://cdn.example.com/theme.css') {
            return ['passed' => false, 'message' => 'https CSS URL not returned correctly'];
        }

        return ['passed' => true, 'message' => 'Valid https CSS URL accepted'];
    },
    $totalTests,
    $passedTests,
    $testResults
);

// Test 3: CSS URL with query parameters
runTest(
    'CSS URL with query parameters is accepted',
    function() use ($baseUrl) {
        $response = makeRequest($baseUrl, [
            'html_blocks' => ['<div>Test</div>'],
            'css_url' => 'http://example.com/style.css?v=1.2.3&theme=dark'
        ]);

        if (!$response['success']) {
            return ['passed' => false, 'message' => 'Request failed: ' . ($response['error'] ?? 'Unknown')];
        }

        if ($response['data']['css_url'] !== 'http://example.com/style.css?v=1.2.3&theme=dark') {
            return ['passed' => false, 'message' => 'CSS URL with query params not preserved'];
        }

        return ['passed' => true, 'message' => 'CSS URL with query parameters accepted'];
    },
    $totalTests,
    $passedTests,
    $testResults
);

// Test 4: CSS URL with port number
runTest(
    'CSS URL with custom port is accepted',
    function() use ($baseUrl) {
        $response = makeRequest($baseUrl, [
            'html_blocks' => ['<div>Test</div>'],
            'css_url' => 'http://localhost:8080/static/main.css'
        ]);

        if (!$response['success']) {
            return ['passed' => false, 'message' => 'Request failed: ' . ($response['error'] ?? 'Unknown')];
        }

        if ($response['data']['css_url'] !== 'http://localhost:8080/static/main.css') {
            return ['passed' => false, 'message' => 'CSS URL with port not handled correctly'];
        }

        return ['passed' => true, 'message' => 'CSS URL with port number accepted'];
    },
    $totalTests,
    $passedTests,
    $testResults
);

// Test 5: CSS URL with path segments
runTest(
    'CSS URL with nested path segments is accepted',
    function() use ($baseUrl) {
        $response = makeRequest($baseUrl, [
            'html_blocks' => ['<div>Test</div>'],
            'css_url' => 'https://cdn.example.com/assets/css/v2/components/buttons.css'
        ]);

        if (!$response['success']) {
            return ['passed' => false, 'message' => 'Request failed: ' . ($response['error'] ?? 'Unknown')];
        }

        if ($response['data']['css_url'] !== 'https://cdn.example.com/assets/css/v2/components/buttons.css') {
            return ['passed' => false, 'message' => 'Nested path CSS URL not preserved'];
        }

        return ['passed' => true, 'message' => 'CSS URL with nested paths accepted'];
    },
    $totalTests,
    $passedTests,
    $testResults
);

// Test 6: Empty css_url parameter (optional)
runTest(
    'Empty css_url parameter is handled gracefully (optional parameter)',
    function() use ($baseUrl) {
        $response = makeRequest($baseUrl, [
            'html_blocks' => ['<div>Test</div>'],
            'css_url' => ''
        ]);

        if (!$response['success']) {
            return ['passed' => false, 'message' => 'Request with empty css_url failed'];
        }

        if ($response['data']['css_url'] !== null) {
            return ['passed' => false, 'message' => 'Empty css_url should return null'];
        }

        return ['passed' => true, 'message' => 'Empty css_url handled correctly (returns null)'];
    },
    $totalTests,
    $passedTests,
    $testResults
);

// Test 7: No css_url parameter provided
runTest(
    'Request without css_url parameter works (css_url is optional)',
    function() use ($baseUrl) {
        $response = makeRequest($baseUrl, [
            'html_blocks' => ['<div>Test</div>']
        ]);

        if (!$response['success']) {
            return ['passed' => false, 'message' => 'Request without css_url failed'];
        }

        if (!array_key_exists('css_url', $response['data'])) {
            return ['passed' => false, 'message' => 'Response should include css_url field'];
        }

        if ($response['data']['css_url'] !== null) {
            return ['passed' => false, 'message' => 'Missing css_url should return null'];
        }

        return ['passed' => true, 'message' => 'Request without css_url works correctly'];
    },
    $totalTests,
    $passedTests,
    $testResults
);

// Test 8: Invalid URL scheme (ftp:// should be rejected)
runTest(
    'Invalid URL scheme (ftp://) is rejected with 400 error',
    function() use ($baseUrl) {
        $response = makeRequest($baseUrl, [
            'html_blocks' => ['<div>Test</div>'],
            'css_url' => 'ftp://example.com/style.css'
        ]);

        if ($response['success']) {
            return ['passed' => false, 'message' => 'FTP URL should be rejected but was accepted'];
        }

        if (!isset($response['error']) || strpos($response['error'], 'http or https') === false) {
            return ['passed' => false, 'message' => 'Wrong error message for invalid scheme'];
        }

        return ['passed' => true, 'message' => 'Invalid URL scheme correctly rejected'];
    },
    $totalTests,
    $passedTests,
    $testResults
);

// Test 9: Invalid URL format
runTest(
    'Invalid URL format is rejected with 400 error',
    function() use ($baseUrl) {
        $response = makeRequest($baseUrl, [
            'html_blocks' => ['<div>Test</div>'],
            'css_url' => 'not-a-valid-url'
        ]);

        if ($response['success']) {
            return ['passed' => false, 'message' => 'Invalid URL should be rejected but was accepted'];
        }

        if (!isset($response['error'])) {
            return ['passed' => false, 'message' => 'Error response missing'];
        }

        return ['passed' => true, 'message' => 'Invalid URL format correctly rejected'];
    },
    $totalTests,
    $passedTests,
    $testResults
);

// Test 10: CSS URL with fragment identifier
runTest(
    'CSS URL with fragment identifier is accepted',
    function() use ($baseUrl) {
        $response = makeRequest($baseUrl, [
            'html_blocks' => ['<div>Test</div>'],
            'css_url' => 'http://example.com/style.css#media-screen'
        ]);

        if (!$response['success']) {
            return ['passed' => false, 'message' => 'Request failed: ' . ($response['error'] ?? 'Unknown')];
        }

        if ($response['data']['css_url'] !== 'http://example.com/style.css#media-screen') {
            return ['passed' => false, 'message' => 'CSS URL with fragment not preserved'];
        }

        return ['passed' => true, 'message' => 'CSS URL with fragment accepted'];
    },
    $totalTests,
    $passedTests,
    $testResults
);

// Test 11: Form-urlencoded POST with css_url
runTest(
    'Form-urlencoded POST with css_url parameter works',
    function() use ($baseUrl) {
        $data = [
            'html_blocks' => ['<div>Test</div>'],
            'css_url' => 'http://example.com/form.css'
        ];

        $options = [
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => http_build_query($data),
                'ignore_errors' => true
            ]
        ];
        $context = stream_context_create($options);
        $response = file_get_contents($baseUrl, false, $context);
        $response = json_decode($response, true);

        if (!$response['success']) {
            return ['passed' => false, 'message' => 'Form-urlencoded request failed: ' . ($response['error'] ?? 'Unknown')];
        }

        if ($response['data']['css_url'] !== 'http://example.com/form.css') {
            return ['passed' => false, 'message' => 'Form-urlencoded css_url not handled correctly'];
        }

        return ['passed' => true, 'message' => 'Form-urlencoded POST with css_url works'];
    },
    $totalTests,
    $passedTests,
    $testResults
);

// Test 12: Absolute URL with authentication (edge case - should still validate URL structure)
runTest(
    'URL with username and password structure is parsed as valid URL',
    function() use ($baseUrl) {
        $response = makeRequest($baseUrl, [
            'html_blocks' => ['<div>Test</div>'],
            'css_url' => 'http://user:pass@example.com/style.css'
        ]);

        if (!$response['success']) {
            return ['passed' => false, 'message' => 'Request with auth URL failed'];
        }

        if ($response['data']['css_url'] !== 'http://user:pass@example.com/style.css') {
            return ['passed' => false, 'message' => 'URL with auth not preserved'];
        }

        return ['passed' => true, 'message' => 'URL with authentication info accepted'];
    },
    $totalTests,
    $passedTests,
    $testResults
);

// Summary
echo "\n" . str_repeat("=", 60) . "\n";
echo "TEST SUMMARY\n";
echo str_repeat("=", 60) . "\n";
echo "Total Tests: $totalTests\n";
echo "Passed: $passedTests\n";
echo "Failed: " . ($totalTests - $passedTests) . "\n";
echo "Success Rate: " . round(($passedTests / $totalTests) * 100, 1) . "%\n";
echo str_repeat("=", 60) . "\n";

if ($passedTests === $totalTests) {
    echo "✅ ALL TESTS PASSED!\n\n";
    exit(0);
} else {
    echo "❌ SOME TESTS FAILED\n\n";
    exit(1);
}
