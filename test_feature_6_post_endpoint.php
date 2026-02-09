<?php
/**
 * Feature #6 Verification: POST endpoint accepts requests
 *
 * This script verifies that the convert.php endpoint properly:
 * 1. Accepts POST requests
 * 2. Responds without routing errors
 * 3. Returns appropriate HTTP status codes (200 for success, 405 for wrong method)
 * 4. Returns Content-Type: application/json
 * 5. Returns valid JSON structure
 */

echo "=== Feature #6 Verification: POST Endpoint ===\n\n";

$tests_passed = 0;
$tests_failed = 0;

// Test 1: POST endpoint is accessible
echo "Test 1: POST endpoint accepts requests\n";
echo "----------------------------------------\n";
$ch = curl_init('http://localhost:8080/convert.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['test' => 'data']));
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

echo "Request: POST http://localhost:8080/convert.php\n";
echo "HTTP Status: $http_code\n";
echo "Content-Type: $content_type\n";
echo "Response: " . substr($response, 0, 200) . "\n";

if ($http_code === 200) {
    echo "✅ PASS: HTTP status is 200\n";
    $tests_passed++;
} else {
    echo "❌ FAIL: HTTP status should be 200, got $http_code\n";
    $tests_failed++;
}

if (strpos($content_type, 'application/json') !== false) {
    echo "✅ PASS: Content-Type is application/json\n";
    $tests_passed++;
} else {
    echo "❌ FAIL: Content-Type should be application/json, got $content_type\n";
    $tests_failed++;
}

$data = json_decode($response, true);
if ($data !== null && isset($data['success'])) {
    echo "✅ PASS: Response is valid JSON\n";
    $tests_passed++;
} else {
    echo "❌ FAIL: Response is not valid JSON\n";
    $tests_failed++;
}

echo "\n";

// Test 2: GET requests are rejected
echo "Test 2: Non-POST methods are rejected\n";
echo "--------------------------------------\n";
$ch = curl_init('http://localhost:8080/convert.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Request: GET http://localhost:8080/convert.php\n";
echo "HTTP Status: $http_code\n";
echo "Response: " . substr($response, 0, 200) . "\n";

if ($http_code === 405) {
    echo "✅ PASS: GET request returns 405 Method Not Allowed\n";
    $tests_passed++;
} else {
    echo "❌ FAIL: GET request should return 405, got $http_code\n";
    $tests_failed++;
}

$data = json_decode($response, true);
if ($data !== null && isset($data['success']) && $data['success'] === false) {
    echo "✅ PASS: Error response has proper JSON structure\n";
    $tests_passed++;
} else {
    echo "❌ FAIL: Error response should have success: false\n";
    $tests_failed++;
}

echo "\n";

// Test 3: OPTIONS preflight is handled
echo "Test 3: OPTIONS preflight request\n";
echo "----------------------------------\n";
$ch = curl_init('http://localhost:8080/convert.php');
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'OPTIONS');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Origin: http://localhost:8080']);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Request: OPTIONS http://localhost:8080/convert.php\n";
echo "HTTP Status: $http_code\n";
echo "Response: " . $response . "\n";

if ($http_code === 200) {
    echo "✅ PASS: OPTIONS request returns 200\n";
    $tests_passed++;
} else {
    echo "❌ FAIL: OPTIONS request should return 200, got $http_code\n";
    $tests_failed++;
}

echo "\n";

// Test 4: Different Content-Type headers work
echo "Test 4: Accepts different content types\n";
echo "----------------------------------------\n";
$ch = curl_init('http://localhost:8080/convert.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['test' => 'data']));
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

echo "Request: POST with form-urlencoded data\n";
echo "HTTP Status: $http_code\n";
echo "Content-Type: $content_type\n";

if ($http_code === 200) {
    echo "✅ PASS: Accepts form-urlencoded data\n";
    $tests_passed++;
} else {
    echo "❌ FAIL: Should accept form-urlencoded data\n";
    $tests_failed++;
}

echo "\n";

// Summary
echo "=== Summary ===\n";
echo "Tests Passed: $tests_passed\n";
echo "Tests Failed: $tests_failed\n";

if ($tests_failed === 0) {
    echo "\n✅ ALL TESTS PASSED - Feature #6 is working correctly!\n";
    exit(0);
} else {
    echo "\n❌ SOME TESTS FAILED - Feature #6 needs fixes\n";
    exit(1);
}
?>
