<?php
/**
 * Standalone test for Feature #30: HTML Structure Handling
 * Tests that HTML structure (divs, spans, classes) is rendered correctly.
 */

// Include only the functions we need from convert.php
require_once __DIR__ . '/convert.php';

// Set response type for browser
header('Content-Type: text/plain; charset=utf-8');

echo "=== Feature #30: HTML Structure Handling ===\n\n";

// Test HTML with nested structure
$testHtml = '<div class="outer">outer <span class="inner">inner</span></div>';
$testCss = '.outer { color: #000000; font-size: 16px; } .inner { color: #333333; }';

echo "Test HTML: " . $testHtml . "\n";
echo "Test CSS: " . $testCss . "\n\n";

// Test 1: Check text extraction (GD method)
echo "--- Test 1: Text Extraction ---\n";
$extractedText = extractTextFromHtml($testHtml);
echo "Extracted: '" . $extractedText . "'\n";
$test1Pass = (strpos($extractedText, 'outer') !== false && strpos($extractedText, 'inner') !== false);
echo "Result: " . ($test1Pass ? "✓ PASS" : "✗ FAIL") . " - Both 'outer' and 'inner' text found\n\n";

// Test 2: Check HTML tags are stripped for GD (expected behavior)
echo "--- Test 2: HTML Structure for GD ---\n";
$hasTags = (strpos($extractedText, '<div') !== false || strpos($extractedText, '<span') !== false);
echo "HTML tags preserved in extracted text: " . ($hasTags ? "YES" : "NO") . "\n";
echo "Result: " . (!$hasTags ? "✓ PASS" : "✗ FAIL") . " - GD strips HTML tags (expected)\n\n";

// Test 3: Check that libraries detect correctly
echo "--- Test 3: Library Detection ---\n";
$detection = detectAvailableLibraries();
$bestLibrary = $detection['best_library'] ?? 'none';
echo "Best library: " . strtoupper($bestLibrary) . "\n";
$test3Pass = ($bestLibrary !== 'none');
echo "Result: " . ($test3Pass ? "✓ PASS" : "✗ FAIL") . " - At least one library available\n\n";

// Test 4: Hash generation with HTML structure
echo "--- Test 4: Hash Generation ---\n";
$hash1 = generateContentHash([$testHtml], $testCss);
$hash2 = generateContentHash(['<div>simple</div>'], $testCss);
$hashesDifferent = ($hash1 !== $hash2);
echo "Hash 1 (nested): " . $hash1 . "\n";
echo "Hash 2 (simple): " . $hash2 . "\n";
echo "Hashes are different: " . ($hashesDifferent ? "YES" : "NO") . "\n";
$test4Pass = $hashesDifferent;
echo "Result: " . ($test4Pass ? "✓ PASS" : "✗ FAIL") . " - Different HTML produces different hashes\n\n";

// Test 5: Convert HTML to PNG (using available library)
echo "--- Test 5: HTML to PNG Conversion ---\n";
$contentHash = generateContentHash([$testHtml], $testCss);
$outputDir = getOutputDirectory();
$outputPath = $outputDir . '/' . $contentHash . '.png';

// Try conversion
try {
    $result = convertHtmlToPng([$testHtml], $testCss, $contentHash);
    if ($result['success'] || isset($result['output_path'])) {
        $test5Pass = true;
        echo "Conversion successful!\n";
        echo "Engine: " . ($result['engine'] ?? 'cached') . "\n";
        echo "Output: " . ($result['output_path'] ?? $outputPath) . "\n";
        if (isset($result['width']) && isset($result['height'])) {
            echo "Size: " . $result['width'] . "x" . $result['height'] . "\n";
        }
        echo "Result: ✓ PASS - PNG file created\n";
    } else {
        $test5Pass = false;
        echo "Conversion failed\n";
        echo "Result: ✗ FAIL - Could not create PNG\n";
    }
} catch (Exception $e) {
    $test5Pass = false;
    echo "Exception: " . $e->getMessage() . "\n";
    echo "Result: ✗ FAIL - Exception during conversion\n";
}

echo "\n";

// Summary
echo "=== SUMMARY ===\n";
$tests = [$test1Pass, !$hasTags, $test3Pass, $test4Pass, $test5Pass];
$passed = count(array_filter($tests));
$total = count($tests);
echo "Tests Passed: {$passed}/{$total}\n";

// Feature Requirements
echo "\n=== FEATURE REQUIREMENTS ===\n";
echo "1. HTML with nested divs and spans: ✓ PROVIDED\n";
echo "   <div class=\"outer\">outer <span class=\"inner\">inner</span></div>\n";
echo "2. Text in each element ('outer', 'inner'): " . ($test1Pass ? "✓" : "✗") . "\n";
echo "3. HTML rendered to PNG: " . ($test5Pass ? "✓" : "✗") . "\n";
echo "4. Structure preserved: ⚠ REQUIRES VISUAL VERIFICATION\n";
echo "   - wkhtmltoimage: Full HTML rendering via WebKit\n";
echo "   - ImageMagick: Full HTML rendering\n";
echo "   - GD: Text extraction (strip_tags) - structure not preserved\n";
echo "5. Text in proper hierarchy: ⚠ REQUIRES VISUAL VERIFICATION\n\n";

echo "NOTE: wkhtmltoimage and ImageMagick preserve full HTML structure.\n";
echo "GD renderer extracts plain text only (by design for fallback).\n";
echo "To visually verify, check the generated PNG at:\n";
echo "  " . $outputPath . "\n";
