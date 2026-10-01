<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();require __DIR__.'/Support/FeatureDatabase.php';
use Conquer\Db\{Connection,MigrationSql};
use Conquer\Game\World\{WorldMapProfile,LuxembourgGeography as G,LuxembourgHydrology,WorldContext,LandGeometry,LandProgressService,WorldSpawnService,WorldSettings};
use Conquer\Game\Map\{WorldPlacement,WorldTerrain,MapSearchService};
function luxCheck(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);echo "PASS $message\n";}
$fixture=null;
try{
    $seen=[];$samples=[];$n=0;
    foreach(G::data()['grid']['rows']as$gy=>$runs){$gx=0;for($i=0;$i<count($runs);$i+=2){for($j=0;$j<$runs[$i+1];$j++){
        $place=G::at(($gx+$j)*4+2,$gy*4+2);
        if($runs[$i]){$c=G::communes()[$runs[$i]-1];if($place['commune_id']!==$c['id']||$place['canton_id']!==$c['canton'])throw new RuntimeException('Field assignment differs from approved grid.');$seen[$c['id']]=true;$n++;}elseif($place!==null)throw new RuntimeException('Ocean assigned to commune.');
    }$gx+=$runs[$i+1];}}
    luxCheck(count($seen)===100,'all 100 communes mapped uniquely for every approved geography cell');
    luxCheck(G::at(-1,500)===null&&G::at(768,500)===null&&G::at(200,1100)===null,'rectangular map bounds reject out-of-world coordinates');
    $border=null;for($y=10;$y<1090&&$border===null;$y+=4)for($x=8;$x<760;$x+=4){$a=G::at($x-1,$y);$b=G::at($x+1,$y);if($a&&$b&&$a['canton_id']!==$b['canton_id']&&G::isDryRectangle($x-1.5,$y-1.5,$x+2.5,$y+2.5)){$border=[$x,$y,$b['canton_id']];break;}}
    luxCheck($border!==null&&!G::isDryRectangle($border[0]-1.5,$border[1]-1.5,$border[0]+2.5,$border[1]+2.5,$border[2]),'a dry city cannot straddle a canton border even when its center is valid');
    $landmarks=G::landmarks();luxCheck(count($landmarks)===113,'100 communes, 12 canton fortresses, and one crown landmark');
    foreach($landmarks as$i=>$landmark){[$l,$t,$r,$b]=G::boundsForSize($landmark['x'],$landmark['y'],$landmark['footprint']);if(!G::isDryRectangle($l-.5,$t-.5,$r+.5,$b+.5,G::at($landmark['x'],$landmark['y'])['canton_id']))throw new RuntimeException('Wet landmark '.$landmark['id']);foreach(array_slice($landmarks,0,$i)as$o){[$ol,$ot,$or,$ob]=G::boundsForSize($o['x'],$o['y'],$o['footprint']);if(!($r+5<$ol||$l-5>$or||$b+5<$ot||$t-5>$ob))throw new RuntimeException('Landmarks overlap.');}}
    luxCheck(true,'all 113 complete landmark footprints dry, inside canton and separated by five fields');
    $edge=LandGeometry::at(768,767,1099,1100);luxCheck($edge['bounds']['y_max']===1099&&$edge['bounds']['x_max']===767,'8x8 development parcels cover the southern rectangular edge independently of communes');
    $fixture=new \ConquerTests\FeatureDatabase();$db=Connection::getInstance();MigrationSql::apply($db->getPdo(),file_get_contents(ROOT_DIR.'/migrations/0119_world_map_profiles.sql'));
    luxCheck(WorldMapProfile::forWorld(1)['key']==='legacy'&&WorldMapProfile::forWorld(1)['height']===256,'additive migration leaves existing map and dimensions untouched');
    $db->execute("INSERT INTO worlds(name,slug,map_size,map_seed,status)VALUES('Lux fixture','lux-fixture',256,89,'running')");$world=$db->lastInsertId();WorldMapProfile::configureEmptyWorld($world);WorldContext::bind($world);
    luxCheck(WorldMapProfile::forWorld($world)['width']===768&&WorldMapProfile::forWorld($world)['height']===1100,'new world stores versioned Luxembourg profile');
    $start=microtime(true);LandProgressService::ensureWorld($world);luxCheck((int)$db->query('SELECT COUNT(*) FROM world_land_parts WHERE world_id=?',[$world])->fetchColumn()===13248,'all 13248 development parcels persist without becoming conquest territories');echo 'Initialization seconds: '.round(microtime(true)-$start,3)."\n";
    \Conquer\Game\World\WorldService::initializeWorld($world);
    luxCheck((int)$db->query('SELECT COUNT(*) FROM territory_targets WHERE world_id=?',[$world])->fetchColumn()===113&&(int)$db->query('SELECT COUNT(*) FROM shrines WHERE world_id=?',[$world])->fetchColumn()===0,'normal new-world initialization imports all targets and no old shrines or Congress');
    $db->execute("INSERT INTO world_event_settings(world_id,enabled,next_start,interval_hours,duration_hours,invasion_enabled,invasion_interval_hours,invasion_next_start) VALUES(?,1,UTC_TIMESTAMP(),336,168,1,72,UTC_TIMESTAMP())",[$world]);
    \Conquer\Game\Conquest\EventService::tick($world);
    luxCheck((int)$db->query('SELECT COUNT(*) FROM shrines WHERE world_id=?',[$world])->fetchColumn()===0&&(int)$db->query('SELECT COUNT(*) FROM conquest_events WHERE world_id=?',[$world])->fetchColumn()===0,'legacy event worker cannot recreate old objectives in a Luxembourg world');
    luxCheck(\Conquer\Game\Shrine\CongressService::state(0)===null&&\Conquer\Game\Shrine\CongressService::eventShrines(0)===[],'legacy Congress and shrine APIs expose no Luxembourg objectives');
    $cityPos=$db->transaction(static function($db)use($world,$landmarks):array{
        WorldPlacement::lockWorld($db,$world);
        foreach($landmarks as$l)if(WorldPlacement::canPlace($db,$world,'city',$l['x'],$l['y']))throw new RuntimeException('City overwrites immutable territory landmark.');
        $commune=array_values(array_filter(G::communes(),static fn($c)=>$c['name']==='Dudelange'))[0];
        $pos=WorldPlacement::findNear($db,$world,'city',(int)$commune['point'][0],(int)$commune['point'][1],null,50);if(!$pos)throw new RuntimeException('No south-city site.');
        if($pos[1]<=767)throw new RuntimeException('Test must cover rectangular southern extension.');return $pos;
    });luxCheck(true,'placement reserves all landmarks and accepts a real dry city south of old square dimensions');
    $db->transaction(static function($db)use($world,$cityPos):void{
        WorldPlacement::lockWorld($db,$world);
        $db->execute("INSERT INTO shrines(world_id,shrine_code,tier,coord_x,coord_y)VALUES(?,'ARCHIVED_TEST','C',?,?)",[$world,...$cityPos]);
        luxCheck(WorldPlacement::canPlace($db,$world,'city',...$cityPos),'retained legacy shrine history does not block placement in a migrated Luxembourg world');
    });
    $db->execute("INSERT INTO players(username,email,password_hash)VALUES('lux_player','lux@invalid.test','unused')");$pid=$db->lastInsertId();$db->execute("INSERT INTO cities(player_id,world_id,name,coord_x,coord_y)VALUES(?,?,'Lux fixture',?,?)",[$pid,$world,...$cityPos]);
    $db->execute("INSERT INTO worlds(name,slug,map_size,map_seed,status)VALUES('Existing fixture','existing-fixture',256,90,'running')");$existing=$db->lastInsertId();$db->execute("INSERT INTO cities(player_id,world_id,name,coord_x,coord_y)VALUES(?,?,'Preserve fixture',50,50)",[$pid,$existing]);
    try{WorldMapProfile::configureEmptyWorld($existing);throw new RuntimeException('Existing world was reinterpreted.');}catch(DomainException){}luxCheck(WorldMapProfile::forWorld($existing)['key']==='legacy','populated existing world cannot be converted implicitly');
    $before=$db->query('SELECT * FROM cities WHERE world_id=?',[$existing])->fetchAll();
    $db->query('SET TRANSACTION READ ONLY');$db->getPdo()->beginTransaction();try{$preview=\Conquer\Game\World\WorldMigrationPreview::build($existing);}finally{$db->getPdo()->rollBack();}
    luxCheck($preview['mode']==='preview_only'&&!$preview['writes_performed']&&$preview['position_mapping'][0]['to']!==null,'migration preview produces dry collision-free position proposals in a read-only transaction');
    luxCheck($before===$db->query('SELECT * FROM cities WHERE world_id=?',[$existing])->fetchAll()&&isset($preview['preserved_inventory']['cities']['sha256']),'migration preview preserves exact city rows and records their review hashes');
    $cfg=WorldSettings::defaults();$cfg['batch_limit']=40;$cfg['resource_limit']=20;$cfg['monster_limit']=12;$cfg['village_limit']=6;$db->execute('INSERT INTO world_spawn_settings(world_id,settings_json)VALUES(?,?)',[$world,json_encode($cfg)]);
    $spawn=WorldSpawnService::tick($world,true,'test')[$world];luxCheck($spawn['resources_spawned']>0&&$spawn['monsters_spawned']>0,'worker spawns genuine resource and monster objects on Luxembourg land');
    foreach(['field_objects'=>'resource','field_monsters'=>'monster','neutral_villages'=>'neutral_village']as$table=>$kind)foreach($db->query('SELECT coord_x,coord_y FROM '.$table.' WHERE world_id=?',[$world])->fetchAll()as$row){$b=WorldPlacement::footprint($kind,(int)$row['coord_x'],(int)$row['coord_y']);if(!WorldTerrain::isDryRectangle(...[...$b,$world]))throw new RuntimeException('Spawned wet '.$table);}
    luxCheck(true,'worker uses full dry footprint rules for every generated object');
    \Conquer\Game\Map\FieldObjectService::spawnObjects($world);
    luxCheck((int)$db->query("SELECT COUNT(*) FROM world_spawn_runs WHERE world_id=? AND trigger_source='field-seed'",[$world])->fetchColumn()===1,'legacy field seeding delegates to bounded whole-map Luxembourg population worker');
    $db->transaction(static function($db)use($world,$cityPos):void{WorldPlacement::lockWorld($db,$world);$position=WorldPlacement::findNear($db,$world,'resource',$cityPos[0]+7,$cityPos[1],null,20);if(!$position)throw new RuntimeException('No nearby search fixture.');$db->execute('INSERT INTO field_objects(world_id,coord_x,coord_y,object_type,level,resource_amount,resource_max,expires_at)VALUES(?,?,?,1,1,1000,1000,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY))',[$world,...$position]);});
    $search=MapSearchService::search($pid,['category'=>'food','level'=>1]);luxCheck($search['target']!==null&&(int)$search['target']['data']['coord_y']>767,'world-scoped resource search finds a real southern target beyond old square dimensions');
    $dryAt=LandProgressService::at($world,...$cityPos);luxCheck($dryAt!==null&&$dryAt['open'],'southern city has persistent, accessible development parcel');
    WorldContext::bind(1);luxCheck(WorldTerrain::isWater(75,65,1),'legacy lake geometry remains unchanged alongside Luxembourg');
    echo "Luxembourg world checks complete.\n";
}finally{$fixture?->close();}
