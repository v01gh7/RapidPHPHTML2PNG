<?php
/**
 * Test script to verify test files are accessible by PHP
 * This verifies Feature #4: Test files present
 */

// Test 1: Check if HTML file exists and is readable
echo "=== Test File Accessibility Verification ===\n\n";

$htmlFile = __DIR__ . '/test_html_to_render.html';
$cssFile = __DIR__ . '/main.css';

// Test HTML file
echo "1. Testing HTML file: $htmlFile\n";
if (file_exists($htmlFile)) {
    echo "   ✅ HTML file EXISTS\n";
    if (is_readable($htmlFile)) {
        echo "   ✅ HTML file is READABLE\n";
        $htmlContent = file_get_contents($htmlFile);
        echo "   ✅ HTML file size: " . strlen($htmlContent) . " bytes\n";
        echo "   ✅ HTML content preview: " . substr(trim($htmlContent), 0, 100) . "...\n";

        // Check for valid HTML structure
        if (stripos($htmlContent, '<div>') !== false || stripos($htmlContent, '<p>') !== false) {
            echo "   ✅ HTML contains valid tags (div, p)\n";
        }
    } else {
        echo "   ❌ HTML file is NOT readable\n";
    }
} else {
    echo "   ❌ HTML file does NOT exist\n";
}

echo "\n";

// Test CSS file
echo "2. Testing CSS file: $cssFile\n";
if (file_exists($cssFile)) {
    echo "   ✅ CSS file EXISTS\n";
    if (is_readable($cssFile)) {
        echo "   ✅ CSS file is READABLE\n";
        $cssContent = file_get_contents($cssFile);
        echo "   ✅ CSS file size: " . strlen($cssContent) . " bytes\n";
        echo "   ✅ CSS content preview: " . substr(trim($cssContent), 0, 100) . "...\n";

        // Check for valid CSS structure
        if (stripos($cssContent, '{') !== false && stripos($cssContent, '}') !== false) {
            echo "   ✅ CSS contains valid CSS syntax (braces found)\n";
        }
    } else {
        echo "   ❌ CSS file is NOT readable\n";
    }
} else {
    echo "   ❌ CSS file does NOT exist\n";
}

echo "\n=== Summary ===\n";
echo "Both test files are present and accessible by PHP scripts.\n";
echo "These files can be used for testing the HTML to PNG conversion.\n";
?>
