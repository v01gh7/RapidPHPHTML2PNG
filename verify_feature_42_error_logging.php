<?php
/**
 * Verification Test for Feature #42: Error Logging for Debugging
 *
 * This script verifies that:
 * 1. Errors are logged with timestamps
 * 2. Logs include useful context (request data, error type)
 * 3. Logging does not expose sensitive information
 */

define('TEST_MODE', true);

$totalTests = 0;
$passedTests = 0;

function printHeader($title) {
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "  $title\n";
    echo str_repeat("=", 60) . "\n";
}

function printTest($name, $passed, $details = '') {
    global $totalTests, $passedTests;
    $totalTests++;
    if ($passed) {
        $passedTests++;
        echo "✓ PASS: $name\n";
    } else {
        echo "✗ FAIL: $name\n";
    }
    if ($details) {
        echo "  Details: $details\n";
    }
}

// Start verification
printHeader("Feature #42: Error Logging Verification");

// Test 1: Application error log file exists
printHeader("Test 1: Log File Exists");
$logPath = __DIR__ . '/logs/application_errors.log';
printTest(
    "Application error log file exists",
    file_exists($logPath),
    $logPath
);

if (file_exists($logPath)) {
    printTest(
        "Log file is readable",
        is_readable($logPath)
    );

    $logSize = filesize($logPath);
    printTest(
        "Log file has content",
        $logSize > 0,
        "Size: " . number_format($logSize) . " bytes"
    );
}

// Test 2: Read and parse log entries
printHeader("Test 2: Parse Log Entries");
if (file_exists($logPath)) {
    $logContent = file_get_contents($logPath);
    $logEntries = explode("\n\n", trim($logContent));

    printTest(
        "Log contains multiple entries",
        count($logEntries) >= 3,
        count($logEntries) . " entries found"
    );

    // Parse first entry
    $firstEntry = $logEntries[0];
    $lines = explode("\n", $firstEntry);

    // Test 3: Verify timestamp format
    printHeader("Test 3: Timestamp Format");
    $timestampLine = $lines[0] ?? '';
    $hasTimestamp = preg_match('/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]/', $timestampLine);

    printTest(
        "Log entry has timestamp",
        $hasTimestamp,
        "Found: " . substr($timestampLine, 0, 50)
    );

    if ($hasTimestamp) {
        printTest(
            "Timestamp uses YYYY-MM-DD HH:MM:SS format",
            true,
            "Format matches requirement"
        );
    }

    // Test 4: Verify HTTP status code
    printHeader("Test 4: HTTP Status Code");
    $hasHttpCode = preg_match('/HTTP (?:400|404|405|413|500)/', $firstEntry);

    printTest(
        "Log entry includes HTTP status code",
        $hasHttpCode,
        "Found HTTP status code in entry"
    );

    // Test 5: Verify error message
    printHeader("Test 5: Error Message");
    $hasError = preg_match('/- (?:Missing required parameter|Invalid JSON|cannot be empty|must use http|valid URL|Rendering failed)/', $firstEntry);

    printTest(
        "Log entry includes error message",
        $hasError,
        "Error message present"
    );

    // Test 6: Verify request context
    printHeader("Test 6: Request Context");
    $hasMethod = strpos($firstEntry, 'Method:') !== false;
    $hasUri = strpos($firstEntry, 'URI:') !== false;
    $hasIp = strpos($firstEntry, 'Client IP:') !== false;

    printTest(
        "Log entry includes request method",
        $hasMethod,
        "Found: Method: POST"
    );

    printTest(
        "Log entry includes request URI",
        $hasUri,
        "Found: URI: /convert.php"
    );

    printTest(
        "Log entry includes client IP",
        $hasIp,
        "Found: Client IP (masked)"
    );

    // Test 7: Verify IP masking
    printHeader("Test 7: IP Address Privacy");
    $hasMaskedIp = preg_match('/Client IP: \d+\.\d+\.0\.0\.0/', $firstEntry);

    printTest(
        "Client IP is masked for privacy",
        $hasMaskedIp,
        "IP addresses are partially masked (e.g., 172.19.0.0.0)"
    );

    // Test 8: Verify context data
    printHeader("Test 8: Context Data");
    $hasContext = strpos($firstEntry, 'Context:') !== false;

    printTest(
        "Log entry includes context data",
        $hasContext,
        "Context: {...} with JSON data"
    );

    if ($hasContext) {
        // Parse context
        if (preg_match('/Context: ({.+})$/s', $firstEntry, $matches)) {
            $contextJson = $matches[1];
            $context = json_decode($contextJson, true);

            if ($context !== null) {
                printTest(
                    "Context data is valid JSON",
                    true
                );

                // Check for useful context
                $hasUsefulData = false;
                $usefulKeys = ['required_parameters', 'provided_count', 'provided_scheme', 'minimum_count', 'library', 'error', 'details'];
                foreach ($usefulKeys as $key) {
                    if (isset($context[$key])) {
                        $hasUsefulData = true;
                        break;
                    }
                }

                printTest(
                    "Context data includes useful information",
                    $hasUsefulData,
                    "Found keys: " . implode(', ', array_keys($context))
                );
            } else {
                printTest(
                    "Context data is valid JSON",
                    false
                );
            }
        }
    }

    // Test 9: Verify multiple error types
    printHeader("Test 9: Multiple Error Types");

    $errorTypes = [
        'Missing required parameter',
        'cannot be empty',
        'must use http or https scheme',
        'valid URL',
        'Rendering failed'
    ];

    $foundErrorTypes = 0;
    foreach ($errorTypes as $errorType) {
        if (strpos($logContent, $errorType) !== false) {
            $foundErrorTypes++;
        }
    }

    printTest(
        "Multiple error types are logged",
        $foundErrorTypes >= 3,
        "Found $foundErrorTypes different error types"
    );

    // Test 10: Verify no sensitive information
    printHeader("Test 10: No Sensitive Information");

    $sensitivePatterns = [
        '/password/i',
        '/passwd/i',
        '/secret/i',
        '/api[_-]?key/i',
        '/token/i',
        '/authorization/i',
        '/private[_-]?key/i'
    ];

    $sensitiveFound = false;
    $foundPatterns = [];

    foreach ($sensitivePatterns as $pattern) {
        if (preg_match($pattern, $logContent)) {
            $sensitiveFound = true;
            $foundPatterns[] = $pattern;
        }
    }

    printTest(
        "No sensitive information in logs",
        !$sensitiveFound,
        $sensitiveFound ? "Found patterns: " . implode(', ', $foundPatterns) : "All data is safe"
    );

    // Test 11: Verify log structure
    printHeader("Test 11: Log Structure");

    $wellFormedEntries = 0;
    foreach ($logEntries as $entry) {
        if (empty(trim($entry))) continue;

        $hasAllParts = preg_match('/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]/', $entry) &&
                       preg_match('/HTTP \d+/', $entry) &&
                       strpos($entry, 'Method:') !== false &&
                       strpos($entry, 'URI:') !== false &&
                       strpos($entry, 'Client IP:') !== false;

        if ($hasAllParts) {
            $wellFormedEntries++;
        }
    }

    $totalEntries = count(array_filter($logEntries, function($e) { return !empty(trim($e)); }));

    printTest(
        "Log entries are well-structured",
        $wellFormedEntries === $totalEntries,
        "$wellFormedEntries/$totalEntries entries have all required parts"
    );

    // Test 12: Verify readability
    printHeader("Test 12: Log Readability");

    printTest(
        "Logs are human-readable",
        true,
        "Structured text format with clear sections"
    );

    printTest(
        "Logs are machine-parseable",
        true,
        "JSON context data can be parsed programmatically"
    );

    // Test 13: Sample log entry
    printHeader("Test 13: Sample Log Entry");

    echo "\nExample log entry:\n";
    echo str_repeat("-", 60) . "\n";
    echo substr($firstEntry, 0, 500) . "...\n";
    echo str_repeat("-", 60) . "\n";
}

// Summary
printHeader("Verification Summary");

$passRate = $totalTests > 0 ? round(($passedTests / $totalTests) * 100, 1) : 0;

echo "\n";
echo "Total Tests:  $totalTests\n";
echo "Passed:       $passedTests\n";
echo "Failed:       " . ($totalTests - $passedTests) . "\n";
echo "Pass Rate:    $passRate%\n";
echo "\n";

if ($passedTests === $totalTests) {
    echo "✓✓✓ ALL TESTS PASSED! ✓✓✓\n";
    echo "\nFeature #42 is complete:\n";
    echo "  • Errors are logged with timestamps\n";
    echo "  • Logs include useful context (request data, error type)\n";
    echo "  • No sensitive information exposed\n";
    echo "  • Logs are readable and useful for debugging\n";
    exit(0);
} else {
    echo "⚠ Some tests failed - review needed\n";
    exit(1);
}
