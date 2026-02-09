<?php
/**
 * Test Sensitive Data Sanitization for Feature #42
 */

define('TEST_MODE', true);

require_once __DIR__ . '/convert.php';

echo "Testing sanitizeLogData function:\n\n";

$testData = [
    'username' => 'testuser',
    'password' => 'secret123',
    'api_key' => 'key_abc123',
    'token' => 'token_xyz',
    'normal_field' => 'safe data',
    'nested' => [
        'secret' => 'hidden',
        'public' => 'visible',
        'access_token' => 'should_be_redacted'
    ]
];

$result = sanitizeLogData($testData);

echo "Original data:\n";
echo json_encode($testData, JSON_PRETTY_PRINT) . "\n\n";

echo "Sanitized data:\n";
echo json_encode($result, JSON_PRETTY_PRINT) . "\n\n";

// Verify redaction
$checks = [
    'password' => $result['password'] === '[REDACTED]',
    'api_key' => $result['api_key'] === '[REDACTED]',
    'token' => $result['token'] === '[REDACTED]',
    'nested.secret' => $result['nested']['secret'] === '[REDACTED]',
    'nested.access_token' => $result['nested']['access_token'] === '[REDACTED]',
    'normal_field preserved' => $result['normal_field'] === 'safe data',
    'nested.public preserved' => $result['nested']['public'] === 'visible'
];

echo "Verification checks:\n";
foreach ($checks as $name => $passed) {
    echo ($passed ? "✓ PASS" : "✗ FAIL") . ": $name\n";
}

echo "\nAll " . (count(array_filter($checks)) === count($checks) ? "passed" : "some failed") . "!\n";
