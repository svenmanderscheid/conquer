<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';date_default_timezone_set('UTC');
\Conquer\Logger::init(sys_get_temp_dir().'/conquer-constellation-test.log','ERROR');
use Conquer\Db\Connection;
use Conquer\Game\Player\{LordLevel,MasteryService,TalentEffects,ActionPoints};
use Conquer\Game\Research\{BuffEngine,ResearchEffects};
use Conquer\Game\World\WorldContext;
use Conquer\Game\City\{CityState,TroopData};
use Conquer\Game\March\{MarchDispatcher,MarchSpeed,PvpRules};
use Conquer\Game\Hospital\HospitalService;
use Conquer\Game\Operation;
$checks=0;$fixture=null;$exit=0;
function ck(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException($label);$checks++;}
function near(float $a,float $b):bool{return abs($a-$b)<1e-6;}
function reject(callable $fn,string $label):void{try{$fn();}catch(DomainException|RuntimeException){ck(true,$label);return;}throw new RuntimeException('Allowed: '.$label);}
function fullBranch(string $branch):array{$r=[];foreach(MasteryService::catalog()['branches'] as $b)if($b['code']===$branch)foreach($b['nodes'] as $n)$r[$n['code']]=$n['max_level'];return $r;}
function applyPlan(array $ranks,int $world=1):array{$s=MasteryService::snapshot(1,$world);$b=['action'=>'mastery.apply','ranks'=>$ranks,'revision'=>$s['revision'],'expected_world_id'=>$world,'operation_key'=>'talents_'.bin2hex(random_bytes(14))];return Operation::run(1,$b,fn()=>MasteryService::change(1,$b,$world));}
try{
 $fixture=new \ConquerTests\FeatureDatabase();$db=Connection::getInstance();WorldContext::bind(1);
 $db->execute("INSERT INTO worlds(id,name,slug,status,map_size) VALUES(2,'Talent fixture','talent-fixture', 'running',256)");
 $db->execute("INSERT INTO players(id,username,email,password_hash) VALUES(1,'TalentFixture','talents@invalid.test','unused')");
 foreach([1,2] as $w){$db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,castle_level,food,lumber,stone,gold) VALUES(?,1,?,'Talent village',40,40,12,100000,100000,100000,100000)",[$w,$w]);foreach(CityState::BUILDING_CODES as $code)$db->execute('INSERT INTO city_buildings(city_id,building_code,level) VALUES(?,?,?)',[$w,$code,$code==='castle'?12:5]);LordLevel::ensure(1,$w);}
 ck(count(MasteryService::catalog()['branches'])===6&&count(MasteryService::nodes())===78,'six branches, 78 nodes');
 ck(LordLevel::MAX_LEVEL===50&&LordLevel::talentPoints(50)===60&&LordLevel::talentPoints(99)===60,'maximum Hunter 50 and 60 Hunter points');
 for($level=1;$level<=50;$level++){
  ck(LordLevel::levelFromTotalXp(LordLevel::totalForLevel($level))===$level,'level threshold '.$level);
  if($level>1)ck(LordLevel::talentPoints($level)-LordLevel::talentPoints($level-1)===($level%10===0?3:1),'milestone points '.$level);
 }
 $en=json_decode(file_get_contents(ROOT_DIR.'/data/i18n/en.json'),true);
 foreach(MasteryService::nodes() as $n){ck(is_file(ROOT_DIR.'/'.$n['icon']),'asset '.$n['code']);foreach(['name','label','description'] as $key)ck(!empty($en[$n[$key.'_key']]),'English '.$n['code'].' '.$key);}
 foreach(MasteryService::catalog()['branches'] as $b){$r=[$b['code'].'_9'=>1,$b['code'].'_0'=>1,$b['code'].'_10'=>1,$b['code'].'_3'=>1];ck(array_sum(MasteryService::validate($r,4))===4,'rank-one route without tier gate '.$b['code']);ck(count(array_filter($b['nodes'],fn($n)=>$n['entry']))===1,'single top entry '.$b['code']);}
 reject(fn()=>MasteryService::validate(['monster_0'=>1],50),'unpaid entry');
 reject(fn()=>MasteryService::validate(['monster_9'=>2],50),'waypoint cannot gain rank two');
 reject(fn()=>MasteryService::validate(['monster_9'=>'1'],50),'strict integer rank');
 reject(fn()=>MasteryService::validate(['monster_9'=>1,'monster_0'=>6],50),'main rank cannot exceed five');
 reject(fn()=>MasteryService::validate(['monster_9'=>1,'monster_0'=>1],1),'budget enforced');
 $short=['monster_9'=>1,'monster_2'=>1,'monster_5'=>1,'monster_7'=>1,'monster_8'=>1];
 reject(fn()=>MasteryService::validate($short,39,20),'master requires Hunter 40');ck(count(MasteryService::validate($short,40))===5,'five-point connected master path');
 $alternate=['monster_9'=>1,'monster_0'=>1,'monster_1'=>1,'monster_3'=>1];unset($alternate['monster_0']);ck(count(MasteryService::validate($alternate,4))===3,'alternate route preserves descendant');unset($alternate['monster_1']);reject(fn()=>MasteryService::validate($alternate,4),'last route cannot be removed');
 $max=fullBranch('monster')+['gathering_9'=>1,'gathering_0'=>5,'gathering_1'=>5,'gathering_2'=>5,'gathering_10'=>1,'gathering_3'=>5,'gathering_11'=>1,'gathering_5'=>5,'gathering_12'=>1,'gathering_6'=>1];
 ck(array_sum(MasteryService::validate($max,50,20))===79,'maximum combined 79-point plan');reject(fn()=>MasteryService::validate(array_replace($max,['gathering_6'=>2]),50,20),'80th point rejected');
 ck(MasteryService::snapshot(1)['available']===1,'initial Hunter point');
 LordLevel::addXp(1,250,1,'talent:kill');LordLevel::addXp(1,250,1,'talent:kill');ck(LordLevel::snapshot(1)['xp']===250,'XP reward exactly once');ck(MasteryService::snapshot(1,2)['earned']===1,'world isolation');
 $db->execute('UPDATE player_lord_progress SET xp=? WHERE player_id=1 AND world_id=1',[LordLevel::totalForLevel(50)]);
 $db->execute('UPDATE players SET action_points=80,last_ap_regen=UTC_TIMESTAMP() WHERE id=1');
 $b=['action'=>'mastery.apply','ranks'=>['monster_9'=>1],'revision'=>0,'expected_world_id'=>1,'operation_key'=>'constellation_once_123456789'];
 $one=Operation::run(1,$b,fn()=>MasteryService::change(1,$b));$again=Operation::run(1,$b,fn()=>MasteryService::change(1,$b));ck($one===$again&&MasteryService::snapshot(1)['revision']===1,'save receipt replays without double spend');
 ck(ActionPoints::get(1)['max']===210&&ActionPoints::get(1)['current']===80,'AP reserve increases maximum without refill');
 ck(MasteryService::reportSnapshot(1,1)['nodes'][0]['max_level']===5&&count(MasteryService::reportSnapshot(1,1)['branches'])===6,'scout snapshot captures six active branches');
 reject(fn()=>MasteryService::change(1,['ranks'=>[],'revision'=>0]),'stale revision rejected');
 applyPlan(fullBranch('monster'));$buffs=BuffEngine::getBuffs(1);
 ck(near($buffs['vs_monster_attack'],.2),'two monster attack nodes add');ck(near(TalentEffects::combat($buffs,'monster',true)['vs_monster_attack'],.3),'boss bonus scoped to owner boss contribution');
 ck(ActionPoints::monsterCost(10,$buffs)===9&&ActionPoints::monsterCost(7,$buffs)===7&&ActionPoints::monsterCost(0,$buffs)===0,'AP discount rounds up and preserves free attacks');
 ck(near(ActionPoints::get(1)['regen_per_hour'],13.8),'AP regeneration uses learned stamina');
 $loot=TalentEffects::monsterLoot(['food'=>100,'lumber'=>100,'gems'=>100,'xp'=>100,'fragments'=>100],$buffs);ck($loot===['food'=>117,'lumber'=>117,'gems'=>100,'xp'=>100,'fragments'=>100],'loot only ordinary resources');
 ck(ResearchEffects::limits($buffs)['hunt_march_slots']===1,'master hunter reserved solo slot');
 for($i=0;$i<3;$i++)$db->execute("INSERT INTO marches(player_id,world_id,march_type,origin_city_id,target_x,target_y,target_type,target_id,troops_json,departure_time,arrival_time,state)VALUES(1,1,7,1,44,44,2,999,'{}',UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),'marching')");
 MarchDispatcher::assertSlotAvailable(1,1,false,true);reject(fn()=>MarchDispatcher::assertSlotAvailable(1,1),'hunting slot cannot launch PvP');
 reject(fn()=>applyPlan([]),'active army blocks talent change');$db->execute("UPDATE marches SET state='returned'");
 HospitalService::addWounded(1,[50100101=>20]);HospitalService::addWounded(1,[50100101=>10],true);
 $h=HospitalService::getStatus(1)['wounded'][0];ck($h['monster_waiting_count']===10&&$h['waiting_count']===30,'hospital distinguishes monster wounds');ck(near($h['monster_seconds_per_troop'],$h['seconds_per_troop']/1.23),'monster-only healing rate');
 $db->transaction(fn()=>HospitalService::perform(1,1,['action'=>'hospital.heal','troops'=>[50100101=>15],'operation_key'=>'wound_fixture_123456789']));
 $batch=$db->query('SELECT * FROM hospital_wounded WHERE city_id=1')->fetch();$duration=strtotime($batch['healing_ends_at'])-strtotime($batch['healing_started_at']);ck($duration===(int)ceil(10*$h['monster_seconds_per_troop']+5*$h['seconds_per_troop']),'mixed wound batch uses each rate');
 HospitalService::addWounded(1,[50100101=>5],true);$db->execute('UPDATE hospital_wounded SET healing_ends_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE city_id=1');HospitalService::processHealed(1);$h=HospitalService::getStatus(1)['wounded'][0];ck($h['waiting_count']===20&&$h['monster_waiting_count']===5,'new wounds stay outside active healing batch');
 $raw=['talent_infantry_atk_vs_cavalry'=>.1,'talent_infantry_damage_from_ranged'=>-.05,'talent_infantry_formation_def'=>.1,'talent_ranged_formation_atk'=>.1,'talent_mixed_combat'=>.05,'talent_rally_attack'=>.06,'talent_ranged_rally_atk'=>.05];
 ck(near(TalentEffects::combat($raw,'pvp',false,[50300101=>100])['infantry_atk'],.1),'counter against pure cavalry');ck(near(TalentEffects::combat($raw,'monster')['troops_atk']??0,0),'PvP counter excluded from monsters');
 $loss=PvpRules::losses([50100101=>1000],.1,$raw,[50200101=>100]);ck($loss['survivors'][50100101]===905,'archer resistance reduces actual infantry casualties');
 ck(near(TalentEffects::formation($raw,[50100101=>100])['infantry_def'],.1),'infantry 70-percent master');ck(!isset(TalentEffects::formation($raw,[50100101=>1,50200101=>1,50300101=>1])['infantry_def']),'mixed army cannot claim infantry formation');
 ck(near(TalentEffects::formation($raw,[50100101=>1,50200101=>1,50300101=>1])['troops_atk'],.05),'20-percent mixed army master');ck(near(TalentEffects::formation($raw,[50200101=>10],true)['ranged_atk'],.15),'rally formation and personal archer bonus add');
 $travel=['talent_cavalry_pvp_march'=>.18,'talent_cavalry_pvp_roundtrip'=>.15];$out=TalentEffects::cavalryMarch($travel,[50300101=>100]);$back=TalentEffects::cavalryMarch($travel,[50300101=>100],true);ck(near($out['talent_pvp_march'],.33)&&near($back['talent_pvp_march'],.15),'cavalry outgoing and return bonuses differ');ck(!isset(TalentEffects::cavalryMarch($travel,[50100101=>100])['talent_pvp_march']),'cavalry march requires qualifying army');
 ck(MarchSpeed::readModel(50300101,$travel)['cavalry_pvp_march_speed']===MarchSpeed::pvp(50300101,$out),'ETA read model matches dispatch speed');
 $db->execute('UPDATE player_lord_progress SET last_respec_at=NULL WHERE player_id=1');applyPlan(fullBranch('gathering'));$buffs=BuffEngine::getBuffs(1);ck(ResearchEffects::limits($buffs)['gather_march_slots']===1&&ResearchEffects::limits($buffs)['hunt_march_slots']===0,'reserved slots follow saved branch');
 ck(ResearchEffects::carryCapacity([50100101=>100],TalentEffects::gather($buffs))>ResearchEffects::carryCapacity([50100101=>100],$buffs),'gathering carry excluded from plunder');
 $db->execute('UPDATE player_lord_progress SET last_respec_at=NULL WHERE player_id=1');applyPlan(['gathering_9'=>1]);reject(fn()=>applyPlan([]),'24-hour respec cooldown');
 $db->execute('DELETE FROM player_lord_talents WHERE player_id=1 AND world_id=1');$db->execute("INSERT INTO player_lord_talents(player_id,world_id,talent_code,rank)VALUES(1,1,'attack_0',5)");
 ck(MasteryService::snapshot(1)['legacy_plan']&&near(MasteryService::bonuses(1)['talent_pvp_attack'],.05),'old saved plan stays active');applyPlan(['infantry_9'=>1]);ck(!MasteryService::snapshot(1)['legacy_plan']&&MasteryService::snapshot(1)['respec_available_at']===0,'explicit conversion is free despite old cooldown');
 $db->execute('DELETE FROM player_lord_talents WHERE player_id=1 AND world_id=1');$db->execute("INSERT INTO player_lord_talents(player_id,world_id,talent_code,rank)VALUES(1,1,'attack_2',1)");ck(MasteryService::bonuses(1)===[],'disconnected archived plan cannot grant bonuses');
 echo "PASS $checks Hunter constellation checks in disposable database.\n";
}catch(Throwable $e){fwrite(STDERR,(string)$e."\n");$exit=1;}finally{$fixture?->close();}exit($exit);
