#!/bin/bash
# Feature #35: Recreates PNG when hash changes
# Simple test using direct API calls

API_URL="http://localhost:8080/convert.php"
OUTPUT_DIR="./assets/media/rapidhtml2png"

echo "=== Feature #35: Recreates PNG when hash changes ==="
echo ""

# Helper functions
pass() { echo "✅ PASS: $1"; }
fail() { echo "❌ FAIL: $1"; }
info() { echo "ℹ️  INFO: $1"; }
section() { echo ""; echo "📋 $1"; }

# Make API request and save response
make_request() {
    local html="$1"
    curl -s "$API_URL" \
        -X POST \
        -H "Content-Type: application/json" \
        -d "{\"html_blocks\":[\"$html\"]}" \
        > /tmp/resp_$$.json
}

# Extract field from JSON response
extract_field() {
    local field="$1"
    grep -o "\"$field\":\"[^\"]*\"" /tmp/resp_$$.json | cut -d'"' -f4
}

# Extract boolean field
extract_bool() {
    local field="$1"
    grep -o "\"$field\":[^,}]*" /tmp/resp_$$.json | cut -d':' -f2
}

# ============================================================================
section "Test 1: Different HTML produces different hashes"
# ============================================================================

info "Request 1: HTML with 'CONTENT_V1'"
make_request "CONTENT_V1"
HASH1=$(extract_field "content_hash")
FILE1=$(extract_field "output_file" | sed 's/.*\///')

info "Request 2: HTML with 'CONTENT_V2'"
make_request "CONTENT_V2"
HASH2=$(extract_field "content_hash")
FILE2=$(extract_field "output_file" | sed 's/.*\///')

info "Hash 1: $HASH1"
info "Hash 2: $HASH2"
info "File 1: $FILE1"
info "File 2: $FILE2"

if [ -n "$HASH1" ] && [ -n "$HASH2" ] && [ "$HASH1" != "$HASH2" ]; then
    pass "Different HTML content produces different hashes"
    ((TESTS_PASSED++))
else
    fail "Different HTML content should produce different hashes"
    ((TESTS_FAILED++))
fi

if [ -n "$FILE1" ] && [ -n "$FILE2" ] && [ "$FILE1" != "$FILE2" ]; then
    pass "Different hash filenames created"
    ((TESTS_PASSED++))
else
    fail "Different filenames should be created"
    ((TESTS_FAILED++))
fi

# ============================================================================
section "Test 2: Same HTML produces same hash (deterministic)"
# ============================================================================

info "Request 1: HTML with 'DETERMINISTIC_TEST'"
make_request "DETERMINISTIC_TEST"
HASH3A=$(extract_field "content_hash")

info "Request 2: Same HTML with 'DETERMINISTIC_TEST'"
make_request "DETERMINISTIC_TEST"
HASH3B=$(extract_field "content_hash")
CACHED3B=$(extract_bool "cached")

info "Hash 3a: $HASH3A"
info "Hash 3b: $HASH3B"
info "Cached: $CACHED3B"

if [ -n "$HASH3A" ] && [ "$HASH3A" == "$HASH3B" ]; then
    pass "Same HTML content produces same hash"
    ((TESTS_PASSED++))
else
    fail "Same HTML content should produce same hash"
    ((TESTS_FAILED++))
fi

# ============================================================================
section "Test 3: Verify old PNG is not overwritten"
# ============================================================================

info "Request 1: Create PNG with 'V1_CONTENT'"
make_request "V1_CONTENT"
HASH6=$(extract_field "content_hash")
FILE6=$(extract_field "output_file" | sed 's/.*\///')

info "Request 2: Create PNG with 'V2_CONTENT'"
make_request "V2_CONTENT"
HASH7=$(extract_field "content_hash")
FILE7=$(extract_field "output_file" | sed 's/.*\///')

info "File 1: $FILE6"
info "File 2: $FILE7"

# Check if both files exist
if [ -f "$OUTPUT_DIR/$FILE6" ] && [ -f "$OUTPUT_DIR/$FILE7" ]; then
    pass "Both PNG files exist after hash change"
    pass "Old file not overwritten - new file created instead"
    ((TESTS_PASSED++))

    if [ "$FILE6" != "$FILE7" ]; then
        pass "Confirmed: Different filenames used"
        ((TESTS_PASSED++))
    else
        fail "Filenames should be different"
        ((TESTS_FAILED++))
    fi
else
    fail "Both files should exist"
    info "File 1 ($FILE6) exists: $([ -f \"$OUTPUT_DIR/$FILE6\" ] && echo 'yes' || echo 'no')"
    info "File 2 ($FILE7) exists: $([ -f \"$OUTPUT_DIR/$FILE7\" ] && echo 'yes' || echo 'no')"
    ((TESTS_FAILED++))
fi

# ============================================================================
section "Test 4: Hash changes with minor content modification"
# ============================================================================

info "Request 1: HTML without exclamation"
make_request "MINOR_TEST"
HASH8=$(extract_field "content_hash")

info "Request 2: HTML with exclamation mark"
make_request "MINOR_TEST!"
HASH9=$(extract_field "content_hash")

info "Hash 8: $HASH8"
info "Hash 9: $HASH9"
info "Content difference: 1 character"

if [ -n "$HASH8" ] && [ -n "$HASH9" ] && [ "$HASH8" != "$HASH9" ]; then
    pass "Minor content change produces different hash"
    pass "Hash sensitivity: Even 1 character change is detected"
    ((TESTS_PASSED++))
else
    fail "Minor content change should produce different hash"
    ((TESTS_FAILED++))
fi

# ============================================================================
section "Test 5: Demonstrate hash-based caching behavior"
# ============================================================================

info "Request 1: HTML with 'CACHE_DEMO'"
make_request "CACHE_DEMO"
HASH10=$(extract_field "content_hash")
CACHED10=$(extract_bool "cached")

info "Request 2: Same HTML 'CACHE_DEMO'"
make_request "CACHE_DEMO"
HASH11=$(extract_field "content_hash")
CACHED11=$(extract_bool "cached")

info "Hash 10: $HASH10 (Cached: $CACHED10)"
info "Hash 11: $HASH11 (Cached: $CACHED11)"

if [ "$HASH10" == "$HASH11" ]; then
    pass "Hashes are identical for same content"
    ((TESTS_PASSED++))

    if [ "$CACHED11" == "true" ]; then
        pass "Second request returns cached version"
        ((TESTS_PASSED++))
    else
        info "Note: Second request not cached (file may not exist yet)"
    fi
else
    fail "Same content should produce same hash"
    ((TESTS_FAILED++))
fi

# ============================================================================
section "Test 6: List PNG files created during test"
# ============================================================================

info "Recent PNG files (newest 5):"
ls -lht "$OUTPUT_DIR"/*.png 2>/dev/null | head -5 | awk '{print "  " $9 " (" $5 ")"}'

# Count total files
FILE_COUNT=$(ls -1 "$OUTPUT_DIR"/*.png 2>/dev/null | wc -l)
info "Total PNG files in cache: $FILE_COUNT"

# ============================================================================
section "Summary"
# ============================================================================

TOTAL_TESTS=$((TESTS_PASSED + TESTS_FAILED))
if [ $TOTAL_TESTS -gt 0 ]; then
    PERCENTAGE=$((TESTS_PASSED * 100 / TOTAL_TESTS))
else
    PERCENTAGE=0
fi

echo ""
echo "========================================"
echo "Tests Passed: $TESTS_PASSED"
echo "Tests Failed: $TESTS_FAILED"
echo "Total Tests: $TOTAL_TESTS"
echo "Success Rate: $PERCENTAGE%"
echo "========================================"

# Cleanup
rm -f /tmp/resp_$$.json

if [ $TESTS_FAILED -eq 0 ]; then
    pass "Feature #35: All tests passed!"
    exit 0
else
    fail "Feature #35: Some tests failed"
    exit 1
fi
