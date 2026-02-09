<?php
/**
 * Test Feature #13: Checks CSS file modification time
 *
 * This test verifies that filemtime() is checked to detect CSS changes
 */

echo "=== Feature #13: Checks CSS file modification time ===\n\n";

// Auto-detect port based on environment
// Inside Docker: use port 80, outside: use port 8080
$isInsideDocker = file_exists('/.dockerenv');
$port = $isInsideDocker ? '80' : '8080';
$baseUrl = "http://127.0.0.1:$port";
$endpoint = '/convert.php';
$url = $baseUrl . $endpoint;

echo "Running environment: " . ($isInsideDocker ? "Inside Docker" : "Host system") . "\n";
echo "Using base URL: $baseUrl\n\n";

// Test data
$htmlBlocks = ['<div class="test">Test HTML Block</div>'];
$cssUrl = $baseUrl . '/main.css';

echo "Test Configuration:\n";
echo "- Endpoint: $url\n";
echo "- CSS URL: $cssUrl\n\n";

$tests = [];

// Test 1: Load CSS for the first time (fresh load)
echo "Test 1: Load CSS for the first time (fresh load)\n";
echo "-------------------------------------------------\n";
$data = [
    'html_blocks' => $htmlBlocks,
    'css_url' => $cssUrl
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response1 = curl_exec($ch);
$httpCode1 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: $httpCode1\n";

$decoded1 = json_decode($response1, true);
$test1Pass = ($httpCode1 === 200) &&
            isset($decoded1['success']) &&
            $decoded1['success'] === true &&
            isset($decoded1['data']['css_cached']) &&
            $decoded1['data']['css_cached'] === false &&
            isset($decoded1['data']['css_fresh']) &&
            $decoded1['data']['css_fresh'] === true &&
            isset($decoded1['data']['css_cache_filemtime']);

if ($test1Pass) {
    echo "CSS loaded freshly (not from cache)\n";
    echo "Cache filemtime: " . $decoded1['data']['css_cache_filemtime_formatted'] . "\n";
    echo "Cache file path: " . ($decoded1['data']['css_cache_file_path'] ?? 'N/A') . "\n";

    // Store filemtime for comparison
    $firstFilemtime = $decoded1['data']['css_cache_filemtime'];
    $firstFilemtimeFormatted = $decoded1['data']['css_cache_filemtime_formatted'];
} else {
    echo "Failed to load CSS freshly\n";
    echo "Response: $response1\n";
}

$tests['First load - fresh CSS'] = $test1Pass;
echo "Result: " . ($test1Pass ? "✅ PASS" : "❌ FAIL") . "\n\n";

// Test 2: Load CSS again immediately (should use cache)
echo "Test 2: Load CSS again immediately (should use cache)\n";
echo "-----------------------------------------------------\n";

sleep(1); // Wait 1 second to ensure different timestamp

$data = [
    'html_blocks' => $htmlBlocks,
    'css_url' => $cssUrl
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response2 = curl_exec($ch);
$httpCode2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: $httpCode2\n";

$decoded2 = json_decode($response2, true);
$test2Pass = ($httpCode2 === 200) &&
            isset($decoded2['success']) &&
            $decoded2['success'] === true &&
            isset($decoded2['data']['css_cached']) &&
            $decoded2['data']['css_cached'] === true &&
            isset($decoded2['data']['css_cache_filemtime']);

if ($test2Pass) {
    echo "CSS loaded from cache\n";
    echo "Cache filemtime: " . $decoded2['data']['css_cache_filemtime_formatted'] . "\n";
    echo "Cache age: " . ($decoded2['data']['css_cache_age_formatted'] ?? 'N/A') . "\n";

    // Verify filemtime is the same as first load
    if (isset($firstFilemtime) && $decoded2['data']['css_cache_filemtime'] === $firstFilemtime) {
        echo "✓ filemtime matches first load (cache is working)\n";
        $test2Pass = $test2Pass && true;
    } else {
        echo "✗ filemtime does not match first load\n";
        echo "  First: $firstFilemtimeFormatted\n";
        echo "  Second: " . $decoded2['data']['css_cache_filemtime_formatted'] . "\n";
        $test2Pass = false;
    }
} else {
    echo "Failed to load CSS from cache\n";
    echo "Response: $response2\n";
}

$tests['Second load - uses cache'] = $test2Pass;
echo "Result: " . ($test2Pass ? "✅ PASS" : "❌ FAIL") . "\n\n";

// Test 3: Verify filemtime() is being checked
echo "Test 3: Verify filemtime() is being checked\n";
echo "--------------------------------------------\n";

// Get the cache file path directly
$cacheDir = __DIR__ . '/assets/media/rapidhtml2png/css_cache';
$cacheKey = md5($cssUrl);
$cacheFilePath = $cacheDir . '/' . $cacheKey . '.css';
$metadataFilePath = $cacheDir . '/' . $cacheKey . '.meta.json';

echo "Cache file path: $cacheFilePath\n";
echo "Metadata file path: $metadataFilePath\n";

$test3Pass = false;

if (file_exists($cacheFilePath)) {
    echo "✓ Cache file exists\n";

    // Get filemtime using PHP's filemtime() function
    $directFilemtime = filemtime($cacheFilePath);
    $directFilemtimeFormatted = date('Y-m-d H:i:s', $directFilemtime);

    echo "Direct filemtime() call: $directFilemtimeFormatted\n";

    // Compare with API response
    if (isset($decoded2['data']['css_cache_filemtime'])) {
        $apiFilemtime = $decoded2['data']['css_cache_filemtime'];
        $apiFilemtimeFormatted = $decoded2['data']['css_cache_filemtime_formatted'];

        echo "API filemtime: $apiFilemtimeFormatted\n";

        if ($directFilemtime === $apiFilemtime) {
            echo "✓ Direct filemtime() matches API response\n";
            $test3Pass = true;
        } else {
            echo "✗ Direct filemtime() does not match API response\n";
            $test3Pass = false;
        }
    }
} else {
    echo "✗ Cache file does not exist\n";
    $test3Pass = false;
}

$tests['filemtime() is checked'] = $test3Pass;
echo "Result: " . ($test3Pass ? "✅ PASS" : "❌ FAIL") . "\n\n";

// Test 4: Metadata file exists and contains cached_at timestamp
echo "Test 4: Metadata file contains cached_at timestamp\n";
echo "----------------------------------------------------\n";

$test4Pass = false;

if (file_exists($metadataFilePath)) {
    echo "✓ Metadata file exists\n";

    $metadataContent = file_get_contents($metadataFilePath);
    $metadata = json_decode($metadataContent, true);

    echo "Metadata content:\n";
    echo json_encode($metadata, JSON_PRETTY_PRINT) . "\n";

    if (isset($metadata['cached_at'])) {
        echo "✓ Metadata contains cached_at: " . date('Y-m-d H:i:s', $metadata['cached_at']) . "\n";
        $test4Pass = true;
    } else {
        echo "✗ Metadata does not contain cached_at\n";
        $test4Pass = false;
    }
} else {
    echo "✗ Metadata file does not exist\n";
    $test4Pass = false;
}

$tests['Metadata file with cached_at'] = $test4Pass;
echo "Result: " . ($test4Pass ? "✅ PASS" : "❌ FAIL") . "\n\n";

// Test 5: Simulate cache expiry by modifying cache file
echo "Test 5: Simulate cache expiry (old filemtime)\n";
echo "----------------------------------------------\n";

$test5Pass = false;

if (file_exists($cacheFilePath)) {
    // Get current filemtime
    $currentFilemtime = filemtime($cacheFilePath);
    echo "Current filemtime: " . date('Y-m-d H:i:s', $currentFilemtime) . "\n";

    // Set filemtime to 2 hours ago (beyond TTL of 1 hour)
    $oldTime = time() - 7200; // 2 hours ago
    if (touch($cacheFilePath, $oldTime)) {
        echo "✓ Modified cache file to be 2 hours old\n";
        echo "New filemtime: " . date('Y-m-d H:i:s', $oldTime) . "\n";

        // Now try to load CSS again - should fetch fresh copy
        sleep(1);

        $data = [
            'html_blocks' => $htmlBlocks,
            'css_url' => $cssUrl
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response5 = curl_exec($ch);
        $httpCode5 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded5 = json_decode($response5, true);

        echo "HTTP Status: $httpCode5\n";
        echo "CSS cached: " . ($decoded5['data']['css_cached'] ?? 'N/A') . "\n";
        echo "CSS fresh: " . ($decoded5['data']['css_fresh'] ?? 'N/A') . "\n";

        // Should load fresh because cache is too old
        $test5Pass = isset($decoded5['data']['css_fresh']) && $decoded5['data']['css_fresh'] === true;

        if ($test5Pass) {
            echo "✓ Cache was expired, fresh CSS loaded\n";
        } else {
            echo "✗ Cache should have been expired but wasn't\n";
        }
    } else {
        echo "✗ Failed to modify cache filemtime\n";
        $test5Pass = false;
    }
} else {
    echo "✗ Cache file does not exist\n";
    $test5Pass = false;
}

$tests['Cache expiry with old filemtime'] = $test5Pass;
echo "Result: " . ($test5Pass ? "✅ PASS" : "❌ FAIL") . "\n\n";

// Test 6: Load again with fresh cache (should use cache again)
echo "Test 6: Load again with fresh cache\n";
echo "------------------------------------\n";

sleep(1);

$data = [
    'html_blocks' => $htmlBlocks,
    'css_url' => $cssUrl
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response6 = curl_exec($ch);
$httpCode6 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$decoded6 = json_decode($response6, true);
$test6Pass = ($httpCode6 === 200) &&
            isset($decoded6['success']) &&
            $decoded6['success'] === true &&
            isset($decoded6['data']['css_cached']) &&
            $decoded6['data']['css_cached'] === true;

if ($test6Pass) {
    echo "✓ CSS loaded from cache after refresh\n";
    echo "Cache age: " . ($decoded6['data']['css_cache_age_formatted'] ?? 'N/A') . "\n";
} else {
    echo "✗ Failed to load from cache\n";
}

$tests['Cache used after refresh'] = $test6Pass;
echo "Result: " . ($test6Pass ? "✅ PASS" : "❌ FAIL") . "\n\n";

// Summary
echo "=== Test Summary ===\n";
$passCount = 0;
$failCount = 0;
foreach ($tests as $testName => $passed) {
    $status = $passed ? "✅ PASS" : "❌ FAIL";
    echo "$status: $testName\n";
    if ($passed) $passCount++;
    else $failCount++;
}

echo "\nTotal: " . count($tests) . " tests";
echo " | Passed: $passCount";
echo " | Failed: $failCount\n";

exit($failCount > 0 ? 1 : 0);
