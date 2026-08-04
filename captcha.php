<?php
session_start();

$code = strval(random_int(1000, 9999));
$_SESSION['setup_captcha'] = $code;
$_SESSION['setup_captcha_time'] = time();

$width = 140;
$height = 50;
$image = imagecreatetruecolor($width, $height);

$bg = imagecolorallocate($image, 255, 255, 255);
$textColor = imagecolorallocate($image, 30, 30, 30);
$lineColor = imagecolorallocate($image, 30, 30, 30);

imagefilledrectangle($image, 0, 0, $width, $height, $bg);

// Noise lines behind the text
for ($i = 0; $i < 6; $i++) {
    imageline(
        $image,
        random_int(0, $width), random_int(0, $height),
        random_int(0, $width), random_int(0, $height),
        $lineColor
    );
}

// Draw each digit at a slightly randomized vertical offset
$x = 18;
for ($i = 0; $i < strlen($code); $i++) {
    imagestring($image, 5, $x, (int)random_int(5, 15), $code[$i], $textColor);
    $x += 28;
}

// Scatter noise dots on top
for ($i = 0; $i < 90; $i++) {
    imagesetpixel($image, random_int(0, $width), random_int(0, $height), $lineColor);
}

header('Content-Type: image/png');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
imagepng($image);
imagedestroy($image);
