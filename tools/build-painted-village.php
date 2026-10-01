<?php
// Resize existing approved assets only; retain original PNGs and sprite alpha.
$source = __DIR__ . '/../assets/art/fantasy-village-v1/';
$output = $source . 'runtime/';
if (!is_dir($output)) mkdir($output, 0777, true);
$names = ['background-approved','castle','academy','treasure_house','hospital','hall_of_alliance','trading_post','storage','watch_tower','stable','archery_range','barrack','farm','lumber_camp','gold_mine','quarry'];
foreach ($names as $name) {
    $image = imagecreatefrompng($source . $name . '.png');
    $max = $name === 'background-approved' ? 1536 : 512;
    $ratio = min(1, $max / max(imagesx($image), imagesy($image)));
    $w = (int)round(imagesx($image)*$ratio); $h = (int)round(imagesy($image)*$ratio);
    $scaled = imagecreatetruecolor($w, $h);
    imagealphablending($scaled, false); imagesavealpha($scaled, true);
    imagecopyresampled($scaled,$image,0,0,0,0,$w,$h,imagesx($image),imagesy($image));
    if (!imagewebp($scaled,$output.$name.'.webp',88)) throw new RuntimeException($name);
    imagedestroy($scaled); imagedestroy($image);
}
echo "Prepared 16 village assets.\n";
