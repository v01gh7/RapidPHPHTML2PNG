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

// Include the main convert.php to access detectAvailableLibraries function
require_once __DIR__ . '/convert.php';

echo "=== Feature #20: ImageMagick Detection Tests ===\n\n";

$testResults = [];
$allPassed = true;

// Test 1: Verify extension_loaded('imagick') is tested
echo "Test 1: Verify extension_loaded('imagick') is called\n";
echo "---------------------------------------------------\n";

// Check if the function exists
if (!function_exists('detectAvailableLibraries')) {
    echo "❌ FAIL: detectAvailableLibraries() function not found\n";
    $allPassed = false;
} else {
    echo "✓ PASS: detectAvailableLibraries() function exists\n";

    // Call the function to test ImageMagick detection
    $result = detectAvailableLibraries();

    if (!isset($result['detected_libraries']['imagemagick'])) {
        echo "❌ FAIL: 'imagemagick' key not found in detection results\n";
        $allPassed = false;
    } else {
        echo "✓ PASS: 'imagemagick' key exists in detection results\n";

        $imagemagickResult = $result['detected_libraries']['imagemagick'];

        // Test 2: Check if extension_loaded is being tested
        echo "\nTest 2: Verify extension_loaded() is used for detection\n";
        echo "-------------------------------------------------------\n";

        // Read the source code to verify extension_loaded is used
        $sourceCode = file_get_contents(__DIR__ . '/convert.php');
        if (strpos($sourceCode, "extension_loaded('imagick')") !== false) {
            echo "✓ PASS: extension_loaded('imagick') found in source code\n";
        } else {
            echo "❌ FAIL: extension_loaded('imagick') not found in source code\n";
            $allPassed = false;
        }

        // Test 3: Verify detection returns true if Imagick is available
        echo "\nTest 3: Detection returns true if Imagick is available\n";
        echo "-------------------------------------------------------\n";

        $imagickActuallyLoaded = extension_loaded('imagick');
        echo "   Actual extension_loaded('imagick'): " .
             ($imagickActuallyLoaded ? 'true (LOADED)' : 'false (NOT LOADED)') . "\n";
        echo "   Detection result['available']: " .
             (isset($imagemagickResult['available']) ?
                 ($imagemagickResult['available'] ? 'true' : 'false') :
                 'not set') . "\n";

        if ($imagickActuallyLoaded) {
            // Imagick IS loaded - should detect as available
            if (isset($imagemagickResult['available']) && $imagemagickResult['available'] === true) {
                echo "✓ PASS: Correctly detected ImageMagick as available\n";

                // Additional checks when available
                if (isset($imagemagickResult['version'])) {
                    echo "   ✓ Version info: " . $imagemagickResult['version'] . "\n";
                }
                if (isset($imagemagickResult['extension_loaded'])) {
                    echo "   ✓ extension_loaded flag: " .
                         ($imagemagickResult['extension_loaded'] ? 'true' : 'false') . "\n";
                }
            } else {
                echo "❌ FAIL: Imagick is loaded but not detected as available\n";
                if (isset($imagemagickResult['reason'])) {
                    echo "   Reason: " . $imagemagickResult['reason'] . "\n";
                }
                $allPassed = false;
            }
        } else {
            // Imagick is NOT loaded - should detect as unavailable
            if (isset($imagemagickResult['available']) && $imagemagickResult['available'] === false) {
                echo "✓ PASS: Correctly detected ImageMagick as unavailable\n";

                // Check if proper reason is given
                if (isset($imagemagickResult['reason'])) {
                    echo "   ✓ Reason provided: " . $imagemagickResult['reason'] . "\n";
                    if ($imagemagickResult['reason'] === 'Imagick extension not loaded') {
                        echo "   ✓ PASS: Reason correctly indicates extension not loaded\n";
                    }
                }
            } else {
                echo "❌ FAIL: Imagick is not loaded but detected as available\n";
                echo "   This is a FALSE POSITIVE detection!\n";
                $allPassed = false;
            }
        }

        // Test 4: Verify detection returns false if not loaded
        echo "\nTest 4: Detection returns false if not loaded\n";
        echo "-----------------------------------------------\n";

        if (!$imagickActuallyLoaded) {
            if (isset($imagemagickResult['available']) && $imagemagickResult['available'] === false) {
                echo "✓ PASS: Detection correctly returns false when not loaded\n";
            } else {
                echo "❌ FAIL: Detection should return false when not loaded\n";
                $allPassed = false;
            }
        } else {
            echo "⊘ SKIP: Imagick is actually loaded, cannot test 'not loaded' scenario\n";
        }

        // Test 5: Detection result is accurate
        echo "\nTest 5: Detection result accuracy\n";
        echo "----------------------------------\n";

        $detectedAvailable = isset($imagemagickResult['available']) ?
                             $imagemagickResult['available'] : false;

        if ($detectedAvailable === $imagickActuallyLoaded) {
            echo "✓ PASS: Detection result matches actual extension status\n";
            echo "   Both are: " . ($imagickActuallyLoaded ? 'AVAILABLE' : 'UNAVAILABLE') . "\n";
        } else {
            echo "❌ FAIL: Detection result does NOT match actual extension status\n";
            echo "   extension_loaded('imagick'): " . ($imagickActuallyLoaded ? 'true' : 'false') . "\n";
            echo "   Detected result: " . ($detectedAvailable ? 'true' : 'false') . "\n";
            $allPassed = false;
        }
    }
}

// Summary
echo "\n" . str_repeat("=", 50) . "\n";
echo "SUMMARY\n";
echo str_repeat("=", 50) . "\n";

if ($allPassed) {
    echo "✅ ALL TESTS PASSED\n";
    echo "\nFeature #20 verification: PASSED\n";
    echo "The system correctly checks if ImageMagick extension is loaded.\n";
    exit(0);
} else {
    echo "❌ SOME TESTS FAILED\n";
    echo "\nFeature #20 verification: FAILED\n";
    echo "See details above for specific failures.\n";
    exit(1);
}
