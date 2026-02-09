#!/bin/bash

# Feature #38: Input Size Limits API Test
# Tests that the API properly rejects excessively large input data

echo "=== Feature #38: Input Size Limits API Test ==="
echo ""

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

BASE_URL="http://localhost:8080"

# Test 1: Small HTML block (should pass validation)
echo "Test 1: Small HTML block (50KB - should pass)"
echo "------------------------------------------------"
SMALL_HTML=$(printf '<div>Test</div>%.0s' {1..1000})
RESPONSE=$(curl -s -X POST "$BASE_URL/convert.php" \
  -H "Content-Type: application/json" \
  -d "{\"html_blocks\": [\"$SMALL_HTML\"]}")

if echo "$RESPONSE" | grep -q '"success":false'; then
  ERROR=$(echo "$RESPONSE" | grep -o '"error":"[^"]*"' | cut -d'"' -f4)
  if echo "$ERROR" | grep -q "exceeds maximum size"; then
    echo -e "${RED}✗ FAIL: Small block was rejected for size${NC}"
    echo "Error: $ERROR"
  else
    echo -e "${YELLOW}⚠ WARN: Small block failed with different error${NC}"
    echo "Error: $ERROR"
  fi
else
  echo -e "${GREEN}✓ PASS: Small block accepted${NC}"
fi
echo ""

# Test 2: Oversized HTML block (should fail with 413)
echo "Test 2: Oversized HTML block (1.5MB - should fail with 413)"
echo "------------------------------------------------"
# Create a 1.5MB HTML block
LARGE_HTML=$(python3 -c "print('<div>' + 'X' * 1500000 + '</div>')")
RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "$BASE_URL/convert.php" \
  -H "Content-Type: application/json" \
  -d "{\"html_blocks\": [\"$LARGE_HTML\"]}")

HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
BODY=$(echo "$RESPONSE" | head -n-1)

if [ "$HTTP_CODE" = "413" ]; then
  echo -e "${GREEN}✓ PASS: Got HTTP 413 (Payload Too Large)${NC}"
elif echo "$BODY" | grep -q "exceeds maximum size"; then
  echo -e "${GREEN}✓ PASS: Size limit enforced in response${NC}"
else
  echo -e "${RED}✗ FAIL: Did not get expected 413 response${NC}"
  echo "HTTP Code: $HTTP_CODE"
fi

echo "Response snippet:"
echo "$BODY" | head -c 300
echo "..."
echo ""

# Test 3: Multiple blocks within limit (should pass)
echo "Test 3: Multiple blocks (5 x 100KB = 500KB - should pass)"
echo "------------------------------------------------"
MULTI_BLOCK_HTML=$(printf '<div>Block</div>%.0s' {1..10000})
RESPONSE=$(curl -s -X POST "$BASE_URL/convert.php" \
  -H "Content-Type: application/json" \
  -d "{\"html_blocks\": [\"$MULTI_BLOCK_HTML\", \"$MULTI_BLOCK_HTML\", \"$MULTI_BLOCK_HTML\", \"$MULTI_BLOCK_HTML\", \"$MULTI_BLOCK_HTML\"]}")

if echo "$RESPONSE" | grep -q '"success":false'; then
  ERROR=$(echo "$RESPONSE" | grep -o '"error":"[^"]*"' | cut -d'"' -f4)
  if echo "$ERROR" | grep -q "exceeds maximum size"; then
    echo -e "${RED}✗ FAIL: Multiple blocks rejected for size${NC}"
    echo "Error: $ERROR"
  else
    echo -e "${YELLOW}⚠ WARN: Multiple blocks failed with different error${NC}"
    echo "Error: $ERROR"
  fi
else
  echo -e "${GREEN}✓ PASS: Multiple blocks accepted${NC}"
fi
echo ""

# Test 4: Check constants are defined
echo "Test 4: Verify size limit constants"
echo "------------------------------------------------"
php -r "
if (defined('MAX_HTML_BLOCK_SIZE')) {
    echo 'MAX_HTML_BLOCK_SIZE = ' . MAX_HTML_BLOCK_SIZE . ' bytes (' . round(MAX_HTML_BLOCK_SIZE/1048576, 2) . ' MB)' . PHP_EOL;
}
if (defined('MAX_TOTAL_INPUT_SIZE')) {
    echo 'MAX_TOTAL_INPUT_SIZE = ' . MAX_TOTAL_INPUT_SIZE . ' bytes (' . round(MAX_TOTAL_INPUT_SIZE/1048576, 2) . ' MB)' . PHP_EOL;
}
if (defined('MAX_CSS_SIZE')) {
    echo 'MAX_CSS_SIZE = ' . MAX_CSS_SIZE . ' bytes (' . round(MAX_CSS_SIZE/1048576, 2) . ' MB)' . PHP_EOL;
}
" 2>/dev/null || echo "Could not check constants (need to include convert.php)"
echo ""

# Test 5: Verify error response format
echo "Test 5: Verify error response format for size violations"
echo "------------------------------------------------"
LARGE_HTML=$(python3 -c "print('<div>' + 'Y' * 2000000 + '</div>')")
RESPONSE=$(curl -s -X POST "$BASE_URL/convert.php" \
  -H "Content-Type: application/json" \
  -d "{\"html_blocks\": [\"$LARGE_HTML\"]}")

# Check for required fields
if echo "$RESPONSE" | grep -q '"success":false'; then
  echo -e "${GREEN}✓ PASS: Response contains success:false${NC}"
else
  echo -e "${RED}✗ FAIL: Response missing success:false${NC}"
fi

if echo "$RESPONSE" | grep -q '"error"'; then
  echo -e "${GREEN}✓ PASS: Response contains error message${NC}"
else
  echo -e "${RED}✗ FAIL: Response missing error message${NC}"
fi

if echo "$RESPONSE" | grep -q '"block_size"'; then
  echo -e "${GREEN}✓ PASS: Response contains block_size${NC}"
else
  echo -e "${RED}✗ FAIL: Response missing block_size${NC}"
fi

if echo "$RESPONSE" | grep -q '"max_allowed_size"'; then
  echo -e "${GREEN}✓ PASS: Response contains max_allowed_size${NC}"
else
  echo -e "${RED}✗ FAIL: Response missing max_allowed_size${NC}"
fi

echo ""
echo "=== Test Complete ==="
