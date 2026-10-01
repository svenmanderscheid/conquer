<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));
require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';
date_default_timezone_set('UTC');\Conquer\Logger::init(sys_get_temp_dir().'/conquer-rally-capacity-test.log','ERROR');

use Conquer\Db\Connection;
use Conquer\Game\City\CityState;
use Conquer\Game\Map\MonsterPower;
use Conquer\Game\Rally\{RallyCapacity,MonsterRally,RallyService};
use Conquer\Game\Research\{BuffEngine,ResearchEffects};
use Conquer\Game\Territory\TerritoryService;
use Conquer\Game\World\{WorldContext,WorldMapProfile};

$checks=0;$fixture=null;$exit=0;
function capCheck(bool $ok,string $message):void{global $checks;if(!$ok)throw new RuntimeException($message);$checks++;echo "PASS $message\n";}
function capReject(callable $fn,string $expected,string $message):void{
    try{$fn();}catch(DomainException|RuntimeException $e){if($e instanceof PDOException)throw $e;capCheck(str_contains($e->getMessage(),$expected),$message.' ('.$e->getMessage().')');return;}
    throw new RuntimeException('Allowed: '.$message);
}
function capMeta(int $rally):array{return json_decode(Connection::getInstance()->query('SELECT result_json FROM rallies WHERE id=?',[$rally])->fetchColumn(),true);}
function capStock(int $city):int{return (int)Connection::getInstance()->query('SELECT count FROM city_troops WHERE city_id=? AND troop_code=50100101',[$city])->fetchColumn();}
function capTerritory(int $player,string $action,array $body):array{return TerritoryService::action($player,['action'=>$action,'world_id'=>3,'expected_world_id'=>3,'request_id'=>bin2hex(random_bytes(16))]+$body,3);}

try{
    $expected=[50000,75000,100000,125000,150000,175000,200000,225000,250000,300000,350000,400000,450000,500000,550000,600000,700000,800000,900000,1000000,1100000,1200000,1300000,1400000,1500000,1600000,1700000,1800000,1900000,2500000];
    foreach($expected as $i=>$base){
        $level=$i+1;$view=RallyCapacity::describe($level,.4);
        capCheck(RallyCapacity::base($level)===$base&&$view['base']===$base&&$view['total']===(int)round($base*1.4),'Hall '.$level.' has its agreed base and exact +40% capacity');
        capCheck($view['next_hall_level']===($level<30?$level+1:null)&&$view['next_base']===($expected[$i+1]??null)&&$view['next_total']===($level<30?(int)round($expected[$i+1]*1.4):null),'Hall '.$level.' next-level preview matches the next capacity');
    }
    capCheck(RallyCapacity::describe(30,.4)['total']===3500000,'Hall 30 plus current research maximum holds exactly 3,500,000 troops');
    capCheck(RallyCapacity::base(0)===50000&&RallyCapacity::base(31)===2500000&&RallyCapacity::describe(1,-.1)['total']===50000,'level and negative-bonus boundaries preserve valid capacity');

    // These are the old NPC reference values, deliberately independent of the new Hall curve.
    $legacy=[1=>20000,6=>60000,10=>100000,13=>145000,16=>190000,20=>250000,23=>325000,26=>400000,30=>500000];$monsters=0;
    foreach(json_decode(file_get_contents(ROOT_DIR.'/data/monsters.json'),true)['monsters'] as $monster){
        $profile=MonsterPower::profile($monster);
        if($profile['rally']){if($profile['capacity']!==$legacy[$profile['castle']])throw new RuntimeException('Monster scaling changed: '.$monster['name'].' '.$monster['level']);$monsters++;}
    }
    capCheck($monsters>0,'all '.$monsters.' rally monster profiles keep their previous balance reference');

    $fixture=new \ConquerTests\FeatureDatabase();$db=Connection::getInstance();
    $db->execute("UPDATE worlds SET status='running' WHERE id=1");
    $db->execute("INSERT INTO worlds(id,name,slug,status,map_size)VALUES(2,'Other capacity world','capacity-other','running',256),(3,'Territory capacity world','capacity-territory','running',768)");
    WorldContext::bind(1);
    for($player=1;$player<=4;$player++){
        $db->execute("INSERT INTO players(id,username,email,password_hash,action_points,last_ap_regen)VALUES(?,?,?,'unused',200,UTC_TIMESTAMP())",[$player,'Capacity'.$player,'capacity'.$player.'@invalid.test']);
        $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y)VALUES(?,?,1,'Capacity city',?,40)",[$player,$player,20+$player*5]);
        foreach(CityState::BUILDING_CODES as $building)$db->execute('INSERT INTO city_buildings(city_id,building_code,level)VALUES(?,?,?)',[$player,$building,$building==='castle'?30:1]);
        $db->execute('INSERT INTO city_troops(city_id,troop_code,count)VALUES(?,50100101,100000)',[$player]);
        $db->execute("INSERT INTO player_research(player_id,world_id,research_code,level)VALUES(?,1,'march_size',5)",[$player]);
    }
    $db->execute("INSERT INTO alliances(id,world_id,name,tag,leader_id)VALUES(1,1,'Capacity group','CAP',1)");
    $db->execute("INSERT INTO alliance_members(alliance_id,player_id,world_id,role)VALUES(1,1,1,'leader'),(1,2,1,'member'),(1,3,1,'member')");
    $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y)VALUES(5,1,2,'Other capacity city',40,40)");
    $db->execute("INSERT INTO city_buildings(city_id,building_code,level)VALUES(5,'hall_of_alliance',30)");
    $db->execute("INSERT INTO player_research(player_id,world_id,research_code,level)VALUES(1,2,'rally_attack_amount',10)");
    capCheck(MonsterRally::capacity(1,5)===3500000&&WorldContext::id()===1,'capacity uses the supplied city world research without changing active world');
    WorldContext::bind(2);
    capCheck(MonsterRally::capacity(1,1)===50000,'other world research never increases the original city capacity');
    capReject(fn()=>RallyCapacity::forCity(2,5),'Diese Stadt gehört dir nicht.','capacity rejects another player city');
    WorldContext::bind(1);
    capCheck(ResearchEffects::limits(BuffEngine::getBuffs(1,1))['march_capacity']===57500,'test army fits personal capacity beyond the Hall limit');
    $db->execute("INSERT INTO field_monsters(world_id,monster_code,coord_x,coord_y,hp_current,monster_type,expires_at)VALUES(1,20202101,60,60,1000000,'rally',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR))");

    capReject(fn()=>MonsterRally::start(1,1,60,60,[50100101=>50001],30,''),'Die Allianzhalle bietet nicht genug Platz','monster rally rejects one troop above Hall capacity');
    capCheck(capStock(1)===100000,'rejected monster start reserves no troops');
    $id=MonsterRally::start(1,1,60,60,[50100101=>50000],30,'');
    capCheck(capMeta($id)['capacity']===50000,'monster rally accepts and snapshots exactly full Hall capacity');RallyService::cancel($id,1);
    $id=MonsterRally::start(1,1,60,60,[50100101=>30000],30,'');
    $db->execute("UPDATE city_buildings SET level=30 WHERE city_id=1 AND building_code='hall_of_alliance'");
    $db->execute("INSERT INTO player_research(player_id,world_id,research_code,level)VALUES(1,1,'rally_attack_amount',10)");
    capReject(fn()=>RallyService::join(2,2,$id,[50100101=>20001]),'Die Rally-Kapazität der Allianzhalle ist erschöpft.','join sum uses the stored capacity after Hall and research upgrades');
    capCheck(capStock(2)===100000,'rejected joining army remains in its city');
    RallyService::join(2,2,$id,[50100101=>20000]);
    capCheck(capMeta($id)['capacity']===50000,'exactly full monster rally keeps its original capacity snapshot');
    capReject(fn()=>RallyService::join(3,3,$id,[50100101=>1]),'Die Rally-Kapazität der Allianzhalle ist erschöpft.','another member cannot overfill a full rally');RallyService::cancel($id,1);
    $db->execute("UPDATE city_buildings SET level=1 WHERE city_id=1 AND building_code='hall_of_alliance'");
    $db->execute("DELETE FROM player_research WHERE player_id=1 AND world_id=1 AND research_code='rally_attack_amount'");

    capReject(fn()=>RallyService::start(1,1,4,40,40,[50100101=>50001],30,''),'Die Allianzhalle bietet nicht genug Platz','new city rally enforces the same Hall start limit');
    $id=RallyService::start(1,1,4,40,40,[50100101=>50000],30,'');
    capCheck(capMeta($id)['capacity']===50000,'new city rally accepts exact capacity and stores it');RallyService::cancel($id,1);
    $id=RallyService::start(1,1,4,40,40,[50100101=>30000],30,'');
    capReject(fn()=>RallyService::join(2,2,$id,[50100101=>20001]),'Die Rally-Kapazität der Allianzhalle ist erschöpft.','city joining sum cannot exceed the saved Hall limit');
    RallyService::join(2,2,$id,[50100101=>20000]);
    capReject(fn()=>RallyService::join(3,3,$id,[50100101=>1]),'Die Rally-Kapazität der Allianzhalle ist erschöpft.','full city rally rejects further troops');
    $db->execute('UPDATE rallies SET result_json=? WHERE id=?',[json_encode(['alliance_id'=>1]),$id]);
    RallyService::join(3,3,$id,[50100101=>1]);
    capCheck(capStock(3)===99999,'legacy city rally without a stored capacity retains its original joining contract');RallyService::cancel($id,1);

    WorldMapProfile::configureEmptyWorld(3);WorldContext::bind(3);TerritoryService::ensureWorld(3);
    $target=(string)$db->query("SELECT id FROM territory_targets WHERE world_id=3 AND kind='commune' ORDER BY id LIMIT 1")->fetchColumn();
    $location=TerritoryService::target(3,$target);
    $db->execute("INSERT INTO alliances(id,world_id,name,tag,leader_id)VALUES(2,3,'Territory capacity group','TCP',1)");
    for($player=1;$player<=3;$player++){
        $city=5+$player;$db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y)VALUES(?,?,3,'Territory capacity city',?,?)",[$city,$player,$location['x']+5*$player,$location['y']+5]);
        foreach(CityState::BUILDING_CODES as $building)$db->execute('INSERT INTO city_buildings(city_id,building_code,level)VALUES(?,?,?)',[$city,$building,$building==='castle'?30:1]);
        $db->execute('INSERT INTO city_troops(city_id,troop_code,count)VALUES(?,50100101,100000)',[$city]);
        $db->execute("INSERT INTO player_research(player_id,world_id,research_code,level)VALUES(?,3,'march_size',5)",[$player]);
        $db->execute('INSERT INTO alliance_members(alliance_id,player_id,world_id,role)VALUES(2,?,3,?)',[$player,$player===1?'leader':'member']);
    }
    $start=['target_id'=>$target,'city_id'=>6,'rally_minutes'=>30];
    capReject(fn()=>capTerritory(1,'start',$start+['troops'=>[50100101=>50001]]),'Die Rally-Kapazität ist erschöpft.','Commune rally rejects one troop above Hall capacity');
    $id=capTerritory(1,'start',$start+['troops'=>[50100101=>50000]])['rally_id'];
    capCheck(capMeta($id)['capacity']===50000,'Commune rally accepts and stores exactly full capacity');capTerritory(1,'cancel',['rally_id'=>$id]);
    $id=capTerritory(1,'start',$start+['troops'=>[50100101=>30000]])['rally_id'];
    capReject(fn()=>capTerritory(2,'join',['rally_id'=>$id,'city_id'=>7,'troops'=>[50100101=>20001]]),'Die Rally-Kapazität der Allianzhalle ist erschöpft.','Commune joining sum enforces the same Hall capacity');
    capTerritory(2,'join',['rally_id'=>$id,'city_id'=>7,'troops'=>[50100101=>20000]]);
    capReject(fn()=>capTerritory(3,'join',['rally_id'=>$id,'city_id'=>8,'troops'=>[50100101=>1]]),'Die Rally-Kapazität der Allianzhalle ist erschöpft.','full Commune rally rejects one more troop');capTerritory(1,'cancel',['rally_id'=>$id]);
    echo "ALL $checks RALLY CAPACITY CHECKS PASSED\n";
}catch(Throwable $e){$exit=1;fwrite(STDERR,$e->getMessage()."\n".$e->getTraceAsString()."\n");}
finally{if($fixture)$fixture->close();}
exit($exit);
