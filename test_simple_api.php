<?php
// Simple API test
$ch = curl_init('http://localhost:8080/convert.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['html_blocks' => ['<div>TEST</div>']]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response:\n";
echo $response . "\n";

if ($httpCode === 200) {
    $data = json_decode($response, true);
    if ($data && isset($data['data']['rendering']['output_file'])) {
        $outputFile = $data['data']['rendering']['output_file'];
        echo "\nOutput file: $outputFile\n";
        echo "File exists: " . (file_exists($outputFile) ? 'YES' : 'NO') . "\n";
        if (file_exists($outputFile)) {
            echo "File size: " . filesize($outputFile) . " bytes\n";
        }
    }
}
