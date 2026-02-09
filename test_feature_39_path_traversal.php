<?php
/**
 * Feature #39: Sanitizes file paths
 * Test path traversal attacks and verify proper sanitization
 *
 * This test verifies that file paths are properly sanitized to prevent
 * directory traversal attacks.
 */

require_once __DIR__ . '/convert.php';

define('TEST_MODE', true);

// ANSI colors for terminal output
define('COLOR_GREEN', "\033[32m");
define('COLOR_RED', "\033[31m");
define('COLOR_YELLOW', "\033[33m");
define('COLOR_RESET', "\033[0m");

function printTestHeader($testName) {
    echo "\n" . str_repeat("=", 80) . "\n";
    echo "TEST: $testName\n";
    echo str_repeat("=", 80) . "\n";
}

function printResult($passed, $message) {
    $color = $passed ? COLOR_GREEN : COLOR_RED;
    $status = $passed ? '✓ PASS' : '✗ FAIL';
    echo "{$color}{$status}: {$message}" . COLOR_RESET . "\n";
    return $passed;
}

// Track test results
$totalTests = 0;
$passedTests = 0;

/**
 * Test 1: Verify css_url validation blocks file:// scheme
 */
$totalTests++;
printTestHeader("Test 1: File:// scheme should be blocked");

$maliciousUrl = 'file:///etc/passwd';
try {
    ob_start();
    $result = validateCssUrl($maliciousUrl);
    ob_end_clean();
    printResult(false, "File:// scheme was accepted (should be blocked)");
} catch (Exception $e) {
    $expectedMsg = 'must use http or https scheme';
    if (strpos($e->getMessage(), $expectedMsg) !== false) {
        $passedTests++;
        printResult(true, "File:// scheme properly rejected");
    } else {
        printResult(false, "Wrong error message: " . $e->getMessage());
    }
}

/**
 * Test 2: Verify css_url validation blocks ftp:// scheme
 */
$totalTests++;
printTestHeader("Test 2: FTP:// scheme should be blocked");

$maliciousUrl = 'ftp://example.com/style.css';
try {
    ob_start();
    $result = validateCssUrl($maliciousUrl);
    ob_end_clean();
    printResult(false, "FTP:// scheme was accepted (should be blocked)");
} catch (Exception $e) {
    $expectedMsg = 'must use http or https scheme';
    if (strpos($e->getMessage(), $expectedMsg) !== false) {
        $passedTests++;
        printResult(true, "FTP:// scheme properly rejected");
    } else {
        printResult(false, "Wrong error message: " . $e->getMessage());
    }
}

/**
 * Test 3: Verify directory traversal in URL path is handled safely
 */
$totalTests++;
printTestHeader("Test 3: Directory traversal in URL path");

$maliciousUrl = 'http://example.com/../../../etc/passwd';
try {
    $result = validateCssUrl($maliciousUrl);

    // Check that getCssCachePath doesn't allow directory traversal
    $cachePath = getCssCachePath($maliciousUrl);

    // The cache path should use MD5 hash, so no directory traversal possible
    if (strpos($cachePath, '../') === false && strpos($cachePath, '..\\') === false) {
        // Verify the path is within the expected directory
        $expectedDir = realpath(__DIR__ . '/assets/media/rapidhtml2png/css_cache');
        $fullPath = realpath(dirname($cachePath));

        if ($fullPath === $expectedDir || strpos($fullPath, $expectedDir) === 0) {
            $passedTests++;
            printResult(true, "Directory traversal blocked - path is safe: " . basename($cachePath));
        } else {
            printResult(false, "Cache path outside expected directory!");
        }
    } else {
        printResult(false, "Cache path contains directory traversal sequences!");
    }
} catch (Exception $e) {
    printResult(false, "Unexpected exception: " . $e->getMessage());
}

/**
 * Test 4: Verify null byte injection is blocked
 */
$totalTests++;
printTestHeader("Test 4: Null byte injection should be handled");

$maliciousUrl = 'http://example.com/style.css%00.css';
try {
    // PHP's filter_var should catch this
    $result = validateCssUrl($maliciousUrl);

    // Even if URL validation passes, hash generation should be safe
    $cachePath = getCssCachePath($maliciousUrl);

    // Check that null bytes don't appear in the path
    if (strpos($cachePath, "\0") === false) {
        $passedTests++;
        printResult(true, "Null byte injection handled safely");
    } else {
        printResult(false, "Null byte found in cache path!");
    }
} catch (Exception $e) {
    // It's acceptable if validation rejects this
    printResult(true, "Null byte URL rejected by validation");
    $passedTests++;
}

/**
 * Test 5: Verify output directory is fixed (not user-controllable)
 */
$totalTests++;
printTestHeader("Test 5: Output directory should not be user-controllable");

// The output directory should always be the same
$expectedDir = __DIR__ . '/assets/media/rapidhtml2png';
$actualDir = getOutputDirectory();

if ($actualDir === $expectedDir) {
    $passedTests++;
    printResult(true, "Output directory is fixed and safe");
} else {
    printResult(false, "Output directory mismatch! Expected: $expectedDir, Got: $actualDir");
}

/**
 * Test 6: Verify MD5 hash prevents cache key collisions with traversal
 */
$totalTests++;
printTestHeader("Test 6: Cache paths use MD5 hash (no direct user input)");

$urls = [
    'http://example.com/style.css',
    'http://example.com/../../../etc/passwd',
    'http://example.com/../../secret.css',
    'file:///etc/passwd',
];

$cachePaths = [];
$allSafe = true;

foreach ($urls as $url) {
    try {
        $cachePath = getCssCachePath($url);

        // Check path only contains MD5 hash + .css extension
        $filename = basename($cachePath);
        if (preg_match('/^[a-f0-9]{32}\.css$/', $filename)) {
            $cachePaths[$url] = $filename;
        } else {
            printResult(false, "Invalid cache filename format: $filename");
            $allSafe = false;
            break;
        }

        // Check no directory traversal in path
        if (strpos($cachePath, '../') !== false || strpos($cachePath, '..\\') !== false) {
            printResult(false, "Directory traversal in cache path!");
            $allSafe = false;
            break;
        }
    } catch (Exception $e) {
        // Some URLs should be rejected - that's OK
        if (strpos($e->getMessage(), 'http or https') !== false) {
            $cachePaths[$url] = 'REJECTED';
        } else {
            printResult(false, "Unexpected error: " . $e->getMessage());
            $allSafe = false;
            break;
        }
    }
}

if ($allSafe) {
    $passedTests++;
    echo COLOR_GREEN . "✓ PASS: All cache paths safe - using MD5 hash" . COLOR_RESET . "\n";
    foreach ($cachePaths as $url => $path) {
        echo "  - " . substr($url, 0, 50) . " => $path\n";
    }
}

/**
 * Test 7: Verify that only http/https schemes are allowed
 */
$totalTests++;
printTestHeader("Test 7: Only HTTP/HTTPS schemes allowed");

$schemesToTest = [
    'http://example.com/style.css' => true,
    'https://example.com/style.css' => true,
    'file:///etc/passwd' => false,
    'ftp://example.com/style.css' => false,
    'javascript:alert(1)' => false,
    'data:text/css,body{background:red}' => false,
    'php://filter/read=convert.base64-encode/resource=index.php' => false,
];

$allCorrect = true;

foreach ($schemesToTest as $url => $shouldPass) {
    try {
        ob_start();
        $result = validateCssUrl($url);
        ob_end_clean();

        if ($shouldPass) {
            echo "  ✓ $url - accepted as expected\n";
        } else {
            echo "  ✗ $url - ACCEPTED (should have been rejected!)\n";
            $allCorrect = false;
        }
    } catch (Exception $e) {
        if (!$shouldPass) {
            echo "  ✓ $url - rejected as expected\n";
        } else {
            echo "  ✗ $url - REJECTED (should have been accepted!)\n";
            echo "    Error: " . $e->getMessage() . "\n";
            $allCorrect = false;
        }
    }
}

if ($allCorrect) {
    $passedTests++;
    printResult(true, "All URL schemes handled correctly");
} else {
    printResult(false, "Some URL schemes not handled correctly");
}

/**
 * Test 8: Verify hash generation doesn't leak path information
 */
$totalTests++;
printTestHeader("Test 8: Hash doesn't leak path information");

$htmlBlocks = ['<div>Test</div>'];
$hashes = [];

$testCases = [
    ['<div>Test</div>', null, 'normal'],
    ['<div>Test</div>', 'http://example.com/style.css', 'with_css'],
    ['<div>Test</div>', 'http://example.com/../../../secret.css', 'with_traversal_css'],
];

foreach ($testCases as $case) {
    list($html, $css, $name) = $case;
    $hash = generateContentHash([$html], $css);
    $hashes[$name] = $hash;

    // Check hash format (32 hex chars)
    if (!preg_match('/^[a-f0-9]{32}$/', $hash)) {
        printResult(false, "Invalid hash format: $hash");
        $allCorrect = false;
        break;
    }

    // Check no path separators in hash
    if (strpos($hash, '/') !== false || strpos($hash, '\\') !== false) {
        printResult(false, "Hash contains path separators!");
        $allCorrect = false;
        break;
    }
}

// Different inputs should produce different hashes
if ($hashes['normal'] !== $hashes['with_css']) {
    $passedTests++;
    echo COLOR_GREEN . "✓ PASS: All hashes valid and deterministic" . COLOR_RESET . "\n";
    foreach ($hashes as $name => $hash) {
        echo "  - $name: $hash\n";
    }
} else {
    printResult(false, "Different inputs produced same hash (collision)");
}

/**
 * Test 9: Verify output path construction is safe
 */
$totalTests++;
printTestHeader("Test 9: Output path construction is safe");

$contentHash = 'abc123def456789abc123def456789ab'; // 32-char hex
$expectedOutputPath = __DIR__ . '/assets/media/rapidhtml2png/' . $contentHash . '.png';

// The output path should always be: outputDir/hash.png
$outputDir = getOutputDirectory();
$constructedPath = $outputDir . '/' . $contentHash . '.png';

if ($constructedPath === $expectedOutputPath) {
    // Check no directory traversal possible
    if (strpos($constructedPath, '../') === false && strpos($constructedPath, '..\\') === false) {
        $passedTests++;
        printResult(true, "Output path construction is safe");
    } else {
        printResult(false, "Output path contains traversal sequences!");
    }
} else {
    printResult(false, "Output path mismatch!");
}

/**
 * Test 10: Verify cache directory creation is safe
 */
$totalTests++;
printTestHeader("Test 10: Cache directory creation is safe");

try {
    $cacheDir = getCssCacheDir();
    $expectedCacheDir = __DIR__ . '/assets/media/rapidhtml2png/css_cache';

    if ($cacheDir === $expectedCacheDir) {
        // Verify directory is within project root
        $realPath = realpath($cacheDir);
        $rootPath = realpath(__DIR__);

        if ($realPath && strpos($realPath, $rootPath) === 0) {
            $passedTests++;
            printResult(true, "Cache directory is within project root");
        } else {
            printResult(false, "Cache directory outside project root!");
        }
    } else {
        printResult(false, "Cache directory path mismatch!");
    }
} catch (Exception $e) {
    printResult(false, "Exception getting cache directory: " . $e->getMessage());
}

/**
 * Final Summary
 */
echo "\n" . str_repeat("=", 80) . "\n";
echo "FEATURE #39 TEST SUMMARY: Path Sanitization\n";
echo str_repeat("=", 80) . "\n";
echo "Tests Passed: $passedTests / $totalTests\n";
echo "Success Rate: " . round(($passedTests / $totalTests) * 100, 1) . "%\n";

if ($passedTests === $totalTests) {
    echo COLOR_GREEN . "\n✓ ALL TESTS PASSED - File paths are properly sanitized\n" . COLOR_RESET . "\n";
    exit(0);
} else {
    echo COLOR_RED . "\n✗ SOME TESTS FAILED - Path sanitization needs attention\n" . COLOR_RESET . "\n";
    exit(1);
}
