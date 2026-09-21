<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';

use Conquer\Db\{Connection,MigrationSql};
use Conquer\Game\World\{LandAccessPolicy,LandGeometry,LandProgressService,LandRules,LandUnlockService};

function landCheck(bool $condition,string $message):void{if(!$condition)throw new RuntimeException($message);echo "PASS $message\n";}
function landReject(callable $fn,string $message):void{try{$fn();}catch(DomainException){echo "PASS $message\n";return;}throw new RuntimeException($message);}

$fixture=null;
try{
    $zones=['outer'=>0,'middle'=>0,'center'=>0];$levels=[];
    foreach(LandGeometry::all(256) as $land){$zones[$land['zone']]++;$levels[$land['initial_level']]=($levels[$land['initial_level']]??0)+1;}
    landCheck($zones===['outer'=>624,'middle'=>336,'center'=>64],'256 geometry keeps the agreed 624/336/64 zones');
    landCheck(($levels[1]??0)===624&&($levels[9]??0)===16,'outer starts at 1 and the central level-9 core stays small');
    foreach([64,255,256,320,1024] as $size){$last=LandGeometry::at($size,$size-1,$size-1);landCheck($last['bounds']['x_max']===$size-1&&$last['bounds']['y_max']===$size-1,"map size $size clips its final parcel correctly");}

    $fixture=new \ConquerTests\FeatureDatabase();$db=Connection::getInstance();
    MigrationSql::apply($db->getPdo(),(string)file_get_contents(ROOT_DIR.'/migrations/0084_land_progression.sql'));
    LandProgressService::ensureWorld(1);
    landCheck(count(LandUnlockService::status(1))===3&&!in_array(false,array_column(LandUnlockService::status(1),'open'),true),'migration keeps every zone of an existing world open');

    $db->execute("INSERT INTO worlds(name,slug,status,map_size,map_seed,created_at,started_at)VALUES('Landtest','landtest','running',256,84,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 DAY),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 DAY))");$world=$db->lastInsertId();
    foreach(['outer','middle','center'] as $zone)$db->execute("INSERT INTO world_land_zones(world_id,zone_key,status,opened_at,opened_reason,rule_revision)VALUES(?,?,'locked',NULL,NULL,1)",[$world,$zone]);
    LandProgressService::ensureWorld($world,false);$status=array_column(LandUnlockService::status($world),null,'key');
    landCheck($status['outer']['open']&&$status['middle']['open']&&$status['center']['open'],'a new or previously locked world opens the complete map immediately');
    landCheck(LandAccessPolicy::isOpen($world,8,8)&&LandAccessPolicy::isOpen($world,128,128),'outer and central targets are accessible from the beginning');
    LandAccessPolicy::assertTargetOpen($world,128,128);

    $db->execute("INSERT INTO players(username,email,password_hash)VALUES('land_player','land@example.test','x')");$player=$db->lastInsertId();
    $db->execute("INSERT INTO cities(player_id,world_id,name,coord_x,coord_y,food,lumber,stone,gold)VALUES(?,?,'Landstadt',8,8,1000000,1000000,1000000,1000000)",[$player,$world]);
    $land=LandProgressService::at($world,8,8);landCheck($land!==null&&$land['level']===1&&$land['open'],'cached at() returns the persisted land contract');
    $beforeFood=(int)$db->query('SELECT food FROM cities WHERE player_id=? AND world_id=?',[$player,$world])->fetchColumn();
    $donation=LandProgressService::donate($world,$player,$land['id'],'land-donation-0001',$land['rule_revision']>0?(int)$db->query('SELECT revision FROM world_land_parts WHERE id=?',[$land['id']])->fetchColumn():1,['food'=>10000]);
    $changedUnits=LandRules::defaults();$changedUnits['resource_units_per_point']=20000;LandRules::save($world,$changedUnits,1,1);
    $retry=LandProgressService::donate($world,$player,$land['id'],'land-donation-0001',1,['food'=>10000]);
    landCheck($donation['credited_points']===100&&$retry['duplicate']===true,'donation retry returns its receipt after balancing rules changed');
    landCheck((int)$db->query('SELECT food FROM cities WHERE player_id=? AND world_id=?',[$player,$world])->fetchColumn()===$beforeFood-10000,'donation debits resources exactly once');
    landReject(fn()=>LandProgressService::donate($world,$player,$land['id'],'land-donation-0001',1,['food'=>10100]),'reusing a request id with another payload is rejected');
    LandRules::save($world,LandRules::defaults(),1,2);

    $kill=LandProgressService::recordMonsterKill($world,900001,8,8,9,$player);$killRetry=LandProgressService::recordMonsterKill($world,900001,8,8,9,$player);
    landCheck($kill['credited_points']===900&&$kill['level_after']===2&&$killRetry['duplicate']===true,'confirmed monster kill reaches the next level exactly once');
    $gather=LandProgressService::recordGather($world,800001,8,8,'food',10000,$player);$gatherRetry=LandProgressService::recordGather($world,800001,8,8,'food',10000,$player);
    landCheck($gather['credited_points']===100&&$gatherRetry['duplicate']===true,'actual gathered amount is idempotent');

    $settings=LandRules::defaults();foreach(range(1,8) as $level)$settings['thresholds'][$level]=1;
    $saved=LandRules::save($world,$settings,1,3);landCheck($saved['revision']===4,'land rules save uses an optimistic revision');
    landCheck(LandProgressService::at($world,8,8)['rule_revision']===4,'spawn snapshots use the current land-rules revision');
    LandProgressService::recordMonsterKill($world,900002,8,8,1,$player);$max=LandProgressService::detail($world,$player,$land['id']);
    landCheck($max['level']===9&&$max['points']===0&&$max['next_threshold']===null&&$max['progress_pct']===100,'every land can permanently reach level 9');

    $zeroFull=LandRules::defaults();$zeroFull['gather_full_daily_ratio']=0;$zeroFull['gather_reduced_factor']=0;LandRules::save($world,$zeroFull,1,4);
    $zeroGather=LandProgressService::recordGather($world,800002,16,8,'food',100,$player);
    landCheck($zeroGather['raw_points']===1&&$zeroGather['credited_points']===0,'zero full and reduced gather ratios credit no hidden minimum point');

    $rules=LandRules::defaults();$rules['gates']['middle']['not_before_days']=0;$rules['gates']['center']['not_before_days']=0;LandRules::save($world,$rules,1,5);
    LandUnlockService::invalidate($world);LandProgressService::invalidate($world);LandUnlockService::evaluate($world);$status=array_column(LandUnlockService::status($world),null,'key');
    landCheck($status['outer']['open']&&$status['middle']['open']&&$status['center']['open'],'regional evaluation keeps the complete map open');

    $overview=LandProgressService::overview($world,$player);landCheck(count($overview['lands'])===1024&&count($overview['zones'])===3,'overview returns compact state for every land and both gates');
    landCheck(array_key_exists('monsters',$max)&&array_key_exists('sources',$max)&&array_key_exists('recent_events',$max),'detail exposes progression and monster contracts');
    echo "ALL LAND PROGRESSION CHECKS PASSED\n";
}finally{if($fixture)$fixture->close();}
