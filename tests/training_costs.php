<?php
declare(strict_types=1);
/** Training affordability and historical receipts, isolated from saved accounts. */
if (PHP_SAPI !== 'cli') exit(1);
date_default_timezone_set('UTC');define('ROOT_DIR',dirname(__DIR__));
require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';
use Conquer\Db\Connection;
use Conquer\Game\City\{CityState,TroopData,TroopTrainer};
use Conquer\Game\Research\BuffEngine;
use Conquer\Game\Defense\DefenseService;
use Conquer\Game\Hospital\HospitalService;
use Conquer\Game\Operation;
function costCheck(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
function costReject(callable $fn):void{try{$fn();}catch(DomainException|RuntimeException $e){return;}throw new LogicException('Invalid action accepted');}
$expected=[
    50100401=>['food'=>400000,'lumber'=>240000,'stone'=>0,'gold'=>0],
    50200401=>['food'=>320000,'lumber'=>160000,'stone'=>0,'gold'=>80000],
    50300401=>['food'=>480000,'lumber'=>0,'stone'=>0,'gold'=>160000],
];
foreach($expected as $code=>$cost){
    costCheck(TroopData::trainingCost($code,2000)===$cost,"2,000 T4 $code use the new affordable budget");
    costCheck(DefenseService::promotionQuote($code-100,2000,[])['cost']===array_map(static fn($v)=>(int)ceil($v*.7),$cost),'promotion keeps its 70% target-tier rate');
    $healing=HospitalService::healingResources($code,2000);
    foreach($cost as $resource=>$value)costCheck($value===0?$healing[$resource]===0:($healing[$resource]>0&&$healing[$resource]<=$value*.2),'T4 healing costs at most 20% including per-unit rounding: '.$resource);
}
foreach([1,2,3] as $type){
    $last=0;
    for($tier=1;$tier<=10;$tier++){
        $code=50000001+$type*100000+$tier*100;$cost=TroopData::trainingCost($code,2000);$total=array_sum($cost);
        costCheck($total>$last&&($last===0||$total<=$last*1.6)&&$cost['stone']===0,"type $type T$tier: costs grow smoothly without adding stone");$last=$total;
    }
    costCheck($last<=4000000,'2,000 T10 remain below four million total resources for type '.$type);
}
$fixture=new \ConquerTests\FeatureDatabase();$db=Connection::getInstance();
try{
    $db->execute("INSERT INTO players(id,username,email,password_hash) VALUES(1,'CostFixture','cost@tests.invalid','unused')");
    $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,castle_level,food,lumber,stone,gold,last_resource_update) VALUES(1,1,1,'Costs',65,65,30,100000000,100000000,100000000,100000000,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY))");
    foreach(CityState::BUILDING_CODES as $school)$db->execute('INSERT INTO city_buildings(city_id,building_code,level) VALUES(1,?,30)',[$school]);
    foreach($expected as $code=>$cost){
        $db->execute('UPDATE cities SET last_resource_update=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY) WHERE id=1');
        $state=CityState::loadForPlayer(1);$before=$db->query('SELECT food,lumber,stone,gold FROM cities WHERE id=1')->fetch();
        $body=['action'=>'troops.train','operation_key'=>'cost_2000_t4_'.$code,'world_id'=>1,'troop_code'=>$code,'count'=>2000];
        $start=static function()use($state,$code):array{TroopTrainer::train($state['city'],$state['buildings'],$code,2000);return ['started'=>true];};
        Operation::run(1,$body,$start);Operation::run(1,$body,$start);
        $after=$db->query('SELECT food,lumber,stone,gold FROM cities WHERE id=1')->fetch();
        foreach($cost as $resource=>$value)costCheck((int)$before[$resource]-(int)$after[$resource]===$value,'actual resource debit matches new quote exactly once: '.$code.' '.$resource);
        $queue=$db->query('SELECT * FROM troop_queue WHERE city_id=1 AND troop_code=? AND is_processed=0',[$code])->fetch();
        costCheck(json_decode($queue['cost_json'],true)===$cost,'payment receipt saves the new batch cost');
        TroopTrainer::cancel(1,(int)$queue['id']);
    }
    $db->execute("INSERT INTO player_research(player_id,world_id,research_code,level) VALUES(1,1,'infantry_training_cost',1)");
    $state=CityState::loadForPlayer(1);$defs=TroopData::forCity($state,BuffEngine::getBuffs(1),[],1);
    $def=array_values(array_filter($defs,static fn($t)=>$t['code']===50100401))[0];
    $discounted=['food'=>396198,'lumber'=>237719,'stone'=>0,'gold'=>0];
    costCheck(array_map(static fn($v)=>(int)ceil($v*2001),$def['training']['cost'])===$discounted,'research preview rounds the discounted 2,001-unit total once');
    TroopTrainer::train($state['city'],$state['buildings'],50100401,2001);
    $row=$db->query('SELECT id,cost_json FROM troop_queue WHERE city_id=1 AND is_processed=0')->fetch();
    costCheck(json_decode($row['cost_json'],true)===$discounted,'actual discounted receipt agrees with preview');TroopTrainer::cancel(1,(int)$row['id']);
    $db->execute('UPDATE cities SET food=1,last_resource_update=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY) WHERE id=1');
    $state=CityState::loadForPlayer(1);costReject(static fn()=>TroopTrainer::train($state['city'],$state['buildings'],50100401,2000));
    costCheck((int)$db->query('SELECT COUNT(*) FROM troop_queue WHERE city_id=1 AND is_processed=0')->fetchColumn()===0,'insufficient resources still reject the cheaper batch');
    $old=['food'=>3600000,'lumber'=>2000000,'stone'=>0,'gold'=>0];
    $db->execute('INSERT INTO troop_queue(city_id,troop_code,count,barrack_slot,started_at,finishes_at,cost_json) VALUES(1,50100401,2000,1,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 216000 SECOND),?)',[json_encode($old)]);
    $id=$db->lastInsertId();CityState::loadForPlayer(1);$refund=TroopTrainer::cancel(1,(int)$id);
    foreach($old as $resource=>$paid)costCheck($refund['refunded_'.$resource]===$paid,'historical cancellation uses the old paid receipt: '.$resource);
    costReject(static fn()=>TroopTrainer::cancel(1,(int)$id));
    echo "ALL TRAINING COST CHECKS PASSED\n";
}finally{$fixture->close();}
