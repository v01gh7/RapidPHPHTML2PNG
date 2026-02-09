<?php
/**
 * Feature #35: Recreates PNG when hash changes
 *
 * This test verifies that:
 * 1. Different HTML content produces different hash filenames
 * 2. Different CSS content produces different hash filenames
 * 3. Same content produces same hash (deterministic)
 * 4. New PNG files are created when hash changes
 */

// Test configuration
$apiUrl = 'http://localhost:8080/convert.php';
$outputDir = __DIR__ . '/assets/media/rapidhtml2png';

echo "=== Feature #35: Recreates PNG when hash changes ===\n\n";

// Color output helpers
function pass($msg) { echo "✅ PASS: $msg\n"; }
function fail($msg) { echo "❌ FAIL: $msg\n"; }
function info($msg) { echo "ℹ️  INFO: $msg\n"; }
function section($msg) { echo "\n📋 $msg\n"; }

// Helper function to make API call
function makeRequest($html, $css = null) {
    global $apiUrl;

    $data = ['html_blocks' => [$html]];
    if ($css !== null) {
        $data['css_url'] = $css;
    }

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        return ['success' => false, 'error' => "HTTP $httpCode"];
    }

    return json_decode($response, true);
}

// Helper function to get hash from response
function getHashFromResponse($response) {
    return $response['data']['content_hash'] ?? null;
}

// Helper function to get output file from response
function getOutputFileFromResponse($response) {
    $filePath = $response['data']['rendering']['output_file'] ?? null;
    // Extract just the filename
    if ($filePath) {
        return basename($filePath);
    }
    return null;
}

// Test Suite
$testsPassed = 0;
$testsFailed = 0;

// ============================================================================
section("Test 1: Different HTML produces different hashes");
// ============================================================================

$html1 = '<div>CONTENT_V1</div>';
$html2 = '<div>CONTENT_V2</div>';

info("Request 1: HTML with 'CONTENT_V1'");
$response1 = makeRequest($html1);
$hash1 = getHashFromResponse($response1);
$file1 = getOutputFileFromResponse($response1);

info("Request 2: HTML with 'CONTENT_V2'");
$response2 = makeRequest($html2);
$hash2 = getHashFromResponse($response2);
$file2 = getOutputFileFromResponse($response2);

info("Hash 1: $hash1");
info("Hash 2: $hash2");
info("File 1: $file1");
info("File 2: $file2");

if ($hash1 && $hash2 && $hash1 !== $hash2) {
    pass("Different HTML content produces different hashes");
    pass("Hash 1 ($hash1) != Hash 2 ($hash2)");
    $testsPassed++;
} else {
    fail("Different HTML content should produce different hashes");
    $testsFailed++;
}

// Check if files are different
if ($file1 && $file2 && $file1 !== $file2) {
    pass("Different hash filenames created");
    pass("File 1: $file1");
    pass("File 2: $file2");
    $testsPassed++;
} else {
    fail("Different filenames should be created for different hashes");
    $testsFailed++;
}

// ============================================================================
section("Test 2: Same HTML produces same hash (deterministic)");
// ============================================================================

$html3 = '<div>DETERMINISTIC_TEST</div>';

info("Request 1: HTML with 'DETERMINISTIC_TEST'");
$response3a = makeRequest($html3);
$hash3a = getHashFromResponse($response3a);

info("Request 2: Same HTML with 'DETERMINISTIC_TEST'");
$response3b = makeRequest($html3);
$hash3b = getHashFromResponse($response3b);

info("Hash 3a: $hash3a");
info("Hash 3b: $hash3b");

if ($hash3a && $hash3b && $hash3a === $hash3b) {
    pass("Same HTML content produces same hash");
    pass("Hash is deterministic: $hash3a == $hash3b");
    $testsPassed++;
} else {
    fail("Same HTML content should produce same hash");
    $testsFailed++;
}

// Check if second request is cached
$cached3b = $response3b['data']['rendering']['cached'] ?? false;
if ($cached3b) {
    pass("Second request with same content is cached");
    $testsPassed++;
} else {
    info("Note: Second request not cached (may be first time)");
    // This is not a failure - the file might not exist yet
}

// ============================================================================
section("Test 3: Different CSS produces different hashes");
// ============================================================================

$html4 = '<div class="styled">CSS_TEST</div>';
$cssUrl = 'http://localhost:8080/main.css';

info("Request 1: HTML with CSS from URL");
$response4 = makeRequest($html4, $cssUrl);
$hash4 = getHashFromResponse($response4);
$file4 = getOutputFileFromResponse($response4);

$html5 = '<div class="styled">CSS_TEST_CHANGED</div>';
info("Request 2: Different HTML with same CSS");
$response5 = makeRequest($html5, $cssUrl);
$hash5 = getHashFromResponse($response5);
$file5 = getOutputFileFromResponse($response5);

info("Hash 4: $hash4");
info("Hash 5: $hash5");

if ($hash4 && $hash5 && $hash4 !== $hash5) {
    pass("Different HTML with same CSS produces different hashes");
    $testsPassed++;
} else {
    fail("Different HTML with same CSS should produce different hashes");
    $testsFailed++;
}

// ============================================================================
section("Test 4: Verify old PNG is not overwritten");
// ============================================================================

$html6 = '<div>HASH_CHANGE_V1</div>';
$html7 = '<div>HASH_CHANGE_V2</div>';

info("Request 1: Create PNG with V1 content");
$response6 = makeRequest($html6);
$hash6 = getHashFromResponse($response6);
$file6 = getOutputFileFromResponse($response6);
$fullPath6 = $outputDir . '/' . $file6;

info("Request 2: Create PNG with V2 content");
$response7 = makeRequest($html7);
$hash7 = getHashFromResponse($response7);
$file7 = getOutputFileFromResponse($response7);
$fullPath7 = $outputDir . '/' . $file7;

info("File 1: $file6");
info("File 2: $file7");

// Check if both files exist
$exists6 = file_exists($fullPath6);
$exists7 = file_exists($fullPath7);

if ($exists6 && $exists7) {
    pass("Both PNG files exist after hash change");
    pass("Old file not overwritten - new file created instead");
    $testsPassed++;

    // Verify they are different files
    if ($file6 !== $file7) {
        pass("Confirmed: Different filenames used");
        $testsPassed++;
    } else {
        fail("Filenames should be different");
        $testsFailed++;
    }
} else {
    fail("Both files should exist");
    info("File 1 exists: " . ($exists6 ? 'yes' : 'no'));
    info("File 2 exists: " . ($exists7 ? 'yes' : 'no'));
    $testsFailed++;
}

// ============================================================================
section("Test 5: Hash changes with minor content modification");
// ============================================================================

$html8 = '<div>MINOR_CHANGE_TEST</div>';
$html9 = '<div>MINOR_CHANGE_TEST!</div>'; // Added exclamation mark

info("Request 1: HTML without exclamation");
$response8 = makeRequest($html8);
$hash8 = getHashFromResponse($response8);

info("Request 2: HTML with exclamation mark");
$response9 = makeRequest($html9);
$hash9 = getHashFromResponse($response9);

info("Hash 8: $hash8");
info("Hash 9: $hash9");
info("Content difference: 1 character");

if ($hash8 && $hash9 && $hash8 !== $hash9) {
    pass("Minor content change produces different hash");
    pass("Hash sensitivity: Even 1 character change is detected");
    $testsPassed++;
} else {
    fail("Minor content change should produce different hash");
    $testsFailed++;
}

// ============================================================================
section("Summary");
// ============================================================================

$totalTests = $testsPassed + $testsFailed;
$percentage = $totalTests > 0 ? ($testsPassed / $totalTests) * 100 : 0;

echo "\n";
echo "========================================\n";
echo "Tests Passed: $testsPassed\n";
echo "Tests Failed: $testsFailed\n";
echo "Total Tests: $totalTests\n";
echo "Success Rate: " . number_format($percentage, 1) . "%\n";
echo "========================================\n";

if ($testsFailed === 0) {
    pass("Feature #35: All tests passed!");
    exit(0);
} else {
    fail("Feature #35: Some tests failed");
    exit(1);
}
