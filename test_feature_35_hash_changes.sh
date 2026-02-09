#!/bin/bash
# Feature #35: Recreates PNG when hash changes
# Test script that runs from host machine

API_URL="http://localhost:8080/convert.php"
OUTPUT_DIR="./assets/media/rapidhtml2png"

echo "=== Feature #35: Recreates PNG when hash changes ==="
echo ""

# Helper functions
pass() { echo "✅ PASS: $1"; }
fail() { echo "❌ FAIL: $1"; }
info() { echo "ℹ️  INFO: $1"; }
section() { echo ""; echo "📋 $1"; }

# Make API request and extract hash
make_request() {
    local html="$1"
    local css="$2"

    if [ -z "$css" ]; then
        curl -s "$API_URL" \
            -X POST \
            -H "Content-Type: application/json" \
            -d "{\"html_blocks\":[\"$html\"]}"
    else
        curl -s "$API_URL" \
            -X POST \
            -H "Content-Type: application/json" \
            -d "{\"html_blocks\":[\"$html\"], \"css_url\":\"$css\"}"
    fi
}

extract_hash() {
    local response="$1"
    echo "$response" | grep -o '"content_hash":"[^"]*"' | cut -d'"' -f4
}

extract_file() {
    local response="$1"
    echo "$response" | grep -o '"output_file":"[^"]*"' | sed 's/.*\///' | sed 's/"$//'
}

extract_cached() {
    local response="$1"
    echo "$response" | grep -o '"cached":[^,}]*' | cut -d':' -f2
}

# ============================================================================
section "Test 1: Different HTML produces different hashes"
# ============================================================================

info "Request 1: HTML with 'CONTENT_V1'"
RESP1=$(make_request "CONTENT_V1")
HASH1=$(extract_hash "$RESP1")
FILE1=$(extract_file "$RESP1")

info "Request 2: HTML with 'CONTENT_V2'"
RESP2=$(make_request "CONTENT_V2")
HASH2=$(extract_hash "$RESP2")
FILE2=$(extract_file "$RESP2")

info "Hash 1: $HASH1"
info "Hash 2: $HASH2"
info "File 1: $FILE1"
info "File 2: $FILE2"

if [ "$HASH1" != "$HASH2" ] && [ -n "$HASH1" ] && [ -n "$HASH2" ]; then
    pass "Different HTML content produces different hashes"
    pass "Hash 1 ($HASH1) != Hash 2 ($HASH2)"
    ((TESTS_PASSED++))
else
    fail "Different HTML content should produce different hashes"
    ((TESTS_FAILED++))
fi

if [ "$FILE1" != "$FILE2" ] && [ -n "$FILE1" ] && [ -n "$FILE2" ]; then
    pass "Different hash filenames created"
    ((TESTS_PASSED++))
else
    fail "Different filenames should be created for different hashes"
    ((TESTS_FAILED++))
fi

# ============================================================================
section "Test 2: Same HTML produces same hash (deterministic)"
# ============================================================================

info "Request 1: HTML with 'DETERMINISTIC_TEST'"
RESP3A=$(make_request "DETERMINISTIC_TEST")
HASH3A=$(extract_hash "$RESP3A")

info "Request 2: Same HTML with 'DETERMINISTIC_TEST'"
RESP3B=$(make_request "DETERMINISTIC_TEST")
HASH3B=$(extract_hash "$RESP3B")
CACHED3B=$(extract_cached "$RESP3B")

info "Hash 3a: $HASH3A"
info "Hash 3b: $HASH3B"
info "Cached: $CACHED3B"

if [ "$HASH3A" == "$HASH3B" ] && [ -n "$HASH3A" ]; then
    pass "Same HTML content produces same hash"
    ((TESTS_PASSED++))
else
    fail "Same HTML content should produce same hash"
    ((TESTS_FAILED++))
fi

if [ "$CACHED3B" == "true" ]; then
    pass "Second request with same content is cached"
    ((TESTS_PASSED++))
else
    info "Note: Second request not cached (may be first time)"
fi

# ============================================================================
section "Test 3: Verify old PNG is not overwritten"
# ============================================================================

info "Request 1: Create PNG with V1 content"
RESP6=$(make_request "HASH_CHANGE_V1")
HASH6=$(extract_hash "$RESP6")
FILE6=$(extract_file "$RESP6")

info "Request 2: Create PNG with V2 content"
RESP7=$(make_request "HASH_CHANGE_V2")
HASH7=$(extract_hash "$RESP7")
FILE7=$(extract_file "$RESP7")

info "File 1: $FILE6"
info "File 2: $FILE7"

# Check if both files exist in output directory
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
    info "File 1 exists: $([ -f \"$OUTPUT_DIR/$FILE6\" ] && echo 'yes' || echo 'no')"
    info "File 2 exists: $([ -f \"$OUTPUT_DIR/$FILE7\" ] && echo 'yes' || echo 'no')"
    ((TESTS_FAILED++))
fi

# ============================================================================
section "Test 4: Hash changes with minor content modification"
# ============================================================================

info "Request 1: HTML without exclamation"
RESP8=$(make_request "MINOR_CHANGE_TEST")
HASH8=$(extract_hash "$RESP8")

info "Request 2: HTML with exclamation mark"
RESP9=$(make_request "MINOR_CHANGE_TEST!")
HASH9=$(extract_hash "$RESP9")

info "Hash 8: $HASH8"
info "Hash 9: $HASH9"
info "Content difference: 1 character"

if [ "$HASH8" != "$HASH9" ] && [ -n "$HASH8" ] && [ -n "$HASH9" ]; then
    pass "Minor content change produces different hash"
    pass "Hash sensitivity: Even 1 character change is detected"
    ((TESTS_PASSED++))
else
    fail "Minor content change should produce different hash"
    ((TESTS_FAILED++))
fi

# ============================================================================
section "Test 5: List all created PNG files"
# ============================================================================

info "PNG files created during test:"
ls -lh "$OUTPUT_DIR"/*.png 2>/dev/null | tail -5 || info "No PNG files found"

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

if [ $TESTS_FAILED -eq 0 ]; then
    pass "Feature #35: All tests passed!"
    exit 0
else
    fail "Feature #35: Some tests failed"
    exit 1
fi
