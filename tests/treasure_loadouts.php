<?php
declare(strict_types=1);
/** Full treasure regressions run against a disposable local database and HTTP server. */
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();date_default_timezone_set('UTC');
use Conquer\Db\Connection;
use Conquer\Game\Treasure\{TreasureData,TreasureService};
use Conquer\Game\Research\{BuffEngine,ResearchEffects};
use Conquer\Game\World\WorldContext;
use Conquer\Game\City\{CityState,BuildingData,BuildingUpgrader,TroopTrainer,TroopData};
use Conquer\Game\Hospital\HospitalService;
use Conquer\Game\Expedition\ExpeditionRules;
use Conquer\Game\Defense\DefenseService;
$checks=0;$catalogSize=count(TreasureData::all());
function tc(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException($label);$checks++;echo 'PASS '.$label."\n";}
function near(float $a,float $b):bool{return abs($a-$b)<1.0e-7;}
function tr(callable $fn,string $label):void{try{$fn();}catch(DomainException $e){tc(true,$label);return;}throw new RuntimeException($label.' allowed');}
function equip(int $code,int $slot=1,int $world=1):void{tc(TreasureService::equipTreasure(1,$code,$slot,25,$world),'equip '.$code.' world'.$world.' slot'.$slot);}
function clearLoadout(int $world=1):void{global $db;foreach($db->query('SELECT treasure_code FROM player_treasure_loadouts WHERE player_id=1 AND world_id=? AND treasure_code IS NOT NULL',[$world])->fetchAll(PDO::FETCH_COLUMN)as$code)TreasureService::unequipTreasure(1,(int)$code,$world);}
function httpTreasure(string $path,array $body,int $status=200,bool $csrf=true):array{global $base,$token;$ch=curl_init($base.$path);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($body),CURLOPT_HTTPHEADER=>['Content-Type: application/json','Cookie: conquer_session='.$token,'X-CSRF-Token: '.($csrf?'treasure-test':'wrong')],CURLOPT_TIMEOUT=>15]);if($path==='/kingdom-state')curl_setopt($ch,CURLOPT_HTTPGET,true);$raw=curl_exec($ch);$actual=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);$json=json_decode((string)$raw,true);tc($actual===$status&&is_array($json),$path.' HTTP'.$status.($actual!==$status?' '.$raw:''));return$json;}
$cfg=require ROOT_DIR.'/config/database.php';if(!in_array($cfg['host']??'',['localhost','127.0.0.1'],true))exit("Local MySQL required.\n");
$name='conquer_treasure_test_'.bin2hex(random_bytes(6));$temp=sys_get_temp_dir().DIRECTORY_SEPARATOR.$name;$admin=null;$server=null;$exit=0;
try{
 $source=$cfg['database'];if(!preg_match('/^[a-zA-Z0-9_]+$/D',$source))throw new RuntimeException('Invalid source database');
 $admin=new PDO('mysql:host='.$cfg['host'].';port='.($cfg['port']??3306).';charset=utf8mb4',$cfg['username'],$cfg['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 $admin->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
 foreach($admin->query('SHOW TABLES FROM `'.$source.'`')->fetchAll(PDO::FETCH_COLUMN)as$table)$admin->exec('CREATE TABLE `'.$name.'`.`'.$table.'` LIKE `'.$source.'`.`'.$table.'`');
 $admin->exec('INSERT INTO `'.$name.'`.worlds SELECT * FROM `'.$source.'`.worlds WHERE id=1');
 mkdir($temp.'/config',0700,true);$cfg['database']=$name;file_put_contents($temp.'/config/database.php',"<?php\nreturn ".var_export($cfg,true).';');
 $db=Connection::init($temp);\Conquer\Logger::init($temp.'/test.log');WorldContext::bind(1);
 $db->execute("UPDATE worlds SET status='open',speed_factor=1 WHERE id=1");
 $db->execute("INSERT INTO worlds(id,name,slug,status) VALUES(2,'Fixture two','fixture-two','open'),(3,'Fixture future','fixture-three','open')");
 $db->execute("INSERT INTO players(id,username,email,password_hash) VALUES(1,'TreasureFixture','treasure@invalid.test','unused'),(2,'EmptyFixture','empty@invalid.test','unused')");
 for($id=1;$id<=2;$id++){
  $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,food,lumber,stone,gold,castle_level) VALUES(?,1,?,'Test city',30,40,10000000,10000000,10000000,10000000,30)",[$id,$id]);
  foreach(CityState::BUILDING_CODES as$code)$db->execute('INSERT INTO city_buildings(city_id,building_code,level) VALUES(?,?,?)',[$id,$code,in_array($code,['castle','academy'])?30:($code==='treasure_house'?25:5)]);
 }
 $db->execute('INSERT INTO player_treasures(player_id,treasure_code,fragments,equipped_slot) VALUES(1,60100002,10,1),(1,60200001,20,2)');
 $migration=file_get_contents(ROOT_DIR.'/migrations/0074_treasure_loadouts.sql');$db->getPdo()->exec($migration);$db->getPdo()->exec($migration);
 tc((int)$db->query('SELECT COUNT(*) FROM player_treasure_loadouts')->fetchColumn()===12,'migration idempotently creates six slots for each existing world');
 tc(TreasureService::getEquippedStats(1,1)===TreasureService::getEquippedStats(1,2),'existing equipment preserved in both existing worlds');
 $catalog=TreasureService::state(1);tc(count($catalog['items'])===$catalogSize&&$catalog['slots']===6&&$catalog['house_level']===25&&$catalog['slot_unlock_levels']===[1,1,5,10,20,25],'complete catalog and authoritative slot thresholds');
 foreach($catalog['items']as$item){tc($item['preview_stats']!==[]&&$item['is_usable']&&$item['unsupported_stats']===[],'actual effect preview '.$item['treasure_code']);if($item['level']===0)tc($item['stats_at_level']===[]&&$item['fragments']===0&&!$item['is_unlocked']&&$item['next_level_stats']===$item['preview_stats'],'unowned state '.$item['treasure_code']);}
 tc(!TreasureService::equipTreasure(1,60500001,1,25),'unowned treasure rejected');
 TreasureService::addFragments(1,60200002,19);tc(!TreasureService::equipTreasure(1,60200002,1,25),'rare treasure requires all20 fragments, not10');
 $unlock=TreasureService::addFragments(1,60200002,1);tc($unlock['newly_unlocked']&&$unlock['level']===1,'fragment threshold unlocks automatically');
 foreach([0,7,-1]as$slot)tc(!TreasureService::equipTreasure(1,60100002,$slot,25),'invalid slot '.$slot.' rejected');
 tc(!TreasureService::equipTreasure(1,99999999,1,25),'unknown code rejected');
 $db->execute("UPDATE city_buildings SET level=1 WHERE city_id=1 AND building_code='treasure_house'");tc(!TreasureService::equipTreasure(1,60100002,6,25),'caller cannot forge higher house level');
 $db->execute("UPDATE city_buildings SET level=25 WHERE city_id=1 AND building_code='treasure_house'");
 TreasureService::addFragments(1,60100001,10);equip(60100001,1);equip(60100001,2);
 $slots=$db->query('SELECT slot,treasure_code FROM player_treasure_loadouts WHERE player_id=1 AND world_id=1 ORDER BY slot')->fetchAll(PDO::FETCH_KEY_PAIR);
 tc($slots[1]===null&&(int)$slots[2]===60100001,'moving replaces target occupant and clears source atomically');
 tc(isset(TreasureService::getEquippedStats(1,2)['lumber_production'])&&!isset(TreasureService::getEquippedStats(1,1)['lumber_production']),'replacement affects only selected world');
 $before=$slots;try{$db->transaction(function(){equip(60100002,2);throw new DomainException('fixture rollback');});}catch(DomainException){}
 tc($db->query('SELECT slot,treasure_code FROM player_treasure_loadouts WHERE player_id=1 AND world_id=1 ORDER BY slot')->fetchAll(PDO::FETCH_KEY_PAIR)===$before,'failed enclosing transaction restores displaced treasure and all slots');
 clearLoadout();$db->getPdo()->exec($migration);tc(TreasureService::getEquippedStats(1,1)===[],'repeated migration never resurrects removed legacy equipment');
 $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y) VALUES(3,1,3,'Future city',30,40)");$db->execute("INSERT INTO city_buildings(city_id,building_code,level) VALUES(3,'treasure_house',25)");$db->getPdo()->exec($migration);
 tc(TreasureService::getEquippedStats(1,3)===[],'world joined after migration starts empty even if migration repeats');
 tc((BuffEngine::getBuffs(1,1)['lumber_production']??0)===0.0&&near(BuffEngine::getBuffs(1,2)['lumber_production'],.02),'BuffEngine uses explicit world rather than request world');
 $baseRate=BuildingData::getHourlyRate('lumber_camp',5);
 $db->execute('UPDATE cities SET lumber=0,last_resource_update=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 HOUR) WHERE id=1');equip(60100002);
 $earned=(int)$db->query('SELECT lumber FROM cities WHERE id=1')->fetchColumn();tc(abs($earned-$baseRate)<=2,'equip settles past production at previous unboosted rate');
 $db->execute('UPDATE cities SET lumber=0,last_resource_update=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 HOUR) WHERE id=1');TreasureService::unequipTreasure(1,60100002);
 $earned=(int)$db->query('SELECT lumber FROM cities WHERE id=1')->fetchColumn();tc(abs($earned-floor($baseRate*1.02))<=2,'unequip settles past production at old boosted rate');
 tc((BuffEngine::getBuffs(1,1)['lumber_production']??0)===0.0,'unequip immediately removes live production bonus');
 equip(60100002);$db->execute('UPDATE cities SET lumber=0,last_resource_update=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 HOUR) WHERE id IN(1,2)');TreasureService::addFragments(1,60100002,10);
 foreach([1,2]as$id)tc(abs((int)$db->query('SELECT lumber FROM cities WHERE id=?',[$id])->fetchColumn()-floor($baseRate*1.02))<=2,'fragment level-up settles old production in equipped world'.$id);
 tc(near(BuffEngine::getBuffs(1,1)['lumber_production'],.025)&&near(BuffEngine::getBuffs(1,2)['lumber_production'],.025),'account fragments level up equipped treasure in each world');
 // Own fixture collection permits checking every consumer without granting any live account gifts.
 foreach(TreasureData::all()as$code=>$def){$have=(int)$db->query('SELECT fragments FROM player_treasures WHERE player_id=1 AND treasure_code=?',[$code])->fetchColumn();$target=(int)$def['fragments_per_level'];if($have<$target)TreasureService::addFragments(1,$code,$target-$have);}
 // Exercise every catalogue treasure through the real world loadout and buff adapter.
 $aliases=['all_attack'=>'troops_atk','all_defense'=>'troops_def','all_hp'=>'troops_hp','infantry_defense'=>'infantry_def','ranged_attack'=>'ranged_atk','cavalry_attack'=>'cavalry_atk'];
 foreach(TreasureService::getPlayerTreasures(1)as$item){
  clearLoadout();$ok=TreasureService::equipTreasure(1,$item['treasure_code'],1,25);$live=BuffEngine::getBuffs(1,1);
  foreach($item['stats_at_level']as$key=>$amount){$flat=in_array($key,['march_capacity','hospital_capacity'],true);$canonical=$flat?$key.'_flat':($aliases[$key]??$key);$ok=$ok&&isset($live[$canonical])&&near((float)$live[$canonical],$flat?$amount:$amount/100);}
  tc($ok,'all actual effects applied when equipping catalogue treasure '.$item['treasure_code']);
 }
 clearLoadout();equip(60400001);$buffs=BuffEngine::getBuffs(1);
 tc(near(BuffEngine::effectiveMultiplier($buffs,'infantry','hp'),1.04)&&near(BuffEngine::effectiveMultiplier($buffs,'ranged','hp'),1),'infantry HP remains type-specific and active');
 tc(near(BuffEngine::effectiveMultiplier($buffs,'infantry','atk'),1.08)&&near(BuffEngine::effectiveMultiplier($buffs,'cavalry','def'),1.05),'all-army attack and defense aliases reach combat engine');
 tc(ExpeditionRules::strength([50100101=>100],'boss',$buffs)>ExpeditionRules::strength([50100101=>100],'boss',[]),'equipped attack increases actual expedition strength');
 equip(60400004,2);$buffs=BuffEngine::getBuffs(1);tc(near(BuffEngine::effectiveMultiplier($buffs,'infantry','hp'),1.09)&&near(BuffEngine::effectiveMultiplier($buffs,'ranged','hp'),1.05),'all HP combines additively with infantry HP');
 tc(near($buffs['vs_monster_attack'],.08),'monster attack modifier is supplied to combat');
 clearLoadout();equip(60400002);$buffs=BuffEngine::getBuffs(1);
 tc(ResearchEffects::limits($buffs)['march_capacity']===51000,'flat march capacity remains1000 places rather than percentage');
 tc(ExpeditionRules::missionTiming($buffs)['march_seconds']<ExpeditionRules::missionTiming([])['march_seconds'],'equipped march speed shortens real expedition travel');
 tc(near($buffs['gathering_speed'],.06),'gathering speed uses canonical gather modifier');
 $baseHospital=HospitalService::getStatus(1)['capacity'];equip(60500002,2);$buffs=BuffEngine::getBuffs(1);
 tc(HospitalService::getStatus(1)['capacity']===$baseHospital+2000,'hospital gains exactly2000 real wounded places');
 tc(near(DefenseService::protectionFraction($buffs),.2),'resource protection reaches city defense loot protection');
 clearLoadout();equip(60300004);TreasureService::addFragments(1,60300004,360); // Staff level10=6.5% (2+9*.5)
 $snap=CityState::loadForPlayer(1);tc(near($snap['vip']['bonuses']['construction_speed'],6.5),'construction speed keeps fractional6.5 percent in live city state');
 $db->execute('UPDATE cities SET food=10000000,lumber=10000000,stone=10000000,gold=10000000 WHERE id=1');$snap=CityState::loadForPlayer(1);
 $queue=BuildingUpgrader::start(1,'farm',$snap['city'],$snap['buildings'],$snap['vip']['level'],$snap['vip']['bonuses']);$seconds=strtotime($queue['finishes_at'])-strtotime($queue['started_at']);
 tc($seconds===(int)round(BuildingData::getBuildTime('farm',6)*.935),'actual building queue uses fractional treasure bonus');$db->execute('DELETE FROM building_queue WHERE city_id=1');
 clearLoadout();equip(60400003);$snap=CityState::loadForPlayer(1);TroopTrainer::train($snap['city'],$snap['buildings'],50100101,100);
 $seconds=(int)$db->query('SELECT TIMESTAMPDIFF(SECOND,started_at,finishes_at) FROM troop_queue WHERE city_id=1')->fetchColumn();tc($seconds===(int)ceil(TroopData::trainingSeconds(50100101,100)/1.04),'actual troop queue duration reflects treasure training speed');
 // Real HTTP handlers, session/CSRF and JSON parsing, against the same isolated DB.
 $token=bin2hex(random_bytes(32));$db->execute("INSERT INTO sessions(player_id,token,csrf_token,ip_address,user_agent,expires_at,active_world_id) VALUES(1,?,'treasure-test','127.0.0.1','isolated treasure test',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 10 MINUTE),1)",[$token]);
 $router="<?php define('ROOT_DIR',".var_export(ROOT_DIR,true).");require ROOT_DIR.'/src/Autoloader.php';(new \\Conquer\\Autoloader(ROOT_DIR.'/src'))->register();date_default_timezone_set('UTC');\\Conquer\\Db\\Connection::init(".var_export($temp,true).");\\Conquer\\Logger::init(".var_export($temp.'/http.log',true).");try{switch(parse_url(\$_SERVER['REQUEST_URI'],PHP_URL_PATH)){case '/kingdom-action':\\Conquer\\Api\\Handlers\\KingdomHandler::action([]);break;case '/kingdom-state':\\Conquer\\Api\\Handlers\\KingdomHandler::state([]);break;case '/research':\\Conquer\\Api\\Handlers\\ResearchHandler::start([]);break;case '/equip':\\Conquer\\Api\\Handlers\\TreasureHandler::equip([]);break;case '/unequip':\\Conquer\\Api\\Handlers\\TreasureHandler::unequip([]);break;}}catch(\\DomainException \$e){\\Conquer\\Api\\Response::error(\$e->getCode()?:400,'DOMAIN',\$e->getMessage());}";
 file_put_contents($temp.'/router.php',$router);$socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);$address=stream_socket_get_name($socket,false);fclose($socket);$base='http://'.$address;
 $server=proc_open([PHP_BINARY,'-S',$address,$temp.'/router.php'],[0=>['pipe','r'],1=>['file',$temp.'/server.log','a'],2=>['file',$temp.'/server.log','a']],$pipes,$temp);fclose($pipes[0]);
 $ready=false;for($i=0;$i<40;$i++){$probe=@fsockopen('127.0.0.1',(int)substr(strrchr($address,':'),1),$errno,$error,.1);if($probe){fclose($probe);$ready=true;break;}usleep(50000);}if(!$ready)throw new RuntimeException('Fixture server did not start');
 httpTreasure('/research',['code'=>'food_production','level_to'=>1]);$seconds=(int)$db->query('SELECT TIMESTAMPDIFF(SECOND,started_at,finishes_at) FROM research_queue WHERE player_id=1')->fetchColumn();$research=\Conquer\Game\Research\ResearchData::get('food_production')['levels'][0];tc($seconds===(int)round($research['time']*.9),'actual research queue receives10 percent treasure speed');
 httpTreasure('/equip',['treasure_code'=>60100002,'slot'=>1],403,false);
 foreach([true,1.5,'1.5',[]]as$invalid)httpTreasure('/equip',['treasure_code'=>60100002,'slot'=>$invalid],400);
 httpTreasure('/equip',['treasure_code'=>60100002,'slot'=>1,'expected_world_id'=>2],409);
 httpTreasure('/equip',['treasure_code'=>60100002,'slot'=>1]);tc(isset(TreasureService::getEquippedStats(1)['lumber_production'])&&!isset(TreasureService::getEquippedStats(1)['research_speed']),'actual endpoint atomically replaces occupied slot');
 $db->execute("UPDATE worlds SET status='paused' WHERE id=1");httpTreasure('/unequip',['treasure_code'=>60100002],409);tc(isset(TreasureService::getEquippedStats(1)['lumber_production']),'paused world cannot mutate loadout');$db->execute("UPDATE worlds SET status='open' WHERE id=1");
 httpTreasure('/unequip',['treasure_code'=>60100002]);tc(!isset(TreasureService::getEquippedStats(1)['lumber_production']),'actual unequip endpoint removes bonus');
 $state=httpTreasure('/kingdom-state',[]);tc(count($state['data']['treasures']['items'])===$catalogSize&&$state['data']['treasures']['world_id']===1,'real kingdom state exposes complete world-scoped treasure contract');
 httpTreasure('/kingdom-action',['action'=>'treasure.equip','treasure_code'=>60100002,'slot'=>1]);
 $result=httpTreasure('/kingdom-action',['action'=>'treasure.equip','treasure_code'=>60500001,'slot'=>1,'expected_world_id'=>1]);$treasures=$result['data']['state']['treasures'];
 tc(count($treasures['items'])===$catalogSize&&near($treasures['bonuses']['all_attack'],15)&&!isset($treasures['bonuses']['lumber_production']),'real KingdomHandler action replaces occupied slot and returns complete updated catalog state and bonuses');
 $card=array_values(array_filter($treasures['items'],fn($item)=>$item['treasure_code']===60400003))[0];tc($card['name_de']==='Spiegel der Wahrheit'&&$card['icon']==='treasures/mirror-of-truth.png'&&$card['icon_framed']===true&&$card['grade']==='mythic','Kingdom HTTP includes original art, German name and matching frame rarity');
 $selected=array_values(array_filter($treasures['items'],fn($item)=>$item['treasure_code']===60500001))[0];tc($selected['equipped_slot']===1&&$selected['level']===1,'Kingdom response marks exactly the newly equipped treasure');
 $result=httpTreasure('/kingdom-action',['action'=>'treasure.unequip','treasure_code'=>60500001,'expected_world_id'=>1]);tc($result['data']['state']['treasures']['bonuses']===[],'real KingdomHandler unequip returns state with removed bonuses');
 httpTreasure('/kingdom-action',['action'=>'treasure.equip','treasure_code'=>60100002,'slot'=>1],403,false);
 httpTreasure('/kingdom-action',['action'=>'treasure.equip','treasure_code'=>60100002,'slot'=>1,'expected_world_id'=>2],422);
 $db->execute("UPDATE worlds SET status='paused' WHERE id=1");httpTreasure('/kingdom-action',['action'=>'treasure.equip','treasure_code'=>60100002,'slot'=>1],422);$db->execute("UPDATE worlds SET status='open' WHERE id=1");
 tc(TreasureService::getEquippedStats(1,1)===[],'denied Kingdom actions leave equipment empty');
 echo 'ALL '.$checks." TREASURE CHECKS PASSED (isolated database).\n";
}catch(Throwable $e){fwrite(STDERR,'FAIL '.$e->getMessage()."\n".$e->getTraceAsString()."\n");$exit=1;}
finally{
 if(is_resource($server)){proc_terminate($server);proc_close($server);}
 if($admin&&preg_match('/^conquer_treasure_test_[a-f0-9]{12}$/D',$name))$admin->exec('DROP DATABASE IF EXISTS `'.$name.'`');
 $resolved=realpath($temp);$parent=realpath(sys_get_temp_dir());
 if($resolved!==false&&$parent!==false&&str_replace('\\','/',$resolved)===str_replace('\\','/',$parent).'/'.$name&&preg_match('/^conquer_treasure_test_[a-f0-9]{12}$/D',basename($resolved))){foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($resolved,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST)as$item){if($item->isDir()&&!$item->isLink())rmdir($item->getPathname());else unlink($item->getPathname());}rmdir($resolved);}
}
exit($exit);
