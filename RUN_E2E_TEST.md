# How to Run Feature #45 E2E Test

## Prerequisites

Before running the end-to-end test, you need to start the PHP development server.

### Option 1: Start Server Manually (Windows)

Open a command prompt in the project directory and run:

```cmd
start_server.bat
```

Or manually:

```cmd
php -S localhost:8080
```

### Option 2: Start Server Manually (Linux/Mac)

```bash
php -S localhost:8080
```

### Option 3: Start Server with Docker

```bash
docker-compose up -d
```

## Running the E2E Test

Once the server is running, you have three options to run the test:

### Option 1: Node.js Test (Recommended - No PHP Needed)

In a NEW terminal (keep the server running in the first terminal):

```bash
node test_feature_45_e2e.js
```

This will run all 6 test steps and display the results.

### Option 2: PHP CLI Test

```bash
php test_feature_45_e2e.php
```

### Option 3: Browser Test

1. Open your browser
2. Navigate to: http://localhost:8080/test_feature_45_browser.html
3. Click the "▶ Run Complete E2E Test" button
4. Watch the test results in real-time

## Expected Output

When all tests pass, you should see:

```
======================================
Feature #45: E2E Conversion Workflow
======================================

Step 1: Verifying test HTML content...
✓ PASS - Test HTML contains identifier

Step 2: Verifying CSS content...
✓ PASS - CSS contains blue color styling

Step 3: Sending POST request to API...
✓ PASS - API responded with HTTP 200
  Generated hash: [32-char-hash]
  Output path: assets/media/rapidhtml2png/[hash].png

Step 4: Verifying PNG file path...
✓ PASS - PNG path verification
  Expected pattern: assets/media/rapidhtml2png/[hash].png
  Actual: assets/media/rapidhtml2png/[hash].png

Step 5: Loading PNG via HTTP...
✓ PASS - PNG HTTP accessibility (HTTP 200)
  URL: http://localhost:8080/assets/media/rapidhtml2png/[hash].png
  Content-Type: image/png
  Size: [X] bytes

Step 6: Verifying PNG validity...
✓ PASS - PNG validity check
  Image type: PNG
  Dimensions: [W]x[H]
  PNG signature: Valid

======================================
Test Results Summary
======================================

✓ PASS - Step 1: Test HTML contains specific text
✓ PASS - Step 2: CSS contains specific styling
✓ PASS - Step 3: Send POST request
✓ PASS - Step 4: PNG created at correct path
✓ PASS - Step 5: PNG accessible via HTTP
✓ PASS - Step 6: PNG is a valid image

======================================
Total: 6/6 tests passed ✓
======================================

✓ ALL E2E TESTS PASSED!
Complete workflow verified successfully.
```

## Troubleshooting

### Server won't start
- Make sure PHP is installed: `php --version`
- Check that port 8080 is not already in use
- Try a different port: `php -S localhost:8081`

### Tests fail at Step 3 (API error)
- Check that convert.php exists in the project directory
- Verify the server is running by opening http://localhost:8080/convert.php in your browser
- Check the server terminal for PHP errors

### Tests fail at Step 5 (PNG not accessible)
- Verify the assets/media/rapidhtml2png directory exists
- Check file permissions on the output directory
- Look for PNG files in assets/media/rapidhtml2png/

### Tests fail at Step 6 (Invalid PNG)
- Check that a rendering library is available (ImageMagick, GD, or wkhtmltoimage)
- Review the server logs for rendering errors
- Verify the generated file exists and is not empty

## Test Data Cleanup

After testing, you can clean up the generated test PNG files:

```bash
# Remove all generated PNG files
rm -f assets/media/rapidhtml2png/*.png

# Or remove specific test files (look for E2E_TEST in the content)
# Since files are named by hash, you would need to identify them by content
```

## What This Test Verifies

This end-to-end test verifies the complete workflow:

1. **Input Validation**: HTML and CSS inputs are correctly formatted
2. **API Communication**: POST request is accepted and processed
3. **Hash Generation**: Content hash is correctly generated
4. **File Creation**: PNG file is created at the correct path
5. **HTTP Access**: File is accessible via HTTP GET request
6. **Image Validity**: Generated file is a valid PNG image

This integrates all previously implemented features and confirms the system works end-to-end.
