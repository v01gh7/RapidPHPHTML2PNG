#!/bin/bash

# Feature #33 Test: File Existence Check
# Tests that cached files are returned without re-rendering

echo "=== Feature #33 Test: File Existence Check ==="
echo ""

# Generate unique test content
UNIQUE_ID="F33_TEST_$(date +%s)"
TEST_HTML="<div style='padding: 20px; background: #3498db; color: white;'>$UNIQUE_ID</div>"

echo "Test HTML: $TEST_HTML"
echo ""

# Test 1: First request - should create new file
echo "Test 1: First Request (should create new file)"
echo "----------------------------------------------"

RESPONSE1=$(curl -s -X POST http://localhost:8080/convert.php \
  -H "Content-Type: application/json" \
  -d "{\"html_blocks\": [\"$TEST_HTML\"]}")

echo "$RESPONSE1" | python3 -m json.tool 2>/dev/null || echo "$RESPONSE1"
echo ""

# Extract values from first response
CACHED1=$(echo "$RESPONSE1" | grep -o '"cached":[^,}]*' | cut -d: -f2)
OUTPUT_FILE1=$(echo "$RESPONSE1" | grep -o '"output_file":"[^"]*"' | cut -d'"' -f4 | sed 's/\\//g')
FILE_SIZE1=$(echo "$RESPONSE1" | grep -o '"file_size":[0-9]*' | cut -d: -f2)
ENGINE1=$(echo "$RESPONSE1" | grep -o '"engine":"[^"]*"' | cut -d'"' -f4)

echo "First Request Results:"
echo "  Cached: $CACHED1"
echo "  Engine: $ENGINE1"
echo "  Output File: $OUTPUT_FILE1"
echo "  File Size: $FILE_SIZE1 bytes"
echo ""

# Test 2: Second request with same content - should return cache
echo "Test 2: Second Request (should return cached file)"
echo "--------------------------------------------------"

RESPONSE2=$(curl -s -X POST http://localhost:8080/convert.php \
  -H "Content-Type: application/json" \
  -d "{\"html_blocks\": [\"$TEST_HTML\"]}")

echo "$RESPONSE2" | python3 -m json.tool 2>/dev/null || echo "$RESPONSE2"
echo ""

# Extract values from second response
CACHED2=$(echo "$RESPONSE2" | grep -o '"cached":[^,}]*' | cut -d: -f2)
OUTPUT_FILE2=$(echo "$RESPONSE2" | grep -o '"output_file":"[^"]*"' | cut -d'"' -f4 | sed 's/\\//g')
FILE_SIZE2=$(echo "$RESPONSE2" | grep -o '"file_size":[0-9]*' | cut -d: -f2)
ENGINE2=$(echo "$RESPONSE2" | grep -o '"engine":"[^"]*"' | cut -d'"' -f4)

echo "Second Request Results:"
echo "  Cached: $CACHED2"
echo "  Engine: $ENGINE2"
echo "  Output File: $OUTPUT_FILE2"
echo "  File Size: $FILE_SIZE2 bytes"
echo ""

# Test 3: Verification
echo "Test 3: Verification"
echo "--------------------"

# Count tests passed
PASS=0
TOTAL=0

# Test 3.1: First request was NOT cached
TOTAL=$((TOTAL + 1))
if [ "$CACHED1" = "false" ]; then
    echo "✅ PASS - First request created new file (not cached)"
    PASS=$((PASS + 1))
else
    echo "❌ FAIL - First request should not be cached (was: $CACHED1)"
fi

# Test 3.2: Second request WAS cached
TOTAL=$((TOTAL + 1))
if [ "$CACHED2" = "true" ]; then
    echo "✅ PASS - Second request returned cached file"
    PASS=$((PASS + 1))
else
    echo "❌ FAIL - Second request should be cached (was: $CACHED2)"
fi

# Test 3.3: Same output file path
TOTAL=$((TOTAL + 1))
if [ "$OUTPUT_FILE1" = "$OUTPUT_FILE2" ]; then
    echo "✅ PASS - Same file path returned"
    PASS=$((PASS + 1))
else
    echo "❌ FAIL - Different file paths"
fi

# Test 3.4: Same file size
TOTAL=$((TOTAL + 1))
if [ "$FILE_SIZE1" = "$FILE_SIZE2" ]; then
    echo "✅ PASS - Same file size"
    PASS=$((PASS + 1))
else
    echo "❌ FAIL - Different file sizes"
fi

# Test 3.5: First request has engine, second doesn't (cache indicator)
TOTAL=$((TOTAL + 1))
if [ -n "$ENGINE1" ] && [ -z "$ENGINE2" ]; then
    echo "✅ PASS - First request has engine, cached does not"
    PASS=$((PASS + 1))
else
    echo "❌ FAIL - Engine field unexpected (1: $ENGINE1, 2: $ENGINE2)"
fi

echo ""
echo "=== Test Summary ==="
echo "Passed: $PASS/$TOTAL tests"
echo "Percentage: $(awk "BEGIN {printf \"%.1f\", ($PASS/$TOTAL)*100}")%"

if [ $PASS -eq $TOTAL ]; then
    echo ""
    echo "✅ Feature #33: PASSED"
    echo "File existence check is working correctly!"
    exit 0
else
    echo ""
    echo "❌ Feature #33: FAILED"
    echo "Some tests did not pass."
    exit 1
fi
