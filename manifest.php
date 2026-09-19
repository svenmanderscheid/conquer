<?php
declare(strict_types=1);
// Public metadata only: no bootstrap, session, credentials, or personalized state.
$scriptPath=parse_url((string)($_SERVER['SCRIPT_NAME']??'/manifest.php'),PHP_URL_PATH);
$base=defined('APP_BASE')?(string)APP_BASE:rtrim(str_replace('\\','/',dirname(is_string($scriptPath)?$scriptPath:'/manifest.php')),'/.');
if($base!==''&&!preg_match('#^/(?:[A-Za-z0-9._~%+-]+/?)+$#D',$base))$base='';
$root=$base.'/';
header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=3600');
header('X-Content-Type-Options: nosniff');
echo json_encode([
    'id'=>$root,'name'=>'Conquer · Chroniken eines Königreichs','short_name'=>'Conquer',
    'description'=>'Baue dein Königreich und erlebe gemeinsame Abenteuer.',
    'lang'=>'de','start_url'=>$root.'?source=pwa','scope'=>$root,
    'display'=>'standalone','orientation'=>'any','background_color'=>'#e9dfcf','theme_color'=>'#5c4270',
    'categories'=>['games','entertainment'],
    'icons'=>[
        ['src'=>$root.'assets/icons/conquer-192.png','sizes'=>'192x192','type'=>'image/png','purpose'=>'any maskable'],
        ['src'=>$root.'assets/icons/conquer-512.png','sizes'=>'512x512','type'=>'image/png','purpose'=>'any maskable'],
    ],
],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
