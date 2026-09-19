<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
date_default_timezone_set('UTC');
define('ROOT_DIR',dirname(__DIR__));
require ROOT_DIR.'/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';
use Conquer\Db\Connection;
use Conquer\Game\City\{TroopData,CityState,TroopTrainer,BuildingData,BuildingUpgrader};
use Conquer\Game\Defense\DefenseService;
use Conquer\Game\Research\{ResearchData,ResearchProcessor};
function unlockCheck(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
function unlockReject(callable $fn,string $message):void{
    try{$fn();}catch(DomainException|RuntimeException $e){if(!str_contains($e->getMessage(),$message))throw $e;return;}
    throw new LogicException('Locked action was accepted');
}
$fixture=new \ConquerTests\FeatureDatabase();$db=Connection::getInstance();
function unlockLevels(string $school,int $level,int $castle):array{
    global $db;
    $db->execute('UPDATE city_buildings SET level=? WHERE city_id=1 AND building_code=?',[$level,$school]);
    $db->execute("UPDATE city_buildings SET level=? WHERE city_id=1 AND building_code='castle'",[$castle]);
    $db->execute('UPDATE cities SET castle_level=? WHERE id=1',[$castle]);
    return CityState::loadForPlayer(1);
}
function unlockBalance():array{global $db;return $db->query('SELECT food,lumber,stone,gold FROM cities WHERE id=1')->fetch();}
try{
    $db->execute("INSERT INTO players(id,username,email,password_hash) VALUES(1,'UnlockFixture','unlock@tests.invalid','unused')");
    $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,castle_level,food,lumber,stone,gold,last_resource_update) VALUES(1,1,1,'Unlocks',65,65,30,100000000,100000000,100000000,100000000,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY))");
    foreach(CityState::BUILDING_CODES as $b)$db->execute('INSERT INTO city_buildings(city_id,building_code,level) VALUES(1,?,1)',[$b]);
    $schoolLevels=[1,4,7,11,13,16,19,22,26,30];$castleLevels=[1,4,7,11,13,16,19,22,26,30];
    foreach(TroopData::all() as $code=>$t){
        $i=$t['tier']-1;$level=$schoolLevels[$i];$castle=$castleLevels[$i];$school=TroopData::buildingFor($code);
        unlockCheck(TroopData::isUnlocked($code,$level,$castle)&&!TroopData::isUnlocked($code,$level-1,$castle)&&!TroopData::isUnlocked($code,$level,$castle-1),"type {$t['type']} T{$t['tier']}: school $level / town center $castle boundaries");
        if($i>0){
            foreach([[$level-1,$castle],[$level,$castle-1]] as [$lowSchool,$lowCastle]){
                $s=unlockLevels($school,$lowSchool,$lowCastle);$before=unlockBalance();
                $defs=array_column(TroopData::forCity($s,[],[],1),null,'code');
                if($defs[$code]['unlocked'])throw new LogicException('Read model unlocked too early');
                unlockReject(fn()=>TroopTrainer::train($s['city'],$s['buildings'],$code,1),'Benötigt');
                if(unlockBalance()!==$before)throw new LogicException('Locked training spent resources');
            }
        }
        $s=unlockLevels($school,$level,$castle);
        $defs=array_column(TroopData::forCity($s,[],[],1),null,'code');
        if(!$defs[$code]['unlocked'])throw new LogicException('Read model did not unlock at threshold');
        TroopTrainer::train($s['city'],$s['buildings'],$code,1);
        $db->execute('UPDATE troop_queue SET finishes_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE city_id=1');
        TroopTrainer::processQueue($db,1);
    }
    unlockCheck((int)$db->query('SELECT COUNT(*) FROM player_research')->fetchColumn()===0,'all 30 tiers train with academy 1 and no research');
    unlockCheck((int)$db->query('SELECT SUM(count) FROM city_troops WHERE city_id=1')->fetchColumn()===30,'all 30 real training orders settle once');

    foreach(['barrack','archery_range','stable'] as $school){
        $db->execute("UPDATE city_buildings SET level=4 WHERE city_id=1 AND building_code='farm'");
        $s=unlockLevels($school,3,3);$before=unlockBalance();
        unlockReject(fn()=>BuildingUpgrader::start(1,$school,$s['city'],$s['buildings']),'Castle Stufe 4');
        if(unlockBalance()!==$before)throw new LogicException('Locked building spent resources');
        $s=unlockLevels($school,3,4);$q=BuildingUpgrader::start(1,$school,$s['city'],$s['buildings']);
        unlockCheck((int)$q['level_to']===4&&BuildingData::getUpgradeRequirements($school,4)===['castle'=>4,'farm'=>4],"$school level 4 requires town center and farm 4 in upgrade service and preview");
        $db->execute('DELETE FROM building_queue WHERE city_id=1');
    }
    foreach([1=>'barrack',2=>'archery_range',3=>'stable'] as $type=>$school){
        $source=50000101+$type*100000;
        $s=unlockLevels($school,12,13);
        unlockReject(fn()=>DefenseService::promote(1,1,$source,1),'Stufe 13');
        unlockLevels($school,13,12);unlockReject(fn()=>DefenseService::promote(1,1,$source,1),'Stufe 13');
        unlockLevels($school,13,13);$p=DefenseService::promote(1,1,$source,1);DefenseService::cancelPromotion(1,$p['promotion_id']);
        unlockCheck(true,"$school promotes from level 13 without research");
        unlockReject(fn()=>DefenseService::promote(1,1,50000501+$type*100000,1),'Zieltruppe');
    }

    $retired=ResearchData::retiredTroopUnlocks();
    unlockCheck(count($retired)===12&&ResearchData::get('warrior')===null&&ResearchData::get('march_limit')!==null,'only troop-tier research is retired, march slots remain');
    $db->execute("INSERT INTO worlds(id,name,slug,status,map_size) VALUES(2,'Other unlock world','unlock-other','running',256)");
    $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,food,lumber,stone,gold) VALUES(2,1,2,'Other',65,65,100,100,100,100)");
    $db->execute("INSERT INTO player_research(player_id,world_id,research_code,level) VALUES(1,1,'knight',1)");
    foreach([1,2]as$world)$db->execute("INSERT INTO research_queue(player_id,world_id,research_code,level_to,started_at,finishes_at) VALUES(1,?,'warrior',1,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY))",[$world]);
    $before=unlockBalance();ResearchProcessor::processQueue(1,1);$after=unlockBalance();ResearchProcessor::processQueue(1,1);
    foreach(['food','lumber','stone','gold']as$r)if((int)$after[$r]-(int)$before[$r]!==$retired['warrior']['levels'][0]['resources'][$r])throw new LogicException('Wrong retired research refund');
    unlockCheck(unlockBalance()===$after,'retired research resources refunded exactly once');
    unlockCheck((int)$db->query('SELECT COUNT(*) FROM research_queue WHERE world_id=1 AND is_processed=0')->fetchColumn()===0&&(int)$db->query('SELECT COUNT(*) FROM research_queue WHERE world_id=2 AND is_processed=0')->fetchColumn()===1,'refund frees only the selected world research slot');
    unlockCheck((int)$db->query("SELECT level FROM player_research WHERE research_code='knight'")->fetchColumn()===1,'historical completed research is preserved');
    ResearchProcessor::processQueue(1,2);
    unlockCheck((int)$db->query('SELECT food FROM cities WHERE id=2')->fetchColumn()===100+$retired['warrior']['levels'][0]['resources']['food'],'background refund uses the original world city');
    echo "ALL BUILDING-BASED TROOP UNLOCK CHECKS PASSED\n";
}finally{$fixture->close();}
