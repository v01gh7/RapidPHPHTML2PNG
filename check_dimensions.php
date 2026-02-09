<?php
$images = [
    'small' => '/var/www/html/assets/media/rapidhtml2png/1c31297f612fa11874955e247ed3cbfe.png',
    'medium' => '/var/www/html/assets/media/rapidhtml2png/26b2b9dac64e9cfe41dd8ff0e811330b.png',
    'large' => '/var/www/html/assets/media/rapidhtml2png/46c4bdd8f4fb8aefe2031ca8209e350c.png',
    'wide' => '/var/www/html/assets/media/rapidhtml2png/0301c2c9b28238df7711ca2a2b0a1a26.png',
    'tall' => '/var/www/html/assets/media/rapidhtml2png/98c743ef5c7833aba8725c2c08ffa43f.png',
];

echo "Feature #29: Auto-sizing Test Results\n";
echo "====================================\n\n";

$tests = [
    'small' => ['condition' => 'width < 200 && height < 100', 'desc' => 'Small content produces small image'],
    'medium' => ['condition' => 'width >= 200 && width < 500', 'desc' => 'Medium content produces medium image'],
    'large' => ['condition' => 'width >= 400 || height >= 100', 'desc' => 'Large content produces large image'],
    'wide' => ['condition' => 'width/height > 2.0', 'desc' => 'Wide content produces wide image'],
    'tall' => ['condition' => 'height/width > 1.5', 'desc' => 'Tall content produces tall image'],
];

$passed = 0;
$total = count($images);

foreach ($images as $name => $path) {
    if (file_exists($path)) {
        $info = getimagesize($path);
        $width = $info[0];
        $height = $info[1];
        $aspect = $name === 'wide' ? $width / $height : ($name === 'tall' ? $height / $width : null);

        echo ucfirst($name) . " content:\n";
        echo "  Dimensions: {$width}x{$height}\n";
        if ($aspect !== null) {
            echo "  Ratio: " . number_format($aspect, 2) . ":1\n";
        }

        // Check condition
        $condition = $tests[$name]['condition'];
        $result = eval("return {$condition};");
        $status = $result ? '✓ PASS' : '✗ FAIL';
        echo "  {$status}: " . $tests[$name]['desc'] . "\n\n";

        if ($result) $passed++;
    } else {
        echo ucfirst($name) . ": File not found\n\n";
    }
}

echo "Summary: {$passed}/{$total} tests passed (" . number_format(($passed/$total)*100, 1) . "%)\n";
