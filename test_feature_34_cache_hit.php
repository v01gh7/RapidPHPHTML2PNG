<?php
/**
 * Feature #34: Returns cached file if hash unchanged
 *
 * This test verifies that when the same content is requested multiple times,
 * the cached PNG file is returned without re-rendering.
 */

// Test configuration
$apiUrl = 'http://localhost:8080/convert.php';
$testContent = 'CACHE_TEST_' . time() . '_34';
$cssUrl = 'http://172.19.0.2/main.css';

echo "=== Feature #34: Cache Hit Test ===\n\n";

// Step 1: First request - should create new file
echo "Step 1: Making first request (expecting cache miss)...\n";
$firstRequestData = [
    'html_blocks' => ["<div class=\"styled-element\">$testContent</div>"],
    'css_url' => $cssUrl
];

$firstResponse = makeApiRequest($apiUrl, $firstRequestData);

if (!$firstResponse['success']) {
    echo "❌ FAILED: First request failed\n";
    print_r($firstResponse);
    exit(1);
}

echo "✅ First request succeeded\n";
echo "   - Cached: " . ($firstResponse['data']['rendering']['cached'] ? 'YES' : 'NO') . " (expected: NO)\n";
echo "   - Output file: " . $firstResponse['data']['rendering']['output_file'] . "\n";
echo "   - File size: " . $firstResponse['data']['rendering']['file_size'] . " bytes\n";
echo "   - Engine: " . $firstResponse['data']['rendering']['engine'] . "\n";

$firstOutputFile = $firstResponse['data']['rendering']['output_file'];
$firstFileSize = $firstResponse['data']['rendering']['file_size'];

if ($firstResponse['data']['rendering']['cached']) {
    echo "❌ FAILED: First request should NOT be cached\n";
    exit(1);
}

// Step 2: Get file modification time
echo "\nStep 2: Getting file modification time...\n";
$firstMtime = getFileModificationTime($firstOutputFile);
if ($firstMtime === null) {
    echo "❌ FAILED: Could not get file modification time\n";
    exit(1);
}
echo "✅ File modification time: " . date('Y-m-d H:i:s', $firstMtime) . "\n";

// Step 3: Wait a moment to ensure timestamp difference would be detectable
echo "\nStep 3: Waiting 1 second to ensure timestamp difference...\n";
sleep(1);

// Step 4: Second identical request - should return cached file
echo "\nStep 4: Making second identical request (expecting cache hit)...\n";
$secondResponse = makeApiRequest($apiUrl, $firstRequestData);

if (!$secondResponse['success']) {
    echo "❌ FAILED: Second request failed\n";
    print_r($secondResponse);
    exit(1);
}

echo "✅ Second request succeeded\n";
echo "   - Cached: " . ($secondResponse['data']['rendering']['cached'] ? 'YES' : 'NO') . " (expected: YES)\n";
echo "   - Output file: " . $secondResponse['data']['rendering']['output_file'] . "\n";
echo "   - File size: " . $secondResponse['data']['rendering']['file_size'] . " bytes\n";
echo "   - Engine: " . $secondResponse['data']['rendering']['engine'] . "\n";

$secondOutputFile = $secondResponse['data']['rendering']['output_file'];
$secondFileSize = $secondResponse['data']['rendering']['file_size'];

// Step 5: Verify cache hit
echo "\nStep 5: Verifying cache hit behavior...\n";

// Check 5.1: Response should indicate cached
if (!$secondResponse['data']['rendering']['cached']) {
    echo "❌ FAILED: Second request should be cached\n";
    exit(1);
}
echo "✅ Check 5.1: Second response correctly indicates cached=true\n";

// Check 5.2: Same file path
if ($firstOutputFile !== $secondOutputFile) {
    echo "❌ FAILED: File paths should be identical\n";
    echo "   First:  $firstOutputFile\n";
    echo "   Second: $secondOutputFile\n";
    exit(1);
}
echo "✅ Check 5.2: File paths are identical\n";

// Check 5.3: Same file size
if ($firstFileSize !== $secondFileSize) {
    echo "❌ FAILED: File sizes should be identical\n";
    echo "   First:  $firstFileSize bytes\n";
    echo "   Second: $secondFileSize bytes\n";
    exit(1);
}
echo "✅ Check 5.3: File sizes are identical\n";

// Check 5.4: File modification time unchanged (no re-rendering)
$secondMtime = getFileModificationTime($secondOutputFile);
if ($secondMtime === null) {
    echo "❌ FAILED: Could not get second file modification time\n";
    exit(1);
}

if ($firstMtime !== $secondMtime) {
    echo "❌ FAILED: File modification time changed (file was re-rendered)\n";
    echo "   First:  " . date('Y-m-d H:i:s', $firstMtime) . "\n";
    echo "   Second: " . date('Y-m-d H:i:s', $secondMtime) . "\n";
    exit(1);
}
echo "✅ Check 5.4: File modification time unchanged (no re-rendering occurred)\n";

// Check 5.5: Same content hash
$firstHash = $firstResponse['data']['content_hash'];
$secondHash = $secondResponse['data']['content_hash'];
if ($firstHash !== $secondHash) {
    echo "❌ FAILED: Content hashes should be identical\n";
    echo "   First:  $firstHash\n";
    echo "   Second: $secondHash\n";
    exit(1);
}
echo "✅ Check 5.5: Content hashes are identical\n";

// Step 6: Third request with different content - should create new file
echo "\nStep 6: Making third request with different content (expecting cache miss)...\n";
$differentContent = $testContent . '_DIFFERENT';
$thirdRequestData = [
    'html_blocks' => ["<div class=\"styled-element\">$differentContent</div>"],
    'css_url' => $cssUrl
];

$thirdResponse = makeApiRequest($apiUrl, $thirdRequestData);

if (!$thirdResponse['success']) {
    echo "❌ FAILED: Third request failed\n";
    print_r($thirdResponse);
    exit(1);
}

echo "✅ Third request succeeded\n";
echo "   - Cached: " . ($thirdResponse['data']['rendering']['cached'] ? 'YES' : 'NO') . " (expected: NO)\n";
echo "   - Output file: " . $thirdResponse['data']['rendering']['output_file'] . "\n";
echo "   - File size: " . $thirdResponse['data']['rendering']['file_size'] . " bytes\n";

if ($thirdResponse['data']['rendering']['cached']) {
    echo "❌ FAILED: Third request with different content should NOT be cached\n";
    exit(1);
}

$thirdOutputFile = $thirdResponse['data']['rendering']['output_file'];

if ($firstOutputFile === $thirdOutputFile) {
    echo "❌ FAILED: Different content should produce different file\n";
    exit(1);
}

echo "✅ Different content produces different file (as expected)\n";

// Summary
echo "\n=== Test Summary ===\n";
echo "✅ All 6 checks passed!\n";
echo "1. ✅ First request creates new file (cache miss)\n";
echo "2. ✅ File modification time captured\n";
echo "3. ✅ Second identical request returns cached file\n";
echo "4. ✅ Cache response indicates cached=true\n";
echo "5. ✅ Same file path returned for identical content\n";
echo "6. ✅ File modification time unchanged (no re-rendering)\n";
echo "7. ✅ Different content creates different file\n";
echo "\nFeature #34: CACHE HIT BEHAVIOR VERIFIED ✅\n";

/**
 * Make API request to convert.php
 */
function makeApiRequest($url, $data) {
    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return [
            'success' => false,
            'error' => 'cURL error: ' . $curlError
        ];
    }

    $decoded = json_decode($response, true);

    if ($decoded === null) {
        return [
            'success' => false,
            'error' => 'JSON decode failed',
            'http_code' => $httpCode,
            'response' => substr($response, 0, 500)
        ];
    }

    return $decoded;
}

/**
 * Get file modification time from container path
 */
function getFileModificationTime($containerPath) {
    // Convert container path to host path
    // Container: /var/www/html/assets/media/rapidhtml2png/{hash}.png
    // Host: D:\_DEV_\SelfProjects\RapidHTML2PNG\assets\media\rapidhtml2png\{hash}.png

    $hostPath = str_replace('/var/www/html/', 'D:\\_DEV_\\SelfProjects\\RapidHTML2PNG\\', $containerPath);
    $hostPath = str_replace('/', '\\', $hostPath);

    if (!file_exists($hostPath)) {
        echo "   Note: Host path not found: $hostPath\n";
        return null;
    }

    return filemtime($hostPath);
}
