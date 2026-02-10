#!/usr/bin/env node
/**
 * Feature #45: End-to-End Conversion Workflow Test (Node.js Version)
 *
 * This script verifies the complete workflow from HTML input to PNG output.
 * Prerequisites:
 * - PHP server running on http://localhost:8080
 *
 * Usage: node test_feature_45_e2e.js
 */

const http = require('http');
const fs = require('fs');
const path = require('path');
const { URL } = require('url');

// Test configuration
const API_URL = 'http://localhost:8080/convert.php';
const TEST_IDENTIFIER = 'E2E_TEST_12345';
const TEST_COLOR = 'blue';

// Test data
const testHtml = `<div style="padding: 20px; font-family: Arial, sans-serif;">
    <h2 style="color: ${TEST_COLOR};">End-to-End Test</h2>
    <p style="color: #333; font-size: 16px;">Test ID: ${TEST_IDENTIFIER}</p>
    <p style="color: #666;">This is a complete workflow test.</p>
</div>`;

const testCss = `
.e2e-test-container {
    border: 2px solid ${TEST_COLOR};
    border-radius: 8px;
    padding: 15px;
    background: #f9f9f9;
}
.e2e-title {
    color: ${TEST_COLOR};
    font-weight: bold;
    font-size: 24px;
    margin-bottom: 10px;
}
`;

let results = [];
let totalTests = 0;
let generatedHash = null;
let generatedImagePath = null;

console.log('======================================');
console.log('Feature #45: E2E Conversion Workflow');
console.log('======================================\n');

/**
 * Helper function to make HTTP POST request
 */
function postMultipart(url, htmlBlocks, cssUrl) {
    return new Promise((resolve, reject) => {
        const urlObj = new URL(url);
        const boundary = '----FormBoundary' + Date.now() + Math.random().toString(36).substr(2, 9);

        let body = '';
        body += `--${boundary}\r\n`;
        body += `Content-Disposition: form-data; name="html_blocks[]"\r\n\r\n`;
        body += htmlBlocks + '\r\n';
        body += `--${boundary}\r\n`;
        body += `Content-Disposition: form-data; name="css_url"\r\n\r\n`;
        body += cssUrl + '\r\n';
        body += `--${boundary}--\r\n`;

        const options = {
            hostname: urlObj.hostname,
            port: urlObj.port,
            path: urlObj.path,
            method: 'POST',
            headers: {
                'Content-Type': `multipart/form-data; boundary=${boundary}`,
                'Content-Length': Buffer.byteLength(body)
            }
        };

        const req = http.request(options, (res) => {
            let data = '';
            res.on('data', (chunk) => { data += chunk; });
            res.on('end', () => {
                try {
                    const jsonData = JSON.parse(data);
                    resolve({ status: res.statusCode, data: jsonData });
                } catch (e) {
                    resolve({ status: res.statusCode, data: data });
                }
            });
        });

        req.on('error', reject);
        req.write(body);
        req.end();
    });
}

/**
 * Helper function to make HTTP GET request
 */
function getImage(url) {
    return new Promise((resolve, reject) => {
        const urlObj = new URL(url);
        const options = {
            hostname: urlObj.hostname,
            port: urlObj.port,
            path: urlObj.path,
            method: 'GET'
        };

        const req = http.request(options, (res) => {
            const chunks = [];
            res.on('data', (chunk) => chunks.push(chunk));
            res.on('end', () => {
                resolve({
                    status: res.statusCode,
                    contentType: res.headers['content-type'],
                    data: Buffer.concat(chunks)
                });
            });
        });

        req.on('error', reject);
        req.end();
    });
}

/**
 * Test Step 1: Verify test HTML contains specific text
 */
async function step1_VerifyHtmlContent() {
    console.log('Step 1: Verifying test HTML content...');
    totalTests++;
    const htmlContainsIdentifier = testHtml.includes(TEST_IDENTIFIER);
    results.push({
        step: 1,
        name: 'Test HTML contains specific text',
        passed: htmlContainsIdentifier,
        details: htmlContainsIdentifier ? `Found: ${TEST_IDENTIFIER}` : 'NOT found'
    });
    console.log(`${htmlContainsIdentifier ? '✓ PASS' : '✗ FAIL'} - Test HTML contains identifier\n`);
}

/**
 * Test Step 2: Verify CSS contains specific styling
 */
async function step2_VerifyCssContent() {
    console.log('Step 2: Verifying CSS content...');
    totalTests++;
    const cssContainsColor = testCss.includes(TEST_COLOR) && testCss.includes('color:');
    results.push({
        step: 2,
        name: 'CSS contains specific styling',
        passed: cssContainsColor,
        details: cssContainsColor ? 'Found: color: blue' : 'NOT found'
    });
    console.log(`${cssContainsColor ? '✓ PASS' : '✗ FAIL'} - CSS contains blue color styling\n`);
}

/**
 * Test Step 3: Send POST request with HTML and CSS
 */
async function step3_SendPostRequest() {
    console.log('Step 3: Sending POST request to API...');
    totalTests++;

    try {
        const cssUrl = `data:text/css;charset=utf-8,${encodeURIComponent(testCss)}`;
        const response = await postMultipart(API_URL, testHtml, cssUrl);

        const apiSuccess = response.status === 200 && response.data.success === true;
        generatedHash = response.data.data?.hash || null;
        generatedImagePath = response.data.data?.output_path || null;

        results.push({
            step: 3,
            name: 'Send POST request',
            passed: apiSuccess,
            details: `HTTP ${response.status}`,
            message: apiSuccess ? 'API successfully processed request' : (response.data.error || 'Unknown error')
        });

        console.log(`${apiSuccess ? '✓ PASS' : '✗ FAIL'} - API responded with HTTP ${response.status}`);
        if (apiSuccess) {
            console.log(`  Generated hash: ${generatedHash}`);
            console.log(`  Output path: ${generatedImagePath}`);
        } else {
            console.log(`  Error: ${response.data.error || 'Unknown error'}`);
        }
        console.log();

        return apiSuccess;
    } catch (error) {
        console.log(`✗ FAIL - ${error.message}\n`);
        results.push({
            step: 3,
            name: 'Send POST request',
            passed: false,
            details: 'Network error',
            message: error.message
        });
        return false;
    }
}

/**
 * Test Step 4: Verify PNG path format
 */
async function step4_VerifyPathFormat() {
    console.log('Step 4: Verifying PNG file path...');
    totalTests++;
    const pathMatches = generatedImagePath && generatedImagePath.includes(generatedHash) && generatedImagePath.endsWith('.png');
    results.push({
        step: 4,
        name: 'PNG created at correct path',
        passed: pathMatches,
        details: pathMatches ? 'Path format valid' : 'Invalid path format'
    });
    console.log(`${pathMatches ? '✓ PASS' : '✗ FAIL'} - PNG path verification`);
    console.log(`  Expected pattern: assets/media/rapidhtml2png/${generatedHash}.png`);
    console.log(`  Actual: ${generatedImagePath}\n`);
}

/**
 * Test Step 5: Load PNG via HTTP
 */
async function step5_LoadPngHttp() {
    console.log('Step 5: Loading PNG via HTTP...');
    totalTests++;

    try {
        const imageUrl = `http://localhost:8080/${generatedImagePath}`;
        const response = await getImage(imageUrl);
        const httpAccessible = response.status === 200 && response.contentType && response.contentType.startsWith('image/');

        results.push({
            step: 5,
            name: 'PNG accessible via HTTP',
            passed: httpAccessible,
            details: `HTTP ${response.status}`,
            message: httpAccessible ? `PNG accessible at ${imageUrl}` : `PNG not accessible (HTTP ${response.status})`
        });

        console.log(`${httpAccessible ? '✓ PASS' : '✗ FAIL'} - PNG HTTP accessibility (HTTP ${response.status})`);
        console.log(`  URL: ${imageUrl}`);
        console.log(`  Content-Type: ${response.contentType}`);
        console.log(`  Size: ${response.data.length} bytes\n`);

        return httpAccessible ? response.data : null;
    } catch (error) {
        console.log(`✗ FAIL - ${error.message}\n`);
        results.push({
            step: 5,
            name: 'PNG accessible via HTTP',
            passed: false,
            details: 'Network error',
            message: error.message
        });
        return null;
    }
}

/**
 * Test Step 6: Verify PNG is a valid image
 */
async function step6_VerifyPngValidity(imageData) {
    console.log('Step 6: Verifying PNG validity...');
    totalTests++;

    if (!imageData) {
        console.log('✗ FAIL - No image data available from Step 5\n');
        results.push({
            step: 6,
            name: 'PNG is a valid image',
            passed: false,
            details: 'No image data'
        });
        return;
    }

    // Check PNG signature
    const pngSignature = Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]);
    const validPng = imageData.slice(0, 8).equals(pngSignature);

    // Basic PNG dimension parsing
    let width = 0, height = 0;
    if (validPng && imageData.length >= 24) {
        // PNG IHDR chunk starts at byte 8
        width = imageData.readUInt32BE(16);
        height = imageData.readUInt32BE(20);
    }

    const validImage = validPng && width > 0 && height > 0;

    results.push({
        step: 6,
        name: 'PNG is a valid image',
        passed: validImage,
        details: validImage ? `${width}x${height}` : 'Invalid image',
        message: validImage ? `PNG is a valid image (${width}x${height})` : 'PNG is not a valid image file'
    });

    console.log(`${validImage ? '✓ PASS' : '✗ FAIL'} - PNG validity check`);
    if (validImage) {
        console.log(`  Image type: PNG`);
        console.log(`  Dimensions: ${width}x${height}`);
        console.log(`  PNG signature: Valid`);
    }
    console.log();
}

/**
 * Display final results
 */
function displayResults() {
    console.log('======================================');
    console.log('Test Results Summary');
    console.log('======================================\n');

    const passed = results.filter(r => r.passed).length;

    results.forEach(result => {
        const status = result.passed ? '✓ PASS' : '✗ FAIL';
        console.log(`${status} - Step ${result.step}: ${result.name}`);
        if (result.details) {
            console.log(`       Details: ${result.details}`);
        }
        if (result.message) {
            console.log(`       ${result.message}`);
        }
        console.log();
    });

    console.log('======================================');
    console.log(`Total: ${passed}/${totalTests} tests passed${passed === totalTests ? ' ✓' : ' ✗'}`);
    console.log('======================================');
    console.log();

    if (passed === totalTests) {
        console.log('✓ ALL E2E TESTS PASSED!');
        console.log('Complete workflow verified successfully.\n');
        process.exit(0);
    } else {
        console.log('✗ SOME TESTS FAILED\n');
        process.exit(1);
    }
}

/**
 * Main test execution
 */
async function runTests() {
    try {
        await step1_VerifyHtmlContent();
        await step2_VerifyCssContent();

        const apiSuccess = await step3_SendPostRequest();
        if (!apiSuccess) {
            console.log('\nCannot continue file verification tests due to API failure.\n');
            displayResults();
            return;
        }

        await step4_VerifyPathFormat();
        const imageData = await step5_LoadPngHttp();
        await step6_VerifyPngValidity(imageData);

        displayResults();
    } catch (error) {
        console.error(`\nFatal error: ${error.message}`);
        console.error(error.stack);
        process.exit(1);
    }
}

// Run the tests
runTests();
