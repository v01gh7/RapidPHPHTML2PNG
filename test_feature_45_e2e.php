<?php
/**
 * Feature #45: End-to-End Conversion Workflow Test
 *
 * This script verifies the complete workflow from HTML input to PNG output.
 * It should be run AFTER starting the PHP server on port 8080.
 *
 * Prerequisites:
 * - PHP server running on http://localhost:8080
 * - convert.php accessible at http://localhost:8080/convert.php
 *
 * Usage: php test_feature_45_e2e.php
 */

echo "======================================\n";
echo "Feature #45: E2E Conversion Workflow\n";
echo "======================================\n\n";

// Test configuration
const API_URL = 'http://localhost:8080/convert.php';
const TEST_IDENTIFIER = 'E2E_TEST_12345';
const TEST_COLOR = 'blue';

// Test data
$testHtml = '<div style="padding: 20px; font-family: Arial, sans-serif;">
    <h2 style="color: ' . TEST_COLOR . ';">End-to-End Test</h2>
    <p style="color: #333; font-size: 16px;">Test ID: ' . TEST_IDENTIFIER . '</p>
    <p style="color: #666;">This is a complete workflow test.</p>
</div>';

$testCss = '
.e2e-test-container {
    border: 2px solid ' . TEST_COLOR . ';
    border-radius: 8px;
    padding: 15px;
    background: #f9f9f9;
}
.e2e-title {
    color: ' . TEST_COLOR . ';
    font-weight: bold;
    font-size: 24px;
    margin-bottom: 10px;
}
';

$results = [];
$totalTests = 0;

/**
 * Test Step 1: Verify test HTML contains specific text
 */
echo "Step 1: Verifying test HTML content...\n";
$totalTests++;
$htmlContainsIdentifier = strpos($testHtml, TEST_IDENTIFIER) !== false;
$results[] = [
    'step' => 1,
    'name' => 'Test HTML contains specific text',
    'passed' => $htmlContainsIdentifier,
    'details' => $htmlContainsIdentifier ? 'Found: ' . TEST_IDENTIFIER : 'NOT found'
];
echo ($htmlContainsIdentifier ? '✓ PASS' : '✗ FAIL') . " - Test HTML contains identifier\n\n";

/**
 * Test Step 2: Verify CSS contains specific styling
 */
echo "Step 2: Verifying CSS content...\n";
$totalTests++;
$cssContainsColor = strpos($testCss, TEST_COLOR) !== false && strpos($testCss, 'color:') !== false;
$results[] = [
    'step' => 2,
    'name' => 'CSS contains specific styling',
    'passed' => $cssContainsColor,
    'details' => $cssContainsColor ? 'Found: color: blue' : 'NOT found'
];
echo ($cssContainsColor ? '✓ PASS' : '✗ FAIL') . " - CSS contains blue color styling\n\n";

/**
 * Test Step 3: Send POST request with HTML and CSS
 */
echo "Step 3: Sending POST request to API...\n";
$totalTests++;

$ch = curl_init(API_URL);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// Prepare multipart form data
$boundary = '----FormBoundary' . uniqid();
$postData = '--' . $boundary . "\r\n";
$postData .= 'Content-Disposition: form-data; name="html_blocks[]"' . "\r\n\r\n";
$postData .= $testHtml . "\r\n";
$postData .= '--' . $boundary . "\r\n";
$postData .= 'Content-Disposition: form-data; name="css_url"' . "\r\n\r\n";
$postData .= 'data:text/css;charset=utf-8,' . urlencode($testCss) . "\r\n";
$postData .= '--' . $boundary . '--';

curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: multipart/form-data; boundary=' . $boundary,
    'Content-Length: ' . strlen($postData)
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo "✗ FAIL - cURL Error: $curlError\n\n";
    $results[] = [
        'step' => 3,
        'name' => 'Send POST request',
        'passed' => false,
        'details' => 'cURL error',
        'message' => $curlError
    ];
    // Cannot continue without API response
    echo "\nCannot continue tests without API response.\n";
    exit(1);
}

$apiResponse = json_decode($response, true);
$apiSuccess = $apiResponse['success'] === true && $httpCode === 200;

$generatedHash = $apiResponse['data']['hash'] ?? null;
$generatedImagePath = $apiResponse['data']['output_path'] ?? null;

$results[] = [
    'step' => 3,
    'name' => 'Send POST request',
    'passed' => $apiSuccess,
    'details' => "HTTP $httpCode",
    'message' => $apiSuccess ? 'API successfully processed request' : ($apiResponse['error'] ?? 'Unknown error')
];

echo ($apiSuccess ? '✓ PASS' : '✗ FAIL') . " - API responded with HTTP $httpCode\n";
if ($apiSuccess) {
    echo "  Generated hash: $generatedHash\n";
    echo "  Output path: $generatedImagePath\n";
} else {
    echo "  Error: " . ($apiResponse['error'] ?? 'Unknown error') . "\n";
}
echo "\n";

if (!$apiSuccess || !$generatedHash || !$generatedImagePath) {
    echo "\nCannot continue file verification tests due to API failure.\n";
    displayResults($results, $totalTests);
    exit(1);
}

/**
 * Test Step 4: Verify PNG path format
 */
echo "Step 4: Verifying PNG file path...\n";
$totalTests++;
$expectedPathPattern = '/assets/media/rapidhtml2png/' . $generatedHash . '.png';
$pathMatches = strpos($generatedImagePath, $generatedHash) !== false &&
               strpos($generatedImagePath, '.png') !== false;

$results[] = [
    'step' => 4,
    'name' => 'PNG created at correct path',
    'passed' => $pathMatches,
    'details' => $pathMatches ? 'Path format valid' : 'Invalid path format',
    'message' => $pathMatches ? "PNG file path follows expected format" : "Path doesn't match expected format"
];
echo ($pathMatches ? '✓ PASS' : '✗ FAIL') . " - PNG path verification\n";
echo "  Expected pattern: $expectedPathPattern\n";
echo "  Actual: $generatedImagePath\n\n";

/**
 * Test Step 5: Load PNG via HTTP
 */
echo "Step 5: Loading PNG via HTTP...\n";
$totalTests++;

$imageUrl = 'http://localhost:8080/' . $generatedImagePath;
$ch = curl_init($imageUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$imageData = curl_exec($ch);
$httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

$httpAccessible = $httpStatus === 200 && strpos($contentType, 'image') === 0;

$results[] = [
    'step' => 5,
    'name' => 'PNG accessible via HTTP',
    'passed' => $httpAccessible,
    'details' => "HTTP $httpStatus",
    'message' => $httpAccessible ? "PNG accessible at $imageUrl" : "PNG not accessible (HTTP $httpStatus)"
];
echo ($httpAccessible ? '✓ PASS' : '✗ FAIL') . " - PNG HTTP accessibility (HTTP $httpStatus)\n";
echo "  URL: $imageUrl\n";
echo "  Content-Type: $contentType\n";
echo "  Size: " . strlen($imageData) . " bytes\n\n";

/**
 * Test Step 6: Verify PNG is a valid image
 */
echo "Step 6: Verifying PNG validity...\n";
$totalTests++;

// Try to load image with GD
$tempFile = sys_get_temp_dir() . '/e2e_test_' . $generatedHash . '.png';
file_put_contents($tempFile, $imageData);

$imageInfo = getimagesize($tempFile);
$validImage = $imageInfo !== false && $imageInfo[2] === IMAGETYPE_PNG;

$imageDimensions = null;
if ($validImage) {
    $imageDimensions = $imageInfo[0] . 'x' . $imageInfo[1];
}

unlink($tempFile);

$results[] = [
    'step' => 6,
    'name' => 'PNG is a valid image',
    'passed' => $validImage,
    'details' => $validImage ? $imageDimensions : 'Invalid image',
    'message' => $validImage ? "PNG is a valid image ($imageDimensions)" : 'PNG is not a valid image file'
];
echo ($validImage ? '✓ PASS' : '✗ FAIL') . " - PNG validity check\n";
if ($validImage) {
    echo "  Image type: PNG\n";
    echo "  Dimensions: $imageDimensions\n";
    echo "  MIME type: " . $imageInfo['mime'] . "\n";
}
echo "\n";

/**
 * Display final results
 */
displayResults($results, $totalTests);

/**
 * Function to display test results
 */
function displayResults($results, $total) {
    echo "======================================\n";
    echo "Test Results Summary\n";
    echo "======================================\n\n";

    $passed = count(array_filter($results, function($r) { return $r['passed']; }));

    foreach ($results as $result) {
        $status = $result['passed'] ? '✓ PASS' : '✗ FAIL';
        echo "{$status} - Step {$result['step']}: {$result['name']}\n";
        if (isset($result['details'])) {
            echo "       Details: {$result['details']}\n";
        }
        if (isset($result['message'])) {
            echo "       {$result['message']}\n";
        }
        echo "\n";
    }

    echo "======================================\n";
    echo "Total: $passed/$total tests passed";
    echo $passed === $total ? " ✓\n" : " ✗\n";
    echo "======================================\n";

    if ($passed === $total) {
        echo "\n✓ ALL E2E TESTS PASSED!\n";
        echo "Complete workflow verified successfully.\n";
        exit(0);
    } else {
        echo "\n✗ SOME TESTS FAILED\n";
        exit(1);
    }
}
?>
