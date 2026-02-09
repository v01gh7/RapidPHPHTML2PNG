#!/bin/bash
# Test multipart/form-data from host system using curl

API_URL="http://localhost:8080/convert.php"
PASSED=0
FAILED=0

echo "Testing multipart/form-data parsing from host system..."
echo "======================================================================="
echo ""

# Test 1: Single HTML block
echo "Test 1: Single HTML block via multipart/form-data"
RESULT=$(curl -s -w "\n%{http_code}" -X POST \
  -F "html_blocks=<div>Test HTML Block</div>" \
  "$API_URL")
HTTP_CODE=$(echo "$RESULT" | tail -n1)
BODY=$(echo "$RESULT" | head -n-1)

if [ "$HTTP_CODE" = "200" ]; then
  echo "  ✅ PASSED - Status: $HTTP_CODE"
  ((PASSED++))
else
  echo "  ❌ FAILED - Expected 200, got $HTTP_CODE"
  ((FAILED++))
fi
echo ""

# Test 2: HTML block with CSS URL
echo "Test 2: HTML block with CSS URL"
RESULT=$(curl -s -w "\n%{http_code}" -X POST \
  -F "html_blocks=<div class='styled'>Content</div>" \
  -F "css_url=http://example.com/styles.css" \
  "$API_URL")
HTTP_CODE=$(echo "$RESULT" | tail -n1)
BODY=$(echo "$RESULT" | head -n-1)

if [ "$HTTP_CODE" = "200" ]; then
  if echo "$BODY" | grep -q "http://example.com/styles.css"; then
    echo "  ✅ PASSED - CSS URL parsed correctly"
    ((PASSED++))
  else
    echo "  ❌ FAILED - CSS URL not found in response"
    ((FAILED++))
  fi
else
  echo "  ❌ FAILED - Expected 200, got $HTTP_CODE"
  ((FAILED++))
fi
echo ""

# Test 3: Missing html_blocks (should fail)
echo "Test 3: Missing html_blocks parameter"
RESULT=$(curl -s -w "\n%{http_code}" -X POST \
  -F "css_url=http://example.com/styles.css" \
  "$API_URL")
HTTP_CODE=$(echo "$RESULT" | tail -n1)
BODY=$(echo "$RESULT" | head -n-1)

if [ "$HTTP_CODE" = "400" ]; then
  echo "  ✅ PASSED - Correctly rejected with 400"
  ((PASSED++))
else
  echo "  ❌ FAILED - Expected 400, got $HTTP_CODE"
  ((FAILED++))
fi
echo ""

# Test 4: Empty html_blocks (should fail)
echo "Test 4: Empty html_blocks parameter"
RESULT=$(curl -s -w "\n%{http_code}" -X POST \
  -F "html_blocks=" \
  "$API_URL")
HTTP_CODE=$(echo "$RESULT" | tail -n1)

if [ "$HTTP_CODE" = "400" ]; then
  echo "  ✅ PASSED - Correctly rejected with 400"
  ((PASSED++))
else
  echo "  ❌ FAILED - Expected 400, got $HTTP_CODE"
  ((FAILED++))
fi
echo ""

# Test 5: Complex HTML with special characters
echo "Test 5: Complex HTML with special characters"
RESULT=$(curl -s -w "\n%{http_code}" -X POST \
  -F "html_blocks=<div class='container'><h1>Title</h1><p>Text with &lt;special&gt; chars</p></div>" \
  "$API_URL")
HTTP_CODE=$(echo "$RESULT" | tail -n1)
BODY=$(echo "$RESULT" | head -n-1)

if [ "$HTTP_CODE" = "200" ]; then
  echo "  ✅ PASSED - Complex HTML parsed correctly"
  ((PASSED++))
else
  echo "  ❌ FAILED - Expected 200, got $HTTP_CODE"
  ((FAILED++))
fi
echo ""

# Test 6: Verify Content-Type header
echo "Test 6: Verify Content-Type is application/json"
CONTENT_TYPE=$(curl -s -I -X POST \
  -F "html_blocks=<div>Test</div>" \
  "$API_URL" | grep -i "Content-Type" | head -n1)

if echo "$CONTENT_TYPE" | grep -qi "application/json"; then
  echo "  ✅ PASSED - Content-Type is JSON"
  ((PASSED++))
else
  echo "  ❌ FAILED - Content-Type not application/json: $CONTENT_TYPE"
  ((FAILED++))
fi
echo ""

# Summary
TOTAL=$((PASSED + FAILED))
echo "======================================================================="
echo "HOST SYSTEM TEST SUMMARY"
echo "======================================================================="
echo "Total Tests: $TOTAL"
echo "Passed: $PASSED"
echo "Failed: $FAILED"
echo "Success Rate: $(awk "BEGIN {printf \"%.1f\", ($PASSED/$TOTAL)*100}")%"
echo "======================================================================="

if [ $FAILED -eq 0 ]; then
  exit 0
else
  exit 1
fi
