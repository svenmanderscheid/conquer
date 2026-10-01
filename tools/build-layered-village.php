<?php
// Lossy delivery copies of generated artwork. Original PNGs remain unchanged.
$dir=__DIR__.'/../assets/art/village-layered-v2/';
$manifest=json_decode(file_get_contents($dir.'sources.json'),true,512,JSON_THROW_ON_ERROR);
if(!is_dir($dir.'runtime'))mkdir($dir.'runtime',0777,true);
foreach($manifest as $name=>$source){
 if(!preg_match('/^[a-z_]+$/',$name))throw new RuntimeException('Invalid asset name');
 if(!is_file($dir.$name.'.png'))copy($source,$dir.$name.'.png');
 $im=imagecreatefrompng($dir.$name.'.png');
 if($name!=='terrain'){
  $trimmed=imagecropauto($im,IMG_CROP_TRANSPARENT);
  if($trimmed!==false){imagedestroy($im);$im=$trimmed;}
 }
 $max=$name==='terrain'?1536:512;
 $scale=min(1,$max/max(imagesx($im),imagesy($im)));
 $w=(int)round(imagesx($im)*$scale);$h=(int)round(imagesy($im)*$scale);
 $out=imagecreatetruecolor($w,$h);imagealphablending($out,false);imagesavealpha($out,true);
 imagecopyresampled($out,$im,0,0,0,0,$w,$h,imagesx($im),imagesy($im));
 if(!imagewebp($out,$dir.'runtime/'.$name.'.webp',88))throw new RuntimeException($name);
 imagedestroy($im);imagedestroy($out);
}
echo count($manifest)." assets prepared.\n";
