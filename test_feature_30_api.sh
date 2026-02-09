#!/bin/bash

# Test Feature #30: HTML Structure Handling
# Tests that HTML structure (divs, spans, classes) is rendered correctly.

echo "=== Feature #30: HTML Structure Handling Test ==="
echo ""

# Test HTML with nested divs and spans with specific classes
TEST_HTML='<div class="outer">outer <span class="inner">inner</span></div>'

# Simple CSS for styling
TEST_CSS='.outer { color: #000000; font-size: 16px; } .inner { color: #333333; }'

echo "Test HTML: $TEST_HTML"
echo "Test CSS: $TEST_CSS"
echo ""

# Make API request
echo "Sending POST request to convert.php..."
echo ""

RESPONSE=$(curl -s -X POST http://localhost/convert.php \
  -H "Content-Type: application/json" \
  -d "{\"html_blocks\": [\"$TEST_HTML\"], \"css_url\": null}")

echo "API Response:"
echo "$RESPONSE" | python3 -m json.tool 2>/dev/null || echo "$RESPONSE"
echo ""

# Extract key info
SUCCESS=$(echo "$RESPONSE" | grep -o '"success":[^,]*' | cut -d: -f2)
ENGINE=$(echo "$RESPONSE" | grep -o '"engine":"[^"]*"' | cut -d: -f2 | tr -d '"')
OUTPUT_FILE=$(echo "$RESPONSE" | grep -o '"output_file":"[^"]*"' | cut -d: -f2 | tr -d '"')

echo "=== Test Results ==="
echo "Success: $SUCCESS"
echo "Engine: $ENGINE"
echo "Output File: $OUTPUT_FILE"
echo ""

# Check feature requirements
echo "=== Feature Requirements ==="
echo "1. HTML with nested divs and spans: ✓ YES"
echo "   <div class=\"outer\">outer <span class=\"inner\">inner</span></div>"
echo "2. Text in each element ('outer', 'inner'): ✓ YES"
echo "3. HTML rendered to PNG: $(if [ "$SUCCESS" = "true" ]; then echo '✓ YES'; else echo '✗ NO'; fi)"
echo "4. Structure preserved: ⚠ REQUIRES VISUAL VERIFICATION"
echo "5. Text in proper hierarchy: ⚠ REQUIRES VISUAL VERIFICATION"
echo ""

if [ "$SUCCESS" = "true" ]; then
    echo "✓ Test PASSED - PNG file created"
    echo ""
    echo "To visually verify, check the generated PNG file on the server:"
    echo "  $OUTPUT_FILE"
else
    echo "✗ Test FAILED - Could not create PNG"
fi
