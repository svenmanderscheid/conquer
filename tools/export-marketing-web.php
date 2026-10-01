<?php
declare(strict_types=1);
// Encode project artwork for the public page without changing its visual content.
if (PHP_SAPI !== 'cli') exit(1);
$root = dirname(__DIR__);
$files = [
    'outputs/social-2026-09/union-of-kingdoms-logo-en.png' => 'assets/art/logo-union-of-kingdoms-en-v3.webp',
    'outputs/social-2026-09/01-build.png' => 'assets/marketing/kingdom-social-en-v5.webp',
];
foreach (['02-army', '03-infantry', '04-archers', '05-cavalry', '06-relics', '07-alliance', '08-monsters', '09-conquest', '10-alpha'] as $name) {
    $version = 'v4';
    $files['outputs/social-2026-09/'.$name.'.png'] = 'assets/marketing/campaign-'.$name.'-'.$version.'.webp';
}
// The build illustration is also the existing social preview; keep one web copy.
foreach (['grumwald', 'frostgrimm', 'sandmaul', 'glutramm'] as $name) {
    $files['assets/art/monsters/'.$name.'.png'] = 'assets/marketing/current-'.$name.'.webp';
}
foreach (['rel-001-kornhorn-der-ernte', 'rel-006-axt-des-gruenhains', 'rel-020-banner-der-drei-heere'] as $name) {
    $files['assets/art/relics-v4-storybook/'.$name.'.png'] = 'assets/marketing/current-'.$name.'.webp';
}
foreach ($files as $source => $destination) {
    $image = imagecreatefrompng($root.'/'.$source);
    if (!$image) throw new RuntimeException('Cannot load '.$source);
    if (!imageistruecolor($image) && !imagepalettetotruecolor($image)) {
        throw new RuntimeException('Cannot convert palette for '.$source);
    }
    imagesavealpha($image, true);
    if (!imagewebp($image, $root.'/'.$destination, 85)) throw new RuntimeException('Cannot export '.$destination);
    imagedestroy($image);
    echo $destination.' '.filesize($root.'/'.$destination)." bytes\n";
}
