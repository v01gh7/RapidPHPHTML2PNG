<?php
/**
 * Test Feature #23: Library Selection Logging
 *
 * This test verifies that the system logs which rendering library is being used.
 */

// Include the main convert.php which has the logging functions
require_once 'convert.php';

echo "=== Feature #23: Library Selection Logging Tests ===\n\n";

$testResults = [];
$totalTests = 0;

// Test 1: Log file path function exists
$totalTests++;
echo "Test 1: Check if getLibraryLogPath() function exists\n";
if (function_exists('getLibraryLogPath')) {
    $logPath = getLibraryLogPath();
    echo "  ✅ getLibraryLogPath() exists\n";
    echo "  📁 Log path: $logPath\n";

    // Verify the path is correct
    $expectedPath = __DIR__ . '/logs/library_selection.log';
    if ($logPath === $expectedPath) {
        echo "  ✅ Log path is correct: $expectedPath\n";
        $testResults[] = ['test' => 'Log path function', 'result' => 'PASS', 'details' => 'Returns correct path'];
    } else {
        echo "  ❌ Log path is incorrect. Expected: $expectedPath, Got: $logPath\n";
        $testResults[] = ['test' => 'Log path function', 'result' => 'FAIL', 'details' => 'Wrong path returned'];
    }
} else {
    echo "  ❌ getLibraryLogPath() function not found\n";
    $testResults[] = ['test' => 'Log path function', 'result' => 'FAIL', 'details' => 'Function not found'];
}
echo "\n";

// Test 2: Log function exists
$totalTests++;
echo "Test 2: Check if logLibrarySelection() function exists\n";
if (function_exists('logLibrarySelection')) {
    echo "  ✅ logLibrarySelection() function exists\n";
    $testResults[] = ['test' => 'Log function exists', 'result' => 'PASS', 'details' => 'Function found'];
} else {
    echo "  ❌ logLibrarySelection() function not found\n";
    $testResults[] = ['test' => 'Log function exists', 'result' => 'FAIL', 'details' => 'Function not found'];
}
echo "\n";

// Test 3: Log directory exists
$totalTests++;
echo "Test 3: Check if logs directory exists\n";
$logDir = __DIR__ . '/logs';
if (is_dir($logDir)) {
    echo "  ✅ Logs directory exists: $logDir\n";
    $testResults[] = ['test' => 'Log directory exists', 'result' => 'PASS', 'details' => 'Directory found'];
} else {
    echo "  ❌ Logs directory does not exist: $logDir\n";
    $testResults[] = ['test' => 'Log directory exists', 'result' => 'FAIL', 'details' => 'Directory not found'];
}
echo "\n";

// Test 4: Log file exists or can be created
$totalTests++;
echo "Test 4: Check if log file exists or can be created\n";
$logPath = __DIR__ . '/logs/library_selection.log';
if (file_exists($logPath)) {
    echo "  ✅ Log file exists: $logPath\n";
    $testResults[] = ['test' => 'Log file exists', 'result' => 'PASS', 'details' => 'File found'];
} else {
    echo "  ℹ️ Log file does not exist yet (will be created on first write)\n";
    // Try to create it
    if (touch($logPath)) {
        echo "  ✅ Log file can be created\n";
        $testResults[] = ['test' => 'Log file creation', 'result' => 'PASS', 'details' => 'File can be created'];
    } else {
        echo "  ❌ Log file cannot be created (permission issue)\n";
        $testResults[] = ['test' => 'Log file creation', 'result' => 'FAIL', 'details' => 'Permission denied'];
    }
}
echo "\n";

// Test 5: Log file is writable
$totalTests++;
echo "Test 5: Check if log file is writable\n";
if (is_writable($logPath)) {
    echo "  ✅ Log file is writable\n";
    $testResults[] = ['test' => 'Log file writable', 'result' => 'PASS', 'details' => 'File has write permissions'];
} else {
    echo "  ❌ Log file is not writable\n";
    $testResults[] = ['test' => 'Log file writable', 'result' => 'FAIL', 'details' => 'Permission denied'];
}
echo "\n";

// Test 6: Write a test log entry
$totalTests++;
echo "Test 6: Write a test log entry\n";
$testDetectionResults = [
    'best_library' => 'test_library',
    'detected_libraries' => [
        'test_library' => [
            'available' => true,
            'version' => '1.0.0-test',
            'info' => ['test' => 'data']
        ],
        'unavailable_library' => [
            'available' => false,
            'reason' => 'Test library not available'
        ]
    ]
];

$initialSize = file_exists($logPath) ? filesize($logPath) : 0;
try {
    logLibrarySelection('test_library', $testDetectionResults, 'Test selection for verification');

    clearstatcache(true, $logPath);
    $newSize = filesize($logPath);

    if ($newSize > $initialSize) {
        echo "  ✅ Log entry written successfully\n";
        echo "  📊 Size before: $initialSize bytes, after: $newSize bytes\n";
        $testResults[] = ['test' => 'Write log entry', 'result' => 'PASS', 'details' => 'Entry written, file grew'];
    } else {
        echo "  ❌ Log entry not written (file size unchanged)\n";
        $testResults[] = ['test' => 'Write log entry', 'result' => 'FAIL', 'details' => 'File size did not change'];
    }
} catch (Exception $e) {
    echo "  ❌ Error writing log entry: " . $e->getMessage() . "\n";
    $testResults[] = ['test' => 'Write log entry', 'result' => 'FAIL', 'details' => $e->getMessage()];
}
echo "\n";

// Test 7: Verify log entry format
$totalTests++;
echo "Test 7: Verify log entry format\n";
$logContent = file_get_contents($logPath);
if (strpos($logContent, '[20') !== false) {
    echo "  ✅ Log entry has timestamp\n";
    $hasTimestamp = true;
} else {
    echo "  ❌ Log entry missing timestamp\n";
    $hasTimestamp = false;
}

if (strpos($logContent, 'Selected Library:') !== false) {
    echo "  ✅ Log entry has library name\n";
    $hasLibraryName = true;
} else {
    echo "  ❌ Log entry missing library name\n";
    $hasLibraryName = false;
}

if (strpos($logContent, 'Reason:') !== false) {
    echo "  ✅ Log entry has reason\n";
    $hasReason = true;
} else {
    echo "  ❌ Log entry missing reason\n";
    $hasReason = false;
}

if (strpos($logContent, 'Detection Results:') !== false) {
    echo "  ✅ Log entry has detection results\n";
    $hasDetectionResults = true;
} else {
    echo "  ❌ Log entry missing detection results\n";
    $hasDetectionResults = false;
}

if ($hasTimestamp && $hasLibraryName && $hasReason && $hasDetectionResults) {
    $testResults[] = ['test' => 'Log entry format', 'result' => 'PASS', 'details' => 'All required fields present'];
} else {
    $testResults[] = ['test' => 'Log entry format', 'result' => 'FAIL', 'details' => 'Missing required fields'];
}
echo "\n";

// Test 8: Verify detailed library information in logs
$totalTests++;
echo "Test 8: Verify detailed library information in logs\n";
$hasAvailableStatus = strpos($logContent, 'AVAILABLE') !== false;
$hasUnavailableStatus = strpos($logContent, 'UNAVAILABLE') !== false;
$hasVersionInfo = strpos($logContent, 'Version:') !== false;
$hasReasonInfo = strpos($logContent, 'Reason:') !== false;

echo "  Available status: " . ($hasAvailableStatus ? '✅' : '❌') . "\n";
echo "  Unavailable status: " . ($hasUnavailableStatus ? '✅' : '❌') . "\n";
echo "  Version info: " . ($hasVersionInfo ? '✅' : '❌') . "\n";
echo "  Reason info: " . ($hasReasonInfo ? '✅' : '❌') . "\n";

if ($hasAvailableStatus && $hasUnavailableStatus) {
    echo "  ✅ Log includes availability status for all libraries\n";
    $testResults[] = ['test' => 'Library availability status', 'result' => 'PASS', 'details' => 'Status logged for all libraries'];
} else {
    echo "  ❌ Log missing availability status for some libraries\n";
    $testResults[] = ['test' => 'Library availability status', 'result' => 'FAIL', 'details' => 'Missing status info'];
}
echo "\n";

// Test 9: Test with actual library detection
$totalTests++;
echo "Test 9: Test logging with actual library detection\n";
if (function_exists('detectAvailableLibraries')) {
    $actualDetection = detectAvailableLibraries();
    $actualLibrary = $actualDetection['best_library'] ?? null;

    if ($actualLibrary) {
        echo "  ✅ Detected best library: " . strtoupper($actualLibrary) . "\n";

        // Log it
        $initialSize = filesize($logPath);
        $priorityOrder = ['wkhtmltoimage' => 1, 'imagemagick' => 2, 'gd' => 3];
        $priority = $priorityOrder[$actualLibrary] ?? 0;
        $reason = sprintf(
            'Selected based on priority (priority %d) - %s is the best available library',
            $priority,
            strtoupper($actualLibrary)
        );

        logLibrarySelection($actualLibrary, $actualDetection, $reason);

        clearstatcache(true, $logPath);
        $newSize = filesize($logPath);

        if ($newSize > $initialSize) {
            echo "  ✅ Actual detection logged successfully\n";
            $testResults[] = ['test' => 'Actual library logging', 'result' => 'PASS', 'details' => "Logged $actualLibrary library"];
        } else {
            echo "  ❌ Actual detection not logged\n";
            $testResults[] = ['test' => 'Actual library logging', 'result' => 'FAIL', 'details' => 'Log not written'];
        }
    } else {
        echo "  ⚠️  No library detected (expected in minimal PHP installation)\n";
        $testResults[] = ['test' => 'Actual library logging', 'result' => 'SKIP', 'details' => 'No library available'];
    }
} else {
    echo "  ❌ detectAvailableLibraries() function not found\n";
    $testResults[] = ['test' => 'Actual library logging', 'result' => 'FAIL', 'details' => 'Detection function missing'];
}
echo "\n";

// Test 10: Verify logs are helpful for debugging
$totalTests++;
echo "Test 10: Verify logs are helpful for debugging\n";
$logLines = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$lastEntry = array_slice($logLines, -10); // Get last 10 lines

echo "  📄 Last log entry preview:\n";
foreach (array_slice($lastEntry, 0, 8) as $line) {
    echo "    " . substr($line, 0, 80) . (strlen($line) > 80 ? '...' : '') . "\n";
}

$hasDebuggingInfo = true;
$missingInfo = [];

if (!preg_match('/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]/', implode("\n", $lastEntry))) {
    $hasDebuggingInfo = false;
    $missingInfo[] = 'timestamp';
}

if (!preg_match('/Selected Library:/i', implode("\n", $lastEntry))) {
    $hasDebuggingInfo = false;
    $missingInfo[] = 'library name';
}

if (!preg_match('/Detection Results:/i', implode("\n", $lastEntry))) {
    $hasDebuggingInfo = false;
    $missingInfo[] = 'detection results';
}

if ($hasDebuggingInfo) {
    echo "  ✅ Log contains all necessary debugging information\n";
    $testResults[] = ['test' => 'Debugging helpfulness', 'result' => 'PASS', 'details' => 'All debugging info present'];
} else {
    echo "  ❌ Log missing debugging info: " . implode(', ', $missingInfo) . "\n";
    $testResults[] = ['test' => 'Debugging helpfulness', 'result' => 'FAIL', 'details' => 'Missing: ' . implode(', ', $missingInfo)];
}
echo "\n";

// Summary
echo "=== Test Summary ===\n";
$passed = count(array_filter($testResults, function($r) { return $r['result'] === 'PASS'; }));
$failed = count(array_filter($testResults, function($r) { return $r['result'] === 'FAIL'; }));
$skipped = count(array_filter($testResults, function($r) { return $r['result'] === 'SKIP'; }));

echo "Total Tests: $totalTests\n";
echo "✅ Passed: $passed\n";
echo "❌ Failed: $failed\n";
echo "⏭️  Skipped: $skipped\n";
echo "Pass Rate: " . round(($passed / $totalTests) * 100, 1) . "%\n\n";

echo "Detailed Results:\n";
foreach ($testResults as $result) {
    $icon = $result['result'] === 'PASS' ? '✅' : ($result['result'] === 'FAIL' ? '❌' : '⏭️');
    echo "$icon {$result['test']}: {$result['result']} - {$result['details']}\n";
}

echo "\n=== Feature #23 Tests Complete ===\n";

// Return exit code based on results
exit($failed > 0 ? 1 : 0);
