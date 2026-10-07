<?php
declare(strict_types=1);
// Public metadata only: no bootstrap, session, credentials, or personalized state.
$scriptPath=parse_url((string)($_SERVER['SCRIPT_NAME']??'/manifest.php'),PHP_URL_PATH);
$base=defined('APP_BASE')?(string)APP_BASE:rtrim(str_replace('\\','/',dirname(is_string($scriptPath)?$scriptPath:'/manifest.php')),'/.');
if($base!==''&&!preg_match('#^/(?:[A-Za-z0-9._~%+-]+/?)+$#D',$base))$base='';
$root=$base.'/';
$iconVersion='20261005-art-fixes';
header('Content-Type: application/manifest+json; charset=utf-8');
// Browsers cache Web App Manifests aggressively. Revalidate every request so
// branding changes reach the install prompt without waiting for an old TTL.
header('Cache-Control: no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');
echo json_encode([
    'id'=>$root,'name'=>'Union of Kingdoms','short_name'=>'Union of Kingdoms',
    'description'=>'Build your kingdom and embark on adventures together.',
    'lang'=>'en','start_url'=>$root.'?source=pwa','scope'=>$root,
    'display'=>'standalone','orientation'=>'any','background_color'=>'#ffffff','theme_color'=>'#ffffff',
    'categories'=>['games','entertainment'],
    'icons'=>[
        ['src'=>$root.'assets/icons/conquer-192.png?v='.$iconVersion,'sizes'=>'192x192','type'=>'image/png','purpose'=>'any'],
        ['src'=>$root.'assets/icons/conquer-512.png?v='.$iconVersion,'sizes'=>'512x512','type'=>'image/png','purpose'=>'any'],
        ['src'=>$root.'assets/icons/conquer-maskable-512.png?v='.$iconVersion,'sizes'=>'512x512','type'=>'image/png','purpose'=>'maskable'],
    ],
],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
