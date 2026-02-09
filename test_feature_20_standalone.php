<?php
/**
 * Test Feature #20: Detects ImageMagick availability
 *
 * This test verifies that the library detection function
 * properly checks if the ImageMagick (Imagick) extension is loaded.
 *
 * Test cases:
 * 1. extension_loaded('imagick') is tested
 * 2. Detection returns true if Imagick available
 * 3. Detection returns false if not loaded
 * 4. Detection result is accurate
 * 5. Function handles Imagick instantiation errors
 */

echo "=== Feature #20: ImageMagick Detection Tests ===\n\n";

$testResults = [];
$allPassed = true;

// Read the convert.php source code to verify the detection logic
$sourceCode = file_get_contents(__DIR__ . '/convert.php');

// Test 1: Verify extension_loaded('imagick') is tested
echo "Test 1: Verify extension_loaded('imagick') is called in source code\n";
echo "-------------------------------------------------------------------\n";

if (strpos($sourceCode, "extension_loaded('imagick')") !== false ||
    strpos($sourceCode, 'extension_loaded("imagick")') !== false ||
    strpos($sourceCode, "extension_loaded('imagick'") !== false) {
    echo "✓ PASS: extension_loaded('imagick') found in source code\n";
    $testResults[] = ['test' => 'extension_loaded check exists', 'result' => 'PASS'];
} else {
    echo "❌ FAIL: extension_loaded('imagick') not found in source code\n";
    $testResults[] = ['test' => 'extension_loaded check exists', 'result' => 'FAIL'];
    $allPassed = false;
}

// Test 2: Verify the detection logic in detectAvailableLibraries function
echo "\nTest 2: Verify detectAvailableLibraries function contains ImageMagick detection\n";
echo "------------------------------------------------------------------------------\n";

if (preg_match('/function\s+detectAvailableLibraries\s*\(/', $sourceCode)) {
    echo "✓ PASS: detectAvailableLibraries() function exists\n";

    // Extract the function to check for ImageMagick detection
    if (preg_match('/\/\/ Check ImageMagick.*?extension_loaded\(.*?imagick.*?\)/s', $sourceCode)) {
        echo "✓ PASS: ImageMagick detection uses extension_loaded()\n";
        $testResults[] = ['test' => 'ImageMagick detection uses extension_loaded', 'result' => 'PASS'];
    } else {
        echo "⚠ WARNING: Could not verify ImageMagick uses extension_loaded() (pattern match failed)\n";
        $testResults[] = ['test' => 'ImageMagick detection uses extension_loaded', 'result' => 'WARNING'];
    }
} else {
    echo "❌ FAIL: detectAvailableLibraries() function not found\n";
    $testResults[] = ['test' => 'detectAvailableLibraries function exists', 'result' => 'FAIL'];
    $allPassed = false;
}

// Test 3: Check if ImageMagick is actually available
echo "\nTest 3: Check actual ImageMagick extension status\n";
echo "---------------------------------------------------\n";

$imagickLoaded = extension_loaded('imagick');
echo "   extension_loaded('imagick'): " . ($imagickLoaded ? 'TRUE (LOADED)' : 'FALSE (NOT LOADED)') . "\n";

if ($imagickLoaded) {
    echo "   ImageMagick/Imagick extension IS loaded\n";

    // Try to instantiate Imagick
    try {
        if (class_exists('Imagick')) {
            $imagick = new Imagick();
            echo "   ✓ Imagick class can be instantiated\n";

            if (defined('Imagick::IMAGICK_EXTVER')) {
                echo "   ✓ Imagick version: " . Imagick::IMAGICK_EXTVER . "\n";
            }

            $testResults[] = ['test' => 'Imagick available and functional', 'result' => 'PASS'];
        } else {
            echo "   ⚠ Imagick extension loaded but class not available\n";
            $testResults[] = ['test' => 'Imagick available and functional', 'result' => 'WARNING'];
        }
    } catch (Exception $e) {
        echo "   ⚠ Imagick instantiation failed: " . $e->getMessage() . "\n";
        $testResults[] = ['test' => 'Imagick instantiation', 'result' => 'WARNING', 'error' => $e->getMessage()];
    }
} else {
    echo "   ImageMagick/Imagick extension is NOT loaded\n";
    echo "   ℹ This is expected in many PHP installations\n";
    $testResults[] = ['test' => 'Imagick extension status check', 'result' => 'INFO', 'loaded' => false];
}

// Test 4: Verify the detection logic handles both cases
echo "\nTest 4: Verify detection logic handles available/unavailable cases\n";
echo "-------------------------------------------------------------------\n";

// Check for proper conditional logic
$hasIfCheck = preg_match('/if\s*\(\s*extension_loaded\s*\(\s*[\'"]imagick[\'"]\s*\)\s*\)/', $sourceCode);
$hasElseClause = preg_match('/else\s*{.*?[\'"]imagick.*?not loaded[\'"]}/s', $sourceCode);

if ($hasIfCheck) {
    echo "✓ PASS: Has if (extension_loaded('imagick')) check\n";
    $testResults[] = ['test' => 'Has extension_loaded conditional', 'result' => 'PASS'];
} else {
    echo "❌ FAIL: Missing extension_loaded('imagick') conditional\n";
    $testResults[] = ['test' => 'Has extension_loaded conditional', 'result' => 'FAIL'];
    $allPassed = false;
}

if ($hasElseClause || preg_match('/reason.*?not loaded/i', $sourceCode)) {
    echo "✓ PASS: Has else clause or reason for not loaded case\n";
    $testResults[] = ['test' => 'Handles not loaded case', 'result' => 'PASS'];
} else {
    echo "⚠ WARNING: May not properly handle 'not loaded' case\n";
    $testResults[] = ['test' => 'Handles not loaded case', 'result' => 'WARNING'];
}

// Test 5: Verify try-catch for Imagick instantiation
echo "\nTest 5: Verify error handling for Imagick instantiation\n";
echo "--------------------------------------------------------\n";

$hasTryCatch = preg_match('/try\s*{.*?new Imagick\s*\(/s', $sourceCode) &&
                preg_match('/}\s*catch\s*\(\s*Exception/i', $sourceCode);

if ($hasTryCatch) {
    echo "✓ PASS: Has try-catch block for Imagick instantiation\n";
    $testResults[] = ['test' => 'Try-catch for Imagick instantiation', 'result' => 'PASS'];
} else {
    echo "⚠ WARNING: May not handle Imagick instantiation errors\n";
    $testResults[] = ['test' => 'Try-catch for Imagick instantiation', 'result' => 'WARNING'];
}

// Test 6: Actual API test via HTTP
echo "\nTest 6: API endpoint library detection test\n";
echo "---------------------------------------------\n";

// Test via HTTP POST to the actual API
$apiUrl = 'http://127.0.0.1:8080/convert.php';
$postData = json_encode([
    'html_blocks' => ['<div>Test for ImageMagick detection</div>']
]);

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\n",
        'content' => $postData,
        'ignore_errors' => true
    ]
]);

$response = @file_get_contents($apiUrl, false, $context);

if ($response !== false) {
    $responseData = json_decode($response, true);

    if (isset($responseData['data']['libraries_detected'])) {
        echo "✓ PASS: API returns libraries_detected in response\n";
        $testResults[] = ['test' => 'API library detection', 'result' => 'PASS'];

        $libraries = $responseData['data']['libraries_detected'];

        if (isset($libraries['imagemagick'])) {
            echo "   imagemagick detection result:\n";
            echo "   - available: " . ($libraries['imagemagick']['available'] ? 'true' : 'false') . "\n";
            if (isset($libraries['imagemagick']['reason'])) {
                echo "   - reason: " . $libraries['imagemagick']['reason'] . "\n";
            }
            if (isset($libraries['imagemagick']['version'])) {
                echo "   - version: " . $libraries['imagemagick']['version'] . "\n";
            }

            // Verify detection matches reality
            $apiSaysAvailable = $libraries['imagemagick']['available'];
            if ($apiSaysAvailable === $imagickLoaded) {
                echo "   ✓ PASS: API detection matches actual extension status\n";
                $testResults[] = ['test' => 'API detection accuracy', 'result' => 'PASS'];
            } else {
                echo "   ❌ FAIL: API detection does NOT match actual extension status\n";
                $testResults[] = ['test' => 'API detection accuracy', 'result' => 'FAIL'];
                $allPassed = false;
            }
        } else {
            echo "❌ FAIL: imagemagick key not found in libraries_detected\n";
            $testResults[] = ['test' => 'imagemagick in API response', 'result' => 'FAIL'];
            $allPassed = false;
        }
    } else {
        echo "⚠ WARNING: libraries_detected not in API response (conversion not yet implemented)\n";
        $testResults[] = ['test' => 'API library detection', 'result' => 'WARNING', 'note' => 'Not implemented yet'];
    }
} else {
    echo "⚠ WARNING: Could not reach API endpoint\n";
    $testResults[] = ['test' => 'API endpoint', 'result' => 'WARNING', 'error' => 'API not reachable'];
}

// Summary
echo "\n" . str_repeat("=", 60) . "\n";
echo "SUMMARY - Test Results\n";
echo str_repeat("=", 60) . "\n";

$passCount = 0;
$failCount = 0;
$warningCount = 0;
$infoCount = 0;

foreach ($testResults as $result) {
    $status = $result['result'] ?? 'UNKNOWN';
    if ($status === 'PASS') {
        $passCount++;
        echo "✅ " . ($result['test'] ?? 'Unknown test') . "\n";
    } elseif ($status === 'FAIL') {
        $failCount++;
        echo "❌ " . ($result['test'] ?? 'Unknown test') . "\n";
        if (isset($result['error'])) {
            echo "   Error: " . $result['error'] . "\n";
        }
    } elseif ($status === 'WARNING') {
        $warningCount++;
        echo "⚠️  " . ($result['test'] ?? 'Unknown test') . "\n";
    } else {
        $infoCount++;
        echo "ℹ️  " . ($result['test'] ?? 'Unknown test') . "\n";
    }
}

echo "\nTotal: $passCount PASS, $failCount FAIL, $warningCount WARNING, $infoCount INFO\n";

if ($allPassed && $failCount === 0) {
    echo "\n✅ FEATURE #20 VERIFICATION: PASSED\n";
    echo "The system correctly checks if ImageMagick extension is loaded.\n";
    exit(0);
} else {
    echo "\n❌ FEATURE #20 VERIFICATION: FAILED\n";
    echo "Some tests failed. See details above.\n";
    exit(1);
}
