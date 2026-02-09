#!/bin/bash

echo "╔══════════════════════════════════════════════════════════════════════╗"
echo "║   Feature #11: Handles missing parameters with error - Tests        ║"
echo "╚══════════════════════════════════════════════════════════════════════╝"
echo ""

# Test 1: Send POST request without html_blocks parameter
echo "Test 1: Send POST request without html_blocks parameter"
echo "═══════════════════════════════════════════════════════════════════════"

RESPONSE=$(curl -s -w "\n%{http_code}\n%{content_type}" -X POST http://localhost:8080/convert.php \
  -H "Content-Type: application/json" \
  -d '{"css_url":"http://example.com/style.css"}')

HTTP_CODE=$(echo "$RESPONSE" | tail -n 2 | head -n 1)
CONTENT_TYPE=$(echo "$RESPONSE" | tail -n 1)
BODY=$(echo "$RESPONSE" | head -n -2)

echo "Request: POST /convert.php"
echo "Payload: {\"css_url\":\"http://example.com/style.css\"}"
echo ""
echo "Response HTTP Status: $HTTP_CODE"
echo "Response Content-Type: $CONTENT_TYPE"
echo "Response Body:"
echo "$BODY"
echo ""

# Verify test results
PASS=true

# Test 2: Verify response has HTTP status 400
echo "Test 2: HTTP status is 400 (Bad Request)"
if [ "$HTTP_CODE" = "400" ]; then
  echo "✅ PASS - HTTP status is 400"
else
  echo "❌ FAIL - HTTP status is $HTTP_CODE (expected 400)"
  PASS=false
fi

# Test 3: Check JSON response contains error message
echo "Test 3: JSON response contains 'error' field"
if echo "$BODY" | grep -q '"error"'; then
  echo "✅ PASS - Response contains 'error' field"
else
  echo "❌ FAIL - Response missing 'error' field"
  PASS=false
fi

# Test 4: Verify error message indicates missing parameter
echo "Test 4: Error message indicates missing parameter"
if echo "$BODY" | grep -qi 'missing.*html_blocks\|html_blocks.*missing'; then
  echo "✅ PASS - Error message mentions html_blocks"
elif echo "$BODY" | grep -qi 'missing\|required'; then
  echo "✅ PASS - Error message indicates missing/required parameter"
else
  echo "❌ FAIL - Error message doesn't indicate missing parameter"
  PASS=false
fi

# Test 5: Confirm response is still valid JSON format
echo "Test 5: Response is valid JSON format"
if echo "$BODY" | python3 -m json.tool > /dev/null 2>&1; then
  echo "✅ PASS - Response is valid JSON"
else
  echo "❌ FAIL - Response is not valid JSON"
  PASS=false
fi

# Additional tests
echo ""
echo "Additional Tests:"

echo -n "Additional: 'success' field is false - "
if echo "$BODY" | grep -q '"success":false'; then
  echo "✅ PASS"
else
  echo "❌ FAIL"
  PASS=false
fi

echo -n "Additional: Response includes 'timestamp' field - "
if echo "$BODY" | grep -q '"timestamp"'; then
  echo "✅ PASS"
else
  echo "❌ FAIL"
  PASS=false
fi

echo -n "Additional: Content-Type is application/json - "
if echo "$CONTENT_TYPE" | grep -q 'application/json'; then
  echo "✅ PASS"
else
  echo "❌ FAIL"
  PASS=false
fi

echo ""
echo "═══════════════════════════════════════════════════════════════════════"

# Additional test 2: Empty POST body
echo ""
echo "Additional Test 2: Empty POST body"
echo "═══════════════════════════════════════════════════════════════════════"

RESPONSE2=$(curl -s -w "\n%{http_code}" -X POST http://localhost:8080/convert.php \
  -H "Content-Type: application/json" \
  -d '')

HTTP_CODE2=$(echo "$RESPONSE2" | tail -n 1)
BODY2=$(echo "$RESPONSE2" | head -n -1)

echo "Request: POST /convert.php with empty body"
echo "Response HTTP Status: $HTTP_CODE2"
echo "Response Body:"
echo "$BODY2"

if [ "$HTTP_CODE2" = "400" ] && echo "$BODY2" | grep -q '"error"'; then
  echo "✅ PASS"
else
  echo "❌ FAIL"
  PASS=false
fi

# Additional test 3: Only optional parameter
echo ""
echo "Additional Test 3: Only optional parameter (css_url)"
echo "═══════════════════════════════════════════════════════════════════════"

RESPONSE3=$(curl -s -w "\n%{http_code}" -X POST http://localhost:8080/convert.php \
  -H "Content-Type: application/json" \
  -d '{"css_url":"http://localhost:8080/main.css"}')

HTTP_CODE3=$(echo "$RESPONSE3" | tail -n 1)
BODY3=$(echo "$RESPONSE3" | head -n -1)

echo "Request: POST /convert.php with only css_url"
echo "Response HTTP Status: $HTTP_CODE3"
echo "Response Body:"
echo "$BODY3"

if [ "$HTTP_CODE3" = "400" ] && echo "$BODY3" | grep -q '"error"'; then
  echo "✅ PASS"
else
  echo "❌ FAIL"
  PASS=false
fi

# Additional test 4: Form-encoded with missing parameter
echo ""
echo "Additional Test 4: Form-encoded with missing parameter"
echo "═══════════════════════════════════════════════════════════════════════"

RESPONSE4=$(curl -s -w "\n%{http_code}" -X POST http://localhost:8080/convert.php \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d 'css_url=http://example.com/style.css')

HTTP_CODE4=$(echo "$RESPONSE4" | tail -n 1)
BODY4=$(echo "$RESPONSE4" | head -n -1)

echo "Request: POST /convert.php (form-encoded) without html_blocks"
echo "Response HTTP Status: $HTTP_CODE4"
echo "Response Body:"
echo "$BODY4"

if [ "$HTTP_CODE4" = "400" ] && echo "$BODY4" | grep -q '"error"'; then
  echo "✅ PASS"
else
  echo "❌ FAIL"
  PASS=false
fi

echo ""
echo "╔══════════════════════════════════════════════════════════════════════╗"
echo "║                          TEST SUMMARY                                ║"
echo "╚══════════════════════════════════════════════════════════════════════╝"

if [ "$PASS" = true ]; then
  echo ""
  echo "🎉 Feature #11: ALL TESTS PASSED! ✅"
  echo ""
  echo "Required Tests (Feature Steps):"
  echo "  ✅ Test 1: Send POST request without html_blocks parameter"
  echo "  ✅ Test 2: HTTP status is 400 (Bad Request)"
  echo "  ✅ Test 3: JSON response contains 'error' field"
  echo "  ✅ Test 4: Error message indicates missing parameter"
  echo "  ✅ Test 5: Response is valid JSON format"
  echo ""
  echo "Additional Tests:"
  echo "  ✅ Additional: 'success' field is false"
  echo "  ✅ Additional: Response includes 'timestamp' field"
  echo "  ✅ Additional: Content-Type is application/json"
  echo "  ✅ Additional Test 2: Empty POST body"
  echo "  ✅ Additional Test 3: Only optional parameter"
  echo "  ✅ Additional Test 4: Form-encoded with missing parameter"
  echo ""
  echo "Total: 10/10 tests passed (100.0%)"
  exit 0
else
  echo ""
  echo "⚠️  Feature #11: Some tests failed"
  echo "Please review the output above for details."
  exit 1
fi
