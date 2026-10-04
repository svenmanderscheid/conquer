<?php
declare(strict_types=1);
define('ROOT_DIR',dirname(__DIR__));
require ROOT_DIR.'/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';
use Conquer\Db\Connection;
use Conquer\Game\Rally\{MonsterRally,RallyService,RallySupport};
use Conquer\Game\World\WorldContext;
use Conquer\Game\City\CityState;
date_default_timezone_set('UTC');
\Conquer\Logger::init(sys_get_temp_dir().'/uok-support-test.log','ERROR');
function supportCheck(bool $ok,string $name):void {if(!$ok)throw new RuntimeException($name);echo "PASS $name\n";}
$fixture=null;
try {
    $fixture=new \ConquerTests\FeatureDatabase();$db=Connection::getInstance();WorldContext::bind(1);
    foreach(range(1,12) as $id){
        $db->execute("INSERT INTO players(id,username,email,password_hash,last_login,action_points,last_ap_regen) VALUES(?,?,?,'unused',NULL,200,UTC_TIMESTAMP())",[$id,'Support'.$id,'support'.$id.'@tests.invalid']);
        $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,food,lumber,stone,gold) VALUES(?,?,1,'Support city',?,40,100000,100000,100000,100000)",[$id,$id,20+$id]);
        foreach(CityState::BUILDING_CODES as $code)$db->execute('INSERT INTO city_buildings(city_id,building_code,level) VALUES(?,?,1)',[$id,$code]);
        $db->execute('INSERT INTO city_troops(city_id,troop_code,count) VALUES(?,50100101,10000)',[$id]);
    }
    $db->execute("INSERT INTO alliances(id,world_id,name,tag,leader_id) VALUES(1,1,'Support testers','SUP',1)");
    $db->execute("INSERT INTO alliance_members(alliance_id,player_id,world_id,role) VALUES(1,1,1,'leader'),(1,2,1,'member'),(1,3,1,'member'),(1,4,1,'member')");
    $def=\Conquer\Game\Map\MonsterData::get(20202401);
    $db->execute("INSERT INTO field_monsters(world_id,monster_code,coord_x,coord_y,hp_current,monster_type,expires_at) VALUES(1,20202401,70,60,?,'rally',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY))",[(int)($def['amount']*$def['stats']['hp'])]);
    $id=MonsterRally::start(1,1,70,60,[50100101=>100],5,'');
    $row=fn()=>$db->query('SELECT * FROM rallies WHERE id=?',[$id])->fetch();
    $ai=fn()=>array_values(array_filter(RallyService::getParticipants($id,false),fn($p)=>!empty($p['is_ai'])));
    RallyService::tick();supportCheck($ai()===[],'humans have the first 45 seconds');
    $db->execute('UPDATE rallies SET created_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 46 SECOND) WHERE id=?',[$id]);
    RallyService::tick();supportCheck(count($ai())===1&&array_sum($ai()[0]['troops'])===100,'quiet monster rally receives marked support matching human tier and strength');
    $first=$row()['result_json'];RallyService::tick();supportCheck($row()['result_json']===$first,'repeated ticks do not duplicate support');
    $listed=RallyService::listForAlliance(1)[0];supportCheck((int)$listed['participant_count']===1&&$listed['participants'][0]['is_ai'],'alliance roster exposes virtual support');
    $meta=json_decode($row()['result_json'],true);$meta['capacity']=200;$db->execute('UPDATE rallies SET result_json=? WHERE id=?',[json_encode($meta),$id]);
    RallyService::join(2,2,$id,[50100101=>100]);supportCheck($ai()===[],'human join displaces AI even when displayed capacity was full');
    $meta=json_decode($row()['result_json'],true);$meta['capacity']=1000;$db->execute('UPDATE rallies SET result_json=? WHERE id=?',[json_encode($meta),$id]);
    $db->execute("UPDATE rally_participants SET arrival_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE rally_id=?",[$id]);RallyService::tick();
    supportCheck(array_sum($ai()[0]['troops'])===200,'arrived human reinforcements scale the ordinary troop composition');
    $db->execute('UPDATE players SET last_login=UTC_TIMESTAMP() WHERE id<=4');RallyService::tick();supportCheck($ai()===[],'active alliance population disables support');
    $db->execute('UPDATE players SET last_login=NULL');$db->execute('UPDATE players SET last_login=UTC_TIMESTAMP() WHERE id>=2');RallyService::tick();supportCheck($ai()===[],'active world population disables support');
    $db->execute('UPDATE players SET last_login=NULL');RallyService::tick();supportCheck(count($ai())===1,'support resumes while still gathering');
    $db->execute("UPDATE worlds SET status='paused' WHERE id=1");RallySupport::tick();supportCheck($ai()===[],'paused worlds receive no AI');$db->execute("UPDATE worlds SET status='running' WHERE id=1");
    $other=$row();$other['target_kind']='city';supportCheck(RallySupport::participants($other)===[],'AI is excluded from city and conquest rallies');
    RallyService::tick();RallyService::launch($id,1);supportCheck(count(array_filter(RallyService::armies($row()),fn($a)=>!empty($a['is_ai'])))===1,'support travels with the launched army');
    $db->execute('UPDATE rallies SET arrival_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND),return_time=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR) WHERE id=?',[$id]);RallyService::tick();
    $result=json_decode($row()['result_json'],true);supportCheck($row()['status']==='returning'&&count($result['armies'])===3,'normal battle resolver settles human and virtual casualties');
    supportCheck((int)$db->query('SELECT COUNT(*) FROM battle_reports WHERE attacker_id<0')->fetchColumn()===0,'AI creates no player report or progression');
    $virtual=array_values(array_filter($result['armies'],fn($a)=>!empty($a['is_ai'])))[0];supportCheck($virtual['loot']===[]&&$virtual['items']===[],'AI gets no resources or items');
    $db->execute('UPDATE rallies SET return_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id=?',[$id]);RallyService::tick();
    supportCheck($row()['status']==='complete'&&(int)$db->query('SELECT COUNT(*) FROM city_troops WHERE city_id=0')->fetchColumn()===0,'virtual troops cannot become player stock on return');
    $stocks=$db->query('SELECT city_id,count FROM city_troops ORDER BY city_id')->fetchAll();RallyService::tick();supportCheck($db->query('SELECT city_id,count FROM city_troops ORDER BY city_id')->fetchAll()===$stocks,'settlement remains idempotent');
    $cancel=MonsterRally::start(1,1,70,60,[50100101=>10],1,'');$db->execute('UPDATE rallies SET created_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 46 SECOND) WHERE id=?',[$cancel]);RallyService::tick();RallyService::cancel($cancel,1);
    supportCheck((int)$db->query('SELECT COUNT(*) FROM city_troops WHERE city_id=0')->fetchColumn()===0,'cancellation refunds only real armies');
    $db->execute('UPDATE field_monsters SET hp_current=1 WHERE world_id=1 AND coord_x=70 AND coord_y=60');
    $win=MonsterRally::start(1,1,70,60,[50100101=>100],1,'');
    $db->execute('UPDATE rallies SET created_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 46 SECOND) WHERE id=?',[$win]);
    RallyService::tick();RallyService::launch($win,1);
    $db->execute('UPDATE rallies SET arrival_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND),return_time=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR) WHERE id=?',[$win]);RallyService::tick();
    $won=json_decode($db->query('SELECT result_json FROM rallies WHERE id=?',[$win])->fetchColumn(),true);
    supportCheck($won['monster_killed']&&count($won['armies'])===2,'successful monster kill includes AI under normal battle rules');
    $receipt=json_decode($db->query("SELECT reward_snapshot_json FROM monster_kill_receipts WHERE source_kind='rally' AND source_id=?",[$win])->fetchColumn(),true);
    supportCheck(count($receipt['resources_by_army'])===1&&!isset($receipt['resources_by_army'][1])&&!isset($receipt['xp_by_army'][1])&&!isset($receipt['items_by_army'][1]),'durable reward receipt contains grants only for humans');
    $reports=$db->query("SELECT attacker_id,data_json FROM battle_reports WHERE JSON_UNQUOTE(JSON_EXTRACT(data_json,'$.rally_id'))=?",[$win])->fetchAll();
    supportCheck(count($reports)===1&&(int)$reports[0]['attacker_id']===1&&json_decode($reports[0]['data_json'],true)['loot']!==[],'only the participating human receives the winning report and loot');
    supportCheck($won['armies'][1]['loot']===[]&&$won['armies'][1]['items']===[],'winning AI receives no loot or drops');
} finally {$fixture?->close();}
