<?php
/**
 * Feature #32: Saves PNG with hash filename
 * Test script to verify PNG files are saved using MD5 hash as filename
 */

// Include the main convert.php file
require_once __DIR__ . '/convert.php';

echo "========================================\n";
echo "Feature #32: Hash Filename Test\n";
echo "========================================\n\n";

// Test 1: Generate hash for test content
echo "Test 1: Generate hash for test content\n";
echo "----------------------------------------\n";

$testHtml = '<div class="test-content-32">FEATURE_32_HASH_TEST</div>';
$testCss = '.test-content-32 { color: #ff0000; font-size: 24px; }';

$generatedHash = generateContentHash([$testHtml], $testCss);

echo "HTML Content: " . substr($testHtml, 0, 50) . "...\n";
echo "CSS Content: " . substr($testCss, 0, 50) . "...\n";
echo "Generated Hash: $generatedHash\n";
echo "Hash Length: " . strlen($generatedHash) . "\n";
echo "Hash Format: " . (preg_match('/^[a-f0-9]{32}$/', $generatedHash) ? 'VALID' : 'INVALID') . "\n";

$test1Pass = ($generatedHash === md5($testHtml . $testCss));
echo "Result: " . ($test1Pass ? '✓ PASS' : '✗ FAIL') . "\n\n";

// Test 2: Verify hash is MD5 format
echo "Test 2: Verify hash is MD5 format\n";
echo "----------------------------------------\n";
echo "Expected: 32-character hexadecimal string\n";
echo "Actual: " . strlen($generatedHash) . " characters\n";
echo "Characters: " . (ctype_xdigit($generatedHash) ? 'all hexadecimal' : 'contains non-hex chars') . "\n";

$test2Pass = (preg_match('/^[a-f0-9]{32}$/', $generatedHash) === 1);
echo "Result: " . ($test2Pass ? '✓ PASS' : '✗ FAIL') . "\n\n";

// Test 3: Render HTML to PNG
echo "Test 3: Render HTML to PNG\n";
echo "----------------------------------------\n";

$outputDir = getOutputDirectory();
echo "Output Directory: $outputDir\n";

$expectedFilename = $generatedHash . '.png';
$expectedPath = $outputDir . '/' . $expectedFilename;

echo "Expected Filename: $expectedFilename\n";
echo "Expected Path: $expectedPath\n";

// Check if file already exists from previous test
$fileExistsBefore = file_exists($expectedPath);
if ($fileExistsBefore) {
    echo "File already exists (cache hit)\n";
    // Delete it to test fresh creation
    unlink($expectedPath);
    echo "Deleted existing file for fresh test\n";
}

// Render the image
$result = convertHtmlToPng([$testHtml], $testCss, $generatedHash);

echo "Rendering Result:\n";
echo "  Success: " . ($result['success'] ? 'true' : 'false') . "\n";
echo "  Cached: " . ($result['cached'] ? 'true' : 'false') . "\n";
if (isset($result['output_path'])) {
    echo "  Output Path: " . $result['output_path'] . "\n";
}
if (isset($result['file_size'])) {
    echo "  File Size: " . $result['file_size'] . " bytes\n";
}

$test3Pass = ($result['success'] === true && file_exists($expectedPath));
echo "Result: " . ($test3Pass ? '✓ PASS' : '✗ FAIL') . "\n\n";

// Test 4: Check /assets/media/rapidhtml2png/ directory
echo "Test 4: Check output directory\n";
echo "----------------------------------------\n";

$dirExists = is_dir($outputDir);
$dirReadable = is_readable($outputDir);
$dirWritable = is_writable($outputDir);

echo "Directory exists: " . ($dirExists ? 'true' : 'false') . "\n";
echo "Directory readable: " . ($dirReadable ? 'true' : 'false') . "\n";
echo "Directory writable: " . ($dirWritable ? 'true' : 'false') . "\n";

$files = glob($outputDir . '/*.png');
echo "PNG files in directory: " . count($files) . "\n";

$test4Pass = ($dirExists && $dirReadable && $dirWritable);
echo "Result: " . ($test4Pass ? '✓ PASS' : '✗ FAIL') . "\n\n";

// Test 5: Verify file exists named {hash}.png
echo "Test 5: Verify file exists with correct name\n";
echo "----------------------------------------\n";

$fileExists = file_exists($expectedPath);
$fileReadable = is_readable($expectedPath);

echo "File exists: " . ($fileExists ? 'true' : 'false') . "\n";
echo "File readable: " . ($fileReadable ? 'true' : 'false') . "\n";
echo "Full path: $expectedPath\n";

if ($fileExists) {
    $fileSize = filesize($expectedPath);
    $filePerms = substr(sprintf('%o', fileperms($expectedPath)), -4);
    echo "File size: $fileSize bytes\n";
    echo "File permissions: $filePerms\n";
}

$test5Pass = ($fileExists && $fileReadable);
echo "Result: " . ($test5Pass ? '✓ PASS' : '✗ FAIL') . "\n\n";

// Test 6: Confirm filename matches generated hash exactly
echo "Test 6: Confirm filename matches generated hash\n";
echo "----------------------------------------\n";

if ($fileExists) {
    $actualFilename = basename($expectedPath);
    $expectedFilename = $generatedHash . '.png';

    echo "Expected Filename: $expectedFilename\n";
    echo "Actual Filename: $actualFilename\n";

    $parts = pathinfo($actualFilename);
    $filenameWithoutExt = $parts['filename'];
    $extension = $parts['extension'];

    echo "Filename (without .png): $filenameWithoutExt\n";
    echo "Extension: $extension\n";

    $matchesHash = ($filenameWithoutExt === $generatedHash);
    $hasPngExt = ($extension === 'png');

    echo "Hash matches: " . ($matchesHash ? 'true' : 'false') . "\n";
    echo "Extension is .png: " . ($hasPngExt ? 'true' : 'false') . "\n";

    $test6Pass = ($matchesHash && $hasPngExt);
    echo "Result: " . ($test6Pass ? '✓ PASS' : '✗ FAIL') . "\n\n";
} else {
    $test6Pass = false;
    echo "Result: ✗ FAIL (file does not exist)\n\n";
}

// Summary
echo "========================================\n";
echo "Test Summary\n";
echo "========================================\n";

$tests = [
    'Generate hash for test content' => $test1Pass,
    'Verify hash is MD5 format' => $test2Pass,
    'Render HTML to PNG' => $test3Pass,
    'Check output directory' => $test4Pass,
    'Verify file exists with correct name' => $test5Pass,
    'Confirm filename matches hash' => $test6Pass,
];

$totalTests = count($tests);
$passedTests = count(array_filter($tests));

foreach ($tests as $testName => $passed) {
    echo ($passed ? '✓' : '✗') . " $testName\n";
}

echo "\n";
echo "Total: $passedTests/$totalTests tests passed (" . round(($passedTests/$totalTests)*100, 1) . "%)\n";
echo "Overall: " . ($passedTests === $totalTests ? '✓ ALL TESTS PASSED' : '✗ SOME TESTS FAILED') . "\n";
echo "========================================\n";

// Return exit code based on test results
exit($passedTests === $totalTests ? 0 : 1);
