<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';date_default_timezone_set('UTC');
use Conquer\Db\Connection;
use Conquer\Game\City\TroopData;
use Conquer\Game\March\{BattleEngine,BattleLuck,BattleReportService,MarchDispatcher,MarchTick};
use Conquer\Game\Research\BuffEngine;
use Conquer\Game\World\WorldContext;
function mrCheck(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
$troops=[50100101=>100,50200101=>80,50300101=>60];
$reportBuffs=['troops_atk'=>.2,'infantry_atk'=>.3,'vs_monster_attack'=>.1];
$expectedAttack=0.0;$expectedPower=0.0;
foreach($troops as $code=>$count){
 $unit=TroopData::get($code);$type=[1=>'infantry',2=>'ranged',3=>'cavalry'][$unit['type']];
 $attack=BuffEngine::effectiveMultiplier($reportBuffs,$type,'atk')*(1+$reportBuffs['vs_monster_attack']);
 $expectedAttack+=$count*$unit['attack']*$attack;
 $expectedPower+=$count*$unit['power']*($attack+BuffEngine::effectiveMultiplier($reportBuffs,$type,'def')+BuffEngine::effectiveMultiplier($reportBuffs,$type,'hp'))/3;
}
// This scenario must win even on the weakest server roll with current troop data.
$definition=['name'=>'Orc','level'=>3,'stats'=>['attack'=>50,'defense'=>30,'hp'=>100],'amount'=>10,'required_power'=>(int)floor($expectedPower*BattleLuck::factor(-10))];
$win=BattleEngine::resolveMonster($troops,['hp_current'=>1000],$definition,$reportBuffs);
mrCheck($win['monster_killed']&&round($win['report']['combat_snapshot']['attack'])===$win['report']['attacker_damage'],'snapshot attack agrees with resolved damage');
mrCheck(abs($win['report']['combat_snapshot']['attack']-$expectedAttack)<.000001&&$win['report']['army_power_before_luck']===round($expectedPower),'reported attack and base power match current troop data and applied bonuses');
mrCheck($win['report']['army_power']>=$win['report']['required_power']&&$win['report']['power_ratio']>=1,'power threshold decides the victory');
mrCheck(abs($win['report']['combat_snapshot']['bonuses']['infantry']['atk']-65)<.00001,'general, infantry and multiplicative monster bonuses are all captured');
mrCheck($win['report']['combat_snapshot']['combat_modifiers']['vs_monster_attack']===10.0,'monster attack modifier is captured separately for the report');
mrCheck($win['report']['combat_snapshot']['bonuses']['ranged']['def']===0.0,'zero troop bonuses are captured explicitly');
mrCheck($win['report']['monster_snapshot']['count']===10&&$win['report']['monster_snapshot']['defense']===300.0,'monster counts and defense use the actual pre-battle pools');
$loss=BattleEngine::resolveMonster([50100101=>10],['hp_current'=>1000],$definition);
mrCheck(!$loss['monster_killed']&&array_sum($loss['attacker_losses'])>0&&array_sum($loss['attacker_survivors'])+array_sum($loss['attacker_losses'])===10,'defeat preserves troop accounting and partial monster HP');
mrCheck(array_sum($loss['attacker_losses'])<=2,'an early underpowered defeat wounds only a small share');
$rally=BattleEngine::resolveMonsterArmies([['troops'=>[50100101=>100],'buffs'=>['troops_atk'=>1]],['troops'=>[50100101=>100],'buffs'=>[]]],['hp_current'=>1000],$definition);
mrCheck($rally['armies'][0]['combat_snapshot']['attack']===2*$rally['armies'][1]['combat_snapshot']['attack'],'rally snapshots retain each owner bonuses');
mrCheck(abs(array_sum(array_column(array_column($rally['armies'],'combat_snapshot'),'attack'))-$rally['report']['combat_snapshot']['attack'])<.000001,'rally total equals the sum of its participants');
$fixture=new \ConquerTests\FeatureDatabase();$db=Connection::getInstance();\Conquer\Logger::init(sys_get_temp_dir().'/conquer-monster-reports.log','ERROR');
try {
 $db->execute("UPDATE worlds SET status='running' WHERE id=1");
 $db->execute("INSERT INTO players(id,username,email,password_hash,action_points,last_ap_regen) VALUES(1,'ReportTester','report@tests.invalid','unused',200,UTC_TIMESTAMP()),(2,'OtherReportTester','other@tests.invalid','unused',200,UTC_TIMESTAMP())");
 foreach([1,2] as $pid){
  $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,food,lumber,stone,gold) VALUES(?,?,1,'Report city',?,65,100000,100000,100000,100000)",[$pid,$pid,60+$pid*5]);
  foreach(\Conquer\Game\City\CityState::BUILDING_CODES as $code)$db->execute('INSERT INTO city_buildings(city_id,building_code,level) VALUES(?,?,1)',[$pid,$code]);
 }
 $db->execute('INSERT INTO city_troops(city_id,troop_code,count) VALUES(1,50100101,1000)');
 $db->execute("INSERT INTO field_monsters(world_id,monster_code,coord_x,coord_y,hp_current,monster_type) VALUES(1,20209901,75,65,1,'solo')");
 MarchDispatcher::dispatchMonster(1,1,65,65,75,65,[50100101=>100]);
 $march=(int)$db->query('SELECT MAX(id) FROM marches')->fetchColumn();
 $db->execute('UPDATE marches SET departure_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 61 SECOND),arrival_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id=?',[$march]);
 MarchTick::runForPlayer(1);
 $report=BattleReportService::list(1)[0]??null;
 mrCheck($report!==null&&$report['reward_delivery']==='returning','real solo resolution creates a report with returning haul');
 mrCheck($report['details']['source_snapshot']['identity']['name']==='ReportTester'&&$report['details']['source_snapshot']['hunter']['level']===1,'identity and Hunter level are captured before reward XP');
 $id=(int)$report['id'];$snapshot=$report['details'];
 mrCheck(BattleReportService::get(2,$id)===null&&!BattleReportService::delete(2,$id),'foreign report read and delete are denied');
 $db->execute("INSERT INTO worlds(id,name,slug,status,map_size) VALUES(2,'Report world','report-world','running',256)");WorldContext::bind(2);
 mrCheck(BattleReportService::get(1,$id)===null&&!BattleReportService::delete(1,$id)&&BattleReportService::list(1)===[],'cross-world report read/list/delete are denied');WorldContext::bind(1);
 $db->execute("UPDATE players SET username='Renamed' WHERE id=1");
 mrCheck(BattleReportService::get(1,$id)['details']===$snapshot,'viewing an old fight never replaces its historical values');
 $before=(int)$db->query('SELECT food FROM cities WHERE id=1')->fetchColumn();
 $itemBefore=(int)$db->query('SELECT COALESCE(SUM(quantity),0) FROM player_inventory WHERE player_id=1 AND item_code=10203022')->fetchColumn();
 $db->execute('UPDATE marches SET return_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id=?',[$march]);MarchTick::runForPlayer(1);
 $after=(int)$db->query('SELECT food FROM cities WHERE id=1')->fetchColumn();
 mrCheck(BattleReportService::get(1,$id)['reward_delivery']==='delivered'&&$after===$before&&(int)$db->query('SELECT COALESCE(SUM(quantity),0) FROM player_inventory WHERE player_id=1 AND item_code=10203022')->fetchColumn()===$itemBefore+1,'real homecoming changes the report status and delivers the guaranteed item');
 MarchTick::runForPlayer(1);mrCheck((int)$db->query('SELECT food FROM cities WHERE id=1')->fetchColumn()===$after&&(int)$db->query('SELECT COALESCE(SUM(quantity),0) FROM player_inventory WHERE player_id=1 AND item_code=10203022')->fetchColumn()===$itemBefore+1,'repeated homecoming cannot duplicate resources or items');
 mrCheck(BattleReportService::delete(1,$id)&&BattleReportService::delete(1,$id),'report deletion is idempotent');
 mrCheck(BattleReportService::get(1,$id)===null&&BattleReportService::list(1)===[],'hidden reports disappear from list and direct access');
 mrCheck((int)$db->query('SELECT COUNT(*) FROM battle_reports WHERE id=?',[$id])->fetchColumn()===1&&(int)$db->query('SELECT food FROM cities WHERE id=1')->fetchColumn()===$after,'deleting mail keeps the battle record and payout intact');
 $db->execute("INSERT INTO field_monsters(world_id,monster_code,coord_x,coord_y,hp_current,monster_type) VALUES(1,20209901,77,65,1,'solo')");
 MarchDispatcher::dispatchMonster(1,1,65,65,77,65,[50100101=>100]);
 $march=(int)$db->query('SELECT MAX(id) FROM marches')->fetchColumn();
 $db->execute('UPDATE marches SET departure_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 61 SECOND),arrival_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id=?',[$march]);MarchTick::runForPlayer(1);
 $pending=BattleReportService::list(1)[0];BattleReportService::delete(1,(int)$pending['id']);
 $before=(int)$db->query('SELECT food FROM cities WHERE id=1')->fetchColumn();
 $itemBefore=(int)$db->query('SELECT COALESCE(SUM(quantity),0) FROM player_inventory WHERE player_id=1 AND item_code=10203022')->fetchColumn();
 $db->execute('UPDATE marches SET return_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id=?',[$march]);MarchTick::runForPlayer(1);
 mrCheck((int)$db->query('SELECT food FROM cities WHERE id=1')->fetchColumn()===$before&&(int)$db->query('SELECT COALESCE(SUM(quantity),0) FROM player_inventory WHERE player_id=1 AND item_code=10203022')->fetchColumn()===$itemBefore+1&&BattleReportService::list(1)===[],'deleting a returning report does not cancel its eventual payout');
 $definition=\Conquer\Game\Map\MonsterData::get(20209901);
 $maximum=(int)round($definition['stats']['hp']*$definition['amount']);
 $db->execute("INSERT INTO field_monsters(world_id,monster_code,coord_x,coord_y,hp_current,monster_type) VALUES(1,20209901,79,65,?,'solo')",[$maximum]);
 $monsterId=$db->lastInsertId();$previousHp=$maximum;
 for($attempt=1;$attempt<=2;$attempt++){
  MarchDispatcher::dispatchMonster(1,1,65,65,79,65,[50100101=>1]);
  $march=(int)$db->query('SELECT MAX(id) FROM marches')->fetchColumn();
  $db->execute('UPDATE marches SET departure_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 61 SECOND),arrival_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id=?',[$march]);MarchTick::runForPlayer(1);
  $monster=$db->query('SELECT * FROM field_monsters WHERE id=?',[$monsterId])->fetch();
  mrCheck($monster!==false,'unsuccessful attack leaves the monster on the map');
  $health=\Conquer\Game\Map\MonsterData::mapData($monster);
  mrCheck((int)$health['hp_current']>0&&(int)$health['hp_current']<$previousHp&&$health['hp_max']===$maximum,'remaining HP decrease while full HP stay fixed after attack '.$attempt);
  $previousHp=(int)$health['hp_current'];
  $failed=BattleReportService::list(1)[0];
  $haul=json_decode($db->query('SELECT haul_json FROM marches WHERE id=?',[$march])->fetchColumn(),true);
  mrCheck($failed['outcome']==='defender_wins'&&$failed['reward_delivery']==='returning'&&($haul['items'][10203022]??0)===1&&$failed['details']['items']===$haul['items']&&$failed['details']['loot']===$haul['loot'],'unsuccessful attack '.$attempt.' reports real guaranteed loot in its returning haul');
  mrCheck(($failed['details']['lord_xp']??0)===0&&(int)$db->query('SELECT COUNT(*) FROM monster_kill_receipts WHERE field_monster_id=?',[$monsterId])->fetchColumn()===0,'unsuccessful attack grants no kill XP or kill receipt');
  $itemBefore=(int)$db->query('SELECT COALESCE(SUM(quantity),0) FROM player_inventory WHERE player_id=1 AND item_code=10203022')->fetchColumn();
  MarchTick::runForPlayer(1);
  mrCheck(json_decode($db->query('SELECT haul_json FROM marches WHERE id=?',[$march])->fetchColumn(),true)===$haul&&(int)$db->query('SELECT COALESCE(SUM(quantity),0) FROM player_inventory WHERE player_id=1 AND item_code=10203022')->fetchColumn()===$itemBefore,'pending defeated army neither rerolls nor pays loot early');
  $db->execute('UPDATE marches SET return_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id=?',[$march]);MarchTick::runForPlayer(1);MarchTick::runForPlayer(1);
  mrCheck(BattleReportService::get(1,(int)$failed['id'])['reward_delivery']==='delivered'&&(int)$db->query('SELECT COALESCE(SUM(quantity),0) FROM player_inventory WHERE player_id=1 AND item_code=10203022')->fetchColumn()===$itemBefore+1,'defeated army delivers guaranteed items exactly once on return');
  mrCheck((int)$db->query("SELECT COUNT(*) FROM reward_grant_ledger WHERE player_id=1 AND item_code=10203022 AND source_reference=? AND rule_revision LIKE 'sha256:%'",['march:'.$march])->fetchColumn()===1,'defeated army loot retains its frozen reward rule in the grant ledger');
 }
 $emptyRewards=\Conquer\Game\Rewards\RewardCatalog::defaults('monster','20209901');
 $emptyRewards['resource_reward']=['food'=>0,'lumber'=>0,'stone'=>0,'gold'=>0];
 $emptyRewards['drops']=[];$emptyRewards['fragment_drops']=[];$emptyRewards['relic_drops']=[];$emptyRewards['gems_drop']=['chance'=>0,'amount'=>0];
 $db->execute("INSERT INTO reward_world_overrides(world_id,source_type,source_key,config_json) VALUES(1,'monster','20209901',?)",[json_encode($emptyRewards)]);
 \Conquer\Game\Rewards\RewardCatalog::resetCache();
 $db->execute("INSERT INTO field_monsters(world_id,monster_code,coord_x,coord_y,hp_current,monster_type) VALUES(1,20209901,81,65,?,'solo')",[$maximum]);
 MarchDispatcher::dispatchMonster(1,1,65,65,81,65,[50100101=>1]);
 $march=(int)$db->query('SELECT MAX(id) FROM marches')->fetchColumn();
 $db->execute('UPDATE marches SET departure_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 61 SECOND),arrival_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id=?',[$march]);MarchTick::runForPlayer(1);
 $fallbackReport=BattleReportService::list(1)[0];
 mrCheck($fallbackReport['outcome']==='defender_wins'&&$fallbackReport['details']['loot']===['food'=>100,'lumber'=>100,'stone'=>50,'gold'=>50]&&$fallbackReport['details']['items']===[],'defeated encounter with an empty world override receives real fallback resources');
 $before=$db->query('SELECT food,lumber,stone,gold FROM cities WHERE id=1')->fetch();
 $db->execute('UPDATE marches SET return_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id=?',[$march]);MarchTick::runForPlayer(1);MarchTick::runForPlayer(1);
 $after=$db->query('SELECT food,lumber,stone,gold FROM cities WHERE id=1')->fetch();
 foreach($fallbackReport['details']['loot'] as $resource=>$quantity)mrCheck((int)$after[$resource]===(int)$before[$resource]+$quantity,'fallback '.$resource.' arrives exactly once');
 mrCheck((int)$db->query("SELECT COUNT(*) FROM reward_grant_ledger WHERE reward_kind='resource' AND source_reference=?",['march:'.$march])->fetchColumn()===4,'fallback resources receive confirmed payout receipts');
 echo "ALL MONSTER REPORT CHECKS PASSED\n";
} finally {$fixture->close();}
