<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';date_default_timezone_set('UTC');\Conquer\Logger::init(sys_get_temp_dir().'/conquer-lord-test.log','ERROR');
use Conquer\Db\Connection;
use Conquer\Game\Player\{LordLevel,MasteryService,TalentEffects,ActionPoints};
use Conquer\Game\Research\{BuffEngine,ResearchEffects};
use Conquer\Game\World\WorldContext;
use Conquer\Game\City\{CityState,BuildingData};
use Conquer\Game\March\{MarchDispatcher,BattleEngine};
use Conquer\Game\Expedition\ExpeditionRules;
use Conquer\Game\Operation;
$checks=0;$fixture=null;$exit=0;
function ck(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException($label);$checks++;echo 'PASS '.$label."\n";}
function reject(callable $fn,string $label):void{try{$fn();}catch(DomainException|RuntimeException){ck(true,$label);return;}throw new RuntimeException('Allowed: '.$label);}
function near(float $a,float $b):bool{return abs($a-$b)<1e-6;}
function fullBranch(string $code):array{$r=[];for($i=0;$i<9;$i++)$r[$code.'_'.$i]=5;return $r;}
function plan(array $ranks,int $world=1):array{$s=MasteryService::snapshot(1,$world);return ['action'=>'mastery.apply','ranks'=>$ranks,'revision'=>$s['revision'],'expected_world_id'=>$world,'operation_key'=>'test_'.bin2hex(random_bytes(14))];}
function applyPlan(array $ranks,int $world=1):array{$b=plan($ranks,$world);return Operation::run(1,$b,fn()=>MasteryService::change(1,$b,$world));}
try{
 $fixture=new \ConquerTests\FeatureDatabase();$db=Connection::getInstance();
 $db->execute("INSERT INTO worlds(id,name,slug,status,map_size) VALUES(2,'Testwelt','lord-world','running',256)");
 $db->execute("INSERT INTO players(id,username,email,password_hash,lord_xp) VALUES(1,'LordFixture','lord@tests.invalid','unused',12345)");
 foreach([1,2]as$w){$db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,castle_level,food,lumber,stone,gold) VALUES(?,1,?,'Talentstadt',40,40,12,100000,100000,100000,100000)",[$w,$w]);foreach(CityState::BUILDING_CODES as$code)$db->execute('INSERT INTO city_buildings(city_id,building_code,level) VALUES(?,?,?)',[$w,$code,$code==='castle'?12:5]);}
 \Conquer\Db\MigrationSql::apply($db->getPdo(),file_get_contents(ROOT_DIR.'/migrations/0075_lord_talents.sql'));
 ck(LordLevel::snapshot(1,1)['xp']===12345&&LordLevel::snapshot(1,2)['xp']===0,'legacy XP preserved once in oldest world');
 $db->execute('UPDATE player_lord_progress SET xp=0 WHERE player_id=1');
 \Conquer\Db\MigrationSql::apply($db->getPdo(),file_get_contents(ROOT_DIR.'/migrations/0075_lord_talents.sql'));
 ck(LordLevel::snapshot(1,1)['xp']===0,'migration replay never restores spent/changed progress');
 ck(MasteryService::snapshot(1)['available']===1,'new lord starts with one point independent of castle');
 ck(count(MasteryService::nodes())===36,'36 talents across four branches');
 for($level=2;$level<=60;$level++){ck(LordLevel::levelFromTotalXp(LordLevel::totalForLevel($level))===$level,'level threshold '.$level);ck(LordLevel::levelFromTotalXp(LordLevel::totalForLevel($level)-1)===$level-1,'just below level '.$level);}
 foreach(MasteryService::nodes()as$n)ck(is_file(ROOT_DIR.'/'.$n['icon']),'icon '.$n['code']);
 LordLevel::addXp(1,250,1,'kill:1');LordLevel::addXp(1,250,1,'kill:1');
 ck(LordLevel::snapshot(1)['xp']===250&&MasteryService::snapshot(1)['earned']===2,'kill receipt cannot grant duplicate XP or points');
 ck(MasteryService::snapshot(1,2)['earned']===1,'world XP is isolated');
 reject(fn()=>MasteryService::validate(['attack_2'=>1],60),'locked child rejected');
 reject(fn()=>MasteryService::validate(['attack_0'=>6],60),'rank six rejected');
 reject(fn()=>MasteryService::validate(['attack_0'=>'1'],60),'string rank rejected');
 reject(fn()=>MasteryService::validate(['unknown'=>1],60),'unknown talent rejected');
 reject(fn()=>MasteryService::validate(['attack_0'=>3],2),'overspending rejected');
 LordLevel::addXp(1,LordLevel::totalForLevel(60),1,'test-cap');
 ck(MasteryService::snapshot(1)['earned']===60,'60 point cap');$atCap=LordLevel::snapshot(1)['xp'];LordLevel::addXp(1,999,1,'over-cap');ck(LordLevel::snapshot(1)['xp']===$atCap,'no further XP above cap');
 $primary=fullBranch('attack');unset($primary['attack_7']);
 ck(array_sum(MasteryService::validate($primary,60))===40,'40 point primary branch reaches final talent');
 reject(fn()=>MasteryService::validate($primary+fullBranch('defense'),60),'two complete branches exceed budget');
 reject(fn()=>MasteryService::validate($primary,39),'capstone requires Lord 40');
 $broken=$primary;$broken['attack_4']=0;reject(fn()=>MasteryService::validate($broken,60),'removing a prerequisite invalidates descendants');
 $body=plan(['gather_0'=>5,'gather_1'=>5]);$run=fn()=>Operation::run(1,$body,fn()=>MasteryService::change(1,$body));$run();$run();
 ck(MasteryService::snapshot(1)['revision']===1&&MasteryService::snapshot(1)['spent']===10,'atomic plan and operation retry');
 ck(near(BuffEngine::getBuffs(1)['food_production'],.10),'production talent reaches actual buff engine');
 ck(near(BuffEngine::getBuffs(1,2)['food_production'],0),'talent buffs are isolated by world');
 reject(fn()=>MasteryService::change(1,$body),'stale revision cannot overwrite new distribution');
 applyPlan([]);ck(MasteryService::snapshot(1)['available']===60,'first respec returns earned budget');
 applyPlan(['attack_0'=>1]);reject(fn()=>applyPlan([]),'24 hour cooldown prevents repeated respec');
 applyPlan(['attack_0'=>2]);ck(MasteryService::snapshot(1)['spent']===2,'adding new points does not consume or require respec');
 $db->execute("INSERT INTO marches(player_id,world_id,march_type,origin_city_id,target_x,target_y,target_type,target_id,troops_json,departure_time,arrival_time,state) VALUES(1,1,5,1,45,45,3,999,'{}',UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),'marching')");
 reject(fn()=>applyPlan(['attack_0'=>3]),'active army blocks bonus switching');
 $db->execute("UPDATE marches SET state='returned'");
 $db->execute('UPDATE player_lord_progress SET last_respec_at=NULL');applyPlan(fullBranch('gather'));
 $buffs=BuffEngine::getBuffs(1);ck(ResearchEffects::limits($buffs)['gather_march_slots']===1,'gather capstone grants exactly one reserved slot');
 ck(ResearchEffects::carryCapacity([50100101=>100],TalentEffects::gather($buffs))===12420,'gather carry talent changes actual load');
 ck(ResearchEffects::carryCapacity([50100101=>100],$buffs)===10800,'gather carry cannot increase city plunder');
 $city=CityState::loadForPlayer(1);ck(BuildingData::getBuildTime('castle',20,$city['vip']['bonuses'])<BuildingData::getBuildTime('castle',20,[]),'construction talent changes build timer');
 for($i=0;$i<3;$i++)$db->execute("INSERT INTO marches(player_id,world_id,march_type,origin_city_id,target_x,target_y,target_type,target_id,troops_json,departure_time,arrival_time,state) VALUES(1,1,5,1,45,45,3,999,'{}',UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),'marching')");
 MarchDispatcher::assertSlotAvailable(1,1,true);ck(true,'reserved slot permits gather after three combat marches');reject(fn()=>MarchDispatcher::assertSlotAvailable(1,1),'reserved slot rejects fourth combat march');
 $db->execute("UPDATE marches SET march_type=9 WHERE player_id=1 AND state='marching' LIMIT 1");MarchDispatcher::assertSlotAvailable(1,1);ck(true,'gathering does not steal the third regular combat slot');
 $db->execute("UPDATE marches SET state='returned'");
 $db->execute('UPDATE player_lord_progress SET last_respec_at=NULL');applyPlan(fullBranch('hunter'));$buffs=BuffEngine::getBuffs(1);
 ck(near($buffs['vs_monster_attack'],.30),'all hunter attack ranks combine');
 ck(near(TalentEffects::combat($buffs,'monster',true)['vs_monster_attack'],.40),'boss attack applies only to own PvE army');
 ck(near((float)(TalentEffects::combat($buffs,'pvp')['troops_atk']??0),0),'hunter attack never becomes PvP attack');
 ck(ExpeditionRules::missionCapacity($buffs)===5500,'Rudeljaeger increases expedition capacity');
 ck(ExpeditionRules::strength([50100101=>100],'boss',$buffs)>ExpeditionRules::strength([50100101=>100],'boss',[]),'hunter changes real boss contribution');
 $loot=TalentEffects::monsterLoot(['food'=>100,'lumber'=>100,'gems'=>100,'fragments'=>100,'xp'=>100],$buffs);
 ck($loot['food']===110&&$loot['lumber']===110&&$loot['gems']===100&&$loot['fragments']===100&&$loot['xp']===100,'loot bonus excludes special drops and XP');
 $db->execute('UPDATE players SET action_points=0,last_ap_regen=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 3000 SECOND) WHERE id=1');
 ActionPoints::get(1);$ap=(int)$db->query('SELECT action_points FROM players WHERE id=1')->fetchColumn();ck($ap===11,'AP regeneration applies previous earned rate');
 WorldContext::bind(2,1);ActionPoints::get(1);$db->execute('UPDATE players SET last_ap_regen=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 3000 SECOND) WHERE id=1');$ap=ActionPoints::get(1);ck($ap['current']===21&&near($ap['regen_per_hour'],12),'world switch settles old rate and uses new world rate');WorldContext::bind(1,1);
 $db->execute('UPDATE player_lord_progress SET last_respec_at=NULL');applyPlan(fullBranch('defense'));$buffs=BuffEngine::getBuffs(1);$def=TalentEffects::combat($buffs,'city_defense');
 ck(near($def['troops_def'],.10)&&near($def['troops_hp'],.05)&&near($def['troops_atk'],.05),'city defense effects reach only defensive army context');
 ck(near((float)(TalentEffects::combat($buffs,'monster')['troops_def']??0),0),'city defense cannot improve hunting');
 ck(near($buffs['hospital_capacity'],.15)&&near($buffs['healing_speed'],.10),'hospital talent units remain fractional');
 $db->execute('UPDATE player_lord_progress SET last_respec_at=NULL');applyPlan(fullBranch('attack'));$buffs=BuffEngine::getBuffs(1);$atk=TalentEffects::combat($buffs,'pvp',true);
 ck(near($atk['troops_atk'],.25)&&near($atk['troops_hp'],.05),'PvP rally uses owner attack and HP bonuses');
 ck(ResearchEffects::limits($buffs)['march_capacity']===23171,'army capacity is percentage, not extra slot');
 ck(near(ResearchEffects::training(50100101,$buffs)['speed_multiplier'],1.10),'training talent changes queue speed');
 echo "OK: $checks lord talent checks.\n";
}catch(Throwable $e){fwrite(STDERR,(string)$e."\n");$exit=1;}finally{$fixture?->close();}exit($exit);
