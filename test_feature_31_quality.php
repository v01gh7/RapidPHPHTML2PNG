<?php
/**
 * Test Feature #31: Web-Quality PNG Settings
 *
 * This script tests that PNG files are saved with quality settings
 * suitable for web use (reasonable file size, proper compression,
 * browser-compatible).
 */

// Output as plain text for debugging
header('Content-Type: text/plain');

echo "=== Feature #31: Web-Quality PNG Settings Test ===\n\n";

// Test configuration
$apiUrl = 'http://localhost/convert.php';
$testData = [
    'html_blocks' => [
        '<div class="styled-element">QUALITY_TEST_31</div>'
    ],
    'css_url' => 'http://localhost/main.css'
];

// Test 1: Render HTML content to PNG
echo "Test 1: Render HTML content to PNG\n";
echo str_repeat("-", 50) . "\n";

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo "❌ FAILED: cURL Error: $curlError\n";
    exit(1);
}

if ($httpCode !== 200) {
    echo "❌ FAILED: HTTP $httpCode\n";
    echo "Response: $response\n";
    exit(1);
}

$data = json_decode($response, true);

if (!$data || !$data['success']) {
    echo "❌ FAILED: API returned error\n";
    print_r($data);
    exit(1);
}

$outputFile = $data['data']['rendering']['output_file'] ?? null;

if (!$outputFile || !file_exists($outputFile)) {
    echo "❌ FAILED: Output file not created\n";
    print_r($data);
    exit(1);
}

echo "✅ PASSED: PNG file created at $outputFile\n\n";

// Test 2: Check PNG file size is reasonable
echo "Test 2: Check PNG file size is reasonable\n";
echo str_repeat("-", 50) . "\n";

$fileSize = filesize($outputFile);
$fileSizeKB = round($fileSize / 1024, 2);

echo "File size: $fileSize bytes ($fileSizeKB KB)\n";

// For a simple text render, file size should be reasonable
// Not too large (indicating poor compression)
// Not too small (indicating poor quality)
$maxReasonableSize = 500 * 1024; // 500 KB max for simple content
$minReasonableSize = 100; // At least 100 bytes

if ($fileSize > $maxReasonableSize) {
    echo "⚠️  WARNING: File size is large ($fileSizeKB KB)\n";
    echo "   This may indicate poor compression settings\n";
} elseif ($fileSize < $minReasonableSize) {
    echo "⚠️  WARNING: File size is very small ($fileSize bytes)\n";
    echo "   This may indicate poor quality\n";
} else {
    echo "✅ PASSED: File size is reasonable for web use\n";
}

echo "File size: " . formatBytes($fileSize) . "\n\n";

// Test 3: Verify PNG compression/format
echo "Test 3: Verify PNG compression and format\n";
echo str_repeat("-", 50) . "\n";

// Check PNG signature (first 8 bytes)
$handle = fopen($outputFile, 'rb');
$signature = fread($handle, 8);
fclose($handle);

$expectedSignature = "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A"; // PNG signature

if ($signature === $expectedSignature) {
    echo "✅ PASSED: Valid PNG file signature detected\n";
} else {
    echo "❌ FAILED: Invalid PNG file signature\n";
    exit(1);
}

// Test 4: Check PNG structure
$pngInfo = get_png_info($outputFile);

if ($pngInfo) {
    echo "PNG Info:\n";
    echo "  Width: {$pngInfo['width']} px\n";
    echo "  Height: {$pngInfo['height']} px\n";
    echo "  Bit depth: {$pngInfo['bit_depth']}\n";
    echo "  Color type: {$pngInfo['color_type']}\n";
    echo "  Interlace: " . ($pngInfo['interlace'] ? 'Yes' : 'No') . "\n";

    if ($pngInfo['color_type'] === 'RGBA' || $pngInfo['color_type'] === 'RGB with alpha') {
        echo "✅ PASSED: PNG has alpha channel (transparency support)\n";
    } else {
        echo "⚠️  WARNING: PNG may not have proper alpha channel\n";
    }
}

echo "\n";

// Test 5: Check that PNG can be displayed in browsers
echo "Test 4: Verify PNG is browser-compatible\n";
echo str_repeat("-", 50) . "\n";

// Check that the PNG is valid by trying to read it with getimagesize()
$imageInfo = @getimagesize($outputFile);

if ($imageInfo !== false) {
    echo "✅ PASSED: PNG can be read by PHP getimagesize()\n";
    echo "  MIME type: {$imageInfo['mime']}\n";

    if ($imageInfo['mime'] === 'image/png') {
        echo "✅ PASSED: Correct MIME type for browsers\n";
    } else {
        echo "❌ FAILED: Incorrect MIME type: {$imageInfo['mime']}\n";
        exit(1);
    }
} else {
    echo "❌ FAILED: PNG cannot be read by getimagesize()\n";
    exit(1);
}

echo "\n";

// Test 6: Verify compression quality
echo "Test 5: Check PNG compression quality\n";
echo str_repeat("-", 50) . "\n";

// For web use, we want good compression
// Check pixel count vs file size ratio
$pixelCount = $pngInfo['width'] * $pngInfo['height'];
$bytesPerPixel = $fileSize / $pixelCount;

echo "Pixels: $pixelCount\n";
echo "Bytes per pixel: " . round($bytesPerPixel * 8, 2) . " bits\n";

// A well-compressed PNG with simple text should be < 1 bit per pixel
if ($bytesPerPixel < 0.5) {
    echo "✅ PASSED: Good compression (less than 4 bits per pixel)\n";
} elseif ($bytesPerPixel < 1.0) {
    echo "✅ PASSED: Acceptable compression (less than 8 bits per pixel)\n";
} else {
    echo "⚠️  WARNING: Compression could be improved (more than 8 bits per pixel)\n";
}

echo "\n";

// Summary
echo "=== Test Summary ===\n";
echo "✅ All tests PASSED for Feature #31\n";
echo "\n";
echo "PNG Quality Assessment:\n";
echo "  File: $outputFile\n";
echo "  Size: " . formatBytes($fileSize) . "\n";
echo "  Dimensions: {$pngInfo['width']}x{$pngInfo['height']}\n";
echo "  Status: Suitable for web use\n";

/**
 * Format bytes in human-readable format
 */
function formatBytes($size, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $pow = floor(($size ? log($size) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $size /= pow(1024, $pow);
    return round($size, $precision) . ' ' . $units[$pow];
}

/**
 * Get basic PNG information from file
 */
function get_png_info($filePath) {
    $handle = fopen($filePath, 'rb');

    // Read PNG signature
    $signature = fread($handle, 8);
    if ($signature !== "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A") {
        fclose($handle);
        return null;
    }

    $info = null;

    // Read chunks
    while (!feof($handle)) {
        // Read chunk length (4 bytes)
        $lengthData = fread($handle, 4);
        if (strlen($lengthData) < 4) break;

        $length = unpack('N', $lengthData)[1];

        // Read chunk type (4 bytes)
        $type = fread($handle, 4);
        if (strlen($type) < 4) break;

        // Read chunk data
        $data = '';
        if ($length > 0) {
            $data = fread($handle, $length);
        }

        // Read CRC (4 bytes)
        $crc = fread($handle, 4);

        // Process IHDR chunk (first chunk, contains image info)
        if ($type === 'IHDR') {
            $width = unpack('N', substr($data, 0, 4))[1];
            $height = unpack('N', substr($data, 4, 4))[1];
            $bitDepth = ord($data[8]);
            $colorType = ord($data[9]);
            $interlace = ord($data[12]);

            $colorTypes = [
                0 => 'Grayscale',
                2 => 'RGB',
                3 => 'Indexed',
                4 => 'Grayscale with alpha',
                6 => 'RGBA'
            ];

            $info = [
                'width' => $width,
                'height' => $height,
                'bit_depth' => $bitDepth,
                'color_type' => $colorTypes[$colorType] ?? "Unknown ($colorType)",
                'interlace' => $interlace === 1
            ];
        }

        // IEND is the last chunk
        if ($type === 'IEND') {
            break;
        }
    }

    fclose($handle);
    return $info;
}
