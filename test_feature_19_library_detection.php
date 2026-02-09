<?php
/**
 * Test Feature #19: Library Detection
 *
 * Tests the detectAvailableLibraries() function to verify:
 * 1. wkhtmltoimage detection via exec()
 * 2. ImageMagick detection
 * 3. GD library detection
 * 4. Priority selection (best library)
 * 5. Logging/detailed detection results
 */

// Include the convert.php file to access the function
require_once __DIR__ . '/convert.php';

// Test function to call library detection
function testLibraryDetection() {
    // Call the detection function
    $result = detectAvailableLibraries();

    // Verify response structure
    $requiredKeys = ['detected_libraries', 'best_library', 'available'];
    foreach ($requiredKeys as $key) {
        if (!array_key_exists($key, $result)) {
            return [
                'success' => false,
                'error' => "Missing required key: $key"
            ];
        }
    }

    // Verify detected_libraries contains all three libraries
    $requiredLibraries = ['wkhtmltoimage', 'imagemagick', 'gd'];
    foreach ($requiredLibraries as $lib) {
        if (!isset($result['detected_libraries'][$lib])) {
            return [
                'success' => false,
                'error' => "Missing detection result for library: $lib"
            ];
        }
    }

    // Verify wkhtmltoimage detection has proper structure
    $wkhtmltoimage = $result['detected_libraries']['wkhtmltoimage'];
    if (!isset($wkhtmltoimage['available'])) {
        return [
            'success' => false,
            'error' => 'wkhtmltoimage missing "available" field'
        ];
    }

    // If wkhtmltoimage is available, verify path is set
    if ($wkhtmltoimage['available'] === true) {
        if (!isset($wkhtmltoimage['path']) || empty($wkhtmltoimage['path'])) {
            return [
                'success' => false,
                'error' => 'wkhtmltoimage marked as available but no path provided'
            ];
        }
        if (!isset($wkhtmltoimage['version'])) {
            return [
                'success' => false,
                'error' => 'wkhtmltoimage missing version information'
            ];
        }
    } else {
        // If not available, should have reason
        if (!isset($wkhtmltoimage['reason'])) {
            return [
                'success' => false,
                'error' => 'wkhtmltoimage not available but no reason provided'
            ];
        }
    }

    // Verify ImageMagick detection
    $imagemagick = $result['detected_libraries']['imagemagick'];
    if (!isset($imagemagick['available'])) {
        return [
            'success' => false,
            'error' => 'imagemagick missing "available" field'
        ];
    }

    // Verify GD library detection
    $gd = $result['detected_libraries']['gd'];
    if (!isset($gd['available'])) {
        return [
            'success' => false,
            'error' => 'gd missing "available" field'
        ];
    }

    // GD should be available in standard PHP installations
    if ($gd['available'] !== true) {
        return [
            'success' => false,
            'error' => 'GD library should be available in standard PHP installation'
        ];
    }

    // Verify best_library is set correctly
    if ($result['best_library'] === null && $result['available'] === false) {
        // This is OK if no libraries are available
    } elseif (!in_array($result['best_library'], $requiredLibraries)) {
        return [
            'success' => false,
            'error' => 'best_library must be one of: wkhtmltoimage, imagemagick, gd'
        ];
    }

    return [
        'success' => true,
        'result' => $result
    ];
}

// Run the test
$testResult = testLibraryDetection();

// Output results
header('Content-Type: application/json; charset=utf-8');
echo json_encode($testResult, JSON_PRETTY_PRINT);
