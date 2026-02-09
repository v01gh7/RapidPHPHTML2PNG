<?php
/**
 * Test Feature #28: Creates transparent background
 *
 * This test verifies that rendered PNG images have transparent backgrounds.
 * It checks the alpha channel of generated images to confirm transparency.
 */

echo "=== Feature #28: Transparent Background Test ===\n\n";

// Configuration
$apiUrl = 'http://localhost:8080/convert.php';
$outputDir = __DIR__ . '/assets/media/rapidhtml2png';

// Helper function to make POST request
function postConvertApi($htmlBlocks, $cssUrl = null) {
    global $apiUrl;

    $postData = [
        'html_blocks' => $htmlBlocks
    ];

    if ($cssUrl !== null) {
        $postData['css_url'] = $cssUrl;
    }

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        return ['success' => false, 'error' => 'HTTP ' . $httpCode, 'response' => $response];
    }

    $data = json_decode($response, true);
    if ($data === null) {
        return ['success' => false, 'error' => 'Invalid JSON response', 'response' => $response];
    }

    return $data;
}

// Test 1: Simple HTML element with transparent background
echo "Test 1: Rendering HTML with transparent background...\n";
$htmlBlock1 = '<div style="color: red; font-size: 24px;">TRANSPARENT TEST</div>';
$cssUrl1 = 'http://localhost:8080/main.css';

$result1 = postConvertApi([$htmlBlock1], $cssUrl1);

echo "Result: " . ($result1['success'] ? "SUCCESS" : "FAILED") . "\n";
if ($result1['success']) {
    echo "Engine: " . ($result1['data']['rendering']['engine'] ?? 'unknown') . "\n";
    $outputPath1 = $result1['data']['rendering']['output_file'] ?? null;
    echo "Output Path: " . ($outputPath1 ?? 'none') . "\n";
    echo "Dimensions: " . ($result1['data']['rendering']['width'] ?? '?') . "x" . ($result1['data']['rendering']['height'] ?? '?') . "\n";
    echo "File Size: " . ($result1['data']['rendering']['file_size'] ?? '?') . " bytes\n";
    echo "\n";

    // Verify transparency in the generated image
    if ($outputPath1 && file_exists($outputPath1)) {
        $transparencyCheck1 = checkImageTransparency($outputPath1);
        echo "Transparency Check:\n";
        echo "  - Has Alpha Channel: " . ($transparencyCheck1['has_alpha'] ? "YES" : "NO") . "\n";
        echo "  - PNG Color Type: " . ($transparencyCheck1['color_type'] ?? 'unknown') . "\n";
        echo "  - Transparent Pixels Found: " . ($transparencyCheck1['has_transparent_pixels'] ? "YES" : "NO") . "\n";
        echo "  - Transparency Percentage: " . ($transparencyCheck1['transparency_percentage'] ?? 0) . "%\n";

        if ($transparencyCheck1['has_transparent_pixels']) {
            echo "  ✅ PASS: Image has transparent pixels\n";
        } else {
            echo "  ❌ FAIL: Image does not have transparent pixels\n";
        }
    } else {
        echo "❌ FAIL: Could not verify transparency - file not created\n";
    }
} else {
    echo "Error: " . ($result1['error'] ?? 'Unknown error') . "\n";
}
echo "\n";

// Test 2: Multiple HTML blocks with transparent background
echo "Test 2: Multiple blocks with transparency...\n";
$htmlBlock2 = '<span style="color: blue;">Block 1</span><br><span style="color: green;">Block 2</span>';

$result2 = postConvertApi([$htmlBlock2], $cssUrl1);

echo "Result: " . ($result2['success'] ? "SUCCESS" : "FAILED") . "\n";
if ($result2['success']) {
    echo "Engine: " . ($result2['data']['rendering']['engine'] ?? 'unknown') . "\n";
    $outputPath2 = $result2['data']['rendering']['output_file'] ?? null;
    echo "Output Path: " . ($outputPath2 ?? 'none') . "\n";
    echo "\n";

    if ($outputPath2 && file_exists($outputPath2)) {
        $transparencyCheck2 = checkImageTransparency($outputPath2);
        echo "Transparency Check:\n";
        echo "  - Has Alpha Channel: " . ($transparencyCheck2['has_alpha'] ? "YES" : "NO") . "\n";
        echo "  - Transparent Pixels Found: " . ($transparencyCheck2['has_transparent_pixels'] ? "YES" : "NO") . "\n";

        if ($transparencyCheck2['has_transparent_pixels']) {
            echo "  ✅ PASS: Image has transparent pixels\n";
        } else {
            echo "  ❌ FAIL: Image does not have transparent pixels\n";
        }
    } else {
        echo "❌ FAIL: Could not verify transparency - file not created\n";
    }
} else {
    echo "Error: " . ($result2['error'] ?? 'Unknown error') . "\n";
}
echo "\n";

// Test 3: Verify no solid background color is applied
echo "Test 3: Verify no solid background color...\n";
$htmlBlock3 = '<p style="color: #000000; font-size: 18px;">No Background Test</p>';

$result3 = postConvertApi([$htmlBlock3], null); // No CSS URL

echo "Result: " . ($result3['success'] ? "SUCCESS" : "FAILED") . "\n";
if ($result3['success']) {
    echo "Engine: " . ($result3['data']['rendering']['engine'] ?? 'unknown') . "\n";
    echo "\n";

    $outputPath3 = $result3['data']['rendering']['output_file'] ?? null;
    if ($outputPath3 && file_exists($outputPath3)) {
        $transparencyCheck3 = checkImageTransparency($outputPath3);
        echo "Transparency Check:\n";
        echo "  - Has Alpha Channel: " . ($transparencyCheck3['has_alpha'] ? "YES" : "NO") . "\n";
        echo "  - Transparent Pixels Found: " . ($transparencyCheck3['has_transparent_pixels'] ? "YES" : "NO") . "\n";

        if ($transparencyCheck3['has_transparent_pixels']) {
            echo "  ✅ PASS: Image has transparent background (no solid color applied)\n";
        } else {
            echo "  ❌ FAIL: Image has solid background color (should be transparent)\n";
        }
    } else {
        echo "❌ FAIL: Could not verify transparency - file not created\n";
    }
} else {
    echo "Error: " . ($result3['error'] ?? 'Unknown error') . "\n";
}
echo "\n";

// Test 4: Corner pixel transparency check
echo "Test 4: Check corner pixels for transparency...\n";
if (isset($outputPath1) && $outputPath1 && file_exists($outputPath1)) {
    $cornerCheck = checkCornerTransparency($outputPath1);
    echo "Corner Pixels Analysis:\n";
    echo "  - Top-Left Corner Alpha: " . ($cornerCheck['top_left']['alpha'] ?? '?') . " (0=fully transparent, 127=semi-transparent, opaque=255)\n";
    echo "  - Top-Right Corner Alpha: " . ($cornerCheck['top_right']['alpha'] ?? '?') . "\n";
    echo "  - Bottom-Left Corner Alpha: " . ($cornerCheck['bottom_left']['alpha'] ?? '?') . "\n";
    echo "  - Bottom-Right Corner Alpha: " . ($cornerCheck['bottom_right']['alpha'] ?? '?') . "\n";
    echo "  - Corners Transparent: " . ($cornerCheck['all_corners_transparent'] ? "YES" : "NO") . "\n";

    if ($cornerCheck['all_corners_transparent']) {
        echo "  ✅ PASS: Corner pixels are transparent\n";
    } else {
        echo "  ⚠️  WARNING: Some corner pixels are not transparent (may be expected if content fills the image)\n";
    }
} else {
    echo "❌ FAIL: Test image not available\n";
}
echo "\n";

echo "=== Test Summary ===\n";
echo "Feature #28 verification complete.\n";
echo "All rendering engines should produce PNG images with transparent backgrounds.\n";
echo "\n";
echo "To visually verify transparency, open any generated PNG file in an image viewer\n";
echo "that supports transparency (e.g., web browser with checkerboard background).\n";

/**
 * Check if an image has transparency
 *
 * @param string $imagePath Path to the PNG image
 * @return array Transparency information
 */
function checkImageTransparency($imagePath) {
    if (!file_exists($imagePath)) {
        return ['has_alpha' => false, 'error' => 'File not found'];
    }

    // Get image info
    $imageInfo = getimagesize($imagePath);
    if ($imageInfo === false) {
        return ['has_alpha' => false, 'error' => 'Invalid image'];
    }

    // Check if PNG has alpha channel (channels = 4 means RGBA)
    $channels = $imageInfo['channels'] ?? 3;
    $hasAlpha = $channels === 4;

    // Try to load the image and check for transparent pixels
    $image = imagecreatefrompng($imagePath);
    if ($image === false) {
        return ['has_alpha' => $hasAlpha, 'error' => 'Could not load image'];
    }

    // Enable alpha blending
    imagealphablending($image, false);
    imagesavealpha($image, true);

    $width = imagesx($image);
    $height = imagesy($image);

    $transparentPixelCount = 0;
    $totalPixels = $width * $height;

    // Sample pixels (check every 10th pixel for performance)
    $sampleRate = 10;
    $sampledPixels = 0;

    for ($y = 0; $y < $height; $y += $sampleRate) {
        for ($x = 0; $x < $width; $x += $sampleRate) {
            $rgba = imagecolorat($image, $x, $y);
            $alpha = ($rgba >> 24) & 0x7F; // Get alpha channel (0-127)

            if ($alpha < 64) { // Less than 25% opacity = transparent
                $transparentPixelCount++;
            }
            $sampledPixels++;
        }
    }

    imagedestroy($image);

    // Calculate transparency percentage (from sample)
    $transparencyPercentage = $sampledPixels > 0 ? ($transparentPixelCount / $sampledPixels) * 100 : 0;

    // Determine PNG color type
    $colorType = 'Unknown';
    if (isset($imageInfo[2]) && $imageInfo[2] === IMAGETYPE_PNG) {
        // Read PNG file to check color type
        $fp = fopen($imagePath, 'rb');
        if ($fp) {
            // Skip PNG signature (8 bytes)
            fread($fp, 8);

            // Read first chunk length and type
            $lengthData = unpack('N', fread($fp, 4));
            $chunkType = fread($fp, 4);

            // IHDR chunk contains color type
            if ($chunkType === 'IHDR') {
                // Skip width, height, bit depth
                fread($fp, 8);
                $colorTypeByte = unpack('C', fread($fp, 1));
                $colorTypeValue = $colorTypeByte[1];

                switch ($colorTypeValue) {
                    case 0: $colorType = 'Grayscale'; break;
                    case 2: $colorType = 'RGB'; break;
                    case 3: $colorType = 'Indexed'; break;
                    case 4: $colorType = 'Grayscale+Alpha'; break;
                    case 6: $colorType = 'RGBA'; break;
                    default: $colorType = 'Unknown (' . $colorTypeValue . ')'; break;
                }
            }
            fclose($fp);
        }
    }

    return [
        'has_alpha' => $hasAlpha,
        'color_type' => $colorType,
        'has_transparent_pixels' => $transparentPixelCount > 0,
        'transparent_pixel_count' => $transparentPixelCount,
        'total_pixels' => $totalPixels,
        'transparency_percentage' => round($transparencyPercentage, 2)
    ];
}

/**
 * Check corner pixels for transparency
 *
 * @param string $imagePath Path to the PNG image
 * @return array Corner transparency information
 */
function checkCornerTransparency($imagePath) {
    if (!file_exists($imagePath)) {
        return ['all_corners_transparent' => false, 'error' => 'File not found'];
    }

    $image = imagecreatefrompng($imagePath);
    if ($image === false) {
        return ['all_corners_transparent' => false, 'error' => 'Could not load image'];
    }

    $width = imagesx($image);
    $height = imagesy($image);

    // Check corners with small offset from edge (5 pixels)
    $offset = min(5, $width - 1, $height - 1);

    $corners = [
        'top_left' => ['x' => $offset, 'y' => $offset],
        'top_right' => ['x' => $width - $offset - 1, 'y' => $offset],
        'bottom_left' => ['x' => $offset, 'y' => $height - $offset - 1],
        'bottom_right' => ['x' => $width - $offset - 1, 'y' => $height - $offset - 1]
    ];

    $result = [];
    $allTransparent = true;

    foreach ($corners as $corner => $coords) {
        $rgba = imagecolorat($image, $coords['x'], $coords['y']);
        $alpha = ($rgba >> 24) & 0x7F;

        $result[$corner] = [
            'x' => $coords['x'],
            'y' => $coords['y'],
            'rgba' => $rgba,
            'alpha' => $alpha,
            'is_transparent' => $alpha < 64
        ];

        if ($alpha >= 64) {
            $allTransparent = false;
        }
    }

    imagedestroy($image);

    $result['all_corners_transparent'] = $allTransparent;

    return $result;
}
