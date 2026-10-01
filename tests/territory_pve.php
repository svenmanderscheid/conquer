<?php
declare(strict_types=1);
/** Actual Luxembourg dispatch/combat/gather/return paths, using only disposable synthetic state. */
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));date_default_timezone_set('UTC');require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();require __DIR__.'/Support/FeatureDatabase.php';
use Conquer\Db\Connection;
use Conquer\Game\World\{WorldContext,WorldMapProfile,LuxembourgGeography};
use Conquer\Game\Territory\{TerritoryService,TerritoryEconomy};
use Conquer\Game\Map\{WorldPlacement,MonsterData};
use Conquer\Game\March\{MarchDispatcher,MarchTick,MarchSpeed,GatherService};
use Conquer\Game\City\{CityState,TroopData};
use Conquer\Game\Research\BuffEngine;

function pveCheck(bool $condition,string $label):void{if(!$condition)throw new RuntimeException($label);echo "PASS $label\n";}
function pveMarch(int $id):array{return Connection::getInstance()->query('SELECT * FROM marches WHERE id=?',[$id])->fetch();}
function pveResources():array{return array_map('intval',Connection::getInstance()->query('SELECT food,lumber,stone,gold FROM cities WHERE id=1')->fetch());}
function pveItems():array{return array_map('intval',Connection::getInstance()->query('SELECT item_code,quantity FROM player_inventory WHERE player_id=1 ORDER BY item_code')->fetchAll(PDO::FETCH_KEY_PAIR));}
function pveBank():array{$r=Connection::getInstance()->query('SELECT food,lumber,stone,gold FROM alliance_treasury WHERE alliance_id=1')->fetch();return $r?array_map('intval',$r):array_fill_keys(['food','lumber','stone','gold'],0);}
function pvePlace(string $kind,array $fort,int $shift):array{
    $db=Connection::getInstance();return $db->transaction(function()use($db,$kind,$fort,$shift):array{
        WorldPlacement::lockWorld($db,2);
        for($radius=0;$radius<=40;$radius++)for($dy=-$radius;$dy<=$radius;$dy++)foreach(abs($dy)===$radius?range(-$radius,$radius):[-$radius,$radius] as $dx){
            $x=(int)$fort['x']+$shift+$dx;$y=(int)$fort['y']+$dy;$geo=LuxembourgGeography::at($x,$y);
            if(($geo['canton_id']??null)===$fort['canton_id']&&WorldPlacement::canPlace($db,2,$kind,$x,$y))return [$x,$y];
        }
        throw new RuntimeException('No dry synthetic PvE placement.');
    });
}
$fixture=new \ConquerTests\FeatureDatabase();
try{
    $db=Connection::getInstance();$logPath=(new ReflectionProperty($fixture,'directory'))->getValue($fixture).'/http.log';\Conquer\Logger::init($logPath,'ERROR');
    $db->execute("INSERT INTO worlds(id,name,slug,status,map_size,speed_factor,gather_factor)VALUES(2,'Territory PvE fixture','territory-pve','running',768,1,1)");WorldMapProfile::configureEmptyWorld(2);WorldContext::bind(2);TerritoryService::ensureWorld(2);
    $fort=$db->query("SELECT * FROM territory_targets WHERE world_id=2 AND kind='canton' AND y>768 ORDER BY y DESC LIMIT 1")->fetch();pveCheck((bool)$fort,'fixture uses a real southern canton beyond the old map bounds');
    [$cx,$cy]=pvePlace('city',$fort,15);
    $db->execute("INSERT INTO players(id,username,email,password_hash,action_points,last_ap_regen)VALUES(1,'TerritoryPve','territory-pve@invalid.test','unused',200,UTC_TIMESTAMP())");
    $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,food,lumber,stone,gold,last_resource_update)VALUES(1,1,2,'Territory PvE city',?,?,100000,100000,100000,100000,UTC_TIMESTAMP())",[$cx,$cy]);
    foreach(CityState::BUILDING_CODES as $code)$db->execute('INSERT INTO city_buildings(city_id,building_code,level)VALUES(1,?,10)',[$code]);
    $code=(int)array_key_first(TroopData::all());$db->execute('INSERT INTO city_troops(city_id,troop_code,count)VALUES(1,?,10000)',[$code]);
    $db->execute("INSERT INTO alliances(id,world_id,name,tag,leader_id)VALUES(1,2,'Regional PvE alliance','PVE',1)");$db->execute("INSERT INTO alliance_members(world_id,alliance_id,player_id,role)VALUES(2,1,1,'leader')");
    $rules=TerritoryService::profile(2);$db->transaction(fn()=>TerritoryEconomy::transfer($fort,1,time()-3600,0,$rules,'fixture:regional-owner'));
    [$mx,$my]=pvePlace('monster',$fort,30);$monsterCode=20200101;$definition=MonsterData::get($monsterCode);pveCheck(MonsterData::isActive($monsterCode)&&($definition['type']??'solo')==='solo','real catalog provides an active solo encounter');
    $hp=(int)$definition['stats']['hp']*(int)$definition['amount'];$db->execute("INSERT INTO field_monsters(world_id,monster_code,coord_x,coord_y,hp_current,monster_type,expires_at)VALUES(2,?,?,?,?, 'solo',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY))",[$monsterCode,$mx,$my,$hp]);$monsterId=$db->lastInsertId();
    // This historical spawn code resolves to the level-one Orc, whose full
    // health pool requires 15,000 power. Send enough ordinary T1 troops to win
    // even at the lowest allowed battle luck, without altering encounter stats.
    $before=pveResources();$request='territory_real_monster_0001';$march=MarchDispatcher::dispatchMonster(1,1,$cx,$cy,$mx,$my,[$code=>6000],$request);$again=MarchDispatcher::dispatchMonster(1,1,$cx,$cy,$mx,$my,[$code=>6000],$request);$row=pveMarch($march);
    pveCheck($march===$again&&(int)$row['world_id']===2&&(int)$db->query('SELECT count FROM city_troops WHERE city_id=1 AND troop_code=?',[$code])->fetchColumn()===4000,'real Luxembourg monster dispatch reserves one army on retry');
    $duration=strtotime($row['arrival_time'].' UTC')-strtotime($row['departure_time'].' UTC');$speed=MarchSpeed::monster($code,BuffEngine::getBuffs(1,2));$expected=max(5,(int)floor(hypot($mx-$cx,$my-$cy)*100*.3/$speed));pveCheck($duration===$expected,'real monster ETA applies the rectangular map travel scale');
    // The first real kill also initializes the world land ledger. Pin the database clock
    // so a slow test machine cannot accidentally finish the short return during that work.
    $at=time();$db->execute('SET timestamp='.$at);
    $db->execute('UPDATE marches SET departure_time=?,arrival_time=? WHERE id=?',[gmdate('Y-m-d H:i:s',$at-$duration-1),gmdate('Y-m-d H:i:s',$at-1),$march]);
    WorldContext::bind(1);MarchTick::runForPlayer(1);pveCheck(WorldContext::id()===1,'background monster settlement uses saved world and restores caller world');WorldContext::bind(2);$row=pveMarch($march);$haul=json_decode($row['haul_json'],true,128,JSON_THROW_ON_ERROR);
    pveCheck($row['state']==='returning'&&!$db->query('SELECT id FROM field_monsters WHERE id=?',[$monsterId])->fetchColumn(),'actual monster battle defeats its target and starts a real return (state '.$row['state'].'; log '.(is_file($logPath)?trim(file_get_contents($logPath)):'empty').')');
    $report=$db->query('SELECT * FROM battle_reports WHERE march_id=? AND world_id=2',[$march])->fetch();pveCheck($report&&$report['outcome']==='attacker_wins','monster settlement writes its actual winning battle report');
    // The shipped level-one Orc grants five 1,000-food packs. Its regional 5% share
    // must therefore be 250 food even though its raw resource_reward is all zero.
    $expectedSupply=['food'=>250,'lumber'=>0,'stone'=>0,'gold'=>0];
    pveCheck(array_sum($expectedSupply)>0&&pveBank()===$expectedSupply,'actual regional monster kill credits the configured shared treasury supply'.(pveBank()===$expectedSupply?'':' (expected '.json_encode($expectedSupply).', actual '.json_encode(pveBank()).', report '.json_encode(json_decode($report['data_json'],true)['regional_supply']??null).')'));
    pveCheck((int)$db->query('SELECT COUNT(*) FROM territory_pve_ledger WHERE world_id=2 AND event_key=?',['monster-march:'.$march])->fetchColumn()===1,'real monster settlement persists exactly one regional reward receipt');
    MarchTick::runForPlayer(1);pveCheck(pveBank()===$expectedSupply&&(int)$db->query('SELECT COUNT(*) FROM battle_reports WHERE march_id=?',[$march])->fetchColumn()===1,'repeated arrival processing duplicates neither regional supply nor report');
    pveCheck(pveResources()===$before&&pveItems()===[],'monster loot remains on the army until the return actually arrives');
    $db->execute('UPDATE marches SET return_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id=?',[$march]);MarchTick::runForPlayer(1);$home=pveResources();$expectedHome=$before;foreach($expectedHome as $resource=>&$amount)$amount+=(int)($haul['loot'][$resource]??0);unset($amount);
    $expectedItems=array_map('intval',$haul['items']);ksort($expectedItems);
    pveCheck(pveMarch($march)['state']==='complete'&&$home===$expectedHome&&pveItems()===$expectedItems&&(int)($expectedItems[10201001]??0)===5&&(int)$db->query('SELECT count FROM city_troops WHERE city_id=1 AND troop_code=?',[$code])->fetchColumn()===4000+(int)$haul['survivors'][$code],'real monster return delivers exactly its surviving army and personal pack loot');
    $stock=(int)$db->query('SELECT count FROM city_troops WHERE city_id=1 AND troop_code=?',[$code])->fetchColumn();MarchTick::runForPlayer(1);pveCheck(pveResources()===$home&&pveItems()===$expectedItems&&pveBank()===$expectedSupply&&(int)$db->query('SELECT count FROM city_troops WHERE city_id=1 AND troop_code=?',[$code])->fetchColumn()===$stock,'repeated homecoming preserves troops, personal loot and shared reward');
    $db->execute('SET timestamp=0');

    [$gx,$gy]=pvePlace('resource',$fort,-20);$db->execute("INSERT INTO field_objects(world_id,coord_x,coord_y,object_type,level,resource_amount,resource_max,spawned_at,expires_at)VALUES(2,?,?,1,1,500,500,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY))",[$gx,$gy]);$node=$db->lastInsertId();$before=pveResources();
    $gatherTroops=(int)ceil(500/(float)TroopData::get($code)['carry']);
    $gather=GatherService::dispatch(1,1,$gx,$gy,selectedTroops:[$code=>$gatherTroops])['march_id'];$row=pveMarch($gather);$duration=strtotime($row['arrival_time'].' UTC')-strtotime($row['departure_time'].' UTC');$speed=GatherService::troopSpeed($code,BuffEngine::getBuffs(1,2,$gx,$gy));
    pveCheck((int)$row['world_id']===2&&$duration===max(5,(int)floor(hypot($gx-$cx,$gy-$cy)*100*.3/$speed))&&(int)$db->query('SELECT count FROM city_troops WHERE city_id=1 AND troop_code=?',[$code])->fetchColumn()===$stock-$gatherTroops,'real gathering dispatch reserves selected troops with Luxembourg ETA');
    $db->execute('UPDATE marches SET departure_time=?,arrival_time=? WHERE id=?',[gmdate('Y-m-d H:i:s',time()-$duration-1),gmdate('Y-m-d H:i:s',time()-1),$gather]);MarchTick::runForPlayer(1);$row=pveMarch($gather);
    pveCheck($row['state']==='arrived'&&(int)$db->query('SELECT gatherer_march_id FROM field_objects WHERE id=?',[$node])->fetchColumn()===$gather,'real scheduled gathering arrival occupies the resource field');
    $gatherData=json_decode($row['haul_json'],true);pveCheck((int)$gatherData['gather']['capacity']>=500&&strtotime($row['gathering_finishes_at'].' UTC')>time(),'real gather rate and army capacity determine future work completion');
    $shift=max(1,strtotime($row['gathering_finishes_at'].' UTC')-time()+$duration+2);$db->execute('UPDATE marches SET departure_time=DATE_SUB(departure_time,INTERVAL ? SECOND),arrival_time=DATE_SUB(arrival_time,INTERVAL ? SECOND),gathering_finishes_at=DATE_SUB(gathering_finishes_at,INTERVAL ? SECOND) WHERE id=?',[$shift,$shift,$shift,$gather]);
    WorldContext::bind(1);MarchTick::runForPlayer(1);pveCheck(WorldContext::id()===1,'offline gathering completion keeps the stored Luxembourg world');WorldContext::bind(2);$row=pveMarch($gather);$haul=json_decode($row['haul_json'],true);$home=pveResources();
    pveCheck($row['state']==='complete'&&(int)$haul['loot']['food']===500&&(int)$db->query('SELECT resource_amount FROM field_objects WHERE id=?',[$node])->fetchColumn()===0,'offline gather work depletes exactly the real field amount and returns home');
    pveCheck($home['food']===$before['food']+500&&$home['lumber']===$before['lumber']&&$home['stone']===$before['stone']&&$home['gold']===$before['gold']&&(int)$db->query('SELECT count FROM city_troops WHERE city_id=1 AND troop_code=?',[$code])->fetchColumn()===$stock,'gathering homecoming restores selected troops and credits only harvested food');
    MarchTick::runForPlayer(1);pveCheck(pveResources()===$home&&pveBank()===$expectedSupply&&(int)$db->query('SELECT COUNT(*) FROM territory_pve_ledger WHERE world_id=2')->fetchColumn()===1,'repeat gathering completion cannot duplicate resources or regional kill rewards');
    pveCheck(!is_file($logPath)||trim((string)file_get_contents($logPath))==='','actual PvE paths emit no hidden processing errors');
    echo "ALL TERRITORY PVE CHECKS PASSED\n";
}finally{Connection::getInstance()->execute('SET timestamp=0');$fixture->close();}
