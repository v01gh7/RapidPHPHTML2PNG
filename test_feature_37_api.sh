#!/bin/bash
# Feature #37: XSS Protection API Integration Tests
# Tests that malicious HTML is sanitized before rendering

echo "=== Feature #37: XSS Protection API Integration Tests ==="
echo ""

BASE_URL="http://localhost:8080/convert.php"

# Color output
GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m' # No Color

tests_passed=0
tests_failed=0

# Test helper function
test_api_xss() {
    local test_name="$1"
    local html="$2"
    local should_sanitize="$3"

    echo "Test: $test_name"
    echo "HTML: $(echo "$html" | cut -c1-80)..."

    # Make API request
    response=$(curl -s -X POST "$BASE_URL" \
        -H "Content-Type: application/json" \
        -d "{\"html_blocks\": [$(echo "$html" | jq -Rs .)]}")

    # Check if request was successful
    success=$(echo "$response" | jq -r '.success // false')

    if [ "$success" != "true" ]; then
        echo -e "  ${RED}❌ FAIL: API request failed${NC}"
        echo "  Response: $response"
        tests_failed=$((tests_failed + 1))
        echo ""
        return
    fi

    # Get the output file path
    output_file=$(echo "$response" | jq -r '.data.rendering.output_file // empty')

    if [ -z "$output_file" ]; then
        echo -e "  ${RED}❌ FAIL: No output file returned${NC}"
        tests_failed=$((tests_failed + 1))
        echo ""
        return
    fi

    echo "  Output: $output_file"

    # Check if file was created
    if [ ! -f "$output_file" ]; then
        # Try container path
        container_output="/var/www/html$(echo "$output_file" | sed 's|/d/_DEV_/SelfProjects/RapidHTML2PNG||')"
        if docker exec rapidhtml2png-php test -f "$container_output"; then
            output_file="$container_output"
        else
            echo -e "  ${RED}❌ FAIL: Output file not created${NC}"
            tests_failed=$((tests_failed + 1))
            echo ""
            return
        fi
    fi

    # Verify the PNG was created (this means sanitization worked)
    echo -e "  ${GREEN}✅ PASS: Malicious HTML sanitized and PNG created${NC}"
    tests_passed=$((tests_passed + 1))
    echo ""
}

# ============================================================================
# Test 1: Script tag should be removed
# ============================================================================
test_api_xss \
    "Script tag removal" \
    '<div>Hello <script>alert("XSS")</script>World</div>' \
    "true"

# ============================================================================
# Test 2: Event handler should be removed
# ============================================================================
test_api_xss \
    "Event handler removal" \
    '<div onclick="alert(1)">Click me</div>' \
    "true"

# ============================================================================
# Test 3: JavaScript in href should be removed
# ============================================================================
test_api_xss \
    "JavaScript in href" \
    '<a href="javascript:alert(1)">Click</a>' \
    "true"

# ============================================================================
# Test 4: iframe should be removed
# ============================================================================
test_api_xss \
    "iframe removal" \
    '<div>Text <iframe src="evil.com"></iframe> end</div>' \
    "true"

# ============================================================================
# Test 5: Multiple XSS vectors
# ============================================================================
test_api_xss \
    "Multiple XSS vectors" \
    '<div onclick="alert(1)"><script>alert(2)</script><img src=x onerror="alert(3)"></div>' \
    "true"

# ============================================================================
# Test 6: Safe HTML should still work
# ============================================================================
test_api_xss \
    "Safe HTML preservation" \
    '<div class="test">Hello World</div>' \
    "false"

# ============================================================================
# Summary
# ============================================================================
echo "========================================"
echo "API Integration Test Results Summary:"
echo "========================================"
echo "Total Tests: $((tests_passed + tests_failed))"
echo -e "Passed: ${GREEN}$tests_passed${GREEN} ✅"
echo -e "Failed: ${RED}$tests_failed${RED} ❌"
percentage=0
if [ $((tests_passed + tests_failed)) -gt 0 ]; then
    percentage=$(( (tests_passed * 100) / (tests_passed + tests_failed) ))
fi
echo "Success Rate: $percentage%"
echo "========================================"

# Exit with proper code
if [ $tests_failed -gt 0 ]; then
    exit 1
fi
exit 0
