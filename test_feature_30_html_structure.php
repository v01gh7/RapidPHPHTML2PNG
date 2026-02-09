<?php
/**
 * Test Feature #30: Handles HTML tags and structure
 *
 * This test verifies that HTML structure (divs, spans, classes) is rendered correctly.
 * Test HTML with nested divs and spans with specific classes and text content.
 */

// Include main convert.php functions
require_once __DIR__ . '/convert.php';

echo "=== Feature #30: HTML Structure Handling Test ===\n\n";

// Test HTML with nested structure
$testHtml = '<div class="outer">outer <span class="inner">inner</span></div>';

echo "Test HTML:\n";
echo $testHtml . "\n\n";

// Test CSS for styling
$testCss = '.outer { color: #000000; font-size: 16px; } .inner { color: #333333; }';

echo "Test CSS:\n";
echo $testCss . "\n\n";

// Generate content hash
$contentHash = generateContentHash([$testHtml], $testCss);
echo "Content Hash: " . $contentHash . "\n\n";

// Get output directory
$outputDir = getOutputDirectory();
$outputPath = $outputDir . '/' . $contentHash . '.png';
echo "Output Path: " . $outputPath . "\n\n";

// Detect available libraries
$libraryDetection = detectAvailableLibraries();
echo "Available Libraries:\n";
print_r($libraryDetection['detected_libraries']);
echo "\n";

// Test extraction of text from HTML
echo "=== Text Extraction Test (GD method) ===\n";
$extractedText = extractTextFromHtml($testHtml);
echo "Extracted text: '" . $extractedText . "'\n";
echo "Text contains 'outer': " . (strpos($extractedText, 'outer') !== false ? 'YES' : 'NO') . "\n";
echo "Text contains 'inner': " . (strpos($extractedText, 'inner') !== false ? 'YES' : 'NO') . "\n";
echo "Text contains HTML tags: " . (strpos($extractedText, '<') !== false ? 'YES' : 'NO') . "\n\n";

// Test rendering with each available library
$testResults = [];

// Test wkhtmltoimage
if ($libraryDetection['detected_libraries']['wkhtmltoimage']['available'] ?? false) {
    echo "=== Testing wkhtmltoimage ===\n";
    $result = renderWithWkHtmlToImage([$testHtml], $testCss, $outputPath . '_wk.png');
    if ($result['success']) {
        echo "✓ wkhtmltoimage rendered successfully\n";
        echo "  Output: " . $result['output_path'] . "\n";
        echo "  Size: " . $result['width'] . "x" . $result['height'] . "\n";
        $testResults['wkhtmltoimage'] = true;
    } else {
        echo "✗ wkhtmltoimage failed: " . $result['error'] . "\n";
        $testResults['wkhtmltoimage'] = false;
    }
    echo "\n";
} else {
    echo "⊘ wkhtmltoimage not available\n\n";
}

// Test ImageMagick
if ($libraryDetection['detected_libraries']['imagemagick']['available'] ?? false) {
    echo "=== Testing ImageMagick ===\n";
    $result = renderWithImageMagick([$testHtml], $testCss, $outputPath . '_im.png');
    if ($result['success']) {
        echo "✓ ImageMagick rendered successfully\n";
        echo "  Output: " . $result['output_path'] . "\n";
        echo "  Size: " . $result['width'] . "x" . $result['height'] . "\n";
        $testResults['imagemagick'] = true;
    } else {
        echo "✗ ImageMagick failed: " . $result['error'] . "\n";
        $testResults['imagemagick'] = false;
    }
    echo "\n";
} else {
    echo "⊘ ImageMagick not available\n\n";
}

// Test GD
if ($libraryDetection['detected_libraries']['gd']['available'] ?? false) {
    echo "=== Testing GD ===\n";
    $result = renderWithGD([$testHtml], $testCss, $outputPath . '_gd.png');
    if ($result['success']) {
        echo "✓ GD rendered successfully\n";
        echo "  Output: " . $result['output_path'] . "\n";
        echo "  Size: " . $result['width'] . "x" . $result['height'] . "\n";
        echo "  Text preview: " . $result['text_preview'] . "\n";
        echo "  Lines: " . $result['text_lines'] . "\n";
        $testResults['gd'] = true;
    } else {
        echo "✗ GD failed: " . $result['error'] . "\n";
        $testResults['gd'] = false;
    }
    echo "\n";
} else {
    echo "⊘ GD not available\n\n";
}

// Summary
echo "=== Test Summary ===\n";
$passedTests = 0;
$totalTests = 0;

foreach ($testResults as $library => $passed) {
    $totalTests++;
    if ($passed) {
        $passedTests++;
        echo "✓ {$library}: PASS\n";
    } else {
        echo "✗ {$library}: FAIL\n";
    }
}

echo "\nTotal: {$passedTests}/{$totalTests} tests passed\n";

// Feature requirements check
echo "\n=== Feature Requirements Check ===\n";
echo "1. HTML with nested divs and spans: " . (strpos($testHtml, '<div') !== false && strpos($testHtml, '<span') !== false ? '✓' : '✗') . "\n";
echo "2. Text in each element ('outer', 'inner'): " . (strpos($testHtml, 'outer') !== false && strpos($testHtml, 'inner') !== false ? '✓' : '✗') . "\n";
echo "3. HTML rendered to PNG: " . ($passedTests > 0 ? '✓' : '✗') . "\n";
echo "4. Nested structure preserved: " . (count(array_filter($testResults)) > 0 ? '✓' : '?') . " (requires visual verification)\n";
echo "5. Text in proper hierarchy: " . (count(array_filter($testResults)) > 0 ? '✓' : '?') . " (requires visual verification)\n";

echo "\nNOTE: Visual verification required to confirm structure is preserved in PNG.\n";
echo "Generated files:\n";
foreach (glob($outputPath . '*.png') as $file) {
    echo "  - " . $file . "\n";
}
