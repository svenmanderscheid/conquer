<?php
declare(strict_types=1);

/** Placement regressions run only against a disposable local database. */
if (PHP_SAPI !== 'cli') { exit(1); }
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR . '/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR . '/src'))->register();
date_default_timezone_set('UTC');

use Conquer\Db\Connection;
use Conquer\Game\Map\FrontierService;
use Conquer\Game\Map\WorldPlacement;
use Conquer\Game\Map\WorldTerrain;

function checkPlacement(bool $ok, string $label): void {
    if (!$ok) { throw new RuntimeException($label); }
    echo 'PASS ' . $label . "\n";
}
function node(Connection $db, int $x, int $y, int $stock = 5757): int {
    $db->execute('INSERT INTO field_objects(world_id,coord_x,coord_y,object_type,level,resource_amount,resource_max,expires_at) VALUES(1,?,?,1,2,?,10000,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY))', [$x,$y,$stock]);
    return $db->lastInsertId();
}
function monster(Connection $db, int $x, int $y): int {
    $db->execute('INSERT INTO field_monsters(world_id,monster_code,coord_x,coord_y,hp_current) VALUES(1,20209901,?,?,99)', [$x,$y]);
    return $db->lastInsertId();
}
function row(Connection $db, string $table, int $id): array {
    return $db->query('SELECT * FROM '.$table.' WHERE id=?', [$id])->fetch();
}
function unchangedPayload(array $before, array $after): bool {
    unset($before['coord_x'],$before['coord_y'],$after['coord_x'],$after['coord_y']);
    return $before === $after;
}
function repair(Connection $db): int {
    return $db->transaction(static function(Connection $db): int {
        WorldPlacement::lockWorld($db,1);
        return FrontierService::repairCollisions($db,1);
    });
}

$cfg = require ROOT_DIR.'/config/database.php';
if (!in_array($cfg['host'] ?? '', ['127.0.0.1','localhost'], true)) { exit("Local MySQL only.\n"); }
$name = 'conquer_placement_test_'.bin2hex(random_bytes(6));
$root = sys_get_temp_dir().DIRECTORY_SEPARATOR.$name;
$admin = null;
$exit = 0;
try {
    $source = $cfg['database'];
    if (!preg_match('/^[a-zA-Z0-9_]+$/D',$source)) { throw new RuntimeException('Invalid source database name.'); }
    $admin = new PDO('mysql:host='.$cfg['host'].';port='.($cfg['port']??3306).';charset=utf8mb4',$cfg['username'],$cfg['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $admin->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    foreach (['worlds','players','cities','field_objects','field_monsters','marches','rallies','rally_participants','expedition_missions','frontier_spawns','shrines','map_charms'] as $table) {
        $admin->exec('CREATE TABLE `'.$name.'`.`'.$table.'` LIKE `'.$source.'`.`'.$table.'`');
    }
    $admin->exec('INSERT INTO `'.$name.'`.worlds SELECT * FROM `'.$source.'`.worlds WHERE id=1');
    mkdir($root.'/config',0700,true);
    $cfg['database'] = $name;
    file_put_contents($root.'/config/database.php',"<?php\nreturn ".var_export($cfg,true).";\n");
    $db = Connection::init($root);
    // FrontierService now checks the adjacent world's scheduler configuration.
    // Only its empty settings table is needed; no live world configuration is copied.
    $spawnSettingsSchema=explode(';',file_get_contents(ROOT_DIR.'/migrations/0063_world_spawn_settings.sql'))[0];
    $db->getPdo()->exec($spawnSettingsSchema);
    $db->execute("INSERT INTO players(id,username,email,password_hash) VALUES(400000001,'PlacementFixture','placement@invalid.test','unused')");
    $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y) VALUES(400000001,400000001,1,'Placement fixture',50,50)");

    $db->transaction(static function(Connection $db): void {
        $size = WorldPlacement::lockWorld($db,1);
        checkPlacement($size===256,'world lock uses actual map size');
        checkPlacement(WorldPlacement::footprint('city',50,50)===[49,49,52,52],'city anchor reserves exactly sixteen tiles with east and south extension');
        foreach (['monster','resource','city'] as $kind) {
            checkPlacement(!WorldPlacement::canPlace($db,1,$kind,75,65),$kind.' cannot occupy lake center');
        }
        $terrain=WorldTerrain::definition();
        $r=$terrain['rivers'];$y=110;$side=$r['sides'][0];
        $x=(int)round($side+sin($y/$r['period']+$side)*$r['amplitude']+sin($y/$r['detailPeriod'])*$r['detailAmplitude']);
        checkPlacement(!WorldPlacement::canPlace($db,1,'monster',$x,$y),'river tiles are excluded');
        $s=$terrain['streams'][0];$x=90;$y=(int)round($s['base']+sin($x/$s['period'])*$s['amplitude']+sin($x/$s['detailPeriod'])*$s['detailAmplitude']);
        checkPlacement(!WorldPlacement::canPlace($db,1,'resource',$x,$y),'stream tiles are excluded');
        foreach (['boss','city'] as $kind) {
            $edge=null;
            for($y=60;$y<=70&&$edge===null;$y++)for($x=69;$x<=81;$x++) {
                if(!WorldTerrain::isWater($x,$y)&&!WorldTerrain::isDryRectangle(...WorldPlacement::footprint($kind,$x,$y))) {$edge=[$x,$y];break;}
            }
            checkPlacement($edge!==null&&!WorldPlacement::canPlace($db,1,$kind,...$edge),$kind.' shoreline check covers entire footprint, not just anchor');
        }
        checkPlacement(WorldPlacement::footprint('resource',20,20)===[20,20,20,20],'resource reserves exactly one tile');
        checkPlacement(WorldPlacement::footprint('monster',20,20)===[20,20,20,20],'small monster reserves exactly one tile');
        checkPlacement(WorldPlacement::canPlace($db,1,'resource',255,20),'single-tile resource fits at world edge');
        checkPlacement(!WorldPlacement::canPlace($db,1,'resource',256,20),'resource outside world is rejected');
        checkPlacement(!WorldPlacement::canPlace($db,1,'city',0,20),'city cannot protrude beyond world edge');
        checkPlacement(!WorldPlacement::canPlace($db,1,'city',254,20),'fourth city column cannot protrude beyond east edge');
        checkPlacement(!WorldPlacement::canPlace($db,1,'city',20,254),'fourth city row cannot protrude beyond south edge');
        checkPlacement(!WorldPlacement::canPlace($db,1,'monster',49,49),'monster excluded from outer city tile');
        checkPlacement(!WorldPlacement::canPlace($db,1,'monster',52,52),'SQL candidates include city anchor two tiles behind a monster');
        checkPlacement(!WorldPlacement::canPlace($db,1,'resource',52,52),'SQL candidates include city anchor two tiles behind a resource');
        checkPlacement(!WorldPlacement::canPlace($db,1,'city',53,53),'cities cannot overlap only at their extended corner');
        checkPlacement(!WorldPlacement::conflicts('city',50,50,'city',54,50),'cities may meet outside their four-tile footprint');
        checkPlacement(WorldPlacement::canPlace($db,1,'resource',48,48),'single-tile resource fits immediately northwest of city');
        checkPlacement(WorldPlacement::canPlace($db,1,'resource',47,47),'resource allowed immediately outside city footprint');
        $node=node($db,20,20);
        checkPlacement(!WorldPlacement::canPlace($db,1,'resource',22,20),'one empty tile between resources is rejected');
        checkPlacement(WorldPlacement::canPlace($db,1,'resource',23,20),'exactly two empty tiles between resources are allowed');
        checkPlacement(WorldPlacement::canPlace($db,1,'resource',24,20),'larger resource spacing remains allowed');
        checkPlacement(!WorldPlacement::canPlace($db,1,'resource',22,22),'diagonal resource spacing rejects one-tile gap');
        checkPlacement(WorldPlacement::canPlace($db,1,'resource',24,23),'diagonal resources allowed with two-tile separation on one axis');
        checkPlacement(WorldPlacement::canPlace($db,1,'monster',21,21),'monster may occupy released southeast resource tile');
        checkPlacement(WorldPlacement::canPlace($db,1,'city',22,22),'city may occupy released southeast resource tile');
        checkPlacement(WorldPlacement::canPlace($db,1,'resource',20,20,$node),'existing resource ignores only its own identity');
        $m=monster($db,30,30);
        checkPlacement(!WorldPlacement::canPlace($db,1,'resource',30,30),'resource placement respects monster footprint');
        checkPlacement(!WorldPlacement::canPlace($db,1,'city',31,31),'city placement respects monster footprint');
        $db->execute('DELETE FROM field_objects');$db->execute('DELETE FROM field_monsters');
    });

    $idleNode=node($db,75,65);$idleMonster=monster($db,50,49);
    $gapNodeA=node($db,20,20);$gapNodeB=node($db,21,20);
    $beforeNode=row($db,'field_objects',$idleNode);$beforeMonster=row($db,'field_monsters',$idleMonster);
    $busyNode=node($db,99,79);$busyMonster=monster($db,42,132);$gatheredNode=node($db,207,42);$rallyNode=node($db,149,151);
    $db->execute("INSERT INTO marches(player_id,world_id,march_type,origin_city_id,target_x,target_y,target_type,target_id,troops_json,departure_time,arrival_time,state) VALUES(400000001,1,9,400000001,99,79,5,?,'{}',UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),'returning')",[$busyNode]);
    $busyMarch=$db->lastInsertId();
    // Even a legacy march with stale coordinates must protect its typed target ID.
    $db->execute("INSERT INTO marches(player_id,world_id,march_type,origin_city_id,target_x,target_y,target_type,target_id,troops_json,departure_time,arrival_time,state) VALUES(400000001,1,5,400000001,41,131,3,?,'{}',UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),'marching')",[$busyMonster]);
    $db->execute('UPDATE field_objects SET gatherer_march_id=? WHERE id=?',[$busyMarch,$gatheredNode]);
    $db->execute("INSERT INTO rallies(world_id,leader_player_id,leader_city_id,target_player_id,target_city_id,target_x,target_y,rally_minutes,troops_json,status,launch_at) VALUES(1,400000001,400000001,400000001,400000001,149,151,5,'{}','returning',UTC_TIMESTAMP())");
    checkPlacement(repair($db)>=3,'repair fixes water, city overlap and insufficient resource spacing');
    $afterNode=row($db,'field_objects',$idleNode);$afterMonster=row($db,'field_monsters',$idleMonster);
    checkPlacement(unchangedPayload($beforeNode,$afterNode)&&[$afterNode['coord_x'],$afterNode['coord_y']]!==[75,65],'repair preserves resource ID, exact stock, max stock, level and expiry');
    checkPlacement(unchangedPayload($beforeMonster,$afterMonster)&&[$afterMonster['coord_x'],$afterMonster['coord_y']]!==[50,49],'repair preserves monster identity and damaged HP');
    foreach ([['field_objects',$busyNode,99,79],['field_monsters',$busyMonster,42,132],['field_objects',$gatheredNode,207,42],['field_objects',$rallyNode,149,151]] as [$table,$id,$x,$y]) {
        $target=row($db,$table,$id);
        checkPlacement((int)$target['coord_x']===$x&&(int)$target['coord_y']===$y,'busy target remains fixed: '.$table.' #'.$id);
    }
    foreach ([[$idleNode,'resource'],[$gapNodeA,'resource'],[$gapNodeB,'resource'],[$idleMonster,'monster']] as [$id,$kind]) {
        $target=row($db,$kind==='resource'?'field_objects':'field_monsters',$id);
        checkPlacement(WorldPlacement::canPlace($db,1,$kind,(int)$target['coord_x'],(int)$target['coord_y'],$id),'repaired target has valid full footprint: '.$kind.' #'.$id);
    }
    $db->execute("UPDATE marches SET state='complete'");$db->execute("UPDATE rallies SET status='complete'");$db->execute('UPDATE field_objects SET gatherer_march_id=NULL');
    checkPlacement(repair($db)===4,'deferred targets relocate once marches, rally and gathering finish');
    checkPlacement(repair($db)===0,'repair is idempotent once positions are valid');

    $db->execute('UPDATE cities SET coord_x=83,coord_y=70 WHERE id=400000001');
    $city=row($db,'cities',400000001);
    $countBefore=(int)$db->query('SELECT COUNT(*) FROM field_objects')->fetchColumn()+(int)$db->query('SELECT COUNT(*) FROM field_monsters')->fetchColumn();
    FrontierService::refresh(400000001,$city);
    $countAfter=(int)$db->query('SELECT COUNT(*) FROM field_objects')->fetchColumn()+(int)$db->query('SELECT COUNT(*) FROM field_monsters')->fetchColumn();
    checkPlacement($countAfter>$countBefore,'frontier creates new nearby targets');
    foreach (['field_objects'=>'resource','field_monsters'=>'monster'] as $table=>$kind) {
        foreach($db->query('SELECT * FROM '.$table)->fetchAll() as $target) {
            checkPlacement(WorldPlacement::canPlace($db,1,$kind,(int)$target['coord_x'],(int)$target['coord_y'],(int)$target['id']),'frontier and repaired '.$kind.' #'.$target['id'].' respect terrain and spacing');
        }
    }
    FrontierService::refresh(400000001,$city);
    checkPlacement($countAfter===(int)$db->query('SELECT COUNT(*) FROM field_objects')->fetchColumn()+(int)$db->query('SELECT COUNT(*) FROM field_monsters')->fetchColumn(),'refresh cooldown does not duplicate frontier targets');
    \Conquer\Logger::init(sys_get_temp_dir().'/conquer-placement-test.log','ERROR');
    \Conquer\Game\Map\FieldObjectService::spawnObjects(1);
    $spawned=$db->query('SELECT * FROM field_objects')->fetchAll();$valid=true;
    foreach($spawned as $node)$valid=$valid&&WorldPlacement::canPlace($db,1,'resource',(int)$node['coord_x'],(int)$node['coord_y'],(int)$node['id']);
    checkPlacement(count($spawned)>100&&$valid,'sector resource seeding obeys water, full footprints and two empty tiles');
    $db->execute('UPDATE cities SET coord_x=75,coord_y=65 WHERE id=400000001');$oldCity=row($db,'cities',400000001);
    $db->execute("UPDATE marches SET state='returning' WHERE id=?",[$busyMarch]);
    checkPlacement($db->transaction(fn($db)=>WorldPlacement::repairCities($db,1,400000001))===0,'legacy water city with a returning army is not moved');
    $db->execute("UPDATE marches SET state='complete'");
    checkPlacement($db->transaction(fn($db)=>WorldPlacement::repairCities($db,1,400000001))===1,'idle legacy water city moves onto dry land');
    $newCity=row($db,'cities',400000001);checkPlacement(unchangedPayload($oldCity,$newCity)&&WorldPlacement::canPlace($db,1,'city',(int)$newCity['coord_x'],(int)$newCity['coord_y'],400000001),'city repair preserves progress and validates all sixteen tiles');

    // Expanding a previously valid 3x3 shore city must check its new fourth row/column.
    $shore=null;
    for($y=57;$y<78&&$shore===null;$y++)for($x=63;$x<88;$x++)if(WorldTerrain::isDryRectangle($x-1,$y-1,$x+1,$y+1)&&!WorldTerrain::isDryRectangle(...WorldPlacement::footprint('city',$x,$y))){$shore=[$x,$y];break;}
    checkPlacement($shore!==null,'fixture distinguishes old dry 3x3 from new wet 4x4');
    $db->execute('UPDATE cities SET coord_x=?,coord_y=? WHERE id=400000001',$shore);
    checkPlacement($db->transaction(fn($db)=>WorldPlacement::repairCities($db,1,400000001))===1,'repair moves a city whose new fourth row or column reaches water');
    $db->execute('DELETE FROM field_objects');$db->execute('DELETE FROM field_monsters');
    $db->execute('UPDATE cities SET coord_x=50,coord_y=50 WHERE id=400000001');
    $db->execute("INSERT INTO players(id,username,email,password_hash) VALUES(400000002,'PlacementNeighbour','neighbour@invalid.test','unused')");
    $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y) VALUES(400000002,400000002,1,'Legacy neighbour',53,50)");
    $older=row($db,'cities',400000001);$neighbour=row($db,'cities',400000002);
    $db->execute("UPDATE marches SET player_id=400000002,state='returning' WHERE id=?",[$busyMarch]);
    checkPlacement($db->transaction(fn($db)=>WorldPlacement::repairCities($db,1))===0,'expanded overlapping city stays fixed while its army returns');
    $db->execute("UPDATE marches SET state='complete'");
    checkPlacement($db->transaction(fn($db)=>WorldPlacement::repairCities($db,1))===1,'expanded idle city collision moves exactly the newer village');
    $moved=row($db,'cities',400000002);
    checkPlacement(row($db,'cities',400000001)===$older&&unchangedPayload($neighbour,$moved)&&WorldPlacement::canPlace($db,1,'city',(int)$moved['coord_x'],(int)$moved['coord_y'],400000002),'collision repair preserves both villages and every progress field');
    checkPlacement($db->transaction(fn($db)=>WorldPlacement::repairCities($db,1))===0,'city expansion repair is idempotent');
    echo "ALL WORLD PLACEMENT CHECKS PASSED (isolated database).\n";
} catch(Throwable $e) {
    fwrite(STDERR,'FAIL '.$e->getMessage()."\n".$e->getTraceAsString()."\n");$exit=1;
} finally {
    if($admin&&preg_match('/^conquer_placement_test_[a-f0-9]{12}$/D',$name)) {$admin->exec('DROP DATABASE IF EXISTS `'.$name.'`');}
    $resolved=realpath($root);$parent=realpath(sys_get_temp_dir());
    if($resolved!==false&&$parent!==false&&str_replace('\\','/',$resolved)===str_replace('\\','/',$parent).'/'.$name&&preg_match('/^conquer_placement_test_[a-f0-9]{12}$/D',basename($resolved))) {
        foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($resolved,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST) as $item) {if($item->isDir()&&!$item->isLink())rmdir($item->getPathname());else unlink($item->getPathname());}rmdir($resolved);
    }
}
exit($exit);
