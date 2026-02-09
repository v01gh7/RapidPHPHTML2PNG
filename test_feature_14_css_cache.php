<?php
/**
 * Feature #14: CSS Caching Verification Test
 *
 * This test verifies that CSS content is cached between requests.
 * Test steps:
 * 1. Make first request with specific css_url
 * 2. Verify CSS is loaded from source (cURL call made)
 * 3. Make second identical request immediately
 * 4. Verify CSS is retrieved from cache (no cURL call)
 * 5. Confirm cached content matches original CSS
 */

// Configuration
// Use container name for internal Docker networking
$baseUrl = 'http://rapidhtml2png-php/convert.php';
$testCssUrl = 'http://rapidhtml2png-php/main.css';

// Helper function to make POST request
function makePostRequest($url, $data) {
    $postData = json_encode($data);

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n" .
                        "Content-Length: " . strlen($postData) . "\r\n",
            'content' => $postData,
            'ignore_errors' => true // Allow reading response body even on error codes
        ]
    ]);

    $response = file_get_contents($url, false, $context);

    // Parse response headers
    $headers = [];
    foreach ($http_response_header as $header) {
        if (strpos($header, ':') !== false) {
            list($key, $value) = explode(':', $header, 2);
            $headers[trim($key)] = trim($value);
        }
    }

    // Decode JSON response
    $json = json_decode($response, true);

    return [
        'headers' => $headers,
        'body' => $json,
        'raw' => $response
    ];
}

// Helper function to get cache file path
function getCacheFilePath($cssUrl) {
    $cacheDir = __DIR__ . '/assets/media/rapidhtml2png/css_cache';
    $cacheKey = md5($cssUrl);
    return $cacheDir . '/' . $cacheKey . '.css';
}

// Helper function to clear CSS cache
function clearCssCache($cssUrl) {
    $cacheFile = getCacheFilePath($cssUrl);
    $metaFile = str_replace('.css', '.meta.json', $cacheFile);

    if (file_exists($cacheFile)) {
        unlink($cacheFile);
    }
    if (file_exists($metaFile)) {
        unlink($metaFile);
    }
}

// Helper function to check if cache file exists
function cacheExists($cssUrl) {
    $cacheFile = getCacheFilePath($cssUrl);
    return file_exists($cacheFile);
}

// Helper function to get cache file modification time
function getCacheFileMtime($cssUrl) {
    $cacheFile = getCacheFilePath($cssUrl);
    if (file_exists($cacheFile)) {
        return filemtime($cacheFile);
    }
    return null;
}

// Helper function to get cache file content
function getCacheContent($cssUrl) {
    $cacheFile = getCacheFilePath($cssUrl);
    if (file_exists($cacheFile)) {
        return file_get_contents($cacheFile);
    }
    return null;
}

echo "=== Feature #14: CSS Caching Verification Test ===\n\n";

// Test data
$htmlBlock = '<div class="test-content">Test Content for CSS Caching</div>';

// STEP 0: Clear cache before starting
echo "STEP 0: Clearing CSS cache before test...\n";
clearCssCache($testCssUrl);
if (cacheExists($testCssUrl)) {
    echo "❌ FAIL: Could not clear cache file\n";
    exit(1);
}
echo "✅ Cache cleared successfully\n\n";

sleep(1);

// STEP 1: Make first request with specific css_url
echo "STEP 1: Making first request with css_url...\n";
echo "CSS URL: $testCssUrl\n";

$request1Data = [
    'html_blocks' => [$htmlBlock],
    'css_url' => $testCssUrl
];

$beforeRequest1 = time();
$response1 = makePostRequest($baseUrl, $request1Data);
$afterRequest1 = time();

echo "Request completed in " . ($afterRequest1 - $beforeRequest1) . " second(s)\n";

// Verify first request was successful
if ($response1['body']['success'] !== true) {
    echo "❌ FAIL: First request was not successful\n";
    print_r($response1);
    exit(1);
}
echo "✅ First request successful\n";

// Verify CSS was loaded
if (!isset($response1['body']['data']['css_loaded']) || $response1['body']['data']['css_loaded'] !== true) {
    echo "❌ FAIL: CSS was not loaded in first request\n";
    print_r($response1);
    exit(1);
}
echo "✅ CSS was loaded\n";

// Verify CSS was NOT from cache (fresh load)
if (isset($response1['body']['data']['css_cached']) && $response1['body']['data']['css_cached'] === true) {
    echo "❌ FAIL: First request should not use cache (cache was cleared)\n";
    print_r($response1);
    exit(1);
}
if (!isset($response1['body']['data']['css_fresh']) || $response1['body']['data']['css_fresh'] !== true) {
    echo "❌ FAIL: First request should have css_fresh=true\n";
    print_r($response1);
    exit(1);
}
echo "✅ CSS was loaded from source (not from cache)\n";

// Verify cache file was created
if (!cacheExists($testCssUrl)) {
    echo "❌ FAIL: Cache file was not created after first request\n";
    exit(1);
}
echo "✅ Cache file was created\n";

// Get cache file info
$cacheMtime1 = getCacheFileMtime($testCssUrl);
$cacheContent1 = getCacheContent($testCssUrl);
echo "Cache file mtime: " . date('Y-m-d H:i:s', $cacheMtime1) . "\n";
echo "Cache file size: " . strlen($cacheContent1) . " bytes\n";
echo "Cache file path: " . getCacheFilePath($testCssUrl) . "\n";

// Store content length for comparison
$cssContentLength1 = $response1['body']['data']['css_content_length'];
echo "CSS content length: $cssContentLength1 bytes\n\n";

sleep(1);

// STEP 2: Make second identical request immediately
echo "STEP 2: Making second identical request (should use cache)...\n";

$request2Data = [
    'html_blocks' => [$htmlBlock],
    'css_url' => $testCssUrl
];

$beforeRequest2 = time();
$response2 = makePostRequest($baseUrl, $request2Data);
$afterRequest2 = time();

echo "Request completed in " . ($afterRequest2 - $beforeRequest2) . " second(s)\n";

// Verify second request was successful
if ($response2['body']['success'] !== true) {
    echo "❌ FAIL: Second request was not successful\n";
    print_r($response2);
    exit(1);
}
echo "✅ Second request successful\n";

// Verify CSS was loaded
if (!isset($response2['body']['data']['css_loaded']) || $response2['body']['data']['css_loaded'] !== true) {
    echo "❌ FAIL: CSS was not loaded in second request\n";
    print_r($response2);
    exit(1);
}
echo "✅ CSS was loaded\n";

// Verify CSS WAS from cache
if (!isset($response2['body']['data']['css_cached']) || $response2['body']['data']['css_cached'] !== true) {
    echo "❌ FAIL: Second request should use cache\n";
    echo "Expected css_cached=true, got: ";
    echo isset($response2['body']['data']['css_cached']) ? var_export($response2['body']['data']['css_cached'], true) : 'not set';
    echo "\n";
    print_r($response2['body']['data']);
    exit(1);
}
echo "✅ CSS was loaded from cache (css_cached=true)\n";

// Verify css_fresh is NOT set or is false
if (isset($response2['body']['data']['css_fresh']) && $response2['body']['data']['css_fresh'] === true) {
    echo "❌ FAIL: Second request should not have css_fresh=true\n";
    print_r($response2);
    exit(1);
}
echo "✅ CSS was not freshly loaded (no cURL call)\n";

// Get cache file info
$cacheMtime2 = getCacheFileMtime($testCssUrl);
echo "Cache file mtime: " . date('Y-m-d H:i:s', $cacheMtime2) . "\n";

// Verify cache file was not modified (same mtime)
if ($cacheMtime1 !== $cacheMtime2) {
    echo "❌ FAIL: Cache file mtime changed between requests\n";
    echo "First mtime: " . date('Y-m-d H:i:s', $cacheMtime1) . "\n";
    echo "Second mtime: " . date('Y-m-d H:i:s', $cacheMtime2) . "\n";
    exit(1);
}
echo "✅ Cache file was not modified (same mtime)\n\n";

// STEP 3: Verify cached content matches
echo "STEP 3: Verifying cached content matches original CSS...\n";

$cssContentLength2 = $response2['body']['data']['css_content_length'];
echo "First request CSS length: $cssContentLength1 bytes\n";
echo "Second request CSS length: $cssContentLength2 bytes\n";

if ($cssContentLength1 !== $cssContentLength2) {
    echo "❌ FAIL: CSS content length differs between requests\n";
    exit(1);
}
echo "✅ CSS content length matches\n";

// Verify content preview matches
$preview1 = $response1['body']['data']['css_preview'];
$preview2 = $response2['body']['data']['css_preview'];
if ($preview1 !== $preview2) {
    echo "❌ FAIL: CSS content preview differs between requests\n";
    echo "Preview 1: " . substr($preview1, 0, 100) . "\n";
    echo "Preview 2: " . substr($preview2, 0, 100) . "\n";
    exit(1);
}
echo "✅ CSS content preview matches\n";

// Verify cache file content is the same
$cacheContent2 = getCacheContent($testCssUrl);
if ($cacheContent1 !== $cacheContent2) {
    echo "❌ FAIL: Cache file content changed between requests\n";
    exit(1);
}
echo "✅ Cache file content matches\n\n";

// STEP 4: Verify cache age is reported correctly
echo "STEP 4: Verifying cache age reporting...\n";

if (!isset($response2['body']['data']['css_cache_age'])) {
    echo "❌ FAIL: Cache age not reported in second request\n";
    exit(1);
}
$cacheAge = $response2['body']['data']['css_cache_age'];
echo "Cache age: $cacheAge seconds\n";

// Cache age should be small (we made requests ~2 seconds apart)
if ($cacheAge < 0 || $cacheAge > 10) {
    echo "❌ FAIL: Cache age seems unreasonable (expected ~2 seconds, got $cacheAge)\n";
    exit(1);
}
echo "✅ Cache age is reasonable\n";

if (!isset($response2['body']['data']['css_cache_age_formatted'])) {
    echo "❌ FAIL: Cache age formatted not reported\n";
    exit(1);
}
echo "Cache age formatted: " . $response2['body']['data']['css_cache_age_formatted'] . "\n\n";

// STEP 5: Verify cache persistence across multiple requests
echo "STEP 5: Verifying cache persists across multiple requests...\n";

// Make third request
$response3 = makePostRequest($baseUrl, $request2Data);

if ($response3['body']['data']['css_cached'] !== true) {
    echo "❌ FAIL: Third request should also use cache\n";
    exit(1);
}
echo "✅ Third request also used cache\n";

// Verify cache file still has same mtime
$cacheMtime3 = getCacheFileMtime($testCssUrl);
if ($cacheMtime1 !== $cacheMtime3) {
    echo "❌ FAIL: Cache file mtime changed after third request\n";
    exit(1);
}
echo "✅ Cache file mtime unchanged after third request\n\n";

// FINAL SUMMARY
echo "=== ALL TESTS PASSED ✅ ===\n\n";
echo "Summary:\n";
echo "✅ STEP 1: First request loaded CSS from source (not cache)\n";
echo "✅ STEP 2: Second request loaded CSS from cache (no cURL call)\n";
echo "✅ STEP 3: Cached content matches original CSS\n";
echo "✅ STEP 4: Cache age is reported correctly\n";
echo "✅ STEP 5: Cache persists across multiple requests\n\n";
echo "Feature #14 verified: CSS content is cached between requests! 🎉\n";

// Cleanup: Clear cache after test
echo "\nCleaning up cache files...\n";
clearCssCache($testCssUrl);
echo "✅ Cache cleared\n";

exit(0);
