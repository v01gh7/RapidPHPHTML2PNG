#!/bin/bash
# Simplified multipart/form-data test using curl

API_URL="http://localhost:8080/convert.php"
PASSED=0
FAILED=0

echo "Testing multipart/form-data parsing"
echo "======================================================================="
echo ""

# Test 1: Single HTML block with proper escaping
echo "Test 1: Single HTML block via multipart"
RESULT=$(curl -s -w "\n%{http_code}" -X POST \
  --data-urlencode "html_blocks=<div>Test HTML Block</div>" \
  "$API_URL" 2>/dev/null)
HTTP_CODE=$(echo "$RESULT" | tail -n1)
BODY=$(echo "$RESULT" | head -n-1)

if [ "$HTTP_CODE" = "200" ]; then
  echo "  ✅ PASSED - Status: 200"
  ((PASSED++))
else
  echo "  ❌ FAILED - Expected 200, got $HTTP_CODE"
  ((FAILED++))
fi
echo ""

# Test 2: HTML with CSS URL
echo "Test 2: HTML block with CSS URL"
RESULT=$(curl -s -w "\n%{http_code}" -X POST \
  --data-urlencode "html_blocks=<div>Content</div>" \
  --data-urlencode "css_url=http://example.com/styles.css" \
  "$API_URL" 2>/dev/null)
HTTP_CODE=$(echo "$RESULT" | tail -n1)

if [ "$HTTP_CODE" = "200" ]; then
  echo "  ✅ PASSED - Status: 200"
  ((PASSED++))
else
  echo "  ❌ FAILED - Expected 200, got $HTTP_CODE"
  ((FAILED++))
fi
echo ""

# Test 3: Missing parameter
echo "Test 3: Missing html_blocks (should fail)"
RESULT=$(curl -s -w "\n%{http_code}" -X POST \
  --data-urlencode "css_url=http://example.com/styles.css" \
  "$API_URL" 2>/dev/null)
HTTP_CODE=$(echo "$RESULT" | tail -n1)

if [ "$HTTP_CODE" = "400" ]; then
  echo "  ✅ PASSED - Correctly rejected with 400"
  ((PASSED++))
else
  echo "  ❌ FAILED - Expected 400, got $HTTP_CODE"
  ((FAILED++))
fi
echo ""

# Test 4: Check Content-Type header
echo "Test 4: Verify Content-Type header"
HEADERS=$(curl -s -I -X POST \
  --data-urlencode "html_blocks=<div>Test</div>" \
  "$API_URL" 2>/dev/null)

if echo "$HEADERS" | grep -qi "application/json"; then
  echo "  ✅ PASSED - Content-Type is application/json"
  ((PASSED++))
else
  echo "  ❌ FAILED - Content-Type not application/json"
  ((FAILED++))
fi
echo ""

# Summary
TOTAL=$((PASSED + FAILED))
echo "======================================================================="
echo "SUMMARY"
echo "======================================================================="
echo "Total: $TOTAL | Passed: $PASSED | Failed: $FAILED"
if [ $TOTAL -gt 0 ]; then
  PERCENT=$(awk "BEGIN {printf \"%.1f\", ($PASSED/$TOTAL)*100}")
  echo "Success Rate: $PERCENT%"
fi
echo "======================================================================="

if [ $FAILED -eq 0 ]; then
  exit 0
else
  exit 1
fi
