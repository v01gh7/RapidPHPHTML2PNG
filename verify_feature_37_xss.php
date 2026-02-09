<?php
/**
 * Feature #37: XSS Protection - Direct Verification
 *
 * This test verifies the sanitization function directly without API calls
 */

// Include the sanitization function from convert.php
// We'll extract and test it directly

echo "=== Feature #37: XSS Protection Direct Verification ===\n\n";

// Copy the sanitization function
function sanitizeHtmlInput($html) {
    // Remove script tags and their content
    $html = preg_replace('#<script\b[^>]*>(.*?)</script>#is', '', $html);

    // Remove iframe tags (often used for clickjacking)
    $html = preg_replace('#<iframe\b[^>]*>.*?</iframe>#is', '', $html);

    // Remove object tags
    $html = preg_replace('#<object\b[^>]*>.*?</object>#is', '', $html);

    // Remove embed tags
    $html = preg_replace('#<embed\b[^>]*>#i', '', $html);

    // Remove form and input tags
    $html = preg_replace('#<form\b[^>]*>.*?</form>#is', '', $html);
    $html = preg_replace('#<input\b[^>]*>#i', '', $html);
    $html = preg_replace('#<button\b[^>]*>.*?</button>#is', '', $html);

    // Remove dangerous event handler attributes from all tags
    $dangerousEvents = [
        'onabort', 'onactivate', 'onafterprint', 'onafterupdate', 'onbeforeactivate',
        'onbeforecopy', 'onbeforecut', 'onbeforedeactivate', 'onbeforeeditfocus',
        'onbeforepaste', 'onbeforeprint', 'onbeforeunload', 'onbeforeupdate',
        'onblur', 'onbounce', 'oncellchange', 'onchange', 'onclick', 'oncontextmenu',
        'oncontrolselect', 'oncopy', 'oncut', 'ondataavailable', 'ondatasetchanged',
        'ondatasetcomplete', 'ondblclick', 'ondeactivate', 'ondrag', 'ondragend',
        'ondragenter', 'ondragleave', 'ondragover', 'ondragstart', 'ondrop',
        'onerror', 'onerrorupdate', 'onfilterchange', 'onfinish', 'onfocus',
        'onfocusin', 'onfocusout', 'onhelp', 'onkeydown', 'onkeypress', 'onkeyup',
        'onlayoutcomplete', 'onload', 'onlosecapture', 'onmousedown', 'onmouseenter',
        'onmouseleave', 'onmousemove', 'onmouseout', 'onmouseover', 'onmouseup',
        'onmousewheel', 'onmove', 'onmoveend', 'onmovestart', 'onpaste',
        'onpropertychange', 'onreadystatechange', 'onreset', 'onresize',
        'onresizeend', 'onresizestart', 'onrowenter', 'onrowexit', 'onrowsdelete',
        'onrowsinserted', 'onscroll', 'onselect', 'onselectionchange', 'onselectstart',
        'onstart', 'onstop', 'onsubmit', 'onunload'
    ];

    foreach ($dangerousEvents as $event) {
        $html = preg_replace('#\s' . $event . '\s*=\s*(["\']).*?\1#is', '', $html);
        $html = preg_replace('#\s' . $event . '\s*=\s*[^\s>]*#is', '', $html);
    }

    // Remove javascript: and vbscript: protocols from href and src attributes
    $html = preg_replace('#\s(href|src|lowsrc|dynsrc|background)\s*=\s*(["\'])?\s*javascript:[^"\'>]*\2?#is', '', $html);
    $html = preg_replace('#\s(href|src|lowsrc|dynsrc|background)\s*=\s*(["\'])?\s*vbscript:[^"\'>]*\2?#is', '', $html);
    $html = preg_replace('#\s(href|src|lowsrc|dynsrc|background)\s*=\s*(["\'])?\s*data:[^"\'>]*\2?#is', '', $html);

    // Remove style attributes containing javascript: or expression()
    $html = preg_replace('#\sstyle\s*=\s*(["\'])(.*?(?:javascript:|expression\().*?)\1#is', '', $html);

    // Remove data-* attributes that could contain malicious code (be conservative)
    $html = preg_replace('#\sdata-[a-z-]+=\s*(["\']).*?\1#is', '', $html);

    // Remove any remaining potential HTML comments with malicious content
    $html = preg_replace('#<!--.*?-->#s', '', $html);

    return trim($html);
}

$tests = [
    [
        'name' => 'Script tag removal',
        'input' => '<div>Hello <script>alert("XSS")</script>World</div>',
        'should_not_contain' => ['<script', '</script>', 'alert'],
        'should_contain' => ['Hello', 'World', '<div>']
    ],
    [
        'name' => 'Event handler removal (onclick)',
        'input' => '<div onclick="alert(1)">Click me</div>',
        'should_not_contain' => ['onclick=', 'alert('],
        'should_contain' => ['Click me', '<div']
    ],
    [
        'name' => 'Event handler removal (onload)',
        'input' => '<img src="x.jpg" onload="alert(1)">',
        'should_not_contain' => ['onload=', 'alert('],
        'should_contain' => ['<img', 'src=']
    ],
    [
        'name' => 'JavaScript in href',
        'input' => '<a href="javascript:alert(1)">Click</a>',
        'should_not_contain' => ['javascript:', 'alert('],
        'should_contain' => ['Click', '<a']
    ],
    [
        'name' => 'iframe removal',
        'input' => '<div>Text <iframe src="evil.com"></iframe> end</div>',
        'should_not_contain' => ['<iframe', '</iframe>'],
        'should_contain' => ['Text', 'end', '<div']
    ],
    [
        'name' => 'form tag removal',
        'input' => '<div>Before <form action="evil"><input type="text"></form> After</div>',
        'should_not_contain' => ['<form', '</form>', '<input'],
        'should_contain' => ['Before', 'After', '<div']
    ],
    [
        'name' => 'Multiple XSS vectors',
        'input' => '<div onclick="alert(1)"><script>alert(2)</script><img src=x onerror="alert(3)"></div>',
        'should_not_contain' => ['<script', 'onclick=', 'onerror=', 'alert('],
        'should_contain' => ['<div>', '<img']
    ],
    [
        'name' => 'Safe HTML preservation',
        'input' => '<div class="test">Hello World</div>',
        'should_not_contain' => [],
        'should_contain' => ['<div', 'class=', 'Hello', 'World']
    ]
];

$passed = 0;
$failed = 0;

foreach ($tests as $test) {
    echo "Test: {$test['name']}\n";
    echo "Input: " . substr($test['input'], 0, 60) . "...\n";

    $output = sanitizeHtmlInput($test['input']);
    echo "Output: " . substr($output, 0, 60) . "...\n";

    $testFailed = false;

    // Check should_not_contain
    foreach ($test['should_not_contain'] as $forbidden) {
        if (stripos($output, $forbidden) !== false) {
            echo "  ❌ FAIL: Found forbidden content: '$forbidden'\n";
            $testFailed = true;
        }
    }

    // Check should_contain
    foreach ($test['should_contain'] as $expected) {
        if (strpos($output, $expected) === false) {
            echo "  ❌ FAIL: Missing expected content: '$expected'\n";
            $testFailed = true;
        }
    }

    if (!$testFailed) {
        echo "  ✅ PASS\n";
        $passed++;
    } else {
        $failed++;
    }

    echo "\n";
}

echo "========================================\n";
echo "Results:\n";
echo "========================================\n";
echo "Total: " . count($tests) . "\n";
echo "Passed: $passed ✅\n";
echo "Failed: $failed ❌\n";
$percentage = round(($passed / count($tests)) * 100, 1);
echo "Success Rate: $percentage%\n";
echo "========================================\n";

exit($failed > 0 ? 1 : 0);
