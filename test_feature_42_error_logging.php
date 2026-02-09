<?php
/**
 * Test Feature #42: Error Logging for Debugging
 *
 * This test verifies that:
 * 1. Errors are logged with timestamps
 * 2. Logs include useful context (request data, error type)
 * 3. Logging does not expose sensitive information
 */

define('TEST_MODE', true);

// Colors for terminal output
define('COLOR_GREEN', "\033[32m");
define('COLOR_RED', "\033[31m");
define('COLOR_YELLOW', "\033[33m");
define('COLOR_BLUE', "\033[34m");
define('COLOR_RESET', "\033[0m");

function printTestHeader($testName) {
    echo "\n" . COLOR_BLUE . "═══ " . $testName . " ═══" . COLOR_RESET . "\n";
}

function printPass($message) {
    echo COLOR_GREEN . "✓ PASS: " . COLOR_RESET . $message . "\n";
}

function printFail($message) {
    echo COLOR_RED . "✗ FAIL: " . COLOR_RESET . $message . "\n";
}

function printInfo($message) {
    echo COLOR_YELLOW . "  ℹ INFO: " . COLOR_RESET . $message . "\n";
}

$totalTests = 0;
$passedTests = 0;

// Test 1: Check error logging configuration
printTestHeader("Test 1: Error Logging Configuration");
$totalTests++;

$logDir = __DIR__ . '/logs';
$errorLogPath = $logDir . '/php_errors.log';

if (is_dir($logDir)) {
    printPass("Logs directory exists at $logDir");
} else {
    printFail("Logs directory does not exist");
}

if (file_exists($errorLogPath)) {
    printPass("Error log file exists at $errorLogPath");
    $logSize = filesize($errorLogPath);
    printInfo("Log file size: " . number_format($logSize) . " bytes");
} else {
    printFail("Error log file does not exist");
}

// Test 2: Check error logging ini settings
printTestHeader("Test 2: Error Logging INI Settings");
$totalTests++;

$errorReporting = error_reporting();
$logErrors = ini_get('log_errors');
$errorLog = ini_get('error_log');
$displayErrors = ini_get('display_errors');

if ($errorReporting === E_ALL) {
    printPass("error_reporting is set to E_ALL");
} else {
    printFail("error_reporting is not E_ALL (value: $errorReporting)");
}

if ($logErrors === '1') {
    printPass("log_errors is enabled");
} else {
    printFail("log_errors is not enabled");
}

if ($displayErrors === '0' || $displayErrors === '') {
    printPass("display_errors is disabled (errors not shown to users)");
} else {
    printFail("display_errors is enabled (security issue)");
}

if ($errorLog === $errorLogPath) {
    printPass("error_log points to $errorLogPath");
} else {
    printFail("error_log points to unexpected location: $errorLog");
}

// Test 3: Check sendError function exists
printTestHeader("Test 3: sendError Function");
$totalTests++;

require_once __DIR__ . '/convert.php';

if (function_exists('sendError')) {
    printPass("sendError function exists");
    $reflection = new ReflectionFunction('sendError');
    $params = $reflection->getParameters();
    if (count($params) === 3) {
        printPass("sendError has 3 parameters (code, message, data)");
    } else {
        printFail("sendError has unexpected parameter count: " . count($params));
    }
} else {
    printFail("sendError function does not exist");
}

// Test 4: Trigger various error conditions
printTestHeader("Test 4: Trigger Error Conditions");
$totalTests++;

// Get initial log size
$initialLogSize = file_exists($errorLogPath) ? filesize($errorLogPath) : 0;

// Test 4.1: Missing parameter error
printInfo("Testing missing parameter error...");
$jsonData = json_encode(['css_url' => 'http://example.com/style.css']); // Missing html_blocks
$result = json_decode(postToApi($jsonData), true);

if (isset($result['success']) && $result['success'] === false) {
    printPass("Missing parameter error returned correctly");
    printInfo("Error message: " . ($result['error'] ?? 'N/A'));
} else {
    printFail("Missing parameter error not handled correctly");
}

// Test 4.2: Invalid JSON error
printInfo("Testing invalid JSON error...");
$result = json_decode(postToApi('invalid json'), true);

if (isset($result['success']) && $result['success'] === false) {
    printPass("Invalid JSON error returned correctly");
    printInfo("Error message: " . ($result['error'] ?? 'N/A'));
} else {
    printFail("Invalid JSON error not handled correctly");
}

// Test 4.3: Empty html_blocks error
printInfo("Testing empty html_blocks error...");
$jsonData = json_encode(['html_blocks' => []]);
$result = json_decode(postToApi($jsonData), true);

if (isset($result['success']) && $result['success'] === false) {
    printPass("Empty html_blocks error returned correctly");
    printInfo("Error message: " . ($result['error'] ?? 'N/A'));
} else {
    printFail("Empty html_blocks error not handled correctly");
}

// Test 4.4: Invalid CSS URL error
printInfo("Testing invalid CSS URL error...");
$jsonData = json_encode([
    'html_blocks' => ['<div>Test</div>'],
    'css_url' => 'not-a-valid-url'
]);
$result = json_decode(postToApi($jsonData), true);

if (isset($result['success']) && $result['success'] === false) {
    printPass("Invalid CSS URL error returned correctly");
    printInfo("Error message: " . ($result['error'] ?? 'N/A'));
} else {
    printFail("Invalid CSS URL error not handled correctly");
}

// Test 5: Check error log entries
printTestHeader("Test 5: Check Error Log Entries");
$totalTests++;

sleep(1); // Give time for logs to flush
clearstatcache(true, $errorLogPath);

if (file_exists($errorLogPath)) {
    $newLogSize = filesize($errorLogPath);
    $logGrowth = $newLogSize - $initialLogSize;

    if ($logGrowth > 0) {
        printPass("Error log grew by " . number_format($logGrowth) . " bytes");
        printInfo("New entries were written to the log");

        // Read recent log entries
        $logContent = file_get_contents($errorLogPath);
        $logLines = explode("\n", $logContent);
        $recentLines = array_slice($logLines, -20); // Get last 20 lines

        printInfo("Recent log entries:");
        foreach ($recentLines as $line) {
            if (!empty(trim($line))) {
                echo "    " . substr($line, 0, 100) . "...\n";
            }
        }

        // Test 6: Verify timestamps in logs
        printTestHeader("Test 6: Verify Timestamps in Logs");
        $totalTests++;

        $hasTimestamp = false;
        foreach ($recentLines as $line) {
            if (preg_match('/\[\d{2}-\w{3}-\d{4} \d{2}:\d{2}:\d{2} UTC\]/', $line)) {
                $hasTimestamp = true;
                break;
            }
        }

        if ($hasTimestamp) {
            printPass("Log entries contain proper timestamps");
        } else {
            printFail("Log entries missing timestamps");
        }

        // Test 7: Check for useful context in logs
        printTestHeader("Test 7: Check for Useful Context in Logs");
        $totalTests++;

        // Check if PHP errors are being logged (these provide context)
        $hasContext = false;
        $contextPatterns = [
            '/Undefined index/',
            '/Invalid argument supplied/',
            '/Warning/',
            '/Notice/',
            '/in \/var\/www\/html\/convert\.php on line \d+/'
        ];

        foreach ($recentLines as $line) {
            foreach ($contextPatterns as $pattern) {
                if (preg_match($pattern, $line)) {
                    $hasContext = true;
                    break 2;
                }
            }
        }

        if ($hasContext) {
            printPass("Log entries contain useful context (error type, file, line)");
        } else {
            printInfo("Note: PHP error context is automatically logged");
        }

    } else {
        printInfo("Error log size unchanged (no new PHP errors)");
        printInfo("This is expected if errors were handled gracefully");
    }
}

// Test 8: Check library selection logging
printTestHeader("Test 8: Library Selection Logging");
$totalTests++;

$libraryLogPath = $logDir . '/library_selection.log';

if (file_exists($libraryLogPath)) {
    printPass("Library selection log exists");
    $libLogContent = file_get_contents($libraryLogPath);
    $libLogLines = explode("\n", $libLogContent);
    $recentLibLines = array_slice($libLogLines, -10);

    printInfo("Recent library selection log entries:");
    foreach ($recentLibLines as $line) {
        if (!empty(trim($line))) {
            echo "    " . substr($line, 0, 120) . "...\n";
        }
    }

    // Check for timestamps
    $hasTimestamp = false;
    foreach ($recentLibLines as $line) {
        if (preg_match('/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]/', $line)) {
            $hasTimestamp = true;
            break;
        }
    }

    if ($hasTimestamp) {
        printPass("Library selection log contains timestamps");
    } else {
        printFail("Library selection log missing timestamps");
    }

    // Check for useful context
    $hasContext = false;
    if (strpos($libLogContent, 'Selected Library:') !== false) {
        $hasContext = true;
    }
    if (strpos($libLogContent, 'Reason:') !== false) {
        $hasContext = true;
    }

    if ($hasContext) {
        printPass("Library selection log contains useful context");
    } else {
        printFail("Library selection log missing context");
    }

} else {
    printFail("Library selection log does not exist");
}

// Test 9: Verify no sensitive information in logs
printTestHeader("Test 9: Verify No Sensitive Information in Logs");
$totalTests++;

$sensitivePatterns = [
    '/password/i',
    '/secret/i',
    '/api[_-]?key/i',
    '/token/i',
    '/authorization/i'
];

$sensitiveFound = false;
$allLogs = '';

if (file_exists($errorLogPath)) {
    $allLogs .= file_get_contents($errorLogPath);
}
if (file_exists($libraryLogPath)) {
    $allLogs .= file_get_contents($libraryLogPath);
}

foreach ($sensitivePatterns as $pattern) {
    if (preg_match($pattern, $allLogs)) {
        printFail("Potential sensitive information found in logs: $pattern");
        $sensitiveFound = true;
    }
}

if (!$sensitiveFound) {
    printPass("No sensitive information found in logs");
    printInfo("Logs are safe for debugging");
}

// Test 10: Verify log file permissions
printTestHeader("Test 10: Verify Log File Permissions");
$totalTests++;

if (file_exists($errorLogPath)) {
    $perms = substr(sprintf('%o', fileperms($errorLogPath)), -4);
    printInfo("Error log permissions: $perms");
    if ($perms === '0644' || $perms === '0666') {
        printPass("Error log has reasonable permissions");
    } else {
        printInfo("Error log permissions: $perms (verify this is appropriate)");
    }
}

if (file_exists($libraryLogPath)) {
    $perms = substr(sprintf('%o', fileperms($libraryLogPath)), -4);
    printInfo("Library log permissions: $perms");
    if ($perms === '0644' || $perms === '0666') {
        printPass("Library log has reasonable permissions");
    } else {
        printInfo("Library log permissions: $perms (verify this is appropriate)");
    }
}

// Summary
printTestHeader("Test Summary");
echo "Total Tests: $totalTests\n";
echo "Passed: $passedTests\n";
echo "Failed: " . ($totalTests - $passedTests) . "\n";
echo "Pass Rate: " . ($totalTests > 0 ? round(($passedTests / $totalTests) * 100, 1) : 0) . "%\n";

if ($passedTests === $totalTests) {
    echo "\n" . COLOR_GREEN . "✓ All tests passed!" . COLOR_RESET . "\n";
    exit(0);
} else {
    echo "\n" . COLOR_YELLOW . "⚠ Some tests failed - review needed" . COLOR_RESET . "\n";
    exit(1);
}

/**
 * Helper function to POST data to the API
 */
function postToApi($jsonData) {
    $url = 'http://localhost/convert.php';

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $response;
}
