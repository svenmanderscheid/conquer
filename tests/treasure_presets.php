<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));require ROOT_DIR.'/src/Bootstrap.php';\Conquer\Bootstrap::init(ROOT_DIR);
require __DIR__.'/Support/FeatureDatabase.php';
use Conquer\Db\Connection;
use Conquer\Game\Treasure\TreasureService as T;
use Conquer\Game\Kingdom\KingdomService as K;
use Conquer\Game\World\WorldContext;
use Conquer\Game\City\{CityState,BuildingData};
use Conquer\Game\Research\BuffEngine;
$fixture=new \ConquerTests\FeatureDatabase();$db=Connection::getInstance();$checks=0;$exit=0;
function ck(bool $ok,string $message):void{global$checks;if(!$ok)throw new RuntimeException($message);$checks++;}
function reject(callable $fn,string $message):void{try{$fn();}catch(DomainException){ck(true,$message);return;}throw new RuntimeException('Allowed: '.$message);}
function slots(int $world=1):array{global$db;$items=array_fill(0,6,null);foreach($db->query('SELECT slot,treasure_code FROM player_treasure_loadouts WHERE player_id=1 AND world_id=?',[$world])->fetchAll()as$r)$items[(int)$r['slot']-1]=$r['treasure_code']===null?null:(int)$r['treasure_code'];return$items;}
try{
 foreach(['0075_lord_talents.sql','0076_treasure_presets.sql']as$file)if(is_file(ROOT_DIR.'/migrations/'.$file))\Conquer\Db\MigrationSql::apply($db->getPdo(),file_get_contents(ROOT_DIR.'/migrations/'.$file));
 $db->execute("UPDATE worlds SET status='open',speed_factor=1 WHERE id=1");$db->execute("INSERT INTO worlds(id,name,slug,status)VALUES(2,'Preset second','preset-second','open')");WorldContext::bind(1);
 $db->execute("INSERT INTO players(id,username,email,password_hash)VALUES(1,'PresetFixture','preset@tests.invalid','unused'),(2,'OtherPreset','other-preset@tests.invalid','unused')");
 foreach([1,2]as$world){
  $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,food,lumber,stone,gold,castle_level)VALUES(?,1,?,'Fixture',30,40,10000,10000,10000,10000,5)",[$world,$world]);
  foreach(CityState::BUILDING_CODES as$code)$db->execute('INSERT INTO city_buildings(city_id,building_code,level)VALUES(?,?,?)',[$world,$code,$code==='treasure_house'?25:5]);
 }
 $state=K::state(1)['treasures'];ck(count($state['presets'])===5,'five presets exposed');
 foreach($state['presets']as$i=>$p)ck($p===['slot'=>$i+1,'saved'=>false,'items'=>array_fill(0,6,null)],'unsaved shape');
 reject(fn()=>T::applyPreset(1,1),'unsaved rejected');
 foreach([0,6,-1]as$p){reject(fn()=>T::savePreset(1,$p),'save range');reject(fn()=>T::applyPreset(1,$p),'apply range');}
 foreach(['2.5',null,[],6]as$p)reject(fn()=>K::action(1,['action'=>'treasure.preset_save','preset'=>$p]),'request integer validation');
 foreach([60100001,60100002,60100003,60100004,60100005,60200001]as$code)T::addFragments(1,$code,100);
 $codes=[60100001,60100002,60100003,60100004,60100005,60200001];
 foreach($codes as$i=>$code)ck(T::equipTreasure(1,$code,$i+1,25),'six slots equip');
 $saved=K::action(1,['action'=>'treasure.preset_save','preset'=>1,'items'=>[99999999]])['state']['treasures']['presets'][0];
 ck($saved===['slot'=>1,'saved'=>true,'items'=>$codes],'server saves actual equipment ignoring client items');
 foreach($codes as$code)T::unequipTreasure(1,$code);T::savePreset(1,2);ck(T::getPresets(1)[1]['saved'],'saved empty distinct from unsaved');
 $result=K::action(1,['action'=>'treasure.preset_apply','preset'=>1]);ck(slots()===$codes,'full six slots restored');ck($result['state']['treasures']['bonuses']===T::getEquippedStats(1),'API returns updated bonuses');
 T::equipTreasure(1,$codes[0],2,25);T::equipTreasure(1,$codes[1],1,25);T::applyPreset(1,1);ck(slots()===$codes,'swaps do not conflict with unique treasure constraint');
 for($i=3;$i<=5;$i++)T::savePreset(1,$i);ck(count(array_filter(T::getPresets(1),fn($p)=>$p['saved']))===5,'all five slots stored independently');
 ck(!T::getPresets(1,2)[0]['saved']&&!T::getPresets(2,1)[0]['saved'],'player and world isolation');
 reject(fn()=>T::applyPreset(1,1,2),'cannot load other world preset');
 T::equipTreasure(1,60100002,1,25,2);T::savePreset(1,1,2);$worldTwo=slots(2);T::applyPreset(1,2,1);ck(slots()===array_fill(0,6,null)&&slots(2)===$worldTwo,'empty preset clears local slots only');
 T::applyPreset(1,1);$before=slots();
 try{$db->transaction(function(){T::applyPreset(1,2);throw new DomainException('rollback');});}catch(DomainException){}
 ck(slots()===$before,'outer transaction rollback restores full equipment');
 try{$db->transaction(function(){T::savePreset(1,2);throw new DomainException('rollback');});}catch(DomainException){}
 ck(T::getPresets(1)[1]['items']===array_fill(0,6,null),'outer transaction rollback restores preset');
 $db->execute("UPDATE city_buildings SET level=1 WHERE city_id=1 AND building_code='treasure_house'");reject(fn()=>T::applyPreset(1,1),'locked equipment slot rejected');ck(slots()===$before,'locked rejection leaves equipment intact');$db->execute("UPDATE city_buildings SET level=25 WHERE city_id=1 AND building_code='treasure_house'");
 $db->execute('UPDATE player_treasures SET fragments=0 WHERE player_id=1 AND treasure_code=?',[$codes[5]]);reject(fn()=>T::applyPreset(1,1),'lost ownership rejected');ck(slots()===$before,'ownership rejection atomic');$db->execute('UPDATE player_treasures SET fragments=100 WHERE player_id=1 AND treasure_code=?',[$codes[5]]);
 foreach([[$codes[0],$codes[0],null,null,null,null],[99999999,null,null,null,null,null],[60100001],['60100001',null,null,null,null,null]]as$bad){$db->execute('UPDATE player_treasure_presets SET items_json=? WHERE player_id=1 AND world_id=1 AND preset=5',[json_encode($bad)]);reject(fn()=>T::applyPreset(1,5),'invalid stored preset rejected');ck(slots()===$before,'invalid stored preset changes nothing');}T::savePreset(1,5);
 $db->execute("UPDATE worlds SET status='paused' WHERE id=1");reject(fn()=>T::applyPreset(1,1),'paused apply rejected');reject(fn()=>T::savePreset(1,1),'paused save rejected');$db->execute("UPDATE worlds SET status='open' WHERE id=1");
 reject(fn()=>K::action(1,['action'=>'treasure.preset_apply','preset'=>1,'expected_world_id'=>2]),'stale world action rejected');
 T::applyPreset(1,2);T::equipTreasure(1,60100002,1,25);T::savePreset(1,3);$rate=BuildingData::getHourlyRate('lumber_camp',5);$bonus=BuffEngine::getBuffs(1,1)['lumber_production'];
 $db->execute('UPDATE cities SET lumber=0,last_resource_update=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 HOUR) WHERE id=1');T::applyPreset(1,2);$earned=(int)$db->query('SELECT lumber FROM cities WHERE id=1')->fetchColumn();ck(abs($earned-floor($rate*(1+$bonus)))<=2,'switch settles earned production at old boosted rate');ck((BuffEngine::getBuffs(1,1)['lumber_production']??0)===0.0,'unequipped boost stops');
 $db->execute('UPDATE cities SET lumber=0,last_resource_update=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 HOUR) WHERE id=1');T::applyPreset(1,3);$earned=(int)$db->query('SELECT lumber FROM cities WHERE id=1')->fetchColumn();ck(abs($earned-$rate)<=2,'switch to boost does not retroactively boost income');
 \Conquer\Db\MigrationSql::apply($db->getPdo(),file_get_contents(ROOT_DIR.'/migrations/0076_treasure_presets.sql'));ck(T::getPresets(1)[0]['items']===$codes,'migration repetition preserves saved presets');
 echo "PASS {$checks} treasure preset checks.\n";
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n".$e->getTraceAsString()."\n");$exit=1;}finally{$fixture->close();}exit($exit);
