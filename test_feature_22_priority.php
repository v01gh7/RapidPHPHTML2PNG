<?php
/**
 * Feature #22: Prioritizes best available library
 *
 * This test verifies that the library detection system correctly prioritizes
 * rendering libraries in the order: wkhtmltoimage > ImageMagick > GD
 */

// Test Configuration
// Use environment variable or default to localhost for testing
$apiUrl = getenv('API_URL') ?: 'http://localhost:8080/convert.php';
$testResults = [];
$testNumber = 1;

// Color output for CLI
function colorOutput($text, $status = 'info') {
    $colors = [
        'success' => "\033[0;32m",
        'error' => "\033[0;31m",
        'info' => "\033[0;36m",
        'warning' => "\033[0;33m",
        'reset' => "\033[0m"
    ];
    return $colors[$status] . $text . $colors['reset'];
}

function runTest($testName, $condition, $details = '') {
    global $testResults, $testNumber;
    $status = $condition ? 'PASS' : 'FAIL';
    $icon = $condition ? '✓' : '✗';

    echo colorOutput("Test {$testNumber}: {$testName}", $condition ? 'success' : 'error') . PHP_EOL;
    echo "  Status: {$status}" . PHP_EOL;
    if ($details) {
        echo "  Details: {$details}" . PHP_EOL;
    }
    echo PHP_EOL;

    $testResults[] = [
        'name' => $testName,
        'passed' => $condition,
        'details' => $details
    ];

    $testNumber++;
    return $condition;
}

echo "========================================" . PHP_EOL;
echo "Feature #22: Library Priority Selection" . PHP_EOL;
echo "========================================" . PHP_EOL;
echo PHP_EOL;

// Test 1: Call API to get library detection results
echo "Fetching library detection results from API..." . PHP_EOL;
$testData = [
    'html_blocks' => ['<div>TEST_PRIORITY_22</div>'],
    'css_url' => ''
];

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($testData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, false);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($httpCode !== 200 || $curlError) {
    echo colorOutput("ERROR: Failed to connect to API", 'error') . PHP_EOL;
    echo "HTTP Code: {$httpCode}" . PHP_EOL;
    echo "cURL Error: {$curlError}" . PHP_EOL;
    exit(1);
}

$data = json_decode($response, true);
if (!$data || (!isset($data['library_detection']) && !isset($data['data']['library_detection']))) {
    echo colorOutput("ERROR: Invalid API response", 'error') . PHP_EOL;
    echo "Response: " . substr($response, 0, 200) . PHP_EOL;
    exit(1);
}

echo colorOutput("✓ API response received", 'success') . PHP_EOL;
echo PHP_EOL;

// Get detection results - handle both response structures
$detection = $data['library_detection'] ?? $data['data']['library_detection'] ?? null;
if (!$detection) {
    echo colorOutput("ERROR: library_detection not found in response", 'error') . PHP_EOL;
    exit(1);
}

$detectedLibs = $detection['detected_libraries'];
$bestLibrary = $detection['best_library'];

echo "Detected Libraries:" . PHP_EOL;
foreach ($detectedLibs as $name => $info) {
    $status = $info['available'] ? 'AVAILABLE' : 'NOT AVAILABLE';
    $color = $info['available'] ? 'success' : 'info';
    echo "  - {$name}: " . colorOutput($status, $color) . PHP_EOL;
    if ($info['available'] && isset($info['version'])) {
        echo "    Version: " . ($info['version'] ?? 'unknown') . PHP_EOL;
    }
    if (!$info['available'] && isset($info['reason'])) {
        echo "    Reason: " . $info['reason'] . PHP_EOL;
    }
}
echo PHP_EOL;

echo "Selected Best Library: " . colorOutput($bestLibrary ?? 'none', 'warning') . PHP_EOL;
echo PHP_EOL;

// Test 2: Verify priority order exists in response
runTest(
    "Response contains detected_libraries",
    isset($detection['detected_libraries']),
    "Found: " . (isset($detection['detected_libraries']) ? 'yes' : 'no')
);

// Test 3: Verify best_library is selected
runTest(
    "Best library is selected",
    !empty($bestLibrary),
    "Selected: " . ($bestLibrary ?? 'none')
);

// Test 4: Verify all three libraries are tested
$librariesTested = array_keys($detectedLibs);
$hasAllLibraries = in_array('wkhtmltoimage', $librariesTested) &&
                   in_array('imagemagick', $librariesTested) &&
                   in_array('gd', $librariesTested);

runTest(
    "All three libraries are tested",
    $hasAllLibraries,
    "Tested: " . implode(', ', $librariesTested)
);

// Test 5: Verify wkhtmltoimage has priority
// If wkhtmltoimage is available, it should be selected
$wkhtmlAvailable = $detectedLibs['wkhtmltoimage']['available'] ?? false;
$priorityCorrect = !$wkhtmlAvailable || $bestLibrary === 'wkhtmltoimage';

runTest(
    "wkhtmltoimage gets first priority",
    $priorityCorrect,
    $wkhtmlAvailable
        ? "wkhtmltoimage available and selected as best"
        : "wkhtmltoimage not available, next library selected"
);

// Test 6: Verify ImageMagick gets second priority
// If wkhtmltoimage is NOT available but ImageMagick IS, it should be selected
$imagickAvailable = $detectedLibs['imagemagick']['available'] ?? false;
$secondPriorityCorrect = true;

if (!$wkhtmlAvailable && $imagickAvailable) {
    $secondPriorityCorrect = ($bestLibrary === 'imagemagick');
} elseif (!$wkhtmlAvailable && !$imagickAvailable) {
    // Both unavailable - should select GD
    $secondPriorityCorrect = ($bestLibrary === 'gd');
}

runTest(
    "ImageMagick gets second priority",
    $secondPriorityCorrect,
    !$wkhtmlAvailable
        ? ($imagickAvailable
            ? "wkhtmltoimage unavailable, ImageMagick selected"
            : "Both wkhtmltoimage and ImageMagick unavailable, GD selected")
        : "wkhtmltoimage available (first priority)"
);

// Test 7: Verify GD is the fallback
$gdAvailable = $detectedLibs['gd']['available'] ?? false;
$fallbackCorrect = $gdAvailable && (!$wkhtmlAvailable && !$imagickAvailable ? $bestLibrary === 'gd' : true);

runTest(
    "GD is baseline fallback",
    $fallbackCorrect,
    $gdAvailable
        ? "GD available as fallback"
        : "GD not available (unexpected - should always be available)"
);

// Test 8: Verify at least one library is available
$anyAvailable = $wkhtmlAvailable || $imagickAvailable || $gdAvailable;

runTest(
    "At least one library is available",
    $anyAvailable,
    "Available: " . implode(', ', array_filter(array_keys($detectedLibs), function($lib) use ($detectedLibs) {
        return $detectedLibs[$lib]['available'] ?? false;
    }))
);

// Test 9: Verify priority order matches specification
// Specification: wkhtmltoimage > ImageMagick > GD
$priorityMatchesSpec = true;
$priorityOrder = ['wkhtmltoimage', 'imagemagick', 'gd'];

if ($wkhtmlAvailable) {
    $priorityMatchesSpec = ($bestLibrary === 'wkhtmltoimage');
} elseif ($imagickAvailable) {
    $priorityMatchesSpec = ($bestLibrary === 'imagemagick');
} elseif ($gdAvailable) {
    $priorityMatchesSpec = ($bestLibrary === 'gd');
}

runTest(
    "Priority order: wkhtmltoimage > ImageMagick > GD",
    $priorityMatchesSpec,
    "Specification order: " . implode(' > ', $priorityOrder) .
    ", Selected: " . $bestLibrary
);

// Test 10: Verify library_detection field includes selection details
$hasSelectionDetails = isset($detection['best_library']) &&
                       isset($detection['available']);

runTest(
    "Library detection includes selection details",
    $hasSelectionDetails,
    "best_library: " . ($detection['best_library'] ?? 'missing') .
    ", available: " . ($detection['available'] ? 'yes' : 'no')
);

// Summary
echo "========================================" . PHP_EOL;
echo "Test Summary" . PHP_EOL;
echo "========================================" . PHP_EOL;

$totalTests = count($testResults);
$passedTests = count(array_filter($testResults, function($t) { return $t['passed']; }));
$failedTests = $totalTests - $passedTests;
$percentage = round(($passedTests / $totalTests) * 100, 1);

echo "Total Tests: {$totalTests}" . PHP_EOL;
echo colorOutput("Passed: {$passedTests}", 'success') . PHP_EOL;
echo colorOutput("Failed: {$failedTests}", $failedTests > 0 ? 'error' : 'info') . PHP_EOL;
echo "Success Rate: {$percentage}%" . PHP_EOL;
echo PHP_EOL;

if ($failedTests > 0) {
    echo colorOutput("FAILED TESTS:", 'error') . PHP_EOL;
    foreach ($testResults as $result) {
        if (!$result['passed']) {
            echo "  - {$result['name']}" . PHP_EOL;
            if ($result['details']) {
                echo "    {$result['details']}" . PHP_EOL;
            }
        }
    }
    echo PHP_EOL;
    exit(1);
}

echo colorOutput("✓ ALL TESTS PASSED!", 'success') . PHP_EOL;
echo PHP_EOL;
echo "Feature #22 verified: Library priority selection works correctly" . PHP_EOL;
echo PHP_EOL;

exit(0);
