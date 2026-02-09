<?php
/**
 * Feature #25 Test Output
 * This file makes an API call and writes the result to a file
 */

// Read raw POST data
$rawInput = file_get_contents('php://input');

// Write to output file
$output = [
    'timestamp' => date('Y-m-d H:i:s'),
    'raw_input' => $rawInput,
    'post_data' => $_POST,
    'files' => $_FILES,
    'get_data' => $_GET,
];

file_put_contents(__DIR__ . '/logs/feature_25_test_output.json', json_encode($output, JSON_PRETTY_PRINT));

// If we have html_blocks, try to process
if (!empty($_POST['html_blocks'])) {
    require_once __DIR__ . '/convert.php';

    $htmlBlocks = $_POST['html_blocks'];
    if (!is_array($htmlBlocks)) {
        $htmlBlocks = [$htmlBlocks];
    }

    $cssContent = null;
    $contentHash = generateContentHash($htmlBlocks, $cssContent);

    // Get output directory
    $outputDir = getOutputDirectory();
    $outputPath = $outputDir . '/' . $contentHash . '.png';

    // Detect libraries
    $detection = detectAvailableLibraries();

    // Try ImageMagick rendering
    $result = null;
    $imAvailable = $detection['detected_libraries']['imagemagick']['available'] ?? false;

    if ($imAvailable) {
        $result = renderWithImageMagick($htmlBlocks, $cssContent, $outputPath);
    }

    // Write result
    file_put_contents(__DIR__ . '/logs/feature_25_test_result.json', json_encode([
        'detection' => $detection,
        'imagemagick_available' => $imAvailable,
        'render_result' => $result,
        'content_hash' => $contentHash,
        'output_path' => $outputPath
    ], JSON_PRETTY_PRINT));

    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'imagemagick_available' => $imAvailable,
        'detection' => $detection,
        'render_result' => $result
    ]);
} else {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'No html_blocks provided',
        'post' => $_POST
    ]);
}
