<?php
declare(strict_types=1);
/** Daily claims and real rewards in disposable DB; no live account gifts. */
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));require ROOT_DIR.'/src/Bootstrap.php';\Conquer\Bootstrap::init(ROOT_DIR);
require __DIR__.'/Support/FeatureDatabase.php';
use Conquer\Db\Connection;
use Conquer\Game\Treasure\ChestService;
use Conquer\Game\World\WorldContext;
$fixture=new \ConquerTests\FeatureDatabase();$db=Connection::getInstance();$checks=0;$exit=0;$server=null;$temporary=[];
function cc(bool $ok,string $label):void{global$checks;if(!$ok)throw new RuntimeException($label);$checks++;}
function denied(callable $fn,string $label):void{try{$fn();}catch(DomainException){cc(true,$label);return;}throw new RuntimeException($label.' permitted');}
function snapshot():array{global$db;return[$db->query('SELECT * FROM player_chests WHERE player_id=1')->fetch(),$db->query('SELECT item_code,quantity FROM player_inventory WHERE player_id=1 ORDER BY item_code')->fetchAll(),$db->query('SELECT treasure_code,fragments FROM player_treasures WHERE player_id=1 ORDER BY treasure_code')->fetchAll()];}
function openChecked(string $type,bool $free=true):array{
 global$db;
 $oldItems=$db->query('SELECT item_code,quantity FROM player_inventory WHERE player_id=1')->fetchAll(PDO::FETCH_KEY_PAIR);
 $oldFragments=$db->query('SELECT treasure_code,fragments FROM player_treasures WHERE player_id=1')->fetchAll(PDO::FETCH_KEY_PAIR);
 $rewards=$free?ChestService::openFreeChest(1,$type):ChestService::openChest(1,$type);
 cc(count($rewards)>0,'chest yields real rewards');$items=[];$fragments=[];
 foreach($rewards as$r){$target=$r['type']==='item'?'items':'fragments';$code=$r[$r['type']==='item'?'item_code':'treasure_code'];${$target}[$code]=(${$target}[$code]??0)+$r['quantity'];cc(!empty($r['name']),'reward name');}
 foreach($items as$code=>$amount)cc((int)$db->query('SELECT quantity FROM player_inventory WHERE player_id=1 AND item_code=?',[$code])->fetchColumn()===(int)($oldItems[$code]??0)+$amount,'item credited to actual inventory');
 foreach($fragments as$code=>$amount)cc((int)$db->query('SELECT fragments FROM player_treasures WHERE player_id=1 AND treasure_code=?',[$code])->fetchColumn()===(int)($oldFragments[$code]??0)+$amount,'fragments credited');
 return$rewards;
}
try{
 $sql=file_get_contents(ROOT_DIR.'/migrations/0077_daily_chests.sql');\Conquer\Db\MigrationSql::apply($db->getPdo(),$sql);\Conquer\Db\MigrationSql::apply($db->getPdo(),$sql);
 $db->execute("INSERT INTO players(id,username,email,password_hash,gems)VALUES(1,'ChestFixture','chest@test.invalid','unused',100),(2,'OtherChest','other@test.invalid','unused',0)");
 $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y)VALUES(1,1,1,'Chest city',30,40)");
 denied(fn()=>ChestService::openFreeChest(1,'silver'),'requires a treasure house');
 $db->execute("INSERT INTO city_buildings(city_id,building_code,level)VALUES(1,'treasure_house',1)");
 $s=ChestService::getChestStatus(1);cc($s['free_silver_remaining']===10&&$s['free_silver_available']&&$s['free_gold_available'],'initially ten blue and one gold claim');
 cc(str_ends_with($s['server_time'],'+00:00')&&strtotime($s['free_silver_resets_at'])>time(),'explicit UTC clock and reset');
 openChecked('silver');$s=ChestService::getChestStatus(1);cc($s['free_silver_remaining']===9&&!$s['free_silver_available']&&abs(strtotime($s['free_silver_next_at'])-time()-600)<3,'first blue applies ten minute cooldown');
 $before=snapshot();denied(fn()=>ChestService::openFreeChest(1,'silver'),'double free click');denied(fn()=>ChestService::openChest(1,'silver'),'legacy endpoint cannot bypass cooldown');cc(snapshot()===$before,'rejected opens leave counters and rewards unchanged');
 cc(ChestService::getChestStatus(2)['free_silver_available'],'another account independent');
 WorldContext::bind(2);cc(!ChestService::getChestStatus(1)['free_silver_available'],'same cooldown in another world');WorldContext::bind(1);
 ChestService::addChest(1,'silver',2);openChecked('silver',false);$s=ChestService::getChestStatus(1);cc($s['silver_count']===1&&$s['free_silver_remaining']===9,'owned silver during cooldown consumed independently');
 for($i=2;$i<=10;$i++){$db->execute('UPDATE player_chests SET last_free_silver_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 601 SECOND) WHERE player_id=1');openChecked('silver');cc(ChestService::getChestStatus(1)['free_silver_remaining']===10-$i,'blue daily count '.$i);}
 $s=ChestService::getChestStatus(1);cc(!$s['free_silver_available']&&strtotime($s['free_silver_next_at'])>=strtotime($s['free_silver_resets_at']),'daily exhaustion exposes next eligible time');
 $before=snapshot();denied(fn()=>ChestService::openFreeChest(1,'silver'),'eleventh free rejected');cc(snapshot()===$before,'exhausted free never spends owned silver');
 openChecked('silver',false);cc(ChestService::getChestStatus(1)['silver_count']===0,'owned chest works when daily free quota exhausted');
 $db->execute('UPDATE player_chests SET last_free_silver_reset=DATE_SUB(UTC_DATE(),INTERVAL 1 DAY) WHERE player_id=1');$s=ChestService::getChestStatus(1);cc($s['free_silver_remaining']===10&&!$s['free_silver_available'],'day rollover resets quota but preserves ten minute interval');
 $db->execute('UPDATE player_chests SET last_free_silver_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 601 SECOND) WHERE player_id=1');openChecked('silver');cc(ChestService::getChestStatus(1)['free_silver_remaining']===9,'new UTC day counter commits from zero');
 openChecked('gold');$s=ChestService::getChestStatus(1);cc(!$s['free_gold_available']&&abs(strtotime($s['free_gold_next_at'])-time()-86400)<3,'gold starts rolling 24h timer');
 $before=snapshot();denied(fn()=>ChestService::openFreeChest(1,'gold'),'duplicate free gold rejected');cc(snapshot()===$before,'gold rejection unchanged');
 ChestService::addChest(1,'gold');openChecked('gold',false);cc(!ChestService::getChestStatus(1)['free_gold_available'],'owned gold does not change free availability');
 $db->execute('UPDATE player_chests SET last_free_gold_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 86300 SECOND) WHERE player_id=1');denied(fn()=>ChestService::openFreeChest(1,'gold'),'calendar rollover is insufficient for gold');
 $db->execute('UPDATE player_chests SET last_free_gold_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 86401 SECOND) WHERE player_id=1');openChecked('gold');
 ChestService::addChest(1,'platinum');openChecked('platinum',false);cc(ChestService::getChestStatus(1)['platinum_count']===0,'legacy platinum unaffected');
 denied(fn()=>ChestService::openFreeChest(1,'platinum'),'no unintended free platinum');
 // Pure boundary calculations with authoritative server timestamps, no production clock override.
 $status=new ReflectionMethod(ChestService::class,'status');$now=strtotime('2026-09-12 00:00:00 UTC');$row=['silver_count'=>0,'gold_count'=>0,'platinum_count'=>0,'free_silver_used_today'=>10,'last_free_silver_reset'=>'2026-09-11','last_free_silver_at'=>'2026-09-11 23:55:00','last_free_gold_at'=>'2026-09-11 00:00:00'];
 $s=$status->invoke(null,$row,$now);cc($s['free_silver_remaining']===10&&!$s['free_silver_available']&&$s['free_gold_available'],'precise midnight and rolling gold boundary');
 cc(!$status->invoke(null,$row,$now+299)['free_silver_available']&&$status->invoke(null,$row,$now+300)['free_silver_available'],'blue opens exactly at 600s');
 // Enclosing Kingdom transactions can roll back BOTH rewards and timer mutations.
 $db->execute('UPDATE player_chests SET last_free_gold_at=NULL WHERE player_id=1');$before=snapshot();try{$db->transaction(function(){ChestService::openFreeChest(1,'gold');throw new DomainException('rollback');});}catch(DomainException){}cc(snapshot()===$before,'outer transaction rolls back free gold and rewards');
 $before=snapshot();$gems=(int)$db->query('SELECT gems FROM players WHERE id=1')->fetchColumn();
 foreach(['gold','platinum'] as $type)denied(fn()=>ChestService::purchaseAndOpenChest(1,$type),'crystal chest purchases rejected');
 cc(snapshot()===$before&&(int)$db->query('SELECT gems FROM players WHERE id=1')->fetchColumn()===$gems,'rejected purchases leave currency and rewards unchanged');
 // Invalid free rewards must still roll back the cooldown.
 $cache=new ReflectionProperty(ChestService::class,'dropTableCache');$original=$cache->getValue();$cache->setValue(null,['chests'=>['gold'=>['rolls'=>1,'drop_table'=>[['item_code'=>99999999,'quantity'=>1,'weight'=>1]]]]]);
 $before=snapshot();try{ChestService::openFreeChest(1,'gold');throw new LogicException('invalid reward allowed');}catch(RuntimeException $e){cc(!($e instanceof LogicException),'invalid reward fails');}cc(snapshot()===$before,'reward failure rolls back free chest cooldown');$cache->setValue(null,$original);

 // Two independent database connections contend for the same free gold claim.
 $dir=(new ReflectionProperty($fixture,'directory'))->getValue($fixture);
 $worker=$dir.'/chest-worker.php';$temporary[]=$worker;
 $boot="<?php define('ROOT_DIR',".var_export(ROOT_DIR,true).");require ROOT_DIR.'/src/Autoloader.php';(new \\Conquer\\Autoloader(ROOT_DIR.'/src'))->register();date_default_timezone_set('UTC');\\Conquer\\Db\\Connection::init(".var_export($dir,true).");";
 file_put_contents($worker,$boot."try{\\Conquer\\Game\\Treasure\\ChestService::openFreeChest(1,'gold');echo 'opened';}catch(\\DomainException \$e){echo 'denied';}");
 $db->execute('UPDATE player_chests SET last_free_gold_at=NULL WHERE player_id=1');
 $db->query("SELECT GET_LOCK('conquer-player-1',5)");$processes=[];
 for($i=0;$i<2;$i++){$p=proc_open([PHP_BINARY,$worker],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$processes[]=[$p,$pipes];}
 $db->query("SELECT RELEASE_LOCK('conquer-player-1')");$answers=[];
 foreach($processes as[$p,$pipes]){$answers[]=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);cc(proc_close($p)===0&&$errors==='','concurrent worker succeeds');}
 sort($answers);cc($answers===['denied','opened'],'concurrent requests grant exactly one free gold');
 // Exercise the actual legacy handler with auth/CSRF and JSON mode selection.
 $token=bin2hex(random_bytes(32));$db->execute("INSERT INTO sessions(player_id,token,csrf_token,ip_address,user_agent,expires_at,active_world_id)VALUES(1,?,'chest-test','127.0.0.1','chesttest',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 10 MINUTE),1)",[$token]);
 $router=$dir.'/chest-router.php';$temporary[]=$router;$log=$dir.'/chest-http.log';$temporary[]=$log;
 file_put_contents($router,$boot."\\Conquer\\Api\\Handlers\\TreasureHandler::openChest([]);");
 $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);$address=stream_socket_get_name($socket,false);fclose($socket);
 $server=proc_open([PHP_BINARY,'-S',$address,$router],[0=>['pipe','r'],1=>['file',$log,'a'],2=>['file',$log,'a']],$pipes,$dir);fclose($pipes[0]);
 for($i=0;$i<40;$i++){$probe=@fsockopen('127.0.0.1',(int)substr(strrchr($address,':'),1),$errno,$error,.1);if($probe){fclose($probe);break;}usleep(50000);}
 $request=static function(array $body,int $expected,bool $csrf=true)use($address,$token):array{
  $curl=curl_init('http://'.$address.'/open-chest');curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($body),CURLOPT_HTTPHEADER=>['Content-Type: application/json','Cookie: conquer_session='.$token,'X-CSRF-Token: '.($csrf?'chest-test':'wrong')],CURLOPT_TIMEOUT=>10]);$raw=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);curl_close($curl);$json=json_decode((string)$raw,true);cc($status===$expected&&is_array($json),'handler HTTP'.$expected.' actual'.$status.($status!==$expected?': '.$raw:''));return$json;
 };
 $request(['chest_type'=>'silver','free'=>true],403,false);
 $request(['chest_type'=>'gold','free'=>true,'buy_with_gems'=>true],400);
 $request(['chest_type'=>'gold','free'=>true],400);
 $db->execute('UPDATE player_chests SET last_free_gold_at=NULL WHERE player_id=1');
 $result=$request(['chest_type'=>'gold','free'=>true],200);cc($result['data']['chest_status']['free_gold_available']===false&&count($result['data']['rewards'])>0,'handler free mode returns new timer and rewards');
 $request(['chest_type'=>'gold','free'=>true],400);
 $db->execute('UPDATE player_chests SET free_silver_used_today=0,last_free_silver_reset=UTC_DATE(),last_free_silver_at=NULL WHERE player_id=1');
 $request(['chest_type'=>'silver'],200);$request(['chest_type'=>'silver'],400);
 $before=snapshot();$request(['chest_type'=>'gold','buy_with_gems'=>true],400);cc(snapshot()===$before,'handler rejects crystal purchase without changing balances');
 $request(['chest_type'=>'gold','buy_with_gems'=>true],400);
 // The production Kingdom action returns a complete updated state, not just rewards.
 foreach(\Conquer\Game\City\CityState::BUILDING_CODES as$code)$db->execute('INSERT IGNORE INTO city_buildings(city_id,building_code,level)VALUES(1,?,1)',[$code]);
 $db->execute("INSERT IGNORE INTO kingdom_profiles(player_id,display_name,welcome_claimed)VALUES(1,'ChestFixture',1)");
 $db->execute('UPDATE player_chests SET last_free_gold_at=NULL WHERE player_id=1');
 $result=\Conquer\Game\Kingdom\KingdomService::action(1,['action'=>'chest.free','chest_type'=>'gold']);
 cc(count($result['result']['drops'])===4&&!$result['state']['chests']['free_gold_available'],'Kingdom action returns actual drops and new chest status');
 foreach($result['result']['drops']as$drop){
  if($drop['type']==='item')cc(count(array_filter($result['state']['inventory'],fn($i)=>(int)$i['item_code']===$drop['item_code']&&$i['quantity']>=$drop['quantity']))===1,'Kingdom updated inventory includes won item');
  else cc(count(array_filter($result['state']['treasures']['items'],fn($i)=>(int)$i['treasure_code']===$drop['treasure_code']&&$i['fragments']>=$drop['quantity']))===1,'Kingdom updated collection includes won fragments');
 }
 denied(fn()=>\Conquer\Game\Kingdom\KingdomService::action(1,['action'=>'chest.free','chest_type'=>'gold']),'Kingdom route cannot bypass gold cooldown');
 $table=json_decode(file_get_contents(ROOT_DIR.'/data/chest_drops.json'),true)['chests'];
 cc($table['gold']['rolls']>$table['silver']['rolls'],'gold grants more reward rolls');
 $valuableChance=static function(array $config):float{
  $all=0;$valuable=0;foreach($config['drop_table']as$drop){$all+=$drop['weight'];$grade=$drop['fragment_grade']??(\Conquer\Game\Inventory\InventoryService::getItemDef((int)($drop['item_code']??0))['rarity']??'normal');if(in_array($grade,['legendary','mythic'],true))$valuable+=$drop['weight'];}
  return 1-pow(1-$valuable/$all,$config['rolls']);
 };
 cc($valuableChance($table['gold'])>$valuableChance($table['silver']),'gold genuinely improves chance of legendary or mythic rewards');
 if(is_file(ROOT_DIR.'/data/trading_shop.json')){
  $catalog=json_decode(file_get_contents(ROOT_DIR.'/data/trading_shop.json'),true);
  foreach($catalog['vip']??[]as$offer)foreach(['silver','gold']as$type)cc(count(array_filter($table[$type]['drop_table'],fn($d)=>($d['item_code']??0)===$offer['item_code']&&$d['weight']>0))>0,'VIP offer eligible in '.$type);
 }
 echo 'PASS '.$checks." daily chest checks; isolated database removed.\n";
}catch(Throwable$e){$exit=1;fwrite(STDERR,$e->getMessage()."\n".$e->getTraceAsString()."\n");}finally{if(is_resource($server)){proc_terminate($server);proc_close($server);}foreach($temporary as$file)if(is_file($file))unlink($file);$fixture->close();}exit($exit);
