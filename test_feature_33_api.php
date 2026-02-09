<?php
/**
 * Feature #33 API Test
 * Tests file existence check via actual HTTP requests
 */

// Test content
$uniqueId = 'F33_TEST_' . time() . '_' . rand(1000, 9999);
$testHtml = '<div style="padding:20px;background:#3498db;color:white;">' . $uniqueId . '</div>';

echo "=== Feature #33: File Existence Check Test ===\n\n";
echo "Test HTML: $testHtml\n\n";

// Test 1: First request
echo "Test 1: First Request (should create new file)\n";
echo "-----------------------------------------------\n";

$payload1 = json_encode(['html_blocks' => [$testHtml]]);
$context1 = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/json',
        'content' => $payload1
    ]
]);

$result1 = file_get_contents('http://localhost/convert.php', false, $context1);
$data1 = json_decode($result1, true);

if ($data1 && $data1['success']) {
    $rendering1 = $data1['data']['rendering'];
    echo "Success: YES\n";
    echo "Cached: " . ($rendering1['cached'] ? 'YES' : 'NO') . "\n";
    echo "Engine: " . ($rendering1['engine'] ?? 'N/A') . "\n";
    echo "File: " . basename($rendering1['output_file']) . "\n";
    echo "Size: " . $rendering1['file_size'] . " bytes\n\n";
} else {
    echo "ERROR: First request failed\n\n";
    exit(1);
}

// Test 2: Second request (same content)
echo "Test 2: Second Request (should return cache)\n";
echo "---------------------------------------------\n";

$payload2 = json_encode(['html_blocks' => [$testHtml]]);
$context2 = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/json',
        'content' => $payload2
    ]
]);

$result2 = file_get_contents('http://localhost/convert.php', false, $context2);
$data2 = json_decode($result2, true);

if ($data2 && $data2['success']) {
    $rendering2 = $data2['data']['rendering'];
    echo "Success: YES\n";
    echo "Cached: " . ($rendering2['cached'] ? 'YES' : 'NO') . "\n";
    echo "Engine: " . ($rendering2['engine'] ?? 'N/A') . "\n";
    echo "File: " . basename($rendering2['output_file']) . "\n";
    echo "Size: " . $rendering2['file_size'] . " bytes\n\n";
} else {
    echo "ERROR: Second request failed\n\n";
    exit(1);
}

// Test 3: Verification
echo "Test 3: Verification\n";
echo "-------------------\n";

$tests = [
    'First request NOT cached' => !$rendering1['cached'],
    'Second request IS cached' => $rendering2['cached'],
    'Same file path' => $rendering1['output_file'] === $rendering2['output_file'],
    'Same file size' => $rendering1['file_size'] === $rendering2['file_size'],
    'File exists in filesystem' => file_exists($rendering1['output_file'])
];

$passing = 0;
foreach ($tests as $name => $passed) {
    echo ($passed ? '✅ PASS' : '❌ FAIL') . " - $name\n";
    if ($passed) $passing++;
}

echo "\nResult: $passing/" . count($tests) . " tests passed\n";

if ($passing === count($tests)) {
    echo "\n✅ Feature #33: PASSED\n";
    echo "File existence check is working correctly!\n";
    echo "Key findings:\n";
    echo "  - System checks file_exists() before rendering (line 1107)\n";
    echo "  - Cache hit returns immediately without re-rendering\n";
    echo "  - Same file path returned for identical content\n";

    // Cleanup
    $file1 = $rendering1['output_file'];
    if (file_exists($file1)) {
        unlink($file1);
        echo "\nCleanup: Deleted test file\n";
    }
    exit(0);
} else {
    echo "\n❌ Feature #33: FAILED\n";
    exit(1);
}
