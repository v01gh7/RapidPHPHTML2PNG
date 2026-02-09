<?php
/**
 * Direct test of ImageMagick rendering
 * Run this file directly to test
 */

// Prevent web execution - this is for CLI only
if (php_sapi_name() !== 'cli') {
    die('This script must be run from CLI');
}

echo "=== ImageMagick Direct Test ===\n\n";

// Test 1: Check extension
echo "Test 1: Extension loaded\n";
if (!extension_loaded('imagick')) {
    echo "FAIL: Imagick extension not loaded\n";
    exit(1);
}
echo "PASS: Imagick extension loaded\n\n";

// Test 2: Check class
echo "Test 2: Imagick class exists\n";
if (!class_exists('Imagick')) {
    echo "FAIL: Imagick class does not exist\n";
    exit(1);
}
echo "PASS: Imagick class exists\n\n";

// Test 3: Create object
echo "Test 3: Create Imagick object\n";
try {
    $imagick = new Imagick();
    echo "PASS: Imagick object created\n";
    $imagick->clear();
    $imagick->destroy();
} catch (Exception $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
    exit(1);
}
echo "\n";

// Test 4: Include convert.php
echo "Test 4: Include convert.php\n";
try {
    require_once __DIR__ . '/convert.php';
    echo "PASS: convert.php included\n";
} catch (Exception $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
    exit(1);
}
echo "\n";

// Test 5: Check function exists
echo "Test 5: renderWithImageMagick function exists\n";
if (!function_exists('renderWithImageMagick')) {
    echo "FAIL: renderWithImageMagick function does not exist\n";
    exit(1);
}
echo "PASS: renderWithImageMagick function exists\n\n";

// Test 6: Test library detection
echo "Test 6: Library detection\n";
$detection = detectAvailableLibraries();
$imAvailable = $detection['detected_libraries']['imagemagick']['available'] ?? false;
echo "ImageMagick Available: " . ($imAvailable ? 'YES' : 'NO') . "\n";
if ($imAvailable) {
    echo "Version: " . ($detection['detected_libraries']['imagemagick']['version'] ?? 'N/A') . "\n";
    echo "PASS: ImageMagick detected\n";
} else {
    echo "Reason: " . ($detection['detected_libraries']['imagemagick']['reason'] ?? 'Unknown') . "\n";
    echo "INFO: ImageMagick not available, will test GD instead\n";
}
echo "\n";

// Test 7: Test rendering
echo "Test 7: Render HTML with text 'IM_RENDER_TEST'\n";
$htmlBlocks = ['<div>IM_RENDER_TEST</div>'];
$cssContent = null;
$hash = md5(implode('', $htmlBlocks));
$outputDir = __DIR__ . '/assets/media/rapidhtml2png';
$outputPath = $outputDir . '/' . $hash . '.png';

// Delete existing file if present
if (file_exists($outputPath)) {
    unlink($outputPath);
    echo "Deleted existing test file\n";
}

try {
    if ($imAvailable) {
        $result = renderWithImageMagick($htmlBlocks, $cssContent, $outputPath);
    } else {
        echo "ImageMagick not available, using GD fallback\n";
        $result = renderWithGD($htmlBlocks, $cssContent, $outputPath);
    }

    if (!$result['success']) {
        echo "FAIL: Rendering failed - " . ($result['error'] ?? 'Unknown error') . "\n";
        exit(1);
    }

    echo "PASS: Rendering successful\n";
    echo "  Engine: " . $result['engine'] . "\n";
    echo "  Output: " . $result['output_path'] . "\n";
    echo "  Size: " . $result['file_size'] . " bytes\n";
    echo "  Dimensions: " . $result['width'] . "x" . $result['height'] . "\n";

} catch (Exception $e) {
    echo "FAIL: Exception during rendering - " . $e->getMessage() . "\n";
    exit(1);
}
echo "\n";

// Test 8: Verify file exists
echo "Test 8: Verify output file exists\n";
if (!file_exists($outputPath)) {
    echo "FAIL: Output file does not exist\n";
    exit(1);
}
echo "PASS: File exists\n";
echo "  File size: " . filesize($outputPath) . " bytes\n";

$imgInfo = getimagesize($outputPath);
if ($imgInfo === false) {
    echo "FAIL: File is not a valid image\n";
    exit(1);
}
echo "  Dimensions: " . $imgInfo[0] . "x" . $imgInfo[1] . "\n";
echo "  Type: " . image_type_to_extension($imgInfo[2], false) . "\n";

if ($imgInfo[2] !== IMAGETYPE_PNG) {
    echo "FAIL: Image is not PNG\n";
    exit(1);
}
echo "PASS: Valid PNG file\n\n";

echo "=== All Tests Passed ===\n";
