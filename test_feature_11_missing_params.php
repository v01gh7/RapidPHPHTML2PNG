<?php
/**
 * Test Feature #11: Handles missing parameters with error
 *
 * This test verifies that the API returns appropriate errors for missing required parameters.
 */

function testMissingHtmlBlocks() {
    echo "Test 1: Send POST request without html_blocks parameter\n";
    echo str_repeat("=", 70) . "\n";

    $data = [
        // Intentionally NOT sending html_blocks
        'css_url' => 'http://example.com/style.css'
    ];

    $ch = curl_init('http://localhost:8080/convert.php');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    echo "Request: POST /convert.php\n";
    echo "Payload: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
    echo "Response HTTP Status: $httpCode\n";
    echo "Response Content-Type: $contentType\n";
    echo "Response Body:\n";
    echo $response . "\n\n";

    // Parse JSON response
    $responseData = json_decode($response, true);

    // Verify test results
    $results = [];

    // Test 2: Verify response has HTTP status 400
    $results['http_400'] = ($httpCode === 400);
    echo "Test 2: HTTP status is 400 (Bad Request) - " .
         ($results['http_400'] ? "✅ PASS" : "❌ FAIL") . "\n";

    // Test 3: Check JSON response contains error message
    $results['has_error'] = isset($responseData['error']);
    echo "Test 3: JSON response contains 'error' field - " .
         ($results['has_error'] ? "✅ PASS" : "❌ FAIL") . "\n";

    // Test 4: Verify error message indicates missing parameter
    $results['error_indicates_missing'] = isset($responseData['error']) &&
        (stripos($responseData['error'], 'html_blocks') !== false ||
         stripos($responseData['error'], 'missing') !== false ||
         stripos($responseData['error'], 'required') !== false);
    echo "Test 4: Error message indicates missing parameter - " .
         ($results['error_indicates_missing'] ? "✅ PASS" : "❌ FAIL") . "\n";

    // Test 5: Confirm response is still valid JSON format
    $results['valid_json'] = ($responseData !== null && JSON_ERROR_NONE === json_last_error());
    echo "Test 5: Response is valid JSON format - " .
         ($results['valid_json'] ? "✅ PASS" : "❌ FAIL") . "\n";

    // Additional tests
    $results['success_false'] = isset($responseData['success']) && $responseData['success'] === false;
    echo "Additional: 'success' field is false - " .
         ($results['success_false'] ? "✅ PASS" : "❌ FAIL") . "\n";

    $results['has_timestamp'] = isset($responseData['timestamp']);
    echo "Additional: Response includes 'timestamp' field - " .
         ($results['has_timestamp'] ? "✅ PASS" : "❌ FAIL") . "\n";

    $results['correct_content_type'] = (strpos($contentType, 'application/json') !== false);
    echo "Additional: Content-Type is application/json - " .
         ($results['correct_content_type'] ? "✅ PASS" : "❌ FAIL") . "\n";

    echo "\n";
    return $results;
}

function testEmptyPostBody() {
    echo "Additional Test: Empty POST body\n";
    echo str_repeat("=", 70) . "\n";

    $ch = curl_init('http://localhost:8080/convert.php');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, '');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $responseData = json_decode($response, true);

    echo "Request: POST /convert.php with empty body\n";
    echo "Response HTTP Status: $httpCode\n";
    echo "Response Body:\n$response\n\n";

    $pass = ($httpCode === 400 && isset($responseData['error']));
    echo "Result: " . ($pass ? "✅ PASS" : "❌ FAIL") . "\n\n";

    return $pass;
}

function testOnlyOptionalParam() {
    echo "Additional Test: Only optional parameter (css_url)\n";
    echo str_repeat("=", 70) . "\n";

    $data = ['css_url' => 'http://localhost:8080/main.css'];

    $ch = curl_init('http://localhost:8080/convert.php');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $responseData = json_decode($response, true);

    echo "Request: POST /convert.php with only css_url\n";
    echo "Response HTTP Status: $httpCode\n";
    echo "Response Body:\n$response\n\n";

    $pass = ($httpCode === 400 && isset($responseData['error']));
    echo "Result: " . ($pass ? "✅ PASS" : "❌ FAIL") . "\n\n";

    return $pass;
}

function testFormEncodedMissingParam() {
    echo "Additional Test: Form-encoded with missing parameter\n";
    echo str_repeat("=", 70) . "\n";

    $data = ['css_url' => 'http://example.com/style.css'];

    $ch = curl_init('http://localhost:8080/convert.php');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: application/json'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $responseData = json_decode($response, true);

    echo "Request: POST /convert.php (form-encoded) without html_blocks\n";
    echo "Response HTTP Status: $httpCode\n";
    echo "Response Body:\n$response\n\n";

    $pass = ($httpCode === 400 && isset($responseData['error']));
    echo "Result: " . ($pass ? "✅ PASS" : "❌ FAIL") . "\n\n";

    return $pass;
}

// Run all tests
echo "╔══════════════════════════════════════════════════════════════════════╗\n";
echo "║   Feature #11: Handles missing parameters with error - Tests        ║\n";
echo "╚══════════════════════════════════════════════════════════════════════╝\n\n";

$results = testMissingHtmlBlocks();
$additional1 = testEmptyPostBody();
$additional2 = testOnlyOptionalParam();
$additional3 = testFormEncodedMissingParam();

// Summary
echo "╔══════════════════════════════════════════════════════════════════════╗\n";
echo "║                          TEST SUMMARY                                ║\n";
echo "╚══════════════════════════════════════════════════════════════════════╝\n";

$totalTests = count($results) + 3;
$passedTests = count(array_filter($results)) + ($additional1 ? 1 : 0) + ($additional2 ? 1 : 0) + ($additional3 ? 1 : 0);

echo "Required Tests (Feature Steps):\n";
foreach ($results as $test => $passed) {
    echo "  " . ($passed ? "✅" : "❌") . " $test\n";
}

echo "\nAdditional Tests:\n";
echo "  " . ($additional1 ? "✅" : "❌") . " Empty POST body\n";
echo "  " . ($additional2 ? "✅" : "❌") . " Only optional parameter\n";
echo "  " . ($additional3 ? "✅" : "❌") . " Form-encoded missing parameter\n";

echo "\nTotal: $passedTests/$totalTests tests passed (" . round($passedTests/$totalTests*100, 1) . "%)\n";

if ($passedTests === $totalTests) {
    echo "\n🎉 Feature #11: ALL TESTS PASSED! ✅\n";
    exit(0);
} else {
    echo "\n⚠️  Feature #11: Some tests failed\n";
    exit(1);
}
