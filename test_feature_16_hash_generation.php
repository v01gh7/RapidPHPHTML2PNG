<?php
/**
 * Test Feature #16: Generates MD5 hash from content
 *
 * This test verifies that the generateContentHash() function:
 * 1. Creates test HTML content: 'TEST_HASH_12345'
 * 2. Creates test CSS content: 'body { color: red; }'
 * 3. Calls hash generation function with both contents
 * 4. Verifies MD5 hash string is returned
 * 5. Confirms hash is 32 character hexadecimal string
 */

// Include the main convert.php file
require_once __DIR__ . '/convert.php';

// ANSI color codes for terminal output
const COLOR_GREEN = "\033[32m";
const COLOR_RED = "\033[31m";
const COLOR_YELLOW = "\033[33m";
const COLOR_CYAN = "\033[36m";
const COLOR_RESET = "\033[0m";

/**
 * Print test result with color
 */
function printTestResult($testName, $passed, $message = '') {
    $color = $passed ? COLOR_GREEN : COLOR_RED;
    $status = $passed ? 'PASS' : 'FAIL';
    echo "{$color}[{$status}]{$COLOR_RESET} {$testName}";
    if ($message) {
        echo " - {$message}";
    }
    echo "\n";
    return $passed;
}

/**
 * Verify hash format is valid MD5
 */
function isValidMd5($hash) {
    return preg_match('/^[a-f0-9]{32}$/', $hash) === 1;
}

echo COLOR_CYAN . "=== Feature #16 Test: MD5 Hash Generation ===" . COLOR_RESET . "\n\n";

$testsPassed = 0;
$testsFailed = 0;

// Test 1: Generate hash from HTML content only
echo COLOR_YELLOW . "Test 1: Generate hash from HTML content only" . COLOR_RESET . "\n";
$testHtml1 = ['TEST_HASH_12345'];
$hash1 = generateContentHash($testHtml1, null);
$passed1 = printTestResult(
    'Hash generated from HTML only',
    isValidMd5($hash1),
    "Hash: {$hash1}"
);
$testsPassed += $passed1 ? 1 : 0;
$testsFailed += $passed1 ? 0 : 1;

// Verify the hash is correct (we know the expected hash for 'TEST_HASH_12345')
$expectedHash1 = md5('TEST_HASH_12345');
$passed1b = printTestResult(
    'Hash matches expected value',
    $hash1 === $expectedHash1,
    "Expected: {$expectedHash1}, Got: {$hash1}"
);
$testsPassed += $passed1b ? 1 : 0;
$testsFailed += $passed1b ? 0 : 1;

echo "\n";

// Test 2: Generate hash from HTML + CSS content
echo COLOR_YELLOW . "Test 2: Generate hash from HTML + CSS content" . COLOR_RESET . "\n";
$testHtml2 = ['TEST_HASH_12345'];
$testCss2 = 'body { color: red; }';
$hash2 = generateContentHash($testHtml2, $testCss2);
$passed2 = printTestResult(
    'Hash generated from HTML + CSS',
    isValidMd5($hash2),
    "Hash: {$hash2}"
);
$testsPassed += $passed2 ? 1 : 0;
$testsFailed += $passed2 ? 0 : 1;

// Verify the hash is correct
$expectedHash2 = md5('TEST_HASH_12345' . 'body { color: red; }');
$passed2b = printTestResult(
    'Hash matches expected value',
    $hash2 === $expectedHash2,
    "Expected: {$expectedHash2}, Got: {$hash2}"
);
$testsPassed += $passed2b ? 1 : 0;
$testsFailed += $passed2b ? 0 : 1;

// Verify hash is different from HTML-only hash
$passed2c = printTestResult(
    'Hash differs from HTML-only hash',
    $hash1 !== $hash2,
    "HTML-only: {$hash1}, HTML+CSS: {$hash2}"
);
$testsPassed += $passed2c ? 1 : 0;
$testsFailed += $passed2c ? 0 : 1;

echo "\n";

// Test 3: Hash length is 32 characters
echo COLOR_YELLOW . "Test 3: Hash length is 32 characters" . COLOR_RESET . "\n";
$passed3 = printTestResult(
    'Hash length is 32 characters',
    strlen($hash1) === 32,
    "Length: " . strlen($hash1)
);
$testsPassed += $passed3 ? 1 : 0;
$testsFailed += $passed3 ? 0 : 1;

echo "\n";

// Test 4: Hash contains only hexadecimal characters
echo COLOR_YELLOW . "Test 4: Hash contains only hexadecimal characters" . COLOR_RESET . "\n";
$passed4 = printTestResult(
    'Hash is lowercase hexadecimal',
    ctype_xdigit($hash1) && strtolower($hash1) === $hash1,
    "Hash: {$hash1}"
);
$testsPassed += $passed4 ? 1 : 0;
$testsFailed += $passed4 ? 0 : 1;

echo "\n";

// Test 5: Multiple HTML blocks are combined correctly
echo COLOR_YELLOW . "Test 5: Multiple HTML blocks are combined correctly" . COLOR_RESET . "\n";
$testHtml5 = ['Block1', 'Block2', 'Block3'];
$hash5 = generateContentHash($testHtml5, null);
$expectedHash5 = md5('Block1Block2Block3');
$passed5 = printTestResult(
    'Multiple blocks combined correctly',
    $hash5 === $expectedHash5,
    "Expected: {$expectedHash5}, Got: {$hash5}"
);
$testsPassed += $passed5 ? 1 : 0;
$testsFailed += $passed5 ? 0 : 1;

echo "\n";

// Test 6: Same content produces same hash
echo COLOR_YELLOW . "Test 6: Same content produces same hash (deterministic)" . COLOR_RESET . "\n";
$testHtml6 = ['TEST_HASH_12345'];
$hash6a = generateContentHash($testHtml6, null);
$hash6b = generateContentHash($testHtml6, null);
$passed6 = printTestResult(
    'Same content produces same hash',
    $hash6a === $hash6b,
    "First: {$hash6a}, Second: {$hash6b}"
);
$testsPassed += $passed6 ? 1 : 0;
$testsFailed += $passed6 ? 0 : 1;

echo "\n";

// Test 7: Different content produces different hash
echo COLOR_YELLOW . "Test 7: Different content produces different hash" . COLOR_RESET . "\n";
$testHtml7a = ['Content_A'];
$testHtml7b = ['Content_B'];
$hash7a = generateContentHash($testHtml7a, null);
$hash7b = generateContentHash($testHtml7b, null);
$passed7 = printTestResult(
    'Different content produces different hash',
    $hash7a !== $hash7b,
    "Hash A: {$hash7a}, Hash B: {$hash7b}"
);
$testsPassed += $passed7 ? 1 : 0;
$testsFailed += $passed7 ? 0 : 1;

echo "\n";

// Test 8: Empty CSS is handled correctly
echo COLOR_YELLOW . "Test 8: Empty CSS is handled correctly" . COLOR_RESET . "\n";
$testHtml8 = ['TEST_HASH_12345'];
$hash8a = generateContentHash($testHtml8, null);
$hash8b = generateContentHash($testHtml8, '');
$passed8 = printTestResult(
    'Empty CSS treated same as null CSS',
    $hash8a === $hash8b,
    "Null CSS: {$hash8a}, Empty CSS: {$hash8b}"
);
$testsPassed += $passed8 ? 1 : 0;
$testsFailed += $passed8 ? 0 : 1;

echo "\n";

// Summary
echo COLOR_CYAN . "=== Test Summary ===" . COLOR_RESET . "\n";
$totalTests = $testsPassed + $testsFailed;
echo "Total tests: {$totalTests}\n";
echo COLOR_GREEN . "Passed: {$testsPassed}" . COLOR_RESET . "\n";
echo COLOR_RED . "Failed: {$testsFailed}" . COLOR_RESET . "\n";
$percentage = $totalTests > 0 ? round(($testsPassed / $totalTests) * 100, 1) : 0;
echo "Success rate: {$percentage}%\n";

// Exit with appropriate code
exit($testsFailed > 0 ? 1 : 0);
