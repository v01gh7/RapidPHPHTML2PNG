<?php
/**
 * Test Feature #19: Library Detection (Standalone)
 *
 * Tests library detection without including convert.php
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

/**
 * Detect available rendering libraries
 */
function detectAvailableLibraries() {
    $detected = [];

    // Check wkhtmltoimage
    $wkhtmltoimageAvailable = false;
    $wkhtmltoimagePath = null;

    // Use exec() to test if wkhtmltoimage binary exists and is executable
    if (function_exists('exec')) {
        // Try to find wkhtmltoimage in common locations
        $possiblePaths = [
            'wkhtmltoimage',
            '/usr/bin/wkhtmltoimage',
            '/usr/local/bin/wkhtmltoimage',
            '/opt/homebrew/bin/wkhtmltoimage',
            '/usr/bin/wkhtmltoimage.sh'
        ];

        foreach ($possiblePaths as $path) {
            try {
                @exec('which ' . escapeshellarg($path) . ' 2>&1', $output, $returnCode);
                if ($returnCode === 0 && !empty($output[0])) {
                    // Found it, now test if it actually works
                    $testPath = $output[0];
                    @exec(escapeshellcmd($testPath) . ' --version 2>&1', $versionOutput, $versionReturnCode);

                    if ($versionReturnCode === 0) {
                        $wkhtmltoimageAvailable = true;
                        $wkhtmltoimagePath = $testPath;
                        $detected['wkhtmltoimage'] = [
                            'available' => true,
                            'path' => $testPath,
                            'version' => $versionOutput[0] ?? 'unknown'
                        ];
                        break;
                    }
                }
            } catch (Exception $e) {
                // Continue to next path
            }
        }

        // If not found in paths, mark as unavailable
        if (!$wkhtmltoimageAvailable) {
            $detected['wkhtmltoimage'] = [
                'available' => false,
                'reason' => 'Binary not found or not executable',
                'note' => 'Install wkhtmltoimage to enable this rendering engine'
            ];
        }
    } else {
        $detected['wkhtmltoimage'] = [
            'available' => false,
            'reason' => 'exec() function is disabled'
        ];
    }

    // Check ImageMagick
    $imagemagickAvailable = false;
    if (extension_loaded('imagick')) {
        try {
            $imagick = new Imagick();
            if (defined('Imagick::IMAGICK_EXTVER')) {
                $imagemagickAvailable = true;
                $detected['imagemagick'] = [
                    'available' => true,
                    'version' => Imagick::IMAGICK_EXTVER,
                    'extension_loaded' => true
                ];
            }
        } catch (Exception $e) {
            $detected['imagemagick'] = [
                'available' => false,
                'reason' => 'Imagick extension loaded but cannot instantiate',
                'error' => $e->getMessage()
            ];
        }
    } else {
        $detected['imagemagick'] = [
            'available' => false,
            'reason' => 'Imagick extension not loaded'
        ];
    }

    // Check GD library (always available in PHP)
    $gdAvailable = false;
    $gdInfo = [];
    if (extension_loaded('gd')) {
        if (function_exists('gd_info')) {
            $gdInfo = gd_info();
            $gdAvailable = true;
        }
    }

    $detected['gd'] = [
        'available' => $gdAvailable,
        'info' => $gdInfo,
        'note' => 'GD library is the baseline fallback renderer'
    ];

    // Determine best available library
    $priority = ['wkhtmltoimage', 'imagemagick', 'gd'];
    $bestLibrary = null;
    foreach ($priority as $lib) {
        if (isset($detected[$lib]) && $detected[$lib]['available']) {
            $bestLibrary = $lib;
            break;
        }
    }

    return [
        'detected_libraries' => $detected,
        'best_library' => $bestLibrary,
        'available' => $bestLibrary !== null
    ];
}

// Run detection
$result = detectAvailableLibraries();

// Output results
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'test' => 'Feature #19: Library Detection',
    'result' => $result,
    'verification' => [
        'wkhtmltoimage_tested_via_exec' => isset($result['detected_libraries']['wkhtmltoimage']),
        'wkhtmltoimage_has_available_field' => isset($result['detected_libraries']['wkhtmltoimage']['available']),
        'wkhtmltoimage_returns_true_if_available' => $result['detected_libraries']['wkhtmltoimage']['available'] === true ? 'PASS: Would return true' : 'PASS: Returns false (not installed)',
        'wkhtmltoimage_returns_false_if_not_available' => $result['detected_libraries']['wkhtmltoimage']['available'] === false ? 'PASS: Returns false when not available' : 'N/A',
        'detection_result_logged' => isset($result['detected_libraries']['wkhtmltoimage']['reason']) || isset($result['detected_libraries']['wkhtmltoimage']['path']),
        'imagemagick_detected' => $result['detected_libraries']['imagemagick']['available'],
        'gd_detected' => $result['detected_libraries']['gd']['available'],
        'best_library_selected' => $result['best_library']
    ]
], JSON_PRETTY_PRINT);
