<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));
require ROOT_DIR.'/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require ROOT_DIR.'/tests/Support/FeatureDatabase.php';
date_default_timezone_set('UTC');

use Conquer\Db\Connection;
use Conquer\Db\MigrationSql;
use Conquer\Game\Charm\{CharmCollectionService,CharmQueryService,MonsterCharmLifecycle};
use Conquer\Game\March\{MarchDispatcher,MarchTick};
use Conquer\Game\World\WorldContext;

function charmCheck(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
function charmReject(callable $action,string $message,string $label):void{try{$action();}catch(Throwable $e){if($e->getMessage()===$message){charmCheck(true,$label);return;}throw $e;}throw new RuntimeException('Expected rejection: '.$label);}
function charmMap(int $world,int $x,int $y,string $category='research',string $grade='epic',float $bonus=5,int $duration=7200,string $expiry='1 HOUR'):int{
    $db=Connection::getInstance();$code=10700002;
    $db->execute("INSERT INTO map_charms(world_id,coord_x,coord_y,stat_category,grade,charm_code,bonus_pct,effect_duration_seconds,spawned_at,expires_at) VALUES(?,?,?,?,?,?,?, ?,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL $expiry))",[$world,$x,$y,$category,$grade,$code,$bonus,$duration]);
    return $db->lastInsertId();
}
function collector(int $player,int $city,int $world,int $charm,int $x,int $y,int $arrivedSecondsAgo):int{
    $db=Connection::getInstance();
    $db->execute("INSERT INTO marches(player_id,world_id,march_type,origin_city_id,target_x,target_y,target_type,target_id,troops_json,departure_time,arrival_time,state) VALUES(?,?,?,?,?,?,4,?,'{\"50100101\":10}',DATE_SUB(UTC_TIMESTAMP(),INTERVAL ? SECOND),DATE_SUB(UTC_TIMESTAMP(),INTERVAL ? SECOND),'marching')",[$player,$world,6,$city,$x,$y,$charm,$arrivedSecondsAgo+20,$arrivedSecondsAgo]);
    return $db->lastInsertId();
}

$fixture=new \ConquerTests\FeatureDatabase();$db=Connection::getInstance();
\Conquer\Logger::init(sys_get_temp_dir().'/conquer-charm-tests.log','ERROR');
try{
    MigrationSql::apply($db->getPdo(),(string)file_get_contents(ROOT_DIR.'/migrations/0085_monster_charms.sql'));
    $db->getPdo()->exec('ALTER TABLE player_charms_active DROP COLUMN source_march_id');
    $db->getPdo()->exec('ALTER TABLE marches DROP INDEX idx_charm_collect_race');
    $db->execute("INSERT INTO map_charms(world_id,coord_x,coord_y,stat_category,grade,charm_code,spawned_at,expires_at) VALUES(1,1,1,'research','epic',10700002,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR))");$legacyCharm=$db->lastInsertId();
    $db->execute("INSERT INTO map_charms(world_id,coord_x,coord_y,stat_category,grade,charm_code,spawned_at,expires_at) VALUES(1,2,1,'research','legendary',10700003,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR))");$legacyLegendary=$db->lastInsertId();
    MigrationSql::apply($db->getPdo(),(string)file_get_contents(ROOT_DIR.'/migrations/0087_charm_compatibility.sql'));
    MigrationSql::apply($db->getPdo(),(string)file_get_contents(ROOT_DIR.'/migrations/0087_charm_compatibility.sql'));
    $legacySnapshot=$db->query('SELECT bonus_pct,effect_duration_seconds FROM map_charms WHERE id=?',[$legacyCharm])->fetch();
    $legendarySnapshot=$db->query('SELECT bonus_pct,effect_duration_seconds FROM map_charms WHERE id=?',[$legacyLegendary])->fetch();
    $columns=$db->query('SHOW COLUMNS FROM player_charms_active')->fetchAll(PDO::FETCH_COLUMN);$indexes=array_column($db->query('SHOW INDEX FROM marches')->fetchAll(),'Key_name');
    charmCheck(in_array('source_march_id',$columns,true)&&in_array('idx_charm_collect_race',$indexes,true)&&(float)$legacySnapshot['bonus_pct']===6.0&&(int)$legacySnapshot['effect_duration_seconds']===7200&&(float)$legendarySnapshot['bonus_pct']===10.0&&(int)$legendarySnapshot['effect_duration_seconds']===14400,'0087 repeatedly repairs early 0085 schemas and preserves canonical grade values');
    $db->execute("INSERT INTO worlds(id,name,slug,map_size,status,speed_factor,gather_factor) VALUES(2,'Charm world','charm-world',256,'open',1,1)");
    foreach([1,2] as $player){
        $db->execute("INSERT INTO players(id,username,email,password_hash) VALUES(?,?,?,'unused')",[$player,'CharmFixture'.$player,'charm'.$player.'@invalid.test']);
        foreach([1,2] as $world){$city=$player+($world===2?100:0);$db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y) VALUES(?,?,?,'Charm city',?,10)",[$city,$player,$world,8+$player*2]);foreach(\Conquer\Game\City\CityState::BUILDING_CODES as $code)$db->execute('INSERT INTO city_buildings(city_id,building_code,level) VALUES(?,?,1)',[$city,$code]);$db->execute('INSERT INTO city_troops(city_id,troop_code,count) VALUES(?,50100101,1000)',[$city]);}
    }

    WorldContext::bind(1);
    $active=\Conquer\Game\Map\MonsterData::get(20200110);
    $first=$db->transaction(fn()=>MonsterCharmLifecycle::settle(1,['id'=>9001,'monster_code'=>20200110,'coord_x'=>44,'coord_y'=>45,'effective_monster_level'=>10],$active,'solo',7001,1,null,['resources'=>['food'=>10]]));
    $retry=$db->transaction(fn()=>MonsterCharmLifecycle::settle(1,['id'=>9001,'monster_code'=>20200110,'coord_x'=>44,'coord_y'=>45,'effective_monster_level'=>10],$active,'solo',7001,1,null,['resources'=>['food'=>10]]));
    charmCheck($first['created']&&$first['charm_id']!==null&&!$retry['created']&&$retry['charm_id']===$first['charm_id'],'one kill receipt creates exactly one guaranteed charm and retries reuse it');
    charmCheck((int)$db->query("SELECT COUNT(*) FROM land_progress_events WHERE world_id=1 AND event_key='monster_kill:9001'")->fetchColumn()===1,'kill receipt and regional progress share the same field-monster idempotency key');
    $spawned=$db->query('SELECT * FROM map_charms WHERE id=?',[$first['charm_id']])->fetch();
    charmCheck((int)$spawned['coord_x']===44&&(int)$spawned['coord_y']===45&&(float)$spawned['bonus_pct']>0&&(int)$spawned['effect_duration_seconds']>0,'kill coordinates, bonus and duration are snapshotted on the map charm');
    $inactive=\Conquer\Game\Map\MonsterData::get(20200901);
    charmReject(fn()=>$db->transaction(fn()=>MonsterCharmLifecycle::settle(1,['id'=>9002,'monster_code'=>20200901,'coord_x'=>46,'coord_y'=>45],$inactive,'solo',7002,1,null,[])),'Dieses Monster ist derzeit nicht aktiv.','inactive Magdar template cannot enter the kill/charm lifecycle');
    foreach([20209901,20200102,20200201,20200301,20200401,20200501,20202101,20202201,20202301,20202401] as $i=>$code)charmCheck(\Conquer\Game\Map\MonsterData::isActive($code)&&\Conquer\Game\Charm\CharmSpawner::spawn(1,50+$i,50,$code,null,null,\Conquer\Game\Map\MonsterData::get($code))!==null,'active roster family '.$code.' has a guaranteed charm path');
    foreach([20202101,20202201,20202301,20202401] as $code)foreach(\Conquer\Game\Rally\MonsterRally::drops(\Conquer\Game\Map\MonsterData::get($code)) as $drop)charmCheck(\Conquer\Game\Inventory\InventoryService::getItemDef((int)$drop['item_code'])!==null,'active rally '.$code.' pays only catalogued item '.$drop['item_code']);
    foreach([20200601,20200701,20200801,20200901] as $code)charmCheck(!\Conquer\Game\Map\MonsterData::isActive($code),'inactive template '.$code.' stays outside the runtime roster');

    $raceCharm=charmMap(1,20,20,'troops_hp','legendary',12.5,4321);
    $earlier=collector(1,1,1,$raceCharm,20,20,20);$later=collector(2,2,1,$raceCharm,20,20,10);
    $race=CharmCollectionService::resolveDue(1,$raceCharm);
    charmCheck($race['winner_march_id']===$earlier&&$race['winner_player_id']===1,'earliest arrival wins even when another player triggers the lazy tick');
    $effect=$db->query('SELECT * FROM player_charms_active WHERE source_map_charm_id=?',[$raceCharm])->fetch();
    charmCheck((int)$effect['player_id']===1&&(int)$effect['world_id']===1&&(float)$effect['bonus_pct']===12.5,'collector receives the persisted world-bound effect snapshot');
    $laterMarch=$db->query('SELECT state,haul_json FROM marches WHERE id=?',[$later])->fetch();
    charmCheck($laterMarch['state']==='returning'&&(json_decode($laterMarch['haul_json'],true)['reason']??null)==='charm_already_collected','losing due collector returns with the precise already-collected result');

    $tieCharm=charmMap(1,21,20);$tieA=collector(1,1,1,$tieCharm,21,20,5);$tieB=collector(2,2,1,$tieCharm,21,20,5);
    $db->execute('UPDATE marches SET arrival_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 5 SECOND) WHERE id IN (?,?)',[$tieA,$tieB]);
    $tie=CharmCollectionService::resolveDue(1,$tieCharm);
    charmCheck($tie['winner_march_id']===min($tieA,$tieB),'equal arrival times use the lower march id');

    $chronologicalOld=charmMap(1,24,20,'march_speed','legendary',10,14400);$chronologicalNew=charmMap(1,25,20,'march_speed','normal',3,1800);
    $oldMarch=collector(1,1,1,$chronologicalOld,24,20,10);$newMarch=collector(1,1,1,$chronologicalNew,25,20,5);
    CharmCollectionService::resolveDue(1,$chronologicalNew);CharmCollectionService::resolveDue(1,$chronologicalOld);
    $chronological=$db->query("SELECT source_map_charm_id,source_march_id,bonus_pct FROM player_charms_active WHERE player_id=1 AND world_id=1 AND stat_category='march_speed'")->fetch();
    charmCheck((int)$chronological['source_map_charm_id']===$chronologicalNew&&(int)$chronological['source_march_id']===$newMarch&&(float)$chronological['bonus_pct']===3.0,'reverse lazy ticks cannot overwrite a chronologically newer same-category effect');

    $lateProcessed=charmMap(1,22,20,'carry','normal',3,1800,'-5 SECOND');$beforeExpiry=collector(2,2,1,$lateProcessed,22,20,10);
    charmCheck(CharmCollectionService::resolveDue(1,$lateProcessed)['winner_march_id']===$beforeExpiry,'arrival before expiry remains eligible when settlement runs late');
    $expired=charmMap(1,23,20,'gathering','normal',3,1800,'-5 SECOND');$afterExpiry=collector(2,2,1,$expired,23,20,1);
    CharmCollectionService::resolveDue(1,$expired);
    charmCheck($db->query('SELECT collected_by FROM map_charms WHERE id=?',[$expired])->fetchColumn()===null&&$db->query('SELECT state FROM marches WHERE id=?',[$afterExpiry])->fetchColumn()==='returning','arrival after expiry returns troops without activating the charm');

    $db->execute("UPDATE marches SET state='complete'");
    $dispatchCharm=charmMap(1,11,10,'construction');$before=(int)$db->query('SELECT count FROM city_troops WHERE city_id=1 AND troop_code=50100101')->fetchColumn();
    $request='retry_charm_00001';$dispatchA=MarchDispatcher::dispatchCharm(1,1,10,10,11,10,$dispatchCharm,[50100101=>7],$request);$dispatchB=MarchDispatcher::dispatchCharm(1,1,10,10,11,10,$dispatchCharm,[50100101=>7],$request);
    charmCheck($dispatchA===$dispatchB&&(int)$db->query('SELECT COUNT(*) FROM marches WHERE request_id=?',[$request])->fetchColumn()===1&&(int)$db->query('SELECT count FROM city_troops WHERE city_id=1 AND troop_code=50100101')->fetchColumn()===$before-7,'dispatch retry reuses its receipt and reserves troops once');
    charmReject(fn()=>MarchDispatcher::dispatchCharm(1,1,10,10,11,10,$dispatchCharm,[50100101=>8],$request),'REQUEST_ID_CONFLICT','request id cannot be reused with another payload');

    $db->execute("UPDATE marches SET state='complete'");$db->execute("INSERT INTO field_monsters(world_id,monster_code,coord_x,coord_y,hp_current,monster_type,expires_at) VALUES(1,20209901,12,10,1,'solo',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR))");
    $apBefore=(int)$db->query('SELECT action_points FROM players WHERE id=1')->fetchColumn();$monsterRequest='retry_monster_001';$monsterA=MarchDispatcher::dispatchMonster(1,1,10,10,12,10,[50100101=>500],$monsterRequest);$monsterB=MarchDispatcher::dispatchMonster(1,1,10,10,12,10,[50100101=>500],$monsterRequest);
    $snapshot=json_decode((string)$db->query('SELECT encounter_snapshot_json FROM marches WHERE id=?',[$monsterA])->fetchColumn(),true);
    charmCheck($monsterA===$monsterB&&($snapshot['definition']['spawn_code']??null)===20209901,'solo dispatch retry is idempotent and stores the resolved encounter snapshot');
    charmCheck((int)$db->query('SELECT action_points FROM players WHERE id=1')->fetchColumn()===$apBefore-(int)$snapshot['definition']['action_point_cost'],'solo dispatch charges catalog AP exactly once');
    $db->execute('UPDATE marches SET departure_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 20 SECOND),arrival_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 10 SECOND) WHERE id=?',[$monsterA]);MarchTick::runForPlayer(1);
    $solo=$db->query('SELECT state,haul_json FROM marches WHERE id=?',[$monsterA])->fetch();$soloHaul=json_decode((string)$solo['haul_json'],true);
    charmCheck(in_array($solo['state'],['returning','complete'],true)&&!$db->query('SELECT id FROM field_monsters WHERE coord_x=12 AND coord_y=10')->fetch()&&isset($soloHaul['loot'],$soloHaul['items']),'solo kill keeps direct loot and items in the returning haul');
    $soloReport=json_decode((string)$db->query('SELECT data_json FROM battle_reports WHERE march_id=?',[$monsterA])->fetchColumn(),true);$soloCharmId=(int)$db->query("SELECT charm_id FROM monster_kill_receipts WHERE source_kind='solo' AND source_id=?",[$monsterA])->fetchColumn();
    charmCheck((int)$soloReport['charm']['id']===$soloCharmId&&$soloReport['charm']['x']===12&&$soloReport['charm']['y']===10&&$soloReport['charm']['ownership']===null,'solo battle report identifies the guaranteed public charm');
    $soloReceiptCount=(int)$db->query("SELECT COUNT(*) FROM monster_kill_receipts WHERE source_kind='solo' AND source_id=?",[$monsterA])->fetchColumn();MarchTick::runForPlayer(1);
    charmCheck($soloReceiptCount===1&&(int)$db->query("SELECT COUNT(*) FROM monster_kill_receipts WHERE source_kind='solo' AND source_id=?",[$monsterA])->fetchColumn()===1,'repeated solo tick cannot duplicate its kill receipt or charm');

    $worldTwo=charmMap(2,30,30,'research');$worldOne=CharmQueryService::inBounds(1,1,30,30,2);$worldTwoRows=CharmQueryService::inBounds(2,1,30,30,2);
    charmCheck(count($worldOne)===0&&count($worldTwoRows)===1&&$worldTwoRows[0]['id']===$worldTwo,'viewport DTO cannot leak charms across worlds');
    charmCheck(array_keys($worldTwoRows[0])===['id','world_id','x','y','stat_category','grade','charm_code','bonus_pct','effect_duration_seconds','spawned_at','expires_at','ownership','collectible'],'query DTO remains explicit and stable');
    $db->execute("INSERT INTO player_charms_active(player_id,world_id,stat_category,grade,charm_code,source_map_charm_id,bonus_pct,expires_at) VALUES(1,2,'troops_hp','legendary',10700009,999999,25,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR))");
    $worldOneBuffs=\Conquer\Game\Research\BuffEngine::getBuffs(1,1);$worldTwoBuffs=\Conquer\Game\Research\BuffEngine::getBuffs(1,2);
    charmCheck(abs((float)($worldOneBuffs['troops_hp']??0)-0.125)<0.0001&&abs((float)($worldTwoBuffs['troops_hp']??0)-0.25)<0.0001,'active charm bonuses are isolated by world');

    $db->execute("UPDATE marches SET state='returning',return_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id IN (?,?)",[$earlier,$later]);
    $troopsBefore=(int)$db->query('SELECT count FROM city_troops WHERE city_id=1 AND troop_code=50100101')->fetchColumn();MarchTick::runForPlayer(1);$once=(int)$db->query('SELECT count FROM city_troops WHERE city_id=1 AND troop_code=50100101')->fetchColumn();MarchTick::runForPlayer(1);
    charmCheck($once===$troopsBefore+10&&(int)$db->query('SELECT count FROM city_troops WHERE city_id=1 AND troop_code=50100101')->fetchColumn()===$once,'collector homecoming returns its troop payload exactly once');
    echo "ALL CHARM LIFECYCLE CHECKS PASSED\n";
}finally{$fixture->close();}
