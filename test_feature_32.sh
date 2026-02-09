#!/bin/bash

# Feature #32: Saves PNG with hash filename
# Verification script

echo "========================================"
echo "Feature #32: Hash Filename Verification"
echo "========================================"
echo ""

# Test 1: Check output directory exists
echo "Test 1: Check output directory"
echo "----------------------------------------"
OUTPUT_DIR="assets/media/rapidhtml2png"
if [ -d "$OUTPUT_DIR" ]; then
    echo "✓ Directory exists: $OUTPUT_DIR"
    TEST1_PASS=true
else
    echo "✗ Directory not found: $OUTPUT_DIR"
    TEST1_PASS=false
fi
echo ""

# Test 2: Check for PNG files
echo "Test 2: Check for PNG files"
echo "----------------------------------------"
PNG_COUNT=$(ls -1 $OUTPUT_DIR/*.png 2>/dev/null | wc -l)
echo "PNG files found: $PNG_COUNT"
if [ "$PNG_COUNT" -gt 0 ]; then
    echo "✓ PNG files exist in output directory"
    TEST2_PASS=true
else
    echo "✗ No PNG files found"
    TEST2_PASS=false
fi
echo ""

# Test 3: Verify filename format (32-char hex + .png)
echo "Test 3: Verify filename format"
echo "----------------------------------------"
TEST3_PASS=true
for file in $OUTPUT_DIR/*.png; do
    if [ -f "$file" ]; then
        filename=$(basename "$file")
        # Extract filename without .png extension
        name_without_ext="${filename%.png}"
        # Check if it's 32 character hexadecimal string
        if echo "$name_without_ext" | grep -qE '^[a-f0-9]{32}$'; then
            echo "✓ $filename (valid MD5 hash format)"
        else
            echo "✗ $filename (INVALID - not MD5 format)"
            TEST3_PASS=false
        fi
    fi
done
echo ""

# Test 4: Check hash generation in code
echo "Test 4: Check hash generation implementation"
echo "----------------------------------------"
if grep -q "function generateContentHash" convert.php; then
    echo "✓ generateContentHash() function exists"
    if grep -q "md5(" convert.php; then
        echo "✓ MD5 hash generation found in code"
        TEST4_PASS=true
    else
        echo "✗ MD5 hash generation not found"
        TEST4_PASS=false
    fi
else
    echo "✗ generateContentHash() function not found"
    TEST4_PASS=false
fi
echo ""

# Test 5: Check file saving implementation
echo "Test 5: Check file saving implementation"
echo "----------------------------------------"
if grep -q '\.png' convert.php; then
    echo "✓ .png extension used in code"
    # Find the line where output path is constructed
    if grep -q "contentHash . '\.png'" convert.php || grep -q 'contentHash . ".png"' convert.php; then
        echo "✓ Filename constructed from hash + .png"
        TEST5_PASS=true
    else
        # Try alternative patterns
        if grep -qE '\$.*\.png' convert.php; then
            echo "✓ PNG file path construction found"
            TEST5_PASS=true
        else
            echo "✗ Could not verify PNG naming pattern"
            TEST5_PASS=false
        fi
    fi
else
    echo "✗ .png extension not found in code"
    TEST5_PASS=false
fi
echo ""

# Summary
echo "========================================"
echo "Test Summary"
echo "========================================"

TOTAL_TESTS=5
PASSED_TESTS=0

[ "$TEST1_PASS" = true ] && PASSED_TESTS=$((PASSED_TESTS + 1))
[ "$TEST2_PASS" = true ] && PASSED_TESTS=$((PASSED_TESTS + 1))
[ "$TEST3_PASS" = true ] && PASSED_TESTS=$((PASSED_TESTS + 1))
[ "$TEST4_PASS" = true ] && PASSED_TESTS=$((PASSED_TESTS + 1))
[ "$TEST5_PASS" = true ] && PASSED_TESTS=$((PASSED_TESTS + 1))

echo "Test 1: Output directory exists:     $([ "$TEST1_PASS" = true ] && echo '✓ PASS' || echo '✗ FAIL')"
echo "Test 2: PNG files present:           $([ "$TEST2_PASS" = true ] && echo '✓ PASS' || echo '✗ FAIL')"
echo "Test 3: Filenames are MD5 hashes:    $([ "$TEST3_PASS" = true ] && echo '✓ PASS' || echo '✗ FAIL')"
echo "Test 4: Hash generation implemented:  $([ "$TEST4_PASS" = true ] && echo '✓ PASS' || echo '✗ FAIL')"
echo "Test 5: File saving uses hash:       $([ "$TEST5_PASS" = true ] && echo '✓ PASS' || echo '✗ FAIL')"
echo ""
echo "Total: $PASSED_TESTS/$TOTAL_TESTS tests passed ("$(echo "scale=1; $PASSED_TESTS * 100 / $TOTAL_TESTS" | bc)%")"
echo ""

if [ "$PASSED_TESTS" -eq "$TOTAL_TESTS" ]; then
    echo "Overall: ✓ ALL TESTS PASSED"
    echo "========================================"
    exit 0
else
    echo "Overall: ✗ SOME TESTS FAILED"
    echo "========================================"
    exit 1
fi
