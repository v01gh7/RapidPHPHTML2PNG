<?php
/**
 * Feature #29: Auto-sizes based on content
 * Test script to verify output image dimensions match content size
 */

require_once 'convert.php';

echo "=== Feature #29: Auto-sizing Based on Content ===\n\n";

// Test 1: Small content (should produce small image)
echo "Test 1: Small content (should produce small image)\n";
echo "---------------------------------------------------\n";

$smallHtml = '<div style="width: 100px; font-size: 12px;">Small</div>';
$smallCss = '.test { color: #000; }';

$smallHash = md5($smallHtml . $smallCss);
$smallOutput = __DIR__ . '/assets/media/rapidhtml2png/' . $smallHash . '.png';

// Clean up any existing file
if (file_exists($smallOutput)) {
    unlink($smallOutput);
}

$result = renderHtmlToPng([$smallHtml], $smallCss, $smallOutput);

if ($result['success']) {
    $imageInfo = getimagesize($smallOutput);
    $width = $imageInfo[0];
    $height = $imageInfo[1];
    $fileSize = filesize($smallOutput);

    echo "✓ Small content rendered successfully\n";
    echo "  Engine: {$result['engine']}\n";
    echo "  Dimensions: {$width}x{$height}\n";
    echo "  File size: {$fileSize} bytes\n";

    // Check if dimensions are reasonable (should be small)
    $test1Pass = ($width < 200 && $height < 100);
    echo "  Result: " . ($test1Pass ? "✓ PASS" : "✗ FAIL") . " - ";
    echo $test1Pass ? "Image is appropriately sized for small content\n" : "Image is too large for small content\n";
} else {
    echo "✗ FAIL: " . $result['error'] . "\n";
    $test1Pass = false;
}

echo "\n";

// Test 2: Medium content (should produce medium image)
echo "Test 2: Medium content (should produce medium image)\n";
echo "---------------------------------------------------\n";

$mediumHtml = '<div style="width: 300px; font-size: 16px;">
    This is medium content that should produce a medium-sized image.
    It has more text and is wider.
</div>';

$mediumHash = md5($mediumHtml . $smallCss);
$mediumOutput = __DIR__ . '/assets/media/rapidhtml2png/' . $mediumHash . '.png';

// Clean up any existing file
if (file_exists($mediumOutput)) {
    unlink($mediumOutput);
}

$result = renderHtmlToPng([$mediumHtml], $smallCss, $mediumOutput);

if ($result['success']) {
    $imageInfo = getimagesize($mediumOutput);
    $width = $imageInfo[0];
    $height = $imageInfo[1];
    $fileSize = filesize($mediumOutput);

    echo "✓ Medium content rendered successfully\n";
    echo "  Engine: {$result['engine']}\n";
    echo "  Dimensions: {$width}x{$height}\n";
    echo "  File size: {$fileSize} bytes\n";

    // Check if dimensions are reasonable (should be medium)
    $test2Pass = ($width >= 200 && $width < 500);
    echo "  Result: " . ($test2Pass ? "✓ PASS" : "✗ FAIL") . " - ";
    echo $test2Pass ? "Image is appropriately sized for medium content\n" : "Image size doesn't match medium content\n";
} else {
    echo "✗ FAIL: " . $result['error'] . "\n";
    $test2Pass = false;
}

echo "\n";

// Test 3: Large content (should produce larger image)
echo "Test 3: Large content (should produce larger image)\n";
echo "---------------------------------------------------\n";

$largeHtml = '<div style="width: 600px; font-size: 18px;">
    <h1>Large Content Test</h1>
    <p>This is a large content block that should produce a larger image.</p>
    <p>It contains multiple lines of text and is wider to test auto-sizing.</p>
    <p>The renderer should automatically adjust the image dimensions to fit this content.</p>
    <p>Line four of content to ensure vertical sizing works correctly.</p>
</div>';

$largeHash = md5($largeHtml . $smallCss);
$largeOutput = __DIR__ . '/assets/media/rapidhtml2png/' . $largeHash . '.png';

// Clean up any existing file
if (file_exists($largeOutput)) {
    unlink($largeOutput);
}

$result = renderHtmlToPng([$largeHtml], $smallCss, $largeOutput);

if ($result['success']) {
    $imageInfo = getimagesize($largeOutput);
    $width = $imageInfo[0];
    $height = $imageInfo[1];
    $fileSize = filesize($largeOutput);

    echo "✓ Large content rendered successfully\n";
    echo "  Engine: {$result['engine']}\n";
    echo "  Dimensions: {$width}x{$height}\n";
    echo "  File size: {$fileSize} bytes\n";

    // Check if dimensions are reasonable (should be larger)
    $test3Pass = ($width >= 400 || $height >= 100);
    echo "  Result: " . ($test3Pass ? "✓ PASS" : "✗ FAIL") . " - ";
    echo $test3Pass ? "Image is appropriately sized for large content\n" : "Image size doesn't match large content\n";
} else {
    echo "✗ FAIL: " . $result['error'] . "\n";
    $test3Pass = false;
}

echo "\n";

// Test 4: Verify width/height ratio matches content
echo "Test 4: Wide content should produce wider image\n";
echo "---------------------------------------------------\n";

$wideHtml = '<div style="width: 800px; font-size: 14px;">
    This is a very wide content block that spans the entire width.
    It should produce a wide image with relatively small height.
</div>';

$wideHash = md5($wideHtml . $smallCss);
$wideOutput = __DIR__ . '/assets/media/rapidhtml2png/' . $wideHash . '.png';

// Clean up any existing file
if (file_exists($wideOutput)) {
    unlink($wideOutput);
}

$result = renderHtmlToPng([$wideHtml], $smallCss, $wideOutput);

if ($result['success']) {
    $imageInfo = getimagesize($wideOutput);
    $width = $imageInfo[0];
    $height = $imageInfo[1];
    $aspectRatio = $width / $height;

    echo "✓ Wide content rendered successfully\n";
    echo "  Engine: {$result['engine']}\n";
    echo "  Dimensions: {$width}x{$height}\n";
    echo "  Aspect ratio: " . number_format($aspectRatio, 2) . ":1\n";

    // Check if aspect ratio indicates wide image
    $test4Pass = ($aspectRatio > 2.0);
    echo "  Result: " . ($test4Pass ? "✓ PASS" : "✗ FAIL") . " - ";
    echo $test4Pass ? "Image width matches wide content\n" : "Image doesn't reflect wide content\n";
} else {
    echo "✗ FAIL: " . $result['error'] . "\n";
    $test4Pass = false;
}

echo "\n";

// Test 5: Tall content should produce taller image
echo "Test 5: Tall content should produce taller image\n";
echo "---------------------------------------------------\n";

$tallHtml = '<div style="width: 200px; font-size: 14px;">
    <p>Line 1</p>
    <p>Line 2</p>
    <p>Line 3</p>
    <p>Line 4</p>
    <p>Line 5</p>
    <p>Line 6</p>
    <p>Line 7</p>
    <p>Line 8</p>
    <p>Line 9</p>
    <p>Line 10</p>
</div>';

$tallHash = md5($tallHtml . $smallCss);
$tallOutput = __DIR__ . '/assets/media/rapidhtml2png/' . $tallHash . '.png';

// Clean up any existing file
if (file_exists($tallOutput)) {
    unlink($tallOutput);
}

$result = renderHtmlToPng([$tallHtml], $smallCss, $tallOutput);

if ($result['success']) {
    $imageInfo = getimagesize($tallOutput);
    $width = $imageInfo[0];
    $height = $imageInfo[1];
    $aspectRatio = $height / $width;

    echo "✓ Tall content rendered successfully\n";
    echo "  Engine: {$result['engine']}\n";
    echo "  Dimensions: {$width}x{$height}\n";
    echo "  Height-to-width ratio: " . number_format($aspectRatio, 2) . ":1\n";

    // Check if aspect ratio indicates tall image
    $test5Pass = ($aspectRatio > 1.5);
    echo "  Result: " . ($test5Pass ? "✓ PASS" : "✗ FAIL") . " - ";
    echo $test5Pass ? "Image height matches tall content\n" : "Image doesn't reflect tall content\n";
} else {
    echo "✗ FAIL: " . $result['error'] . "\n";
    $test5Pass = false;
}

echo "\n";

// Summary
echo "=== Summary ===\n";
$totalTests = 5;
$passedTests = array_sum([$test1Pass, $test2Pass, $test3Pass, $test4Pass, $test5Pass]);
echo "Passed: {$passedTests}/{$totalTests}\n";
echo "Percentage: " . number_format(($passedTests / $totalTests) * 100, 1) . "%\n";

if ($passedTests === $totalTests) {
    echo "\n✓ All tests passed! Auto-sizing is working correctly.\n";
    exit(0);
} else {
    echo "\n✗ Some tests failed. Auto-sizing may need improvement.\n";
    exit(1);
}
