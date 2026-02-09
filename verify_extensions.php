<?php
/**
 * RapidHTML2PNG - PHP Extensions Verification Script
 *
 * This script verifies that all required PHP extensions are loaded.
 * It can be run inside the Docker container or on any PHP 7.4+ installation.
 *
 * Usage: php verify_extensions.php
 *        Or visit: http://localhost:8080/verify_extensions.php
 */

// Required extensions for RapidHTML2PNG
$required_extensions = [
    'curl' => 'Required for fetching CSS files from URLs',
    'gd' => 'Required for basic image processing and rendering',
    'mbstring' => 'Required for string manipulation (multibyte support)'
];

// Optional but recommended extensions
$optional_extensions = [
    'imagick' => 'Provides better image rendering quality than GD',
    'zip' => 'Useful for batch operations'
];

echo "==========================================\n";
echo "RapidHTML2PNG - PHP Extensions Check\n";
echo "==========================================\n\n";

// PHP Version
echo "PHP Version: " . phpversion() . "\n";
echo "Required: 7.4+\n";

if (version_compare(phpversion(), '7.4.0', '<')) {
    echo "❌ ERROR: PHP version is too old!\n\n";
    exit(1);
} else {
    echo "✓ PHP version is compatible\n\n";
}

// Check required extensions
echo "Required Extensions:\n";
echo "--------------------\n";
$all_required_loaded = true;

foreach ($required_extensions as $ext => $description) {
    $loaded = extension_loaded($ext);
    $status = $loaded ? '✓' : '✗';
    $state = $loaded ? 'LOADED' : 'MISSING';

    echo "{$status} {$ext} ({$state})";
    echo " - {$description}\n";

    if (!$loaded) {
        $all_required_loaded = false;
    }
}

echo "\n";

// Check optional extensions
echo "Optional Extensions:\n";
echo "--------------------\n";

foreach ($optional_extensions as $ext => $description) {
    $loaded = extension_loaded($ext);
    $status = $loaded ? '✓' : '○';
    $state = $loaded ? 'LOADED' : 'NOT AVAILABLE';

    echo "{$status} {$ext} ({$state})";
    echo " - {$description}\n";
}

echo "\n";

// Detailed information about GD extension
if (extension_loaded('gd')) {
    echo "GD Extension Details:\n";
    echo "--------------------\n";
    $gd_info = gd_info();
    foreach ($gd_info as $key => $value) {
        $status = $value ? '✓' : '✗';
        echo "{$status} {$key}: " . ($value ? 'Yes' : 'No') . "\n";
    }
    echo "\n";
}

// cURL availability
if (extension_loaded('curl')) {
    echo "cURL Extension Details:\n";
    echo "----------------------\n";
    $curl_version = curl_version();
    echo "✓ cURL Version: " . $curl_version['version'] . "\n";
    echo "✓ SSL Support: " . ($curl_version['ssl_version'] ?? 'No') . "\n";
    echo "\n";
}

// Summary
echo "==========================================\n";
if ($all_required_loaded) {
    echo "✓ SUCCESS: All required extensions are loaded!\n";
    echo "==========================================\n\n";

    // Display loaded modules (full list)
    echo "All Loaded Modules:\n";
    echo "-------------------\n";
    $loaded_modules = get_loaded_extensions();
    natcasesort($loaded_modules);
    foreach ($loaded_modules as $module) {
        echo "  - {$module}\n";
    }

    exit(0);
} else {
    echo "✗ FAILURE: Some required extensions are missing!\n";
    echo "==========================================\n\n";
    echo "To install missing extensions in Docker:\n";
    echo "1. Update Dockerfile to include: docker-php-ext-install <extension_name>\n";
    echo "2. Rebuild: docker-compose build\n";
    echo "3. Restart: docker-compose up -d\n\n";
    exit(1);
}
