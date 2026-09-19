<?php
declare(strict_types=1);
// Explicit local maintenance. The audit records positions before and after repair.
if(PHP_SAPI!=='cli')exit(1);
$root=dirname(__DIR__);require $root.'/src/Autoloader.php';(new \Conquer\Autoloader($root.'/src'))->register();
\Conquer\Logger::init($root.'/logs/world-placement.log','ERROR');
$db=\Conquer\Db\Connection::init($root);
$apply=in_array('--apply',$argv,true);
$snapshot=static function()use($db):array{$result=[];foreach(['cities','field_objects','field_monsters']as$table)$result[$table]=$db->query('SELECT id,coord_x,coord_y FROM '.$table.' WHERE world_id=1 ORDER BY id')->fetchAll();return $result;};
$audit=['at'=>gmdate('c'),'before'=>$snapshot()];
if(!$apply){echo "Dry run: ".json_encode(array_map('count',$audit['before'])).". Use --apply to repair idle positions.\n";exit;}
$file=$root.'/logs/world-placement-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(3)).'.json';
if(file_put_contents($file,json_encode($audit,JSON_PRETTY_PRINT))===false)throw new RuntimeException('Cannot save position audit.');
$result=$db->transaction(static function($db):array{
    \Conquer\Game\Map\WorldPlacement::lockWorld($db,1);
    $cities=\Conquer\Game\Map\WorldPlacement::repairCities($db,1);
    $targets=\Conquer\Game\Map\FrontierService::repairCollisions($db,1,5000);
    return compact('cities','targets');
});
$audit['after']=$snapshot();$audit['moved']=$result;file_put_contents($file,json_encode($audit,JSON_PRETTY_PRINT));
echo json_encode($result)."\nAudit: ".$file."\n";
