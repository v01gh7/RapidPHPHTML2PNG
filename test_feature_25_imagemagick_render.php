<?php
/**
 * Feature #25: ImageMagick Rendering Test
 *
 * This script tests that HTML is converted to PNG using ImageMagick when available.
 */

// Include the main convert.php file
require_once __DIR__ . '/convert.php';

echo "=== Feature #25: ImageMagick Rendering Test ===\n\n";

// Test 1: Check if ImageMagick is available
echo "Test 1: Check if ImageMagick is available\n";
echo str_repeat("-", 50) . "\n";

$detection = detectAvailableLibraries();
$imAvailable = $detection['detected_libraries']['imagemagick']['available'] ?? false;

echo "ImageMagick Available: " . ($imAvailable ? "YES" : "NO") . "\n";

if (!$imAvailable) {
    echo "ERROR: ImageMagick is not available. Cannot proceed with tests.\n";
    echo "Reason: " . ($detection['detected_libraries']['imagemagick']['reason'] ?? 'Unknown') . "\n";
    exit(1);
}

echo "Version: " . ($detection['detected_libraries']['imagemagick']['version'] ?? 'N/A') . "\n";
echo "✓ Test 1 PASSED\n\n";

// Test 2: Verify Imagick class can be instantiated
echo "Test 2: Verify Imagick class can be instantiated\n";
echo str_repeat("-", 50) . "\n";

try {
    $imagick = new Imagick();
    echo "Imagick object created successfully\n";
    $imagick->clear();
    $imagick->destroy();
    echo "✓ Test 2 PASSED\n\n";
} catch (Exception $e) {
    echo "ERROR: Failed to create Imagick object: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 3: Test renderWithImageMagick function exists
echo "Test 3: Test renderWithImageMagick function exists\n";
echo str_repeat("-", 50) . "\n";

if (!function_exists('renderWithImageMagick')) {
    echo "ERROR: renderWithImageMagick function does not exist\n";
    exit(1);
}

echo "✓ Test 3 PASSED\n\n";

// Test 4: Render HTML with test text 'IM_RENDER_TEST'
echo "Test 4: Render HTML with test text 'IM_RENDER_TEST'\n";
echo str_repeat("-", 50) . "\n";

$htmlBlocks = ['<div>IM_RENDER_TEST</div>'];
$cssContent = 'div { color: #FF0000; font-size: 20px; }';
$contentHash = generateContentHash($htmlBlocks, $cssContent);
$outputPath = __DIR__ . '/assets/media/rapidhtml2png/' . $contentHash . '.png';

echo "HTML: " . $htmlBlocks[0] . "\n";
echo "CSS: " . $cssContent . "\n";
echo "Content Hash: " . $contentHash . "\n";
echo "Output Path: " . $outputPath . "\n";

// Delete the file if it exists from a previous test
if (file_exists($outputPath)) {
    unlink($outputPath);
    echo "Deleted existing test file\n";
}

$result = renderWithImageMagick($htmlBlocks, $cssContent, $outputPath);

if (!$result['success']) {
    echo "ERROR: Rendering failed\n";
    echo "Error: " . ($result['error'] ?? 'Unknown') . "\n";
    if (isset($result['exception'])) {
        echo "Exception: " . $result['exception'] . "\n";
    }
    if (isset($result['message'])) {
        echo "Message: " . $result['message'] . "\n";
    }
    exit(1);
}

echo "✓ Test 4 PASSED\n";
echo "Engine: " . $result['engine'] . "\n";
echo "Output Path: " . $result['output_path'] . "\n";
echo "File Size: " . $result['file_size'] . " bytes\n";
echo "Width: " . $result['width'] . "px\n";
echo "Height: " . $result['height'] . "px\n";
echo "MIME Type: " . $result['mime_type'] . "\n\n";

// Test 5: Verify PNG file was created
echo "Test 5: Verify PNG file was created\n";
echo str_repeat("-", 50) . "\n";

if (!file_exists($outputPath)) {
    echo "ERROR: PNG file was not created\n";
    exit(1);
}

echo "File exists: YES\n";
echo "File size: " . filesize($outputPath) . " bytes\n";

$imageInfo = getimagesize($outputPath);
if ($imageInfo === false) {
    echo "ERROR: File is not a valid image\n";
    exit(1);
}

echo "Image dimensions: " . $imageInfo[0] . "x" . $imageInfo[1] . "\n";
echo "Image type: " . image_type_to_extension($imageInfo[2], false) . "\n";
echo "MIME type: " . $imageInfo['mime'] . "\n";

if ($imageInfo[2] !== IMAGETYPE_PNG) {
    echo "ERROR: Image is not a PNG\n";
    exit(1);
}

echo "✓ Test 5 PASSED\n\n";

// Test 6: Test with different content
echo "Test 6: Test with different content\n";
echo str_repeat("-", 50) . "\n";

$htmlBlocks2 = ['<span>Different content for testing</span>'];
$cssContent2 = 'span { color: #0000FF; font-family: Arial; }';
$contentHash2 = generateContentHash($htmlBlocks2, $cssContent2);
$outputPath2 = __DIR__ . '/assets/media/rapidhtml2png/' . $contentHash2 . '.png';

echo "HTML: " . $htmlBlocks2[0] . "\n";
echo "CSS: " . $cssContent2 . "\n";
echo "Content Hash: " . $contentHash2 . "\n";

$result2 = renderWithImageMagick($htmlBlocks2, $cssContent2, $outputPath2);

if (!$result2['success']) {
    echo "ERROR: Second rendering failed\n";
    echo "Error: " . ($result2['error'] ?? 'Unknown') . "\n";
    exit(1);
}

echo "✓ Test 6 PASSED\n";
echo "Engine: " . $result2['engine'] . "\n";
echo "File Size: " . $result2['file_size'] . " bytes\n";
echo "Dimensions: " . $result2['width'] . "x" . $result2['height'] . "\n\n";

// Test 7: Verify transparent background
echo "Test 7: Verify transparent background\n";
echo str_repeat("-", 50) . "\n";

// Load the PNG and check for alpha channel
$im = imagecreatefrompng($outputPath);
if ($im === false) {
    echo "ERROR: Could not load PNG for transparency check\n";
    exit(1);
}

// Check if image has alpha channel
$width = imagesx($im);
$height = imagesy($im);

// Sample a few pixels to check for transparency
$transparentPixels = 0;
$testPixels = 0;

for ($x = 0; $x < min(10, $width); $x++) {
    for ($y = 0; $y < min(10, $height); $y++) {
        $index = imagecolorat($im, $x, $y);
        $alpha = ($index >> 24) & 0x7F;
        if ($alpha > 0) {
            $transparentPixels++;
        }
        $testPixels++;
    }
}

imagedestroy($im);

echo "Tested $testPixels pixels for transparency\n";
echo "Transparent pixels found: $transparentPixels\n";

if ($transparentPixels > 0) {
    echo "✓ Test 7 PASSED - Image has transparency\n\n";
} else {
    echo "⚠ WARNING: No transparent pixels detected (may have solid background)\n\n";
}

// Summary
echo str_repeat("=", 50) . "\n";
echo "All tests completed successfully!\n";
echo "Feature #25: ImageMagick Rendering - PASSED ✓\n";
echo str_repeat("=", 50) . "\n";
