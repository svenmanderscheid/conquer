<?php
declare(strict_types=1);
/** Current star progression, migration and world loadout integration. Disposable DB only. */
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';date_default_timezone_set('UTC');
use Conquer\Db\{Connection,MigrationSql};
use Conquer\Game\Treasure\{TreasureData,TreasureService as T};
use Conquer\Game\World\WorldContext;
use Conquer\Game\Research\BuffEngine;
use Conquer\Game\City\{CityState,BuildingData};
$checks=0;$fixture=null;$exit=0;
function tc(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException($label);++$checks;echo "PASS $label\n";}
function near(float $actual,float $expected):bool{return abs($actual-$expected)<1e-7;}
function rejectTreasure(callable $fn,string $label):void{try{$fn();}catch(DomainException){tc(true,$label);return;}throw new RuntimeException('Accepted '.$label);}
try{
 $fixture=new \ConquerTests\FeatureDatabase();$db=Connection::getInstance();WorldContext::bind(1);
 $db->execute("UPDATE worlds SET status='open',speed_factor=1 WHERE id=1");
 $db->execute("INSERT INTO worlds(id,name,slug,status)VALUES(2,'Relic second','relic-second','open'),(3,'Relic future','relic-future','open')");
 $db->execute("INSERT INTO players(id,username,email,password_hash)VALUES(1,'RelicFixture','relic@tests.invalid','unused')");
 foreach([1,2] as $world){
  $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,food,lumber,stone,gold,castle_level)VALUES(?,1,?,'Relic city',30,40,10000000,10000000,10000000,10000000,30)",[$world,$world]);
  foreach(CityState::BUILDING_CODES as $code)$db->execute('INSERT INTO city_buildings(city_id,building_code,level)VALUES(?,?,?)',[$world,$code,$code==='treasure_house'?25:($code==='castle'?30:5)]);
 }
 $db->execute('INSERT INTO player_treasures(player_id,treasure_code,fragments,equipped_slot)VALUES(1,60400002,80,1),(1,60100107,10,2)');
 $loadoutSql=(string)file_get_contents(ROOT_DIR.'/migrations/0074_treasure_loadouts.sql');$starSql=(string)file_get_contents(ROOT_DIR.'/migrations/0116_treasure_effect_progress.sql');
 MigrationSql::apply($db->getPdo(),$loadoutSql);MigrationSql::apply($db->getPdo(),$loadoutSql);MigrationSql::apply($db->getPdo(),$starSql);
 $before=$db->query('SELECT treasure_code,fragments FROM player_treasures ORDER BY treasure_code')->fetchAll();$balances=array_column($before,'fragments','treasure_code');
 tc((int)$balances[60400002]===70&&(int)$balances[60100107]===0,'first-star migration charges exactly ten fragments once');
 MigrationSql::apply($db->getPdo(),$starSql);tc($db->query('SELECT treasure_code,fragments FROM player_treasures ORDER BY treasure_code')->fetchAll()===$before,'repeated first-star migration preserves every balance');
 tc(T::getEquippedStats(1,1)===T::getEquippedStats(1,2)&&T::getEquippedStats(1,1)!==[],'saved legacy equipment survives in both worlds');
 $catalog=T::state(1);tc(count($catalog['items'])===72&&$catalog['slots']===6&&$catalog['slot_unlock_levels']===[1,1,5,10,20,25],'71 active cards plus one owned non-mythic legacy card are shown');
 tc(!in_array(60400002,TreasureData::getCodesByGrade('legendary'),true),'legacy equipment stays outside the random pool');
 foreach($catalog['items'] as $item)tc($item['is_usable']&&$item['preview_stats']!==[]&&is_file(ROOT_DIR.'/assets/art/items/'.$item['icon']),'usable illustrated card '.$item['treasure_code']);
 tc(!T::equipTreasure(1,60500001,1,25),'unowned legacy relic rejected');
 T::addFragments(1,60200002,9);tc(!T::equipTreasure(1,60200002,1,25),'nine fragments do not unlock');
 $unlock=T::addFragments(1,60200002,1);tc($unlock['newly_unlocked']&&$unlock['fragments']===0&&T::equipTreasure(1,60200002,1,25),'tenth fragment unlocks first star; zero spare fragments retain ownership');
 foreach([0,7,-1] as $slot)tc(!T::equipTreasure(1,60100107,$slot,25),'invalid slot rejected '.$slot);
 tc(!T::equipTreasure(1,99999999,1,25),'unknown code rejected');
 $db->execute("UPDATE city_buildings SET level=1 WHERE city_id=1 AND building_code='treasure_house'");tc(!T::equipTreasure(1,60100107,6,25),'client cannot forge unlocked slots');$db->execute("UPDATE city_buildings SET level=25 WHERE city_id=1 AND building_code='treasure_house'");
 T::equipTreasure(1,60100107,1,25);T::equipTreasure(1,60100107,2,25);
 $slots=$db->query('SELECT slot,treasure_code FROM player_treasure_loadouts WHERE player_id=1 AND world_id=1 ORDER BY slot')->fetchAll(PDO::FETCH_KEY_PAIR);tc($slots[1]===null&&(int)$slots[2]===60100107,'moving clears the old slot');
 try{$db->transaction(function(){T::equipTreasure(1,60200002,2,25);throw new DomainException('rollback');});}catch(DomainException){}
 tc($db->query('SELECT slot,treasure_code FROM player_treasure_loadouts WHERE player_id=1 AND world_id=1 ORDER BY slot')->fetchAll(PDO::FETCH_KEY_PAIR)===$slots,'rollback restores displaced equipment');
 tc(near(BuffEngine::getBuffs(1,1)['lumber_production'],.005),'first part supplies 0.5 percent production');T::addFragments(1,60100107,1000);tc(near(BuffEngine::getBuffs(1,1)['lumber_production'],.005),'stockpiling fragments does not upgrade effects');
 $rate=BuildingData::getHourlyRate('lumber_camp',5,\Conquer\Game\Vip\VipService::status(1)['bonuses']);$db->execute('UPDATE cities SET lumber=0,last_resource_update=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 HOUR) WHERE id IN(1,2)');T::upgradeEffect(1,60100107,0);
 foreach([1,2] as $world){tc(abs((int)$db->query('SELECT lumber FROM cities WHERE id=?',[$world])->fetchColumn()-floor($rate*1.005))<=2,'upgrade settles old income in world '.$world);tc(near(BuffEngine::getBuffs(1,$world)['lumber_production'],.0125),'upgrade applies in equipped world '.$world);}
 for($i=0;$i<3;$i++)T::upgradeEffect(1,60100107,0);tc(near(BuffEngine::getBuffs(1,1)['lumber_production'],.10),'fifth part adds five-percent master bonus');
 rejectTreasure(fn()=>T::upgradeEffect(1,60100107,0),'maximum star cannot upgrade');rejectTreasure(fn()=>T::upgradeEffect(1,60100107,99),'unknown effect rejected');
 T::unequipTreasure(1,60100107,1);tc(empty(BuffEngine::getBuffs(1,1)['lumber_production'])&&near(BuffEngine::getBuffs(1,2)['lumber_production'],.10),'unequip affects only selected world');
 MigrationSql::apply($db->getPdo(),$loadoutSql);tc(T::getEquippedStats(1,1)===[],'migration retry does not resurrect equipment');
 $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y)VALUES(3,1,3,'Future',30,40)");$db->execute("INSERT INTO city_buildings(city_id,building_code,level)VALUES(3,'treasure_house',25)");MigrationSql::apply($db->getPdo(),$loadoutSql);tc(T::getEquippedStats(1,3)===[],'future world starts empty');
 $token=str_repeat('e',64);$db->execute("INSERT INTO sessions(player_id,token,csrf_token,ip_address,user_agent,expires_at,active_world_id)VALUES(1,?,'relic-csrf','127.0.0.1','test',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),1)",[$token]);
 $base=$fixture->serve('switch(parse_url($_SERVER["REQUEST_URI"],PHP_URL_PATH)){case "/action":\Conquer\Api\Handlers\KingdomHandler::action([]);break;default:http_response_code(404);}');
 $request=static function(array $body,bool $csrf=true)use($base,$token):array{$c=curl_init($base.'/action');curl_setopt_array($c,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($body),CURLOPT_HTTPHEADER=>['Content-Type: application/json','Cookie: conquer_session='.$token,'X-CSRF-Token: '.($csrf?'relic-csrf':'wrong')],CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15]);$raw=curl_exec($c);$status=(int)curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);return[$status,json_decode((string)$raw,true)];};
 [$status,$result]=$request(['action'=>'treasure.equip','treasure_code'=>60100107,'slot'=>1]);tc($status===200&&near($result['data']['state']['treasures']['bonuses']['lumber_production'],10),'real API returns upgraded equipped bonuses');
 tc($request(['action'=>'treasure.unequip','treasure_code'=>60100107],false)[0]===403,'HTTP validates CSRF');tc($request(['action'=>'treasure.equip','treasure_code'=>60100107,'slot'=>1,'expected_world_id'=>2])[0]===422,'HTTP rejects stale world');
 $db->execute("UPDATE worlds SET status='paused' WHERE id=1");tc($request(['action'=>'treasure.unequip','treasure_code'=>60100107])[0]===422,'HTTP rejects paused world');$db->execute("UPDATE worlds SET status='open' WHERE id=1");
 [$status,$result]=$request(['action'=>'treasure.unequip','treasure_code'=>60100107]);tc($status===200&&$result['data']['state']['treasures']['bonuses']===[],'HTTP unequip clears bonuses');
 echo "ALL $checks TREASURE CHECKS PASSED (isolated database, current star contract).\n";
}catch(Throwable $e){fwrite(STDERR,'FAIL '.$e->getMessage()."\n".$e->getTraceAsString()."\n");$exit=1;}finally{if($fixture)$fixture->close();}exit($exit);
