<?php
/**
 * Feature #26 Test: GD Rendering
 *
 * This script tests the basic GD rendering functionality.
 */

define('TEST_MODE', true);
require_once __DIR__ . '/convert.php';

echo "=== Feature #26 Test: GD Rendering ===\n\n";

// Test data
$testCases = [
    [
        'name' => 'Simple text with GD_RENDER_TEST marker',
        'html' => '<div>GD_RENDER_TEST - Basic rendering works</div>',
        'css' => null
    ],
    [
        'name' => 'Text with CSS styling',
        'html' => '<p>GD_RENDER_TEST - Styled text</p>',
        'css' => 'color: #FF0000; font-size: 20px;'
    ],
    [
        'name' => 'Multi-line text',
        'html' => '<div><p>Line 1: GD_RENDER_TEST</p><p>Line 2: Multi-line rendering</p></div>',
        'css' => 'color: #0000FF; background: transparent;'
    ],
    [
        'name' => 'Text with colored background',
        'html' => '<h2>GD_RENDER_TEST with background</h2>',
        'css' => 'color: white; background: #333333;'
    ]
];

$passed = 0;
$failed = 0;
$results = [];

foreach ($testCases as $i => $test) {
    $testNum = $i + 1;
    echo "Test $testNum: {$test['name']}\n";

    try {
        // Prepare input
        $htmlBlocks = [$test['html']];
        $cssContent = $test['css'];

        // Generate hash
        $hash = generateContentHash($htmlBlocks, $cssContent);
        echo "  Hash: $hash\n";

        // Get output path
        $outputDir = getOutputDirectory();
        $outputPath = $outputDir . '/' . $hash . '.png';

        // Delete existing file if present
        if (file_exists($outputPath)) {
            unlink($outputPath);
        }

        // Render with GD
        $result = renderWithGD($htmlBlocks, $cssContent, $outputPath);

        // Verify results
        $testPassed = true;
        $messages = [];

        if (!$result['success']) {
            $testPassed = false;
            $messages[] = "FAILED: Rendering failed - {$result['error']}";
        } else {
            $messages[] = "SUCCESS: Rendering completed";

            // Check engine
            if ($result['engine'] === 'gd') {
                $messages[] = "✓ Engine is GD";
            } else {
                $testPassed = false;
                $messages[] = "✗ Wrong engine: {$result['engine']}";
            }

            // Check file exists
            if (file_exists($outputPath)) {
                $messages[] = "✓ File created: $outputPath";
            } else {
                $testPassed = false;
                $messages[] = "✗ File not created";
            }

            // Check file is valid PNG
            $imageInfo = getimagesize($outputPath);
            if ($imageInfo !== false) {
                $messages[] = "✓ Valid PNG image: {$imageInfo[0]}x{$imageInfo[1]}";
                $messages[] = "  MIME type: {$imageInfo['mime']}";

                // Check for transparency
                if (strpos($imageInfo['mime'], 'png') !== false) {
                    $messages[] = "✓ PNG format supports transparency";
                }
            } else {
                $testPassed = false;
                $messages[] = "✗ Invalid image file";
            }

            // Check text preview contains marker
            if (isset($result['text_preview']) && strpos($result['text_preview'], 'GD_RENDER_TEST') !== false) {
                $messages[] = "✓ Text contains test marker";
            } else {
                $messages[] = "⚠ Text preview: " . ($result['text_preview'] ?? 'N/A');
            }

            // Check dimensions are reasonable
            if ($result['width'] > 0 && $result['height'] > 0) {
                $messages[] = "✓ Valid dimensions: {$result['width']}x{$result['height']}";
            } else {
                $testPassed = false;
                $messages[] = "✗ Invalid dimensions";
            }

            // Check file size
            if ($result['file_size'] > 0) {
                $messages[] = "✓ File size: {$result['file_size']} bytes";
            } else {
                $testPassed = false;
                $messages[] = "✗ Empty file";
            }
        }

        foreach ($messages as $msg) {
            echo "  $msg\n";
        }

        if ($testPassed) {
            $passed++;
            $results[] = "Test $testNum: PASSED";
        } else {
            $failed++;
            $results[] = "Test $testNum: FAILED";
        }

    } catch (Exception $e) {
        $failed++;
        $results[] = "Test $testNum: FAILED - Exception: " . $e->getMessage();
        echo "  EXCEPTION: {$e->getMessage()}\n";
    }

    echo "\n";
}

// Summary
echo "=== Test Summary ===\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";
echo "Total: " . ($passed + $failed) . "\n";
echo "\n";

foreach ($results as $result) {
    echo "$result\n";
}

echo "\n";
exit($failed > 0 ? 1 : 0);
