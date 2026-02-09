<?php
/**
 * Simple test for ImageMagick availability and basic functionality
 */

echo "=== ImageMagick Availability Test ===\n\n";

// Test 1: Check if extension is loaded
echo "Test 1: Extension loaded\n";
if (!extension_loaded('imagick')) {
    echo "ERROR: Imagick extension not loaded\n";
    exit(1);
}
echo "✓ Imagick extension is loaded\n\n";

// Test 2: Check if class exists
echo "Test 2: Imagick class exists\n";
if (!class_exists('Imagick')) {
    echo "ERROR: Imagick class does not exist\n";
    exit(1);
}
echo "✓ Imagick class exists\n\n";

// Test 3: Create Imagick object
echo "Test 3: Create Imagick object\n";
try {
    $imagick = new Imagick();
    echo "✓ Imagick object created\n";
    $imagick->clear();
    $imagick->destroy();
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
echo "\n";

// Test 4: Create a simple PNG with text
echo "Test 4: Create PNG with text 'IM_RENDER_TEST'\n";
try {
    $imagick = new Imagick();
    $imagick->newImage(400, 50, new ImagickPixel('transparent'));
    $imagick->setFillColor(new ImagickPixel('#FF0000'));
    $imagick->setFont('Arial');
    $imagick->setFontSize(20);
    $imagick->setGravity(Imagick::GRAVITY_CENTER);
    $imagick->annotateImage(new ImagickDraw(), 0, 0, 0, 'IM_RENDER_TEST');
    $imagick->setImageFormat('png');
    $imagick->setImageAlphaChannel(Imagick::ALPHACHANNEL_ACTIVATE);

    $outputPath = __DIR__ . '/assets/media/rapidhtml2png/test_imagemagick.png';
    $imagick->writeImage($outputPath);

    echo "✓ PNG created at: $outputPath\n";
    echo "✓ File exists: " . (file_exists($outputPath) ? 'YES' : 'NO') . "\n";
    echo "✓ File size: " . filesize($outputPath) . " bytes\n";

    $imagick->clear();
    $imagick->destroy();
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
echo "\n";

echo "=== All Tests Passed ===\n";
