<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli' || !function_exists('imagewebp')) {
    exit("PHP CLI with GD/WebP support is required.\n");
}

$root = dirname(__DIR__);
$target = $root . '/assets/marketing';
if (!is_dir($target) && !mkdir($target, 0775, true) && !is_dir($target)) {
    throw new RuntimeException('Could not create assets/marketing.');
}

/** @return GdImage */
function cover(GdImage $source, int $targetWidth, int $targetHeight): GdImage
{
    $sourceWidth = imagesx($source);
    $sourceHeight = imagesy($source);
    $scale = max($targetWidth / $sourceWidth, $targetHeight / $sourceHeight);
    $cropWidth = (int) round($targetWidth / $scale);
    $cropHeight = (int) round($targetHeight / $scale);
    $sourceX = max(0, (int) round(($sourceWidth - $cropWidth) / 2));
    $sourceY = max(0, (int) round(($sourceHeight - $cropHeight) * .38));
    $result = imagecreatetruecolor($targetWidth, $targetHeight);
    imagecopyresampled($result, $source, 0, 0, $sourceX, $sourceY, $targetWidth, $targetHeight, $cropWidth, $cropHeight);
    return $result;
}

/** @return GdImage */
function containWidth(GdImage $source, int $targetWidth): GdImage
{
    $height = (int) round(imagesy($source) * ($targetWidth / imagesx($source)));
    $result = imagecreatetruecolor($targetWidth, $height);
    imagecopyresampled($result, $source, 0, 0, 0, 0, $targetWidth, $height, imagesx($source), imagesy($source));
    return $result;
}

/** @return GdImage */
function trimTransparent(GdImage $source, int $padding = 24): GdImage
{
    $width = imagesx($source);
    $height = imagesy($source);
    $left = $width;
    $top = $height;
    $right = -1;
    $bottom = -1;

    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $alpha = (imagecolorat($source, $x, $y) >> 24) & 0x7f;
            if ($alpha >= 120) { continue; }
            $left = min($left, $x);
            $top = min($top, $y);
            $right = max($right, $x);
            $bottom = max($bottom, $y);
        }
    }

    if ($right < $left || $bottom < $top) {
        throw new RuntimeException('Transparent artwork contains no visible pixels.');
    }

    $left = max(0, $left - $padding);
    $top = max(0, $top - $padding);
    $right = min($width - 1, $right + $padding);
    $bottom = min($height - 1, $bottom + $padding);
    $cropped = imagecreatetruecolor($right - $left + 1, $bottom - $top + 1);
    imagealphablending($cropped, false);
    imagesavealpha($cropped, true);
    $transparent = imagecolorallocatealpha($cropped, 0, 0, 0, 127);
    imagefill($cropped, 0, 0, $transparent);
    imagecopy($cropped, $source, 0, 0, $left, $top, imagesx($cropped), imagesy($cropped));
    return $cropped;
}

$village = imagecreatefrompng($root . '/assets/art/village2.png');
foreach ([760, 1280, 1920] as $width) {
    $rendered = containWidth($village, $width);
    imagewebp($rendered, $target . "/village-{$width}.webp", 80);
    imagedestroy($rendered);
}
$social = cover($village, 1200, 630);
imagejpeg($social, $target . '/conquer-social.jpg', 86);
imagedestroy($social);
imagedestroy($village);

$world = imagecreatefrompng($root . '/assets/art/world.png');
$worldRendered = cover($world, 960, 640);
imagewebp($worldRendered, $target . '/world-960.webp', 80);
imagedestroy($worldRendered);
imagedestroy($world);

$gameCaptures = [
    'game-city.webp' => $root . '/artifacts/map-search/city-overview.png',
    'game-world.webp' => $root . '/artifacts/map-search/1280x800-result.png',
    'game-battle.webp' => $root . '/artifacts/encounter-cards-20260913/1280x800-orc-attack.png',
];
foreach ($gameCaptures as $filename => $sourcePath) {
    if (!is_file($sourcePath)) { continue; }
    $capture = imagecreatefrompng($sourcePath);
    $rendered = cover($capture, 1280, 800);
    imagewebp($rendered, $target . '/' . $filename, 82);
    imagedestroy($rendered);
    imagedestroy($capture);
}

$characters = [
    'character-infantry.webp' => $root . '/assets/art/characters/infantry-t1-ui-v1.png',
    'character-archer.webp' => $root . '/assets/art/characters/archer-t1-ui-v1.png',
];
foreach ($characters as $filename => $sourcePath) {
    $character = imagecreatefrompng($sourcePath);
    $cropped = trimTransparent($character);
    imagewebp($cropped, $target . '/' . $filename, 84);
    imagedestroy($cropped);
    imagedestroy($character);
}

echo "Landing images generated in assets/marketing.\n";
