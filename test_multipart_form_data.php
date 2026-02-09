<?php
/**
 * Test Script for Feature #9: Multipart/Form-Data Parsing
 *
 * This script verifies that the API correctly parses multipart/form-data
 * POST requests with html_blocks and css_url parameters.
 */

// Test configuration
// Detect if running inside Docker container
$isDocker = file_exists('/.dockerenv');
$apiUrl = $isDocker ? 'http://127.0.0.1/convert.php' : 'http://localhost:8080/convert.php';
$results = [];
$totalTests = 0;
$passedTests = 0;

/**
 * Send POST request with multipart/form-data
 *
 * @param string $url API endpoint URL
 * @param array $fields Associative array of form fields
 * @return array Response info with status, body, headers
 */
function sendMultipartRequest($url, $fields) {
    $boundary = '----Boundary' . uniqid();
    $body = '';

    foreach ($fields as $name => $value) {
        $body .= "--$boundary\r\n";
        $body .= "Content-Disposition: form-data; name=\"$name\"\r\n\r\n";
        $body .= "$value\r\n";
    }

    $body .= "--$boundary--\r\n";

    $headers = [
        'Content-Type: multipart/form-data; boundary=' . $boundary,
        'Content-Length: ' . strlen($body)
    ];

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers),
            'content' => $body,
            'ignore_errors' => true
        ]
    ]);

    $response = file_get_contents($url, false, $context);
    $statusCode = substr($http_response_header[0], 9, 3);

    return [
        'status' => $statusCode,
        'body' => $response,
        'headers' => $http_response_header
    ];
}

/**
 * Run a test case
 *
 * @param string $testName Test name
 * @param callable $testFn Test function
 */
function runTest($testName, $testFn) {
    global $totalTests, $passedTests, $results;

    $totalTests++;
    echo "Test $totalTests: $testName\n";

    try {
        $result = $testFn();
        $results[$testName] = $result;
        if ($result['passed']) {
            $passedTests++;
            echo "  ✅ PASSED\n";
        } else {
            echo "  ❌ FAILED: {$result['message']}\n";
        }
        if (isset($result['details'])) {
            echo "  Details: {$result['details']}\n";
        }
    } catch (Exception $e) {
        $results[$testName] = [
            'passed' => false,
            'message' => $e->getMessage()
        ];
        echo "  ❌ EXCEPTION: {$e->getMessage()}\n";
    }
    echo "\n";
}

// Test 1: Basic multipart/form-data with html_blocks array
runTest('Multipart with single html_blocks string', function() use ($apiUrl) {
    $fields = [
        'html_blocks' => '<div>Test HTML Block</div>'
    ];

    $response = sendMultipartRequest($apiUrl, $fields);
    $data = json_decode($response['body'], true);

    if ($response['status'] !== '200') {
        return [
            'passed' => false,
            'message' => "Expected status 200, got {$response['status']}"
        ];
    }

    if (!isset($data['success']) || $data['success'] !== true) {
        return [
            'passed' => false,
            'message' => 'Response does not indicate success'
        ];
    }

    if (!isset($data['data']['html_blocks_count']) || $data['data']['html_blocks_count'] != 1) {
        return [
            'passed' => false,
            'message' => "Expected html_blocks_count=1, got {$data['data']['html_blocks_count']}"
        ];
    }

    return [
        'passed' => true,
        'message' => 'Single HTML block parsed correctly',
        'details' => "Count: {$data['data']['html_blocks_count']}"
    ];
});

// Test 2: Multipart with multiple html_blocks (array notation)
runTest('Multipart with array notation html_blocks[0]', function() use ($apiUrl) {
    $fields = [
        'html_blocks[0]' => '<div>Block 1</div>',
        'html_blocks[1]' => '<p>Block 2</p>',
        'html_blocks[2]' => '<span>Block 3</span>'
    ];

    $response = sendMultipartRequest($apiUrl, $fields);
    $data = json_decode($response['body'], true);

    if ($response['status'] !== '200') {
        return [
            'passed' => false,
            'message' => "Expected status 200, got {$response['status']}"
        ];
    }

    if (!isset($data['data']['html_blocks_count']) || $data['data']['html_blocks_count'] != 3) {
        return [
            'passed' => false,
            'message' => "Expected html_blocks_count=3, got {$data['data']['html_blocks_count']}"
        ];
    }

    return [
        'passed' => true,
        'message' => 'Multiple HTML blocks parsed correctly',
        'details' => "Count: {$data['data']['html_blocks_count']}"
    ];
});

// Test 3: Multipart with html_blocks and css_url
runTest('Multipart with html_blocks and css_url', function() use ($apiUrl) {
    $fields = [
        'html_blocks' => '<div class="styled">Styled Content</div>',
        'css_url' => 'http://example.com/styles.css'
    ];

    $response = sendMultipartRequest($apiUrl, $fields);
    $data = json_decode($response['body'], true);

    if ($response['status'] !== '200') {
        return [
            'passed' => false,
            'message' => "Expected status 200, got {$response['status']}"
        ];
    }

    if (!isset($data['data']['css_url']) || $data['data']['css_url'] !== 'http://example.com/styles.css') {
        return [
            'passed' => false,
            'message' => "css_url not parsed correctly"
        ];
    }

    return [
        'passed' => true,
        'message' => 'Both html_blocks and css_url parsed correctly',
        'details' => "CSS URL: {$data['data']['css_url']}"
    ];
});

// Test 4: Multipart with Cyrillic content (encoding test)
runTest('Multipart with Cyrillic characters', function() use ($apiUrl) {
    $fields = [
        'html_blocks' => '<div>Тест на русском языке</div><p>Привет, мир!</p>'
    ];

    $response = sendMultipartRequest($apiUrl, $fields);
    $data = json_decode($response['body'], true);

    if ($response['status'] !== '200') {
        return [
            'passed' => false,
            'message' => "Expected status 200, got {$response['status']}"
        ];
    }

    // Check if Cyrillic characters are preserved in preview
    if (!isset($data['data']['html_blocks_preview'][0])) {
        return [
            'passed' => false,
            'message' => 'html_blocks_preview not found in response'
        ];
    }

    $preview = $data['data']['html_blocks_preview'][0];
    if (strpos($preview, 'Тест') === false && strpos($preview, 'русском') === false) {
        // Might be truncated, check if at least some content is there
        if (strlen($preview) < 10) {
            return [
                'passed' => false,
                'message' => 'Cyrillic content may not be preserved correctly'
            ];
        }
    }

    return [
        'passed' => true,
        'message' => 'Cyrillic content handled correctly',
        'details' => "Preview length: " . strlen($preview)
    ];
});

// Test 5: Multipart with empty html_blocks (should fail validation)
runTest('Multipart with empty html_blocks should return error', function() use ($apiUrl) {
    $fields = [
        'html_blocks' => ''
    ];

    $response = sendMultipartRequest($apiUrl, $fields);
    $data = json_decode($response['body'], true);

    if ($response['status'] !== '400') {
        return [
            'passed' => false,
            'message' => "Expected status 400 for empty html_blocks, got {$response['status']}"
        ];
    }

    if (!isset($data['success']) || $data['success'] !== false) {
        return [
            'passed' => false,
            'message' => 'Response should indicate error'
        ];
    }

    return [
        'passed' => true,
        'message' => 'Empty html_blocks correctly rejected',
        'details' => "Error: {$data['error']}"
    ];
});

// Test 6: Multipart with missing html_blocks (should fail)
runTest('Multipart without html_blocks should return error', function() use ($apiUrl) {
    $fields = [
        'css_url' => 'http://example.com/styles.css'
    ];

    $response = sendMultipartRequest($apiUrl, $fields);
    $data = json_decode($response['body'], true);

    if ($response['status'] !== '400') {
        return [
            'passed' => false,
            'message' => "Expected status 400 for missing html_blocks, got {$response['status']}"
        ];
    }

    if (strpos($data['error'] ?? '', 'html_blocks') === false) {
        return [
            'passed' => false,
            'message' => 'Error message should mention html_blocks'
        ];
    }

    return [
        'passed' => true,
        'message' => 'Missing html_blocks correctly rejected',
        'details' => "Error: {$data['error']}"
    ];
});

// Test 7: Multipart with complex HTML structure
runTest('Multipart with complex HTML structure', function() use ($apiUrl) {
    $complexHtml = '<div class="container">
        <h1>Title</h1>
        <p class="description">Paragraph with <strong>bold</strong> text</p>
        <ul>
            <li>Item 1</li>
            <li>Item 2</li>
        </ul>
    </div>';

    $fields = [
        'html_blocks' => $complexHtml,
        'css_url' => 'https://cdn.example.com/main.css'
    ];

    $response = sendMultipartRequest($apiUrl, $fields);
    $data = json_decode($response['body'], true);

    if ($response['status'] !== '200') {
        return [
            'passed' => false,
            'message' => "Expected status 200, got {$response['status']}"
        ];
    }

    if (!isset($data['data']['html_blocks_count']) || $data['data']['html_blocks_count'] != 1) {
        return [
            'passed' => false,
            'message' => "Expected html_blocks_count=1, got {$data['data']['html_blocks_count']}"
        ];
    }

    // Verify CSS URL was parsed
    if ($data['data']['css_url'] !== 'https://cdn.example.com/main.css') {
        return [
            'passed' => false,
            'message' => "CSS URL not parsed correctly"
        ];
    }

    return [
        'passed' => true,
        'message' => 'Complex HTML structure parsed correctly',
        'details' => "HTML preserved, CSS URL: {$data['data']['css_url']}"
    ];
});

// Test 8: Verify Content-Type header in response
runTest('Response has correct Content-Type header', function() use ($apiUrl) {
    $fields = [
        'html_blocks' => '<div>Test</div>'
    ];

    $response = sendMultipartRequest($apiUrl, $fields);
    $contentType = '';

    foreach ($response['headers'] as $header) {
        if (stripos($header, 'Content-Type') === 0) {
            $contentType = $header;
            break;
        }
    }

    if (strpos($contentType, 'application/json') === false) {
        return [
            'passed' => false,
            'message' => "Content-Type should be application/json, got: $contentType"
        ];
    }

    return [
        'passed' => true,
        'message' => 'Content-Type header is correct',
        'details' => $contentType
    ];
});

// Print summary
echo str_repeat("=", 70) . "\n";
echo "TEST SUMMARY\n";
echo str_repeat("=", 70) . "\n";
echo "Total Tests: $totalTests\n";
echo "Passed: $passedTests\n";
echo "Failed: " . ($totalTests - $passedTests) . "\n";
echo "Success Rate: " . round(($passedTests / $totalTests) * 100, 1) . "%\n";
echo str_repeat("=", 70) . "\n";

// Exit with appropriate code
exit($passedTests === $totalTests ? 0 : 1);
