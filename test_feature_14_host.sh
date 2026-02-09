#!/bin/bash
# Feature #14: CSS Caching Verification Test (Host Machine Version)
# This test runs from the host machine to verify caching works via HTTP

set -e

BASE_URL="http://localhost:8080/convert.php"
CSS_URL="http://localhost:8080/main.css"
CACHE_DIR="./assets/media/rapidhtml2png/css_cache"

echo "=== Feature #14: CSS Caching Verification Test (Host) ==="
echo ""

# Helper function to make POST request
make_request() {
    local html_block="$1"
    curl -s -X POST \
        -H "Content-Type: application/json" \
        -d "{\"html_blocks\": [\"$html_block\"], \"css_url\": \"$CSS_URL\"}" \
        "$BASE_URL"
}

# Helper function to get cache file path
get_cache_path() {
    local css_url="$1"
    local cache_key=$(echo -n "$css_url" | md5sum | cut -d' ' -f1)
    echo "$CACHE_DIR/${cache_key}.css"
}

# Helper function to clear cache
clear_cache() {
    local cache_file=$(get_cache_path "$CSS_URL")
    local meta_file="${cache_file%.css}.meta.json"
    rm -f "$cache_file" "$meta_file" 2>/dev/null || true
}

# Helper function to check if cache exists
cache_exists() {
    local cache_file=$(get_cache_path "$CSS_URL")
    [ -f "$cache_file" ]
}

# Helper function to get cache file mtime
get_cache_mtime() {
    local cache_file=$(get_cache_path "$CSS_URL")
    if [ -f "$cache_file" ]; then
        stat -c %Y "$cache_file" 2>/dev/null || stat -f %m "$cache_file"
    fi
}

# STEP 0: Clear cache
echo "STEP 0: Clearing CSS cache before test..."
clear_cache
if cache_exists; then
    echo "❌ FAIL: Could not clear cache file"
    exit 1
fi
echo "✅ Cache cleared successfully"
echo ""

sleep 1

# STEP 1: First request
echo "STEP 1: Making first request with css_url..."
echo "CSS URL: $CSS_URL"

html_block='<div class="test-content">Test Content for CSS Caching</div>'

response1=$(make_request "$html_block")

# Check if request was successful
if ! echo "$response1" | grep -q '"success": true'; then
    echo "❌ FAIL: First request was not successful"
    echo "$response1"
    exit 1
fi
echo "✅ First request successful"

# Check if CSS was loaded
if ! echo "$response1" | grep -q '"css_loaded": true'; then
    echo "❌ FAIL: CSS was not loaded in first request"
    echo "$response1"
    exit 1
fi
echo "✅ CSS was loaded"

# Check if CSS was NOT from cache (fresh load)
if echo "$response1" | grep -q '"css_cached": true'; then
    echo "❌ FAIL: First request should not use cache"
    echo "$response1"
    exit 1
fi

if ! echo "$response1" | grep -q '"css_fresh": true'; then
    echo "❌ FAIL: First request should have css_fresh=true"
    echo "$response1"
    exit 1
fi
echo "✅ CSS was loaded from source (not from cache)"

# Check if cache file was created
if ! cache_exists; then
    echo "❌ FAIL: Cache file was not created"
    exit 1
fi
echo "✅ Cache file was created"

cache_mtime1=$(get_cache_mtime)
echo "Cache file mtime: $(date -d @$cache_mtime1 '+%Y-%m-%d %H:%M:%S')"

# Extract content length
css_length1=$(echo "$response1" | grep -o '"css_content_length":[0-9]*' | cut -d':' -f2)
echo "CSS content length: $css_length1 bytes"
echo ""

sleep 1

# STEP 2: Second request
echo "STEP 2: Making second identical request (should use cache)..."

response2=$(make_request "$html_block")

# Check if request was successful
if ! echo "$response2" | grep -q '"success": true'; then
    echo "❌ FAIL: Second request was not successful"
    echo "$response2"
    exit 1
fi
echo "✅ Second request successful"

# Check if CSS was loaded
if ! echo "$response2" | grep -q '"css_loaded": true'; then
    echo "❌ FAIL: CSS was not loaded in second request"
    echo "$response2"
    exit 1
fi
echo "✅ CSS was loaded"

# Check if CSS WAS from cache
if ! echo "$response2" | grep -q '"css_cached": true'; then
    echo "❌ FAIL: Second request should use cache"
    echo "$response2"
    exit 1
fi
echo "✅ CSS was loaded from cache (css_cached=true)"

# Check if css_fresh is false or not present
if echo "$response2" | grep -q '"css_fresh": true'; then
    echo "❌ FAIL: Second request should not have css_fresh=true"
    echo "$response2"
    exit 1
fi
echo "✅ CSS was not freshly loaded (no cURL call)"

cache_mtime2=$(get_cache_mtime)
echo "Cache file mtime: $(date -d @$cache_mtime2 '+%Y-%m-%d %H:%M:%S')"

# Check if cache file was not modified
if [ "$cache_mtime1" != "$cache_mtime2" ]; then
    echo "❌ FAIL: Cache file mtime changed"
    exit 1
fi
echo "✅ Cache file was not modified (same mtime)"
echo ""

# STEP 3: Verify content matches
echo "STEP 3: Verifying cached content matches original CSS..."

css_length2=$(echo "$response2" | grep -o '"css_content_length":[0-9]*' | cut -d':' -f2)
echo "First request CSS length: $css_length1 bytes"
echo "Second request CSS length: $css_length2 bytes"

if [ "$css_length1" != "$css_length2" ]; then
    echo "❌ FAIL: CSS content length differs"
    exit 1
fi
echo "✅ CSS content length matches"

# Extract and compare previews
preview1=$(echo "$response1" | grep -o '"css_preview":"[^"]*"' | cut -d'"' -f4)
preview2=$(echo "$response2" | grep -o '"css_preview":"[^"]*"' | cut -d'"' -f4)

if [ "$preview1" != "$preview2" ]; then
    echo "❌ FAIL: CSS content preview differs"
    exit 1
fi
echo "✅ CSS content preview matches"
echo ""

# STEP 4: Verify cache age
echo "STEP 4: Verifying cache age reporting..."

if ! echo "$response2" | grep -q '"css_cache_age":[0-9]*'; then
    echo "❌ FAIL: Cache age not reported"
    exit 1
fi

cache_age=$(echo "$response2" | grep -o '"css_cache_age":[0-9]*' | cut -d':' -f2)
echo "Cache age: $cache_age seconds"

if [ "$cache_age" -lt 0 ] || [ "$cache_age" -gt 10 ]; then
    echo "❌ FAIL: Cache age seems unreasonable (expected ~2 seconds, got $cache_age)"
    exit 1
fi
echo "✅ Cache age is reasonable"

cache_age_formatted=$(echo "$response2" | grep -o '"css_cache_age_formatted":"[^"]*"' | cut -d'"' -f4)
echo "Cache age formatted: $cache_age_formatted"
echo ""

# STEP 5: Third request
echo "STEP 5: Verifying cache persists across multiple requests..."

response3=$(make_request "$html_block")

if ! echo "$response3" | grep -q '"css_cached": true'; then
    echo "❌ FAIL: Third request should also use cache"
    exit 1
fi
echo "✅ Third request also used cache"

cache_mtime3=$(get_cache_mtime)
if [ "$cache_mtime1" != "$cache_mtime3" ]; then
    echo "❌ FAIL: Cache file mtime changed after third request"
    exit 1
fi
echo "✅ Cache file mtime unchanged after third request"
echo ""

# FINAL SUMMARY
echo "=== ALL TESTS PASSED ✅ ==="
echo ""
echo "Summary:"
echo "✅ STEP 1: First request loaded CSS from source (not cache)"
echo "✅ STEP 2: Second request loaded CSS from cache (no cURL call)"
echo "✅ STEP 3: Cached content matches original CSS"
echo "✅ STEP 4: Cache age is reported correctly"
echo "✅ STEP 5: Cache persists across multiple requests"
echo ""
echo "Feature #14 verified: CSS content is cached between requests! 🎉"

# Cleanup
echo ""
echo "Cleaning up cache files..."
clear_cache
echo "✅ Cache cleared"

exit 0
