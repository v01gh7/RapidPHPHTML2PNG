<?php
// Debug script to test ImageMagick functionality
$logFile = __DIR__ . '/logs/imagemagick_debug.log';

function logMsg($msg) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $msg\n", FILE_APPEND);
}

logMsg("=== ImageMagick Debug Test Started ===");

// Test 1: Extension loaded
logMsg("Test 1: Checking extension_loaded('imagick')...");
if (extension_loaded('imagick')) {
    logMsg("PASS: Imagick extension is loaded");
} else {
    logMsg("FAIL: Imagick extension NOT loaded");
    die;
}

// Test 2: Class exists
logMsg("Test 2: Checking class_exists('Imagick')...");
if (class_exists('Imagick')) {
    logMsg("PASS: Imagick class exists");
} else {
    logMsg("FAIL: Imagick class does NOT exist");
    die;
}

// Test 3: Create Imagick object
logMsg("Test 3: Creating Imagick object...");
try {
    $imagick = new Imagick();
    logMsg("PASS: Imagick object created successfully");
    $imagick->clear();
    $imagick->destroy();
} catch (Exception $e) {
    logMsg("FAIL: " . $e->getMessage());
    die;
}

// Test 4: Check if renderWithImageMagick function exists
logMsg("Test 4: Checking function_exists('renderWithImageMagick')...");
if (function_exists('renderWithImageMagick')) {
    logMsg("PASS: renderWithImageMagick function exists");
} else {
    logMsg("INFO: Function not defined yet (need to include convert.php)");
}

// Test 5: Try to include convert.php
logMsg("Test 5: Including convert.php...");
try {
    include_once __DIR__ . '/convert.php';
    logMsg("PASS: convert.php included successfully");
} catch (Exception $e) {
    logMsg("FAIL: " . $e->getMessage());
    die;
}

// Test 6: Check function again after include
logMsg("Test 6: Checking function after include...");
if (function_exists('renderWithImageMagick')) {
    logMsg("PASS: renderWithImageMagick function exists after include");
} else {
    logMsg("FAIL: Function still does not exist");
    die;
}

// Test 7: Test library detection
logMsg("Test 7: Testing detectAvailableLibraries()...");
$detection = detectAvailableLibraries();
$imAvailable = $detection['detected_libraries']['imagemagick']['available'] ?? false;
logMsg("ImageMagick available: " . ($imAvailable ? 'YES' : 'NO'));
if ($imAvailable) {
    logMsg("Version: " . ($detection['detected_libraries']['imagemagick']['version'] ?? 'N/A'));
} else {
    logMsg("Reason: " . ($detection['detected_libraries']['imagemagick']['reason'] ?? 'Unknown'));
}

logMsg("=== All Tests Completed ===");

echo "Debug completed. Check logs at: $logFile\n";
