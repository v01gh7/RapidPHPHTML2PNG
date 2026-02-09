# Feature #8 Verification: Endpoint accepts css_url parameter

## Test Date
2026-02-09

## Description
Verify that the API endpoint properly accepts, validates, and parses the `css_url` parameter.

## Test Results Summary
✅ **ALL 12 TESTS PASSED**

## Test Details

### ✅ Test 1: Valid http CSS URL is accepted
**Request:**
```bash
POST /convert.php
Content-Type: application/json
{"html_blocks":["<div>Test</div>"],"css_url":"http://example.com/style.css"}
```
**Result:** Success - css_url returned as `http://example.com/style.css`

### ✅ Test 2: Valid https CSS URL is accepted
**Request:**
```bash
POST /convert.php
Content-Type: application/json
{"html_blocks":["<div>Test</div>"],"css_url":"https://cdn.example.com/theme.css"}
```
**Result:** Success - css_url returned as `https://cdn.example.com/theme.css`

### ✅ Test 3: CSS URL with query parameters is accepted
**Request:**
```bash
POST /convert.php
Content-Type: application/json
{"html_blocks":["<div>Test</div>"],"css_url":"http://example.com/style.css?v=1.2.3&theme=dark"}
```
**Result:** Success - Full URL with query parameters preserved

### ✅ Test 4: CSS URL with custom port is accepted
**Request:**
```bash
POST /convert.php
Content-Type: application/json
{"html_blocks":["<div>Test</div>"],"css_url":"http://localhost:8080/static/main.css"}
```
**Result:** Success - Port number preserved in URL

### ✅ Test 5: CSS URL with nested path segments is accepted
**Request:**
```bash
POST /convert.php
Content-Type: application/json
{"html_blocks":["<div>Test</div>"],"css_url":"https://cdn.example.com/assets/css/v2/components/buttons.css"}
```
**Result:** Success - Nested path structure preserved

### ✅ Test 6: Empty css_url parameter is handled gracefully
**Request:**
```bash
POST /convert.php
Content-Type: application/json
{"html_blocks":["<div>Test</div>"],"css_url":""}
```
**Result:** Success - css_url returns `null` (empty string treated as not provided)

### ✅ Test 7: Request without css_url parameter works
**Request:**
```bash
POST /convert.php
Content-Type: application/json
{"html_blocks":["<div>Test</div>"]}
```
**Result:** Success - css_url returns `null` (parameter is optional)

### ✅ Test 8: Invalid URL scheme (ftp://) is rejected
**Request:**
```bash
POST /convert.php
Content-Type: application/json
{"html_blocks":["<div>Test</div>"],"css_url":"ftp://example.com/style.css"}
```
**Result:** Error - Returns 400 with message "css_url must use http or https scheme"

### ✅ Test 9: Invalid URL format is rejected
**Request:**
```bash
POST /convert.php
Content-Type: application/json
{"html_blocks":["<div>Test</div>"],"css_url":"not-a-valid-url"}
```
**Result:** Error - Returns 400 with message "css_url must be a valid URL"

### ✅ Test 10: CSS URL with fragment identifier is accepted
**Request:**
```bash
POST /convert.php
Content-Type: application/json
{"html_blocks":["<div>Test</div>"],"css_url":"http://example.com/style.css#media-screen"}
```
**Result:** Success - Fragment identifier preserved

### ✅ Test 11: Form-urlencoded POST with css_url parameter works
**Request:**
```bash
POST /convert.php
Content-Type: application/x-www-form-urlencoded
html_blocks[0]=<div>Test</div>&css_url=http://example.com/form.css
```
**Result:** Success - Form-encoded css_url properly parsed

### ✅ Test 12: URL with username and password is accepted
**Request:**
```bash
POST /convert.php
Content-Type: application/json
{"html_blocks":["<div>Test</div>"],"css_url":"http://user:pass@example.com/style.css"}
```
**Result:** Success - Authentication info preserved in URL

## Implementation Details

The `css_url` parameter is handled by the `validateCssUrl()` function in `convert.php` (lines 171-204):

1. **Optional Parameter**: If `css_url` is null or empty string, returns `null`
2. **Type Validation**: Ensures input is a string
3. **URL Validation**: Uses `filter_var($cssUrl, FILTER_VALIDATE_URL)` for basic validation
4. **Scheme Validation**: Only `http` and `https` schemes are allowed
5. **Error Responses**: Returns 400 status codes with detailed error messages for invalid inputs

## Code Location
- **File**: `convert.php`
- **Function**: `validateCssUrl()` (lines 171-204)
- **Called from**: Main request handler (line 211)

## Feature Status
✅ **PASSED** - All requirements met

The endpoint correctly:
- Accepts `css_url` parameter in both JSON and form-urlencoded formats
- Validates URL format and scheme (http/https only)
- Returns the validated URL in the response
- Handles missing/empty `css_url` gracefully (returns null)
- Provides appropriate error messages for invalid URLs
