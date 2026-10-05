<?php
declare(strict_types=1);
/** Combined Hunter/VIP point budgets, checked only in a disposable database. */
if (PHP_SAPI !== 'cli') exit(1);
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR.'/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';
date_default_timezone_set('UTC');
use Conquer\Db\Connection;
use Conquer\Game\City\CityState;
use Conquer\Game\Player\{LordLevel, MasteryService};
use Conquer\Game\Research\BuffEngine;
use Conquer\Game\Vip\VipService;
use Conquer\Game\World\WorldContext;

$fixture=null; $exit=0; $checks=0;
function vhCheck(bool $ok, string $label): void {
    global $checks;
    if (!$ok) throw new RuntimeException($label);
    $checks++;
}
function vhReject(callable $action, string $label): void {
    try { $action(); } catch (DomainException $e) { vhCheck(true,$label); return; }
    throw new RuntimeException('Allowed: '.$label);
}
try {
    $fixture=new \ConquerTests\FeatureDatabase(); $db=Connection::getInstance(); WorldContext::bind(1);
    $db->execute("INSERT INTO worlds(id,name,slug,status) VALUES(2,'Hunter fixture','vip-hunter-fixture','running')");
    $db->execute("INSERT INTO players(id,username,email,password_hash) VALUES(1,'HunterFixture','hunter-vip@invalid.test','unused')");
    foreach ([1,2] as $world) {
        $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y) VALUES(?,1,?,'Hunter fixture',40,40)",[$world,$world]);
        foreach (CityState::BUILDING_CODES as $code) $db->execute('INSERT INTO city_buildings(city_id,building_code,level) VALUES(?,?,1)',[$world,$code]);
        LordLevel::ensure(1,$world);VipService::ensure(1,$world);
    }
    $initial=MasteryService::snapshot(1);
    vhCheck($initial['earned']===1&&$initial['points_from_hunter']===1&&$initial['points_from_vip']===0,'VIP 1 retains the initial Hunter point');
    foreach (range(1,20) as $level) vhCheck(VipService::bonuses($level)['hunter_points']===$level-1,'VIP allowance at level '.$level);
    vhCheck(VipService::hunterPoints(-1)===0&&VipService::hunterPoints(99)===19,'VIP allowance is bounded');
    $levels=array_column(VipService::levels(),'points','level');
    $db->execute('UPDATE player_world_vip SET vip_points=?,vip_level=10 WHERE player_id=1 AND world_id=1',[$levels[10]]);
    $db->execute('UPDATE player_lord_progress SET xp=? WHERE player_id=1 AND world_id=1',[LordLevel::totalForLevel(30)]);
    $state=MasteryService::snapshot(1);
    vhCheck($state['earned']===45&&$state['available']===45&&$state['points_from_hunter']===36&&$state['points_from_vip']===9,'Hunter 30 and VIP 10 supply exactly 45 points');
    vhCheck(MasteryService::snapshot(1,2)['earned']===1,'VIP in another world grants no Hunter points');
    $db->execute('UPDATE player_world_vip SET vip_points=?,vip_level=20 WHERE player_id=1 AND world_id=1',[$levels[20]]);
    $db->execute('UPDATE player_lord_progress SET xp=0 WHERE player_id=1');
    $roots=['infantry_9'=>1,'infantry_0'=>5,'infantry_1'=>5,'infantry_2'=>5,'archer_9'=>1,'archer_0'=>3];
    $state=MasteryService::snapshot(1);
    $result=MasteryService::change(1,['ranks'=>$roots,'revision'=>$state['revision'],'earned'=>999,'vip_level'=>999]);
    vhCheck($result['mastery']['earned']===20&&$result['mastery']['spent']===20&&$result['mastery']['available']===0,'server accepts the combined budget without raising the Hunter level');
    vhCheck(LordLevel::snapshot(1)['level']===1&&LordLevel::snapshot(1)['xp']===0,'VIP points never award Hunter XP or levels');
    vhCheck(abs(MasteryService::bonuses(1)['infantry_atk']-.1)<1e-9,'plans using VIP points still produce real talent bonuses');
    vhCheck(abs(BuffEngine::getBuffs(1)['infantry_atk']-.1)<1e-9,'combined plans reach gameplay calculations');
    $state=MasteryService::snapshot(1);
    vhCheck($state['available']===0&&MasteryService::snapshot(1,2)['available']===1,'spending and rereading never duplicate points or leak world plans');
    vhReject(fn()=>MasteryService::change(1,['ranks'=>array_replace($roots,['archer_0'=>4]),'revision'=>$state['revision'],'earned'=>999,'vip_level'=>999]),'client cannot increase its point budget');
    $branch=static function(string $name): array { $r=[]; foreach(MasteryService::catalog()['branches'] as $b)if($b['code']===$name)foreach($b['nodes'] as $n)$r[$n['code']]=$n['max_level'];return $r; };
    $primary=$branch('monster');
    vhReject(fn()=>MasteryService::validate($primary,39,20),'VIP never bypasses the Hunter 40 capstone requirement');
    $plan=$branch('monster')+['gathering_9'=>1,'gathering_0'=>5,'gathering_1'=>5,'gathering_2'=>5,'gathering_10'=>1,'gathering_3'=>5,'gathering_11'=>1,'gathering_5'=>5,'gathering_12'=>1,'gathering_6'=>1];
    vhCheck(array_sum(MasteryService::validate($plan,50,20))===79,'Hunter 50 and VIP 20 permit 79 points');
    vhReject(fn()=>MasteryService::validate(array_replace($plan,['gathering_6'=>2]),50,20),'the 80th point remains unavailable');
    $db->execute('UPDATE player_lord_progress SET xp=? WHERE player_id=1 AND world_id=1',[LordLevel::totalForLevel(50)]);
    $state=MasteryService::snapshot(1);
    $result=MasteryService::change(1,['ranks'=>$plan,'revision'=>$state['revision']]);
    vhCheck($result['mastery']['earned']===79&&$result['mastery']['available']===0,'the maximum combined plan persists through the real handler');
    vhCheck((MasteryService::bonuses(1)['vs_monster_attack']??0)>0,'persisted plans above 60 points retain their bonuses');
    echo "PASS $checks VIP/Hunter checks.\n";
} catch (Throwable $e) { fwrite(STDERR,(string)$e."\n"); $exit=1; }
finally { $fixture?->close(); }
exit($exit);
