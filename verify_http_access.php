<?php
/**
 * Verification script for Feature #5: HTTP Access to convert.php
 *
 * This script verifies that the main PHP script responds correctly to HTTP requests
 */

echo "==========================================\n";
echo "Feature #5 Verification: HTTP Access\n";
echo "==========================================\n\n";

// Use localhost:80 from inside container (Apache is on port 80 internally)
$baseUrl = 'http://localhost:80';
$tests = [];

// Test 1: GET request (should return 405 Method Not Allowed)
echo "Test 1: GET request to /convert.php\n";
echo "Expected: 405 Method Not Allowed\n";
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'ignore_errors' => true  // Allow reading response body on 4xx/5xx errors
    ]
]);
$response = @file_get_contents($baseUrl . '/convert.php', false, $context);
$headers = $http_response_header ?? [];
$statusCode = 0;
foreach ($headers as $header) {
    if (preg_match('/HTTP\/\d\.\d\s+(\d+)/', $header, $matches)) {
        $statusCode = (int)$matches[1];
        break;
    }
}
echo "Actual Status: $statusCode\n";
echo "Response: " . substr($response, 0, 100) . "...\n";

// Verify JSON response
$jsonData = json_decode($response, true);
if ($statusCode === 405 && $jsonData && $jsonData['success'] === false && $jsonData['error'] === 'Method Not Allowed') {
    echo "✓ Test 1 PASSED - Correct 405 response with JSON\n\n";
    $tests[] = true;
} else {
    echo "✗ Test 1 FAILED - Expected 405 with JSON error\n";
    if (!$jsonData) {
        echo "  (JSON decode failed or response empty)\n";
    }
    echo "\n";
    $tests[] = false;
}

// Test 2: POST request (should return 200 OK)
echo "Test 2: POST request to /convert.php\n";
echo "Expected: 200 OK with JSON response\n";
$postData = json_encode([
    'html_blocks' => ['<div>Test HTML</div>'],
    'css_url' => null
]);
$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\n",
        'content' => $postData,
        'ignore_errors' => true  // Allow reading response body on errors
    ]
]);
$response = @file_get_contents($baseUrl . '/convert.php', false, $context);
$headers = $http_response_header ?? [];
$statusCode = 0;
$contentType = '';
foreach ($headers as $header) {
    if (preg_match('/HTTP\/\d\.\d\s+(\d+)/', $header, $matches)) {
        $statusCode = (int)$matches[1];
    }
    if (preg_match('/Content-Type:\s*(.+)/i', $header, $matches)) {
        $contentType = trim($matches[1]);
    }
}
echo "Actual Status: $statusCode\n";
echo "Content-Type: $contentType\n";

// Verify JSON response
$jsonData = json_decode($response, true);
if ($statusCode === 200 && $jsonData && $jsonData['success'] === true) {
    echo "✓ Test 2 PASSED - Correct 200 response with JSON\n\n";
    $tests[] = true;
} else {
    echo "✗ Test 2 FAILED\n\n";
    $tests[] = false;
}

// Test 3: Verify Content-Type header
echo "Test 3: Content-Type header verification\n";
echo "Expected: application/json; charset=utf-8\n";
if (strpos($contentType, 'application/json') !== false) {
    echo "✓ Test 3 PASSED - Correct Content-Type header\n\n";
    $tests[] = true;
} else {
    echo "✗ Test 3 FAILED - Wrong Content-Type: $contentType\n\n";
    $tests[] = false;
}

// Test 4: Verify no 404 or 500 errors
echo "Test 4: Verify no 404 or 500 errors\n";
if ($statusCode !== 404 && $statusCode !== 500) {
    echo "✓ Test 4 PASSED - No 404 or 500 errors\n\n";
    $tests[] = true;
} else {
    echo "✗ Test 4 FAILED - Got error code: $statusCode\n\n";
    $tests[] = false;
}

// Test 5: Verify response structure
echo "Test 5: Verify JSON response structure\n";
$requiredFields = ['success', 'timestamp'];
$hasAllFields = true;
foreach ($requiredFields as $field) {
    if (!isset($jsonData[$field])) {
        echo "Missing field: $field\n";
        $hasAllFields = false;
    }
}
if ($hasAllFields) {
    echo "✓ Test 5 PASSED - JSON has required fields\n\n";
    $tests[] = true;
} else {
    echo "✗ Test 5 FAILED - Missing required fields\n\n";
    $tests[] = false;
}

// Summary
echo "==========================================\n";
$passed = count(array_filter($tests));
$total = count($tests);
echo "Results: $passed/$total tests passed\n";
echo "==========================================\n";

if ($passed === $total) {
    echo "\n✓ Feature #5 VERIFIED - All tests passed!\n";
    exit(0);
} else {
    echo "\n✗ Feature #5 FAILED - Some tests did not pass\n";
    exit(1);
}
