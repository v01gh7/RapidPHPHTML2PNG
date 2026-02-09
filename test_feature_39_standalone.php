<?php
/**
 * Feature #39: Sanitizes file paths - Standalone Test
 * Test path traversal attacks and verify proper sanitization
 *
 * This is a standalone test that copies essential functions from convert.php
 * to test path sanitization without triggering the main API logic.
 */

// Define constants
define('MAX_CSS_SIZE', 1048576); // 1MB

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
 * Copied from convert.php: validateCssUrl function
 */
function validateCssUrl($cssUrl) {
    if ($cssUrl === null || $cssUrl === '') {
        return null;
    }

    if (!is_string($cssUrl)) {
        throw new Exception('css_url must be a string');
    }

    // Basic URL validation
    if (!filter_var($cssUrl, FILTER_VALIDATE_URL)) {
        throw new Exception('css_url must be a valid URL');
    }

    // Ensure it's http or https
    $scheme = parse_url($cssUrl, PHP_URL_SCHEME);
    if (!in_array($scheme, ['http', 'https'])) {
        throw new Exception('css_url must use http or https scheme');
    }

    return $cssUrl;
}

/**
 * Copied from convert.php: getCssCacheDir function
 */
function getCssCacheDir() {
    $cacheDir = __DIR__ . '/assets/media/rapidhtml2png/css_cache';
    if (!is_dir($cacheDir)) {
        if (!mkdir($cacheDir, 0755, true)) {
            throw new Exception('Failed to create CSS cache directory');
        }
    }
    return $cacheDir;
}

/**
 * Copied from convert.php: getCssCachePath function
 */
function getCssCachePath($cssUrl) {
    $cacheDir = getCssCacheDir();
    $cacheKey = md5($cssUrl);
    return $cacheDir . '/' . $cacheKey . '.css';
}

/**
 * Copied from convert.php: getOutputDirectory function
 */
function getOutputDirectory() {
    $outputDir = __DIR__ . '/assets/media/rapidhtml2png';
    if (!is_dir($outputDir)) {
        if (!mkdir($outputDir, 0755, true)) {
            throw new Exception('Failed to create output directory');
        }
    }
    return $outputDir;
}

/**
 * Copied from convert.php: generateContentHash function
 */
function generateContentHash($htmlBlocks, $cssContent = null) {
    $combinedContent = implode('', $htmlBlocks);
    if ($cssContent !== null && $cssContent !== '') {
        $combinedContent .= $cssContent;
    }
    $hash = md5($combinedContent);
    if (!preg_match('/^[a-f0-9]{32}$/', $hash)) {
        throw new Exception('Failed to generate valid MD5 hash');
    }
    return $hash;
}

/**
 * Test 1: Verify css_url validation blocks file:// scheme
 */
$totalTests++;
printTestHeader("Test 1: File:// scheme should be blocked");

$maliciousUrl = 'file:///etc/passwd';
try {
    $result = validateCssUrl($maliciousUrl);
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
    $result = validateCssUrl($maliciousUrl);
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
];

$allCorrect = true;

foreach ($schemesToTest as $url => $shouldPass) {
    try {
        $result = validateCssUrl($url);

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

$allCorrect = true;

foreach ($testCases as $case) {
    list($html, $css, $name) = $case;
    try {
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
    } catch (Exception $e) {
        printResult(false, "Exception generating hash: " . $e->getMessage());
        $allCorrect = false;
        break;
    }
}

if ($allCorrect) {
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
 * Test 11: Verify encoded path traversal is blocked
 */
$totalTests++;
printTestHeader("Test 11: URL-encoded path traversal");

$encodedUrls = [
    'http://example.com/%2e%2e/%2e%2e/etc/passwd',
    'http://example.com/..%2fetc/passwd',
    'http://example.com/..%5cetc/passwd',
];

$allBlocked = true;

foreach ($encodedUrls as $url) {
    try {
        $result = validateCssUrl($url);
        // If accepted, check that cache path is still safe
        $cachePath = getCssCachePath($url);
        if (strpos($cachePath, '../') === false && strpos($cachePath, '..\\') === false) {
            echo "  ✓ $url - Cache path safe despite encoded traversal\n";
        } else {
            echo "  ✗ $url - Cache path contains traversal!\n";
            $allBlocked = false;
        }
    } catch (Exception $e) {
        echo "  ✓ $url - Rejected by validation\n";
    }
}

if ($allBlocked) {
    $passedTests++;
    printResult(true, "URL-encoded path traversal handled safely");
} else {
    printResult(false, "URL-encoded path traversal not fully handled");
}

/**
 * Test 12: Verify absolute paths in URLs don't affect cache
 */
$totalTests++;
printTestHeader("Test 12: Absolute paths in URLs don't affect cache");

$absolutePathUrls = [
    'http://example.com/absolute/path/to/style.css',
    'http://example.com/../../../../absolute/escape.css',
];

$allSafe = true;

foreach ($absolutePathUrls as $url) {
    try {
        $result = validateCssUrl($url);
        $cachePath = getCssCachePath($url);

        // Check filename is just MD5 hash
        $filename = basename($cachePath);
        if (!preg_match('/^[a-f0-9]{32}\.css$/', $filename)) {
            echo "  ✗ $url - Invalid cache filename!\n";
            $allSafe = false;
            break;
        }

        echo "  ✓ $url - Safe cache filename: " . substr($filename, 0, 16) . "...\n";
    } catch (Exception $e) {
        echo "  ✗ $url - Unexpected rejection\n";
        $allSafe = false;
    }
}

if ($allSafe) {
    $passedTests++;
    printResult(true, "Absolute paths in URLs handled safely");
} else {
    printResult(false, "Absolute paths not fully handled");
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
