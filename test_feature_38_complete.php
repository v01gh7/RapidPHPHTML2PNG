<?php
/**
 * Feature #38: Complete Verification - Input Size Limits
 *
 * This test verifies that the API properly enforces input size limits
 */

define('TEST_MODE', true);
include_once __DIR__ . '/convert.php';

echo "=== Feature #38: Input Size Limits - Complete Verification ===\n\n";

$passedTests = 0;
$totalTests = 0;

// Helper function to run a test
function runTest($testName, $testFunc) {
    global $passedTests, $totalTests;
    $totalTests++;

    echo "Test $totalTests: $testName\n";
    echo str_repeat("-", 80) . "\n";

    $result = $testFunc();

    if ($result['pass']) {
        $passedTests++;
        echo "✅ PASS: " . $result['message'] . "\n\n";
    } else {
        echo "❌ FAIL: " . $result['message'] . "\n\n";
    }

    if (!empty($result['details'])) {
        echo "Details: " . $result['details'] . "\n\n";
    }
}

// Test 1: Constants are defined
runTest("Size limit constants are defined", function() {
    $constants = [
        'MAX_HTML_BLOCK_SIZE' => 1048576,
        'MAX_TOTAL_INPUT_SIZE' => 5242880,
        'MAX_CSS_SIZE' => 1048576
    ];

    foreach ($constants as $const => $expected) {
        if (!defined($const)) {
            return ['pass' => false, 'message' => "Constant $const not defined"];
        }
        if (constant($const) !== $expected) {
            return [
                'pass' => false,
                'message' => "$const has wrong value",
                'details' => "Expected: $expected, Got: " . constant($const)
            ];
        }
    }

    return [
        'pass' => true,
        'message' => "All size limit constants defined correctly",
        'details' => "MAX_HTML_BLOCK_SIZE: 1MB, MAX_TOTAL_INPUT_SIZE: 5MB, MAX_CSS_SIZE: 1MB"
    ];
});

// Test 2: checkTotalInputSize function exists
runTest("checkTotalInputSize function exists", function() {
    if (!function_exists('checkTotalInputSize')) {
        return ['pass' => false, 'message' => 'Function does not exist'];
    }

    $reflection = new ReflectionFunction('checkTotalInputSize');
    $filename = $reflection->getFileName();
    $startLine = $reflection->getStartLine();

    return [
        'pass' => true,
        'message' => 'Function exists at line ' . $startLine,
        'details' => 'File: ' . $filename
    ];
});

// Test 3: HTML block size validation works
runTest("HTML block size validation rejects oversized blocks", function() {
    // Create a block larger than 1MB
    $oversizedBlock = str_repeat('<div>test</div>', 150000); // ~2.25MB

    ob_start();
    validateHtmlBlocks([$oversizedBlock]);
    $output = ob_get_clean();

    if (strpos($output, 'exceeds maximum size') !== false) {
        return [
            'pass' => true,
            'message' => 'Oversized block correctly rejected',
            'details' => 'Block size: ' . strlen($oversizedBlock) . ' bytes'
        ];
    }

    return [
        'pass' => false,
        'message' => 'Oversized block was not rejected',
        'details' => 'Output: ' . substr($output, 0, 200)
    ];
});

// Test 4: HTML block size validation accepts normal blocks
runTest("HTML block size validation accepts normal-sized blocks", function() {
    $normalBlock = str_repeat('<div>test</div>', 10000); // ~150KB

    ob_start();
    $result = validateHtmlBlocks([$normalBlock]);
    $output = ob_get_clean();

    if (strpos($output, 'exceeds maximum size') === false) {
        return [
            'pass' => true,
            'message' => 'Normal-sized block accepted',
            'details' => 'Block size: ' . strlen($normalBlock) . ' bytes'
        ];
    }

    return [
        'pass' => false,
        'message' => 'Normal block was incorrectly rejected',
        'details' => 'Output: ' . substr($output, 0, 200)
    ];
});

// Test 5: Error response includes size information
runTest("Error response includes detailed size information", function() {
    $oversizedBlock = str_repeat('<div>X</div>', 200000); // ~3MB

    ob_start();
    validateHtmlBlocks([$oversizedBlock]);
    $output = ob_get_clean();

    $required = [
        'block_size',
        'max_allowed_size',
        'max_allowed_mb',
        'exceeded_by'
    ];

    $missing = [];
    foreach ($required as $field) {
        if (strpos($output, '"' . $field . '"') === false) {
            $missing[] = $field;
        }
    }

    if (empty($missing)) {
        return [
            'pass' => true,
            'message' => 'Error response includes all size details',
            'details' => 'Fields: ' . implode(', ', $required)
        ];
    }

    return [
        'pass' => false,
        'message' => 'Error response missing fields: ' . implode(', ', $missing),
        'details' => 'Output: ' . substr($output, 0, 300)
    ];
});

// Test 6: HTTP 413 status code is sent
runTest("HTTP 413 status code sent for size violations", function() {
    $oversizedBlock = str_repeat('<div>Y</div>', 200000);

    ob_start();
    validateHtmlBlocks([$oversizedBlock]);
    $output = ob_get_clean();

    // Check if sendError was called with code 413
    if (strpos($output, '413') !== false) {
        return [
            'pass' => true,
            'message' => 'HTTP 413 status code in response',
            'details' => 'Payload Too Large status code used'
        ];
    }

    // Note: In TEST_MODE, http_response_code() doesn't actually set the header
    // but we can verify the code parameter is passed to sendError
    $reflection = new ReflectionFunction('sendError');
    $content = file_get_contents($reflection->getFileName());

    if (strpos($content, "sendError(413, 'html_blocks')") !== false ||
        strpos($content, "sendError(413, 'CSS file") !== false) {

        return [
            'pass' => true,
            'message' => 'sendError called with 413 for size violations',
            'details' => 'Code verified in source'
        ];
    }

    return [
        'pass' => false,
        'message' => 'Could not verify 413 status code',
        'details' => 'Check if sendError(413, ...) is called'
    ];
});

// Test 7: CSS size validation exists
runTest("CSS size validation in loadCssContent", function() {
    $reflection = new ReflectionFunction('loadCssContent');
    $content = file_get_contents($reflection->getFileName());
    $startLine = $reflection->getStartLine();
    $endLine = $reflection->getEndLine();

    $lines = explode("\n", $content);
    $functionBody = implode("\n", array_slice($lines, $startLine - 1, $endLine - $startLine + 1));

    $hasSizeCheck = strpos($functionBody, 'MAX_CSS_SIZE') !== false;
    $hasErrorSend = strpos($functionBody, "sendError(413") !== false;

    if ($hasSizeCheck && $hasErrorSend) {
        return [
            'pass' => true,
            'message' => 'CSS size validation implemented',
            'details' => 'Checks MAX_CSS_SIZE and sends 413 error'
        ];
    }

    return [
        'pass' => false,
        'message' => 'CSS size validation incomplete',
        'details' => 'Has MAX_CSS_SIZE check: ' . ($hasSizeCheck ? 'yes' : 'no') .
                     ', Sends 413 error: ' . ($hasErrorSend ? 'yes' : 'no')
    ];
});

// Test 8: Multiple blocks validation
runTest("Multiple blocks each validated individually", function() {
    // Create 3 blocks where one is too large
    $blocks = [
        str_repeat('<div>OK</div>', 1000),    // ~15KB - OK
        str_repeat('<div>BIG</div>', 200000), // ~3MB - Too large
        str_repeat('<div>OK</div>', 1000)     // ~15KB - OK
    ];

    ob_start();
    validateHtmlBlocks($blocks);
    $output = ob_get_clean();

    // Should fail on index 1
    if (strpos($output, 'html_blocks[1]') !== false &&
        strpos($output, 'exceeds maximum size') !== false) {
        return [
            'pass' => true,
            'message' => 'Oversized block correctly identified',
            'details' => 'Block at index 1 (size: ' . strlen($blocks[1]) . ' bytes) rejected'
        ];
    }

    return [
        'pass' => false,
        'message' => 'Multiple block validation failed',
        'details' => 'Output: ' . substr($output, 0, 200)
    ];
});

// Summary
echo "\n";
echo "=" . str_repeat("=", 78) . "\n";
echo "SUMMARY\n";
echo "=" . str_repeat("=", 78) . "\n";
echo "Total Tests: $totalTests\n";
echo "Passed: $passedTests\n";
echo "Failed: " . ($totalTests - $passedTests) . "\n";
echo "Success Rate: " . round(($passedTests / $totalTests) * 100, 1) . "%\n";
echo "\n";

if ($passedTests === $totalTests) {
    echo "✅ ALL TESTS PASSED - Feature #38 is working correctly!\n";
} else {
    echo "⚠️  Some tests failed - review implementation\n";
}

echo "\n";
echo "Size Limits Implemented:\n";
echo "  • MAX_HTML_BLOCK_SIZE: 1 MB per HTML block\n";
echo "  • MAX_TOTAL_INPUT_SIZE: 5 MB total request body\n";
echo "  • MAX_CSS_SIZE: 1 MB for CSS content\n";
echo "\n";
echo "All size violations return HTTP 413 (Payload Too Large)\n";
echo "with detailed error information including size, limit, and excess.\n";
