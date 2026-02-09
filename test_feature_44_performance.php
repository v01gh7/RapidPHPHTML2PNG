<?php
/**
 * Feature #44: Performance Test - Request Completes in Reasonable Time
 *
 * This test measures the response time for various HTML rendering requests
 * and verifies they complete within acceptable time limits.
 *
 * Requirements:
 * 1. Typical content should render in under 5 seconds
 * 2. Performance should be consistent across requests
 * 3. Caching should improve performance on subsequent requests
 * 4. No significant performance degradation with moderately complex content
 */

// Configuration
define('API_URL', 'http://localhost:8080/convert.php');
define('MAX_ACCEPTABLE_TIME', 5.0); // 5 seconds maximum
define('EXPECTED_AVG_TIME', 2.0); // Expected average time
define('CACHE_IMPROVEMENT_FACTOR', 0.5); // Cached requests should be at least 50% faster

// Test HTML content (moderately complex)
$testCases = [
    'simple' => [
        'html' => '<div style="color: red;">Simple Test</div>',
        'description' => 'Simple HTML block'
    ],
    'moderate' => [
        'html' => '
            <div class="container">
                <h1 style="color: #333; font-size: 24px;">Performance Test</h1>
                <p style="color: #666; font-size: 14px;">This is a moderately complex HTML block.</p>
                <ul style="list-style-type: disc; padding-left: 20px;">
                    <li>Item 1</li>
                    <li>Item 2</li>
                    <li>Item 3</li>
                </ul>
            </div>
        ',
        'description' => 'Moderately complex HTML'
    ],
    'complex' => [
        'html' => '
            <div class="card" style="border: 1px solid #ddd; border-radius: 8px; padding: 20px; background: white;">
                <h2 style="color: #2c3e50; margin-top: 0;">Complex Card Component</h2>
                <div class="content" style="display: flex; gap: 15px;">
                    <div style="flex: 1;">
                        <p style="line-height: 1.6;">Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p>
                        <button style="background: #3498db; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer;">Click Me</button>
                    </div>
                    <div style="flex: 1;">
                        <ul style="list-style: none; padding: 0;">
                            <li style="padding: 5px 0; border-bottom: 1px solid #eee;">Feature 1</li>
                            <li style="padding: 5px 0; border-bottom: 1px solid #eee;">Feature 2</li>
                            <li style="padding: 5px 0; border-bottom: 1px solid #eee;">Feature 3</li>
                        </ul>
                    </div>
                </div>
            </div>
        ',
        'description' => 'Complex nested HTML with multiple styles'
    ]
];

// Colors for terminal output
$GREEN = "\033[0;32m";
$RED = "\033[0;31m";
$YELLOW = "\033[1;33m";
$BLUE = "\033[0;34m";
$NC = "\033[0m";

echo "\n";
echo "==========================================\n";
echo "Feature #44: Performance Test\n";
echo "==========================================\n";
echo "Testing API: " . API_URL . "\n";
echo "Max acceptable time: " . MAX_ACCEPTABLE_TIME . " seconds\n";
echo "==========================================\n\n";

$testNumber = 1;
$totalTests = 0;
$passedTests = 0;
$allTimes = [];

/**
 * Make a request and measure time
 */
function makeTimedRequest($html, $testCase) {
    global $testNumber, $totalTests, $passedTests, $GREEN, $RED, $YELLOW, $NC, $allTimes;

    echo $BLUE . "Test #{$testNumber}: {$testCase['description']}" . $NC . "\n";

    $data = [
        'html_blocks' => [$html],
        'css_url' => 'http://localhost:8080/main.css'
    ];

    $startTime = microtime(true);
    $ch = curl_init(API_URL);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $endTime = microtime(true);
    curl_close($ch);

    $duration = round($endTime - $startTime, 3);
    $allTimes[] = $duration;

    $totalTests++;

    echo "  Duration: {$duration} seconds\n";
    echo "  HTTP Status: {$httpCode}\n";

    $result = json_decode($response, true);

    // Check if request completed within acceptable time
    if ($duration <= MAX_ACCEPTABLE_TIME) {
        echo $GREEN . "  ✓ PASS: Request completed within acceptable time" . $NC . "\n";
        $passedTests++;
    } else {
        echo $RED . "  ✗ FAIL: Request took too long (max: " . MAX_ACCEPTABLE_TIME . "s)" . $NC . "\n";
    }

    // Check if response is successful
    if ($result && isset($result['success']) && $result['success'] === true) {
        echo $GREEN . "  ✓ PASS: Response indicates success" . $NC . "\n";
        $passedTests++;
    } else {
        echo $RED . "  ✗ FAIL: Response does not indicate success" . $NC . "\n";
    }
    $totalTests++;

    $testNumber++;

    return [
        'duration' => $duration,
        'success' => ($result && isset($result['success']) && $result['success'] === true),
        'hash' => ($result && isset($result['data']['hash']) ? $result['data']['hash'] : null)
    ];
}

/**
 * Make a cached request and verify performance improvement
 */
function testCachedPerformance($html, $testCase, $hash) {
    global $testNumber, $totalTests, $passedTests, $GREEN, $RED, $YELLOW, $NC, $allTimes;

    echo $BLUE . "Test #{$testNumber}: Cached request for {$testCase['description']}" . $NC . "\n";

    $data = [
        'html_blocks' => [$html],
        'css_url' => 'http://localhost:8080/main.css'
    ];

    $startTime = microtime(true);
    $ch = curl_init(API_URL);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $endTime = microtime(true);
    curl_close($ch);

    $duration = round($endTime - $startTime, 3);
    $allTimes[] = $duration;

    echo "  Duration: {$duration} seconds\n";
    echo "  HTTP Status: {$httpCode}\n";

    $result = json_decode($response, true);

    // Check if cached response is faster
    if ($duration <= MAX_ACCEPTABLE_TIME) {
        echo $GREEN . "  ✓ PASS: Cached request completed within acceptable time" . $NC . "\n";
        $passedTests++;
    } else {
        echo $RED . "  ✗ FAIL: Cached request took too long" . $NC . "\n";
    }
    $totalTests++;

    // Check if response indicates cache hit
    if ($result && isset($result['cached']) && $result['cached'] === true) {
        echo $GREEN . "  ✓ PASS: Response indicates cache hit" . $NC . "\n";
        $passedTests++;
    } else {
        echo $YELLOW . "  ⚠ WARNING: Response does not indicate cache hit" . $NC . "\n";
    }
    $totalTests++;

    $testNumber++;

    return $duration;
}

// Test each case
$firstResults = [];
foreach ($testCases as $key => $testCase) {
    $firstResults[$key] = makeTimedRequest($testCase['html'], $testCase);
    echo "\n";
}

// Test cache performance
echo $BLUE . "========================================" . $NC . "\n";
echo $BLUE . "Testing Cache Performance Improvement" . $NC . "\n";
echo $BLUE . "========================================" . $NC . "\n\n";

foreach ($testCases as $key => $testCase) {
    if ($firstResults[$key]['hash']) {
        $cachedDuration = testCachedPerformance($testCase['html'], $testCase, $firstResults[$key]['hash']);

        // Calculate improvement
        $originalDuration = $firstResults[$key]['duration'];
        $improvement = $originalDuration - $cachedDuration;
        $improvementPercent = round(($improvement / $originalDuration) * 100, 1);

        echo "  Original: {$originalDuration}s | Cached: {$cachedDuration}s | Improvement: {$improvementPercent}%\n";

        if ($cachedDuration < $originalDuration) {
            echo $GREEN . "  ✓ PASS: Cached request is faster" . $NC . "\n";
            $passedTests++;
        } else {
            echo $YELLOW . "  ⚠ WARNING: Cached request not faster (may be timing granularity)" . $NC . "\n";
        }
        $totalTests++;

        echo "\n";
    }
}

// Test consistency (multiple requests)
echo $BLUE . "========================================" . $NC . "\n";
echo $BLUE . "Testing Performance Consistency" . $NC . "\n";
echo $BLUE . "========================================" . $NC . "\n\n";

$consistencyRuns = 5;
$consistencyTimes = [];

for ($i = 0; $i < $consistencyRuns; $i++) {
    $html = '<div style="color: blue;">Consistency Test ' . ($i + 1) . '</div>';

    $data = [
        'html_blocks' => [$html],
        'css_url' => 'http://localhost:8080/main.css'
    ];

    $startTime = microtime(true);
    $ch = curl_init(API_URL);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $response = curl_exec($ch);
    $endTime = microtime(true);
    curl_close($ch);

    $duration = round($endTime - $startTime, 3);
    $consistencyTimes[] = $duration;

    echo "  Run " . ($i + 1) . ": {$duration}s\n";
}

$avgConsistency = round(array_sum($consistencyTimes) / count($consistencyTimes), 3);
$minConsistency = min($consistencyTimes);
$maxConsistency = max($consistencyTimes);
$variance = round($maxConsistency - $minConsistency, 3);

echo "\n";
echo "  Average: {$avgConsistency}s\n";
echo "  Min: {$minConsistency}s | Max: {$maxConsistency}s\n";
echo "  Variance: {$variance}s\n";

// Check if variance is reasonable (within 2 seconds)
if ($variance < 2.0) {
    echo $GREEN . "  ✓ PASS: Performance is consistent (variance < 2s)" . $NC . "\n";
    $passedTests++;
} else {
    echo $RED . "  ✗ FAIL: High variance indicates inconsistent performance" . $NC . "\n";
}
$totalTests++;

// Calculate overall statistics
echo "\n";
echo "==========================================\n";
echo "Performance Statistics\n";
echo "==========================================\n";

if (!empty($allTimes)) {
    $avgTime = round(array_sum($allTimes) / count($allTimes), 3);
    $minTime = min($allTimes);
    $maxTime = max($allTimes);
    $medianTime = round($allTimes[count($allTimes) / 2], 3);

    echo "Total requests: " . count($allTimes) . "\n";
    echo "Average time: {$avgTime}s\n";
    echo "Median time: {$medianTime}s\n";
    echo "Min time: {$minTime}s\n";
    echo "Max time: {$maxTime}s\n";

    // Check if average is acceptable
    $totalTests++;
    if ($avgTime <= EXPECTED_AVG_TIME) {
        echo $GREEN . "✓ PASS: Average time meets expectation (≤ " . EXPECTED_AVG_TIME . "s)" . $NC . "\n";
        $passedTests++;
    } else {
        echo $YELLOW . "⚠ WARNING: Average time exceeds expectation (" . EXPECTED_AVG_TIME . "s)" . $NC . "\n";
    }

    // Check if max is acceptable
    $totalTests++;
    if ($maxTime <= MAX_ACCEPTABLE_TIME) {
        echo $GREEN . "✓ PASS: All requests completed within max time (≤ " . MAX_ACCEPTABLE_TIME . "s)" . $NC . "\n";
        $passedTests++;
    } else {
        echo $RED . "✗ FAIL: Some requests exceeded max time (" . MAX_ACCEPTABLE_TIME . "s)" . $NC . "\n";
    }
}

// Summary
echo "\n";
echo "==========================================\n";
echo "Test Summary\n";
echo "==========================================\n";
echo "Total tests: {$totalTests}\n";
echo "Passed: {$passedTests}\n";
echo "Failed: " . ($totalTests - $passedTests) . "\n";
echo "Success rate: " . round(($passedTests / $totalTests) * 100, 1) . "%\n";

if ($passedTests === $totalTests) {
    echo "\n" . $GREEN . "✓ ALL TESTS PASSED!" . $NC . "\n";
    exit(0);
} else {
    echo "\n" . $RED . "✗ SOME TESTS FAILED" . $NC . "\n";
    exit(1);
}
