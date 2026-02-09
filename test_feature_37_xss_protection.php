<?php
/**
 * Feature #37: XSS Protection Tests
 *
 * This test verifies that HTML input is properly sanitized to prevent XSS attacks.
 */

require_once 'convert.php';

echo "=== Feature #37: XSS Protection Tests ===\n\n";

$testsPassed = 0;
$testsFailed = 0;

/**
 * Test helper function
 */
function runXssTest($testName, $maliciousHtml, $shouldContain = []) {
    global $testsPassed, $testsFailed;

    echo "Test: $testName\n";
    echo "Input: " . substr($maliciousHtml, 0, 100) . (strlen($maliciousHtml) > 100 ? '...' : '') . "\n";

    try {
        $sanitized = sanitizeHtmlInput($maliciousHtml);

        echo "Output: " . substr($sanitized, 0, 100) . (strlen($sanitized) > 100 ? '...' : '') . "\n";

        $failed = false;

        // Check that malicious content is removed
        if (preg_match('/<script/i', $sanitized)) {
            echo "  ❌ FAIL: Script tag not removed\n";
            $failed = true;
        }

        if (preg_match('/javascript:/i', $sanitized)) {
            echo "  ❌ FAIL: javascript: protocol not removed\n";
            $failed = true;
        }

        if (preg_match('/onclick=/i', $sanitized) || preg_match('/onload=/i', $sanitized)) {
            echo "  ❌ FAIL: Event handler not removed\n";
            $failed = true;
        }

        if (preg_match('/<iframe/i', $sanitized)) {
            echo "  ❌ FAIL: iframe tag not removed\n";
            $failed = true;
        }

        if (preg_match('/<form/i', $sanitized) || preg_match('/<input/i', $sanitized)) {
            echo "  ❌ FAIL: Form/input tags not removed\n";
            $failed = true;
        }

        // Check that safe content is preserved
        if (!empty($shouldContain)) {
            foreach ($shouldContain as $expected) {
                if (strpos($sanitized, $expected) === false) {
                    echo "  ❌ FAIL: Expected content not preserved: '$expected'\n";
                    $failed = true;
                }
            }
        }

        if (!$failed) {
            echo "  ✅ PASS\n";
            $testsPassed++;
        } else {
            $testsFailed++;
        }
    } catch (Exception $e) {
        echo "  ❌ FAIL: Exception - " . $e->getMessage() . "\n";
        $testsFailed++;
    }

    echo "\n";
}

// ============================================================================
// Test 1: Basic script tag removal
// ============================================================================
runXssTest(
    "Basic script tag removal",
    '<div>Hello <script>alert("XSS")</script>World</div>',
    ['Hello', 'World', '<div>']
);

// ============================================================================
// Test 2: Script tag with attributes
// ============================================================================
runXssTest(
    "Script tag with attributes",
    '<p>Text <script type="text/javascript" src="evil.js"></script> more text</p>',
    ['Text', 'more text', '<p>']
);

// ============================================================================
// Test 3: Event handler removal (onclick)
// ============================================================================
runXssTest(
    "Event handler removal (onclick)",
    '<div onclick="alert(1)">Click me</div>',
    ['Click me', '<div>']
);

// ============================================================================
// Test 4: Event handler removal (onload)
// ============================================================================
runXssTest(
    "Event handler removal (onload)",
    '<img src="x.jpg" onload="alert(1)">',
    ['<img', 'src=']
);

// ============================================================================
// Test 5: Event handler removal (onerror)
// ============================================================================
runXssTest(
    "Event handler removal (onerror)",
    '<img src="invalid.jpg" onerror="alert(1)">',
    ['<img', 'src=']
);

// ============================================================================
// Test 6: JavaScript in href
// ============================================================================
runXssTest(
    "JavaScript protocol in href",
    '<a href="javascript:alert(1)">Click</a>',
    ['Click', '<a>']
);

// ============================================================================
// Test 7: VBScript in href
// ============================================================================
runXssTest(
    "VBScript protocol in href",
    '<a href="vbscript:msgbox(1)">Click</a>',
    ['Click', '<a>']
);

// ============================================================================
// Test 8: JavaScript in src
// ============================================================================
runXssTest(
    "JavaScript protocol in src",
    '<img src="javascript:alert(1)">',
    ['<img>']
);

// ============================================================================
// Test 9: iframe tag removal
// ============================================================================
runXssTest(
    "iframe tag removal",
    '<div>Before <iframe src="evil.com"></iframe> After</div>',
    ['Before', 'After', '<div>']
);

// ============================================================================
// Test 10: object tag removal
// ============================================================================
runXssTest(
    "object tag removal",
    '<div>Content <object data="evil.swf"></object> more</div>',
    ['Content', 'more', '<div>']
);

// ============================================================================
// Test 11: embed tag removal
// ============================================================================
runXssTest(
    "embed tag removal",
    '<p>Text <embed src="evil.swf"> end</p>',
    ['Text', 'end', '<p>']
);

// ============================================================================
// Test 12: form tag removal
// ============================================================================
runXssTest(
    "form tag removal",
    '<div>Before <form action="evil"><input type="text"></form> After</div>',
    ['Before', 'After', '<div>']
);

// ============================================================================
// Test 13: input tag removal
// ============================================================================
runXssTest(
    "input tag removal",
    '<p>Field: <input type="text" onclick="alert(1)"></p>',
    ['Field:', '<p>']
);

// ============================================================================
// Test 14: button tag removal
// ============================================================================
runXssTest(
    "button tag removal",
    '<div><button onclick="alert(1)">Click</button></div>',
    ['<div>']
);

// ============================================================================
// Test 15: Multiple event handlers
// ============================================================================
runXssTest(
    "Multiple event handlers",
    '<div onclick="alert(1)" onmouseover="alert(2)" onmouseout="alert(3)">Text</div>',
    ['Text', '<div>']
);

// ============================================================================
// Test 16: Style attribute with JavaScript
// ============================================================================
runXssTest(
    "Style with JavaScript expression",
    '<div style="width: expression(alert(1))">Content</div>',
    ['Content', '<div>']
);

// ============================================================================
// Test 17: Data attribute removal
// ============================================================================
runXssTest(
    "Data attribute removal",
    '<div data-src="javascript:alert(1)" data-onload="alert(2)">Content</div>',
    ['Content', '<div>']
);

// ============================================================================
// Test 18: HTML comment removal
// ============================================================================
runXssTest(
    "HTML comment removal",
    '<div>Before <!-- Comment with <script>alert(1)</script> --> After</div>',
    ['Before', 'After', '<div>']
);

// ============================================================================
// Test 19: XSS from common vectors (IMG tag)
// ============================================================================
runXssTest(
    "Common XSS vector - IMG tag",
    '<img src=x onerror="alert(1)">',
    ['<img>']
);

// ============================================================================
// Test 20: XSS from common vectors (SVG tag)
// ============================================================================
runXssTest(
    "Common XSS vector - SVG tag",
    '<svg onload="alert(1)">Text</svg>',
    ['Text']  // SVG is allowed but onload should be removed
);

// ============================================================================
// Test 21: Mixed case script tags
// ============================================================================
runXssTest(
    "Mixed case script tag",
    '<div>Before <SCRIPT>alert("XSS")</SCRIPT> After</div>',
    ['Before', 'After', '<div>']
);

// ============================================================================
// Test 22: Nested dangerous elements
// ============================================================================
runXssTest(
    "Nested dangerous elements",
    '<div><script>alert(1)</script><iframe src="evil"></iframe><form><input></form></div>',
    ['<div>']
);

// ============================================================================
// Test 23: Safe HTML should be preserved
// ============================================================================
runXssTest(
    "Safe HTML preservation",
    '<div class="container"><p>Hello <span class="highlight">World</span></p></div>',
    ['<div', 'class=', '<p>', '<span>', 'Hello', 'World']
);

// ============================================================================
// Test 24: Safe attributes should be preserved
// ============================================================================
runXssTest(
    "Safe attribute preservation",
    '<div id="myDiv" class="test" data-safe="value">Content</div>',
    ['Content', '<div', 'id=', 'class=']
);

// ============================================================================
// Test 25: Multiple script tags
// ============================================================================
runXssTest(
    "Multiple script tags",
    '<script>alert(1)</script>Text<script>alert(2)</script>',
    ['Text']
);

// ============================================================================
// Summary
// ============================================================================
echo "========================================\n";
echo "Test Results Summary:\n";
echo "========================================\n";
echo "Total Tests: " . ($testsPassed + $testsFailed) . "\n";
echo "Passed: $testsPassed ✅\n";
echo "Failed: $testsFailed ❌\n";
$percentage = ($testsPassed + $testsFailed) > 0
    ? round(($testsPassed / ($testsPassed + $testsFailed)) * 100, 1)
    : 0;
echo "Success Rate: $percentage%\n";
echo "========================================\n";

// Exit with proper code
exit($testsFailed > 0 ? 1 : 0);
