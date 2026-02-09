<?php
/**
 * Feature #37: XSS Protection API Integration Tests
 *
 * Tests that malicious HTML is sanitized before rendering through the API
 */

$baseUrl = 'http://localhost:8080/convert.php';

$testsPassed = 0;
$testsFailed = 0;

echo "=== Feature #37: XSS Protection API Integration Tests ===\n\n";

/**
 * Test helper function
 */
function testApiXss($testName, $html, $shouldSanitize = true) {
    global $baseUrl, $testsPassed, $testsFailed;

    echo "Test: $testName\n";
    echo "HTML: " . substr($html, 0, 80) . (strlen($html) > 80 ? '...' : '') . "\n";

    // Prepare the request
    $postData = json_encode([
        'html_blocks' => [$html]
    ]);

    // Make API request using curl
    $ch = curl_init($baseUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Parse response
    $data = json_decode($response, true);

    if ($data === null) {
        echo "  ❌ FAIL: Invalid JSON response\n";
        echo "  Response: $response\n";
        $testsFailed++;
        echo "\n";
        return;
    }

    // Check if request was successful
    if (!isset($data['success']) || $data['success'] !== true) {
        echo "  ❌ FAIL: API request failed\n";
        echo "  Error: " . ($data['error'] ?? 'Unknown error') . "\n";
        $testsFailed++;
        echo "\n";
        return;
    }

    // Check that rendering succeeded
    if (!isset($data['data']['rendering']['success']) || $data['data']['rendering']['success'] !== true) {
        echo "  ❌ FAIL: Rendering failed\n";
        echo "  Error: " . ($data['data']['rendering']['error'] ?? 'Unknown error') . "\n";
        $testsFailed++;
        echo "\n";
        return;
    }

    // Get the output file path
    $outputFile = $data['data']['rendering']['output_file'] ?? '';

    if (empty($outputFile)) {
        echo "  ❌ FAIL: No output file returned\n";
        $testsFailed++;
        echo "\n";
        return;
    }

    echo "  Output: $outputFile\n";

    // Convert Windows path to container path if needed
    $containerPath = $outputFile;
    if (strpos($outputFile, ':/') !== false) {
        // Windows path, convert to container path
        $containerPath = '/var/www/html' . str_replace(['D:/', '\\'], ['', '/'], $outputFile);
    }

    // Check if file was created (using Docker exec)
    $fileExists = shell_exec("docker exec rapidhtml2png-php test -f '$containerPath' 2>&1 && echo YES || echo NO");

    if (strpos($fileExists, 'YES') === false) {
        echo "  ❌ FAIL: Output file not created at $containerPath\n";
        $testsFailed++;
        echo "\n";
        return;
    }

    echo "  ✅ PASS: Malicious HTML sanitized and PNG created\n";
    $testsPassed++;
    echo "\n";
}

// ============================================================================
// Test 1: Script tag should be removed
// ============================================================================
testApiXss(
    "Script tag removal",
    '<div>Hello <script>alert("XSS")</script>World</div>',
    true
);

// ============================================================================
// Test 2: Event handler should be removed
// ============================================================================
testApiXss(
    "Event handler removal",
    '<div onclick="alert(1)">Click me</div>',
    true
);

// ============================================================================
// Test 3: JavaScript in href should be removed
// ============================================================================
testApiXss(
    "JavaScript in href",
    '<a href="javascript:alert(1)">Click</a>',
    true
);

// ============================================================================
// Test 4: iframe should be removed
// ============================================================================
testApiXss(
    "iframe removal",
    '<div>Text <iframe src="evil.com"></iframe> end</div>',
    true
);

// ============================================================================
// Test 5: Multiple XSS vectors
// ============================================================================
testApiXss(
    "Multiple XSS vectors",
    '<div onclick="alert(1)"><script>alert(2)</script><img src=x onerror="alert(3)"></div>',
    true
);

// ============================================================================
// Test 6: Safe HTML should still work
// ============================================================================
testApiXss(
    "Safe HTML preservation",
    '<div class="test">Hello World</div>',
    false
);

// ============================================================================
// Test 7: onload event handler
// ============================================================================
testApiXss(
    "onload event handler removal",
    '<img src="x.jpg" onload="alert(1)">',
    true
);

// ============================================================================
// Test 8: onerror event handler
// ============================================================================
testApiXss(
    "onerror event handler removal",
    '<img src="invalid.jpg" onerror="alert(1)">',
    true
);

// ============================================================================
// Test 9: Form tag removal
// ============================================================================
testApiXss(
    "Form tag removal",
    '<div>Before <form action="evil.com"><input type="text"></form> After</div>',
    true
);

// ============================================================================
// Test 10: Input tag removal
// ============================================================================
testApiXss(
    "Input tag removal",
    '<p>Field: <input type="text" onclick="alert(1)"></p>',
    true
);

// ============================================================================
// Summary
// ============================================================================
echo "========================================\n";
echo "API Integration Test Results Summary:\n";
echo "========================================\n";
echo "Total Tests: " . ($testsPassed + $testsFailed) . "\n";
echo "Passed: $testsPassed ✅\n";
echo "Failed: $testsFailed ❌\n";
$percentage = ($testsPassed + $testsFailed) > 0
    ? round(($testsPassed / ($testsPassed + $testsFailed)) * 100, 1)
    : 0;
echo "Success Rate: $percentage%\n";
echo "========================================\n";

// Exit with proper code
exit($testsFailed > 0 ? 1 : 0);
