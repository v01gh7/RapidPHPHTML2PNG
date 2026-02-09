#!/bin/bash

echo "=== Feature #25: ImageMagick Rendering Test via API ==="
echo ""

# Test the API endpoint
echo "Sending POST request to convert.php..."
echo ""

RESPONSE=$(curl -s -X POST http://localhost:8080/convert.php \
  -H "Content-Type: multipart/form-data" \
  -F "html_blocks[]=<div>IM_RENDER_TEST</div>" \
  -F "css_url=http://localhost:8080/main.css")

echo "Response:"
echo "$RESPONSE" | head -50
echo ""

# Check if response contains library_detection
echo "Checking for ImageMagick in library_detection..."
echo "$RESPONSE" | grep -i imagemagick
echo ""

# Check if rendering succeeded
echo "Checking rendering engine..."
echo "$RESPONSE" | grep -i '"engine"'
echo ""

# Check if file was created
echo "Checking for output file..."
echo "$RESPONSE" | grep -i 'output_file'
echo ""
