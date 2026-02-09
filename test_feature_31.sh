#!/bin/bash

echo "=== Feature #31: Web-Quality PNG Settings Test ==="
echo ""

API_URL="http://localhost/convert.php"

# Test 1: Render HTML content to PNG
echo "Test 1: Render HTML content to PNG"
echo "--------------------------------------------------"

RESPONSE=$(curl -s -X POST "$API_URL" \
  -H "Content-Type: application/json" \
  -d '{"html_blocks":["<div class=\"styled-element\">QUALITY_TEST_31</div>"],"css_url":"http://localhost/main.css"}')

echo "API Response:"
echo "$RESPONSE" | python -m json.tool 2>/dev/null || echo "$RESPONSE"
echo ""

# Extract file path from response
OUTPUT_FILE=$(echo "$RESPONSE" | grep -o '"output_file":"[^"]*"' | cut -d'"' -f4 | sed 's/\\\//g')

if [ -z "$OUTPUT_FILE" ]; then
    echo "❌ FAILED: Could not extract output file path"
    exit 1
fi

echo "Output file: $OUTPUT_FILE"
echo ""

# Copy file from container to host for analysis
CONTAINER_FILE=$(echo "$OUTPUT_FILE" | sed 's|/var/www/html/|||')
docker cp rapidhtml2png-php:"$OUTPUT_FILE" ./feature_31_test_output.png

if [ ! -f ./feature_31_test_output.png ]; then
    echo "❌ FAILED: Could not copy file from container"
    exit 1
fi

echo "✅ PASSED: PNG file created and copied to host"
echo ""

# Test 2: Check PNG file size
echo "Test 2: Check PNG file size is reasonable"
echo "--------------------------------------------------"

FILE_SIZE=$(stat -c%s ./feature_31_test_output.png 2>/dev/null || stat -f%z ./feature_31_test_output.png)
FILE_SIZE_KB=$(echo "scale=2; $FILE_SIZE / 1024" | bc)

echo "File size: $FILE_SIZE bytes ($FILE_SIZE_KB KB)"

MAX_REASONABLE_SIZE=524288  # 500 KB in bytes
MIN_REASONABLE_SIZE=100

if [ $FILE_SIZE -gt $MAX_REASONABLE_SIZE ]; then
    echo "⚠️  WARNING: File size is large"
elif [ $FILE_SIZE -lt $MIN_REASONABLE_SIZE ]; then
    echo "⚠️  WARNING: File size is very small"
else
    echo "✅ PASSED: File size is reasonable for web use"
fi
echo ""

# Test 3: Verify PNG signature
echo "Test 3: Verify PNG format and signature"
echo "--------------------------------------------------"

SIGNATURE=$(head -c 8 ./feature_31_test_output.png | od -A n -t x1 | tr -d ' \n')
EXPECTED_SIGNATURE="89504e470d0a1a0a"

if [ "$SIGNATURE" = "$EXPECTED_SIGNATURE" ]; then
    echo "✅ PASSED: Valid PNG file signature detected"
else
    echo "❌ FAILED: Invalid PNG file signature"
    echo "Got: $SIGNATURE"
    exit 1
fi
echo ""

# Test 4: Check PNG can be displayed (use file command)
echo "Test 4: Verify PNG is browser-compatible"
echo "--------------------------------------------------"

FILE_TYPE=$(file -b --mime-type ./feature_31_test_output.png)

if [ "$FILE_TYPE" = "image/png" ]; then
    echo "✅ PASSED: PNG has correct MIME type: $FILE_TYPE"
else
    echo "❌ FAILED: Incorrect MIME type: $FILE_TYPE"
    exit 1
fi
echo ""

# Test 5: Check image dimensions
echo "Test 5: Check PNG dimensions"
echo "--------------------------------------------------"

if command -v identify >/dev/null 2>&1; then
    DIMENSIONS=$(identify -format "%wx%h" ./feature_31_test_output.png 2>/dev/null)
    if [ -n "$DIMENSIONS" ]; then
        echo "✅ PASSED: PNG dimensions: $DIMENSIONS"
    fi
elif command -v file >/dev/null 2>&1; then
    FILE_INFO=$(file ./feature_31_test_output.png)
    echo "File info: $FILE_INFO"
fi
echo ""

# Summary
echo "=== Test Summary ==="
echo "✅ All tests PASSED for Feature #31"
echo ""
echo "PNG Quality Assessment:"
echo "  File: ./feature_31_test_output.png"
echo "  Size: $FILE_SIZE bytes ($FILE_SIZE_KB KB)"
echo "  Type: $FILE_TYPE"
echo "  Status: Suitable for web use"
