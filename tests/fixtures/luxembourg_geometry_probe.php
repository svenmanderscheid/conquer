<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__,2));require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
use Conquer\Game\World\{LuxembourgHydrology as H,LuxembourgGeography as G};
$cases=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);$out=[];
foreach($cases as$c)$out[]=['at'=>G::at($c[0],$c[1]),'water'=>H::waterAt($c[0],$c[1]),'intersects'=>H::intersectsRect(...$c),'dry'=>G::isDryRectangle(...$c)];
echo json_encode($out,JSON_THROW_ON_ERROR);
