<?php
/**
 * Test Feature #43: Handles concurrent requests
 *
 * This test sends multiple simultaneous POST requests to verify:
 * 1. All requests complete without errors
 * 2. All PNGs are created correctly
 * 3. Response times are reasonable
 * 4. No race conditions or file conflicts occur
 */

define('TEST_MODE', true);

// Test configuration
$baseUrl = 'http://localhost:8080/convert.php';
$concurrentRequests = 5;
$testHtmlTemplates = [
    '<div style="color: red;">Concurrent Test 1</div>',
    '<div style="color: blue;">Concurrent Test 2</div>',
    '<div style="color: green;">Concurrent Test 3</div>',
    '<div style="color: purple;">Concurrent Test 4</div>',
    '<div style="color: orange;">Concurrent Test 5</div>'
];

echo "==========================================\n";
echo "Feature #43: Concurrent Request Handling\n";
echo "==========================================\n\n";

echo "Test Configuration:\n";
echo "  Base URL: $baseUrl\n";
echo "  Concurrent Requests: $concurrentRequests\n";
echo "  Test Templates: " . count($testHtmlTemplates) . "\n\n";

// Function to send a single conversion request
function sendConversionRequest($url, $htmlContent, $requestId) {
    $startTime = microtime(true);

    $data = [
        'html_blocks' => [$htmlContent],
        'css_url' => null
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    $endTime = microtime(true);
    $duration = round(($endTime - $startTime) * 1000, 2); // in milliseconds

    return [
        'request_id' => $requestId,
        'html_content' => $htmlContent,
        'http_code' => $httpCode,
        'response' => $response,
        'error' => $error,
        'duration_ms' => $duration,
        'success' => $httpCode === 200 && $response !== false
    ];
}

// Test 1: Sequential requests (baseline)
echo "Test 1: Sequential Requests (Baseline)\n";
echo "------------------------------------------\n";

$sequentialResults = [];
$sequentialStart = microtime(true);

foreach ($testHtmlTemplates as $index => $html) {
    $result = sendConversionRequest($baseUrl, $html, $index + 1);
    $sequentialResults[] = $result;
    echo "  Request {$result['request_id']}: HTTP {$result['http_code']}, {$result['duration_ms']}ms\n";
}

$sequentialTotal = round((microtime(true) - $sequentialStart) * 1000, 2);
echo "  Total Time: {$sequentialTotal}ms\n";
echo "  Average: " . round($sequentialTotal / count($sequentialResults), 2) . "ms per request\n\n";

// Verify all sequential requests succeeded
$sequentialSuccess = count(array_filter($sequentialResults, fn($r) => $r['success']));
if ($sequentialSuccess === count($sequentialResults)) {
    echo "  ✓ All $sequentialSuccess sequential requests succeeded\n\n";
} else {
    echo "  ✗ Only $sequentialSuccess/" . count($sequentialResults) . " sequential requests succeeded\n\n";
}

// Test 2: Concurrent requests using curl_multi
echo "Test 2: Concurrent Requests (curl_multi)\n";
echo "------------------------------------------\n";

$concurrentStart = microtime(true);

// Create multiple cURL handles
$multiHandle = curl_multi_init();
$handles = [];

foreach ($testHtmlTemplates as $index => $html) {
    $data = [
        'html_blocks' => [$html],
        'css_url' => null
    ];

    $ch = curl_init($baseUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $handles[$index] = [
        'handle' => $ch,
        'request_id' => $index + 1,
        'html_content' => $html,
        'start_time' => microtime(true)
    ];

    curl_multi_add_handle($multiHandle, $ch);
}

// Execute all handles concurrently
$active = null;
do {
    $status = curl_multi_exec($multiHandle, $active);
    curl_multi_select($multiHandle);
} while ($active && $status == CURLM_OK);

// Collect results
$concurrentResults = [];
foreach ($handles as $index => $handleInfo) {
    $ch = $handleInfo['handle'];
    $response = curl_multi_getcontent($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    $endTime = microtime(true);
    $duration = round(($endTime - $handleInfo['start_time']) * 1000, 2);

    $concurrentResults[] = [
        'request_id' => $handleInfo['request_id'],
        'html_content' => $handleInfo['html_content'],
        'http_code' => $httpCode,
        'response' => $response,
        'error' => $error,
        'duration_ms' => $duration,
        'success' => $httpCode === 200 && $response !== false
    ];

    echo "  Request {$handleInfo['request_id']}: HTTP {$httpCode}, {$duration}ms\n";

    curl_multi_remove_handle($multiHandle, $ch);
    curl_close($ch);
}

curl_multi_close($multiHandle);

$concurrentTotal = round((microtime(true) - $concurrentStart) * 1000, 2);
echo "  Total Time: {$concurrentTotal}ms\n";
echo "  Average: " . round($concurrentTotal / count($concurrentResults), 2) . "ms per request\n\n";

// Verify all concurrent requests succeeded
$concurrentSuccess = count(array_filter($concurrentResults, fn($r) => $r['success']));
if ($concurrentSuccess === count($concurrentResults)) {
    echo "  ✓ All $concurrentSuccess concurrent requests succeeded\n\n";
} else {
    echo "  ✗ Only $concurrentSuccess/" . count($concurrentResults) . " concurrent requests succeeded\n\n";
}

// Test 3: Verify all PNG files were created correctly
echo "Test 3: Verify PNG Files Created\n";
echo "------------------------------------------\n";

$filesVerified = 0;
$uniqueHashes = [];

foreach ($concurrentResults as $result) {
    if ($result['success']) {
        $response = json_decode($result['response'], true);
        if (isset($response['data']['rendering']['output_file'])) {
            $outputFile = $response['data']['rendering']['output_file'];

            // Extract hash from filename
            $hash = basename($outputFile, '.png');
            $uniqueHashes[] = $hash;

            if (file_exists($outputFile)) {
                $fileSize = filesize($outputFile);
                echo "  ✓ File {$hash}.png exists ({$fileSize} bytes)\n";
                $filesVerified++;
            } else {
                echo "  ✗ File {$hash}.png does not exist\n";
            }
        }
    }
}

echo "\n";

// Test 4: Check for unique hashes (no conflicts)
echo "Test 4: Check for Hash Collisions\n";
echo "------------------------------------------\n";

$uniqueHashCount = count(array_unique($uniqueHashes));
$totalHashes = count($uniqueHashes);

if ($uniqueHashCount === $totalHashes) {
    echo "  ✓ All $totalHashes hashes are unique (no collisions)\n\n";
} else {
    echo "  ✗ Hash collision detected! Only $uniqueHashCount unique hashes out of $totalHashes\n";
    echo "  This indicates a race condition in hash generation\n\n";
}

// Test 5: Performance comparison
echo "Test 5: Performance Comparison\n";
echo "------------------------------------------\n";

$speedup = round($sequentialTotal / $concurrentTotal, 2);
echo "  Sequential Total: {$sequentialTotal}ms\n";
echo "  Concurrent Total: {$concurrentTotal}ms\n";
echo "  Speedup: {$speedup}x\n\n";

if ($speedup > 1) {
    echo "  ✓ Concurrent requests are faster than sequential\n\n";
} else {
    echo "  ⚠ No performance benefit from concurrency (possibly single-threaded)\n\n";
}

// Test 6: Response time consistency
echo "Test 6: Response Time Analysis\n";
echo "------------------------------------------\n";

$durations = array_map(fn($r) => $r['duration_ms'], $concurrentResults);
$minDuration = min($durations);
$maxDuration = max($durations);
$avgDuration = round(array_sum($durations) / count($durations), 2);
$variance = round($maxDuration - $minDuration, 2);

echo "  Min: {$minDuration}ms\n";
echo "  Max: {$maxDuration}ms\n";
echo "  Average: {$avgDuration}ms\n";
echo "  Variance: {$variance}ms\n\n";

if ($variance < $avgDuration * 0.5) {
    echo "  ✓ Response times are consistent (low variance)\n\n";
} else {
    echo "  ⚠ High variance in response times (possible resource contention)\n\n";
}

// Final Summary
echo "==========================================\n";
echo "Test Summary\n";
echo "==========================================\n";

$allTestsPassed = true;

// Test 1: Sequential baseline
if ($sequentialSuccess === count($sequentialResults)) {
    echo "✓ Test 1: Sequential requests - PASSED\n";
} else {
    echo "✗ Test 1: Sequential requests - FAILED\n";
    $allTestsPassed = false;
}

// Test 2: Concurrent requests
if ($concurrentSuccess === count($concurrentResults)) {
    echo "✓ Test 2: Concurrent requests - PASSED\n";
} else {
    echo "✗ Test 2: Concurrent requests - FAILED\n";
    $allTestsPassed = false;
}

// Test 3: File creation
if ($filesVerified === count($concurrentResults)) {
    echo "✓ Test 3: All PNG files created - PASSED\n";
} else {
    echo "✗ Test 3: All PNG files created - FAILED ($filesVerified/" . count($concurrentResults) . ")\n";
    $allTestsPassed = false;
}

// Test 4: Hash uniqueness
if ($uniqueHashCount === $totalHashes) {
    echo "✓ Test 4: No hash collisions - PASSED\n";
} else {
    echo "✗ Test 4: No hash collisions - FAILED\n";
    $allTestsPassed = false;
}

// Test 5: Performance
if ($speedup > 0.8) { // Allow some tolerance
    echo "✓ Test 5: Performance is reasonable - PASSED\n";
} else {
    echo "✗ Test 5: Performance is reasonable - FAILED\n";
    $allTestsPassed = false;
}

// Test 6: Response time consistency
if ($variance < $avgDuration * 2) { // Allow 2x variance
    echo "✓ Test 6: Response times consistent - PASSED\n";
} else {
    echo "✗ Test 6: Response times consistent - FAILED\n";
    $allTestsPassed = false;
}

echo "\n";

if ($allTestsPassed) {
    echo "✓ ALL TESTS PASSED - Feature #43 verified\n";
    exit(0);
} else {
    echo "✗ SOME TESTS FAILED - Feature #43 not fully verified\n";
    exit(1);
}
