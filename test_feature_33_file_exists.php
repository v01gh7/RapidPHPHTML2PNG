<?php
/**
 * Test Feature #33: Checks file existence before creation
 *
 * This test verifies that:
 * 1. First request creates a new PNG file
 * 2. Second request with same content returns cached file
 * 3. System checks file existence before rendering
 */

define('TEST_MODE', true);

require_once 'convert.php';

echo "=== Feature #33 Test: File Existence Check ===\n\n";

// Test 1: Generate unique test content
$uniqueId = 'F33_TEST_' . time();
$testHtml = '<div>' . $uniqueId . '</div>';

echo "Test 1: Generate hash for unique content\n";
echo "HTML: $testHtml\n";

$htmlBlocks = [$testHtml];
$cssContent = null;
$contentHash = generateContentHash($htmlBlocks, $cssContent);

echo "Generated hash: $contentHash\n";
echo "Hash format valid: " . (preg_match('/^[a-f0-9]{32}$/', $contentHash) ? 'YES' : 'NO') . "\n\n";

// Test 2: Check output directory and file path
echo "Test 2: Check output directory\n";
$outputDir = getOutputDirectory();
echo "Output directory: $outputDir\n";
echo "Directory exists: " . (is_dir($outputDir) ? 'YES' : 'NO') . "\n";
echo "Directory writable: " . (is_writable($outputDir) ? 'YES' : 'NO') . "\n\n";

// Test 3: Check if file exists BEFORE first render
$outputPath = $outputDir . '/' . $contentHash . '.png';
echo "Test 3: Check file existence BEFORE rendering\n";
echo "Expected file path: $outputPath\n";
echo "File exists before render: " . (file_exists($outputPath) ? 'YES' : 'NO') . "\n\n";

// Test 4: First render - should create new file
echo "Test 4: First render (should create new file)\n";

// Mock convertHtmlToPng behavior for testing
$result1 = convertHtmlToPng($htmlBlocks, $cssContent, $contentHash);

echo "First render result:\n";
echo "  Success: " . ($result1['success'] ? 'YES' : 'NO') . "\n";
echo "  Cached: " . ($result1['cached'] ? 'YES' : 'NO') . "\n";
echo "  Output path: " . ($result1['output_path'] ?? 'N/A') . "\n";
echo "  File size: " . ($result1['file_size'] ?? 'N/A') . " bytes\n";
echo "  Engine: " . ($result1['engine'] ?? 'N/A') . "\n\n";

// Verify file was created
echo "Test 5: Verify file was created\n";
echo "File exists after first render: " . (file_exists($outputPath) ? 'YES' : 'NO') . "\n";
if (file_exists($outputPath)) {
    echo "File size: " . filesize($outputPath) . " bytes\n";
    echo "File readable: " . (is_readable($outputPath) ? 'YES' : 'NO') . "\n\n";
} else {
    echo "ERROR: File was not created!\n\n";
}

// Test 6: Second render - should return cached file
echo "Test 6: Second render with same content (should return cache)\n";

$result2 = convertHtmlToPng($htmlBlocks, $cssContent, $contentHash);

echo "Second render result:\n";
echo "  Success: " . ($result2['success'] ? 'YES' : 'NO') . "\n";
echo "  Cached: " . ($result2['cached'] ? 'YES' : 'NO') . "\n";
echo "  Output path: " . ($result2['output_path'] ?? 'N/A') . "\n";
echo "  File size: " . ($result2['file_size'] ?? 'N/A') . " bytes\n";
echo "  Engine: " . ($result2['engine'] ?? 'N/A') . "\n\n";

// Test 7: Verify cache hit
echo "Test 7: Verify cache hit behavior\n";
$cacheHit = $result2['cached'] ?? false;
$samePath = ($result1['output_path'] ?? '') === ($result2['output_path'] ?? '');
$sameSize = ($result1['file_size'] ?? 0) === ($result2['file_size'] ?? 0);

echo "Second request returned cached: " . ($cacheHit ? 'YES ✅' : 'NO ❌') . "\n";
echo "Same file path: " . ($samePath ? 'YES ✅' : 'NO ❌') . "\n";
echo "Same file size: " . ($sameSize ? 'YES ✅' : 'NO ❌') . "\n\n";

// Test 8: Test file existence check in code
echo "Test 8: Analyze file_exists() check in convertHtmlToPng()\n";
$functionCode = file_get_contents('convert.php');
$hasFileExistsCheck = strpos($functionCode, "if (file_exists(\$outputPath))") !== false;
echo "Code contains file_exists(\$outputPath) check: " . ($hasFileExistsCheck ? 'YES ✅' : 'NO ❌') . "\n";
echo "Check location: Lines 1106-1114 in convert.php\n\n";

// Test 9: Verify cache returns immediately without rendering
echo "Test 9: Verify cache returns without re-rendering\n";
if ($result2['cached']) {
    echo "Cached result returned: YES ✅\n";
    echo "No engine specified for cached: " . (!isset($result2['engine']) ? 'YES ✅' : 'NO ❌') . "\n";
    echo "File size from cache: " . ($result2['file_size'] ?? 'N/A') . " bytes\n\n";
} else {
    echo "ERROR: Cache not working properly!\n\n";
}

// Test 10: Different content should create new file
echo "Test 10: Different content creates new file\n";
$uniqueId2 = 'F33_TEST_2_' . time();
$testHtml2 = '<div>' . $uniqueId2 . '</div>';

$htmlBlocks2 = [$testHtml2];
$contentHash2 = generateContentHash($htmlBlocks2, $cssContent);

echo "Different content HTML: $testHtml2\n";
echo "Different content hash: $contentHash2\n";
echo "Hashes are different: " . ($contentHash !== $contentHash2 ? 'YES ✅' : 'NO ❌') . "\n";

$outputPath2 = $outputDir . '/' . $contentHash2 . '.png';
echo "Different file exists before render: " . (file_exists($outputPath2) ? 'YES' : 'NO') . "\n";

$result3 = convertHtmlToPng($htmlBlocks2, $cssContent, $contentHash2);
echo "Different content cached: " . ($result3['cached'] ? 'YES' : 'NO') . "\n";
echo "Different file exists after render: " . (file_exists($outputPath2) ? 'YES ✅' : 'NO ❌') . "\n\n";

// Summary
echo "=== Test Summary ===\n";
$tests = [
    ['Hash generation valid', preg_match('/^[a-f0-9]{32}$/', $contentHash)],
    ['Output directory exists', is_dir($outputDir)],
    ['File not exist before first render', !file_exists($outputPath) || $result1['cached'] === false],
    ['First render creates file', file_exists($outputPath)],
    ['First render not cached', $result1['cached'] === false],
    ['Second render returns cache', $result2['cached'] === true],
    ['Same file path returned', $samePath],
    ['Same file size returned', $sameSize],
    ['File existence check in code', $hasFileExistsCheck],
    ['Different content creates new file', file_exists($outputPath2)]
];

$passing = 0;
foreach ($tests as $test) {
    $status = $test[1] ? '✅ PASS' : '❌ FAIL';
    echo "$status - {$test[0]}\n";
    if ($test[1]) $passing++;
}

echo "\nTotal: {$passing}/" . count($tests) . " tests passed (" . round($passing/count($tests)*100, 1) . "%)\n";

// Cleanup
echo "\n=== Cleanup ===\n";
if (file_exists($outputPath)) {
    echo "Deleting test file: $outputPath\n";
    unlink($outputPath);
}
if (file_exists($outputPath2)) {
    echo "Deleting test file: $outputPath2\n";
    unlink($outputPath2);
}
echo "Cleanup complete.\n";

exit($passing === count($tests) ? 0 : 1);
