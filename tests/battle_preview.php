<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit(1);
define('ROOT_DIR',dirname(__DIR__));
require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';date_default_timezone_set('UTC');
use Conquer\Db\Connection;
use Conquer\Game\March\{BattlePreview,BattleEngine,PvpRules};
use Conquer\Game\World\WorldContext;
use Conquer\Game\Map\MonsterData;
use Conquer\Game\Research\BuffEngine;
function checkPreview(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
function rejectPreview(callable $fn,string $label):void{try{$fn();}catch(DomainException|RuntimeException $e){if($e instanceof PDOException)throw $e;checkPreview(true,$label);return;}throw new RuntimeException('Accepted: '.$label);}
$fixture=new \ConquerTests\FeatureDatabase();register_shutdown_function(static fn()=>$fixture->close());
$db=Connection::getInstance();WorldContext::bind(1);
$db->execute("UPDATE worlds SET status='running',speed_factor=1 WHERE id=1");
$db->execute("INSERT INTO players(id,username,email,password_hash) VALUES(1,'Calculator','calculator@tests.invalid','unused')");
$db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y) VALUES(1,1,1,'Calculator',65,65)");
foreach(\Conquer\Game\City\CityState::BUILDING_CODES as $code)$db->execute('INSERT INTO city_buildings(city_id,building_code,level) VALUES(1,?,1)',[$code]);
$db->execute('INSERT INTO city_troops(city_id,troop_code,count) VALUES(1,50100101,5000),(1,50200101,5000),(1,50300101,5000)');
$db->execute("INSERT INTO field_monsters(id,world_id,monster_code,coord_x,coord_y,hp_current,monster_type,expires_at) VALUES(1,1,20209901,70,65,10,'solo',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY))");
$base=['kind'=>'monsters','target_id'=>1,'target_x'=>70,'target_y'=>65,'troops'=>[50100101=>10]];
$snapshot=static fn()=>[$db->query('SELECT troop_code,count FROM city_troops ORDER BY troop_code')->fetchAll(),$db->query('SELECT * FROM cities')->fetchAll(),$db->query('SELECT hp_current FROM field_monsters')->fetchAll(),$db->query('SELECT action_points FROM players')->fetchAll(),(int)$db->query('SELECT COUNT(*) FROM marches')->fetchColumn(),(int)$db->query('SELECT COUNT(*) FROM battle_reports')->fetchColumn()];
BuffEngine::getBuffs(1,1);$before=$snapshot();
foreach([1,8,10,100,5000] as $count){
    $body=array_replace($base,['troops'=>[50100101=>$count]]);
    $preview=BattlePreview::calculate(1,$body);
    $real=BattleEngine::resolveMonster($body['troops'],['monster_code'=>20209901,'hp_current'=>10],MonsterData::get(20209901),BuffEngine::getBuffs(1,1));
    checkPreview($preview['outcome']===$real['outcome']&&$preview['monster_hp_after']===$real['new_monster_hp']&&$preview['attacker']['wounded']===array_sum($real['attacker_losses']),'preview matches actual monster engine for '.$count.' troops');
    checkPreview(array_sum($preview['attacker'])===$count,'preview conserves every troop');
}
checkPreview($snapshot()===$before,'calculations never spend, dispatch, damage or create reports');
foreach([['troops'=>[]],['troops'=>[50100101=>1.5]],['troops'=>[50100101=>-1]],['troops'=>[50100101=>5001]],['troops'=>[999=>1]],['target_x'=>'70'],['target_x'=>-1],['target_x'=>1024],['target_id'=>2],['kind'=>'monster-rally'],['kind'=>'unsupported']] as $change)rejectPreview(fn()=>BattlePreview::calculate(1,array_replace($base,$change)),'invalid preview rejected '.json_encode($change));
$db->execute('UPDATE field_monsters SET expires_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id=1');
rejectPreview(fn()=>BattlePreview::calculate(1,$base),'expired target rejected');
$db->execute("INSERT INTO worlds(id,name,slug,status,map_size) VALUES(2,'Other','preview-other','running',256)");
WorldContext::bind(2);rejectPreview(fn()=>BattlePreview::calculate(1,$base),'cross-world city and target rejected');WorldContext::bind(1);
$pvp=array_replace($base,['kind'=>'players','troops'=>[50100101=>100],'defender_troops'=>[50100101=>100],'wall_bonus'=>0,'defender_bonus'=>0]);
$equal=BattlePreview::calculate(1,$pvp);checkPreview($equal['outcome']==='defender_wins','equal armies include 10 percent city defense advantage');
$win=BattlePreview::calculate(1,array_replace($pvp,['troops'=>[50100101=>1000]]));checkPreview($win['outcome']==='attacker_wins'&&$win['attacker']===['survivors'=>900,'wounded'=>30,'dead'=>70],'PvP calculator uses actual city casualty rules');
$buffed=BattlePreview::calculate(1,array_replace($pvp,['defender_bonus'=>100,'wall_bonus'=>50]));checkPreview(abs($buffed['defender_score']-$equal['defender_score']*3)<=2,'assumed buffs and wall apply once');
foreach([['defender_bonus'=>-1],['wall_bonus'=>1001],['wall_bonus'=>'20'],['defender_troops'=>[50100101=>500001]]] as $change)rejectPreview(fn()=>BattlePreview::calculate(1,array_replace($pvp,$change)),'invalid hypothetical input rejected');
checkPreview(!PvpRules::attackerWins(100,100),'ties favor the defender');
foreach([1,2,3,7,10,101,50000] as $count)foreach([.1,.3] as $rate)checkPreview(array_sum(array_map('array_sum',PvpRules::losses([50100101=>$count],$rate)))===$count,'PvP rounding conserves troops');
checkPreview($snapshot()===$before,'PvP also leaves armies, resources and reports untouched');
echo "PASS battle calculator rules, world isolation and side-effect checks\n";
