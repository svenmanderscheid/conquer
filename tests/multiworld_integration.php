<?php
declare(strict_types=1);
/** Empty throwaway database: copies schema only; never creates or changes live players. */
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
define('ROOT_DIR',dirname(__DIR__));define('APP_BASE','/conquer');date_default_timezone_set('UTC');
require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
use Conquer\Db\Connection;
use Conquer\Admin\AdminService;
use Conquer\Auth\{Session,OAuth};
use Conquer\Game\World\{WorldContext,WorldService};
use Conquer\Game\Kingdom\{KingdomService,KingdomInventory};
use Conquer\Game\Community\CommunityService;
use Conquer\Game\Expedition\{ExpeditionService,ExpeditionException};
use Conquer\Game\Trading\MarketService;
use Conquer\Game\World\{WorldSettings,WorldSpawnService};
set_error_handler(static function(int $severity,string $message,string $file,int $line):never{throw new ErrorException($message,0,$severity,$file,$line);});
$cfg=require ROOT_DIR.'/config/database.php';
$server=new PDO('mysql:host='.$cfg['host'].';port='.($cfg['port']??3306).';charset=utf8mb4',$cfg['username'],$cfg['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$schema='conquer_world_test_'.bin2hex(random_bytes(6));$original=$cfg['database'];$created=false;$failed=false;
function verifyAdmin(bool $condition,string $label):void{if(!$condition)throw new RuntimeException($label);echo "PASS $label\n";}
function rejectsAdmin(callable $callback,string $label):void{try{$callback();}catch(InvalidArgumentException|DomainException|ExpeditionException $e){verifyAdmin(true,$label);return;}throw new RuntimeException('Accepted invalid request: '.$label);}
function adminAction(string $action,array $body=[],?string $op=null,int $admin=1):array{return AdminService::execute($admin,$action,array_replace(['world_id'=>1,'player_id'=>1,'reason'=>'Isolated integration test','operation_id'=>$op??bin2hex(random_bytes(16))],$body));}
try {
    if(!preg_match('/^[A-Za-z0-9_]+$/D',$original))throw new RuntimeException('Unsupported database name.');
    $server->exec('CREATE DATABASE `'.$schema.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');$created=true;
    $tables=$server->query('SHOW TABLES FROM `'.$original.'`')->fetchAll(PDO::FETCH_COLUMN);
    foreach($tables as $table){if(!preg_match('/^[a-zA-Z0-9_]+$/D',$table))continue;$server->exec('CREATE TABLE `'.$schema.'`.`'.$table.'` LIKE `'.$original.'`.`'.$table.'`');}
    $pdo=new PDO('mysql:host='.$cfg['host'].';port='.($cfg['port']??3306).';dbname='.$schema.';charset=utf8mb4',$cfg['username'],$cfg['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    $pdo->exec("SET time_zone='+00:00'");
    foreach(['0063_world_spawn_settings.sql'=>'world_spawn_settings','0064_admin_operations_gifts.sql'=>'admin_operations','0068_progression_and_events.sql'=>'world_event_settings'] as $file=>$table) {
        if(in_array($table,$tables,true)||!is_file(ROOT_DIR.'/migrations/'.$file))continue;
        \Conquer\Db\MigrationSql::apply($pdo,(string)file_get_contents(ROOT_DIR.'/migrations/'.$file));
    }
    $reflection=new ReflectionClass(Connection::class);$db=$reflection->newInstanceWithoutConstructor();$reflection->getProperty('pdo')->setValue($db,$pdo);$reflection->getProperty('instance')->setValue(null,$db);
    \Conquer\Db\MigrationSql::apply($pdo,(string)file_get_contents(ROOT_DIR.'/migrations/0065_reconcile_admin_audit.sql'));
    foreach(['0072_multiworld_context.sql','0073_city_anti_spy.sql'] as $file) \Conquer\Db\MigrationSql::apply($pdo,(string)file_get_contents(ROOT_DIR.'/migrations/'.$file));
    foreach(['0083_reward_overrides.sql','0084_land_progression.sql','0085_monster_charms.sql','0086_reward_world_revisions.sql','0087_charm_compatibility.sql'] as $file) \Conquer\Db\MigrationSql::apply($pdo,(string)file_get_contents(ROOT_DIR.'/migrations/'.$file));
    $db->execute("INSERT INTO worlds(id,name,slug,status,map_size,map_seed) VALUES(1,'Alpha','alpha','running',256,42),(2,'Beta','beta','running',256,42),(3,'Paused','paused','paused',256,42),(4,'Empty','empty','running',256,42)");
    $db->execute("INSERT INTO players(id,username,email,password_hash,gems) VALUES(1,'AlphaPlayer','alpha@example.invalid','unused',1000),(2,'BetaPlayer','beta@example.invalid','unused',500),(3,'GammaPlayer','gamma@example.invalid','unused',0)");
    OAuth::createDefaultCity($db,1,'AlphaPlayer',1);OAuth::createDefaultCity($db,2,'BetaPlayer',1);OAuth::createDefaultCity($db,2,'BetaPlayer',2);
    $city1=(int)$db->query('SELECT id FROM cities WHERE player_id=1 AND world_id=1')->fetchColumn();
    $db->execute("INSERT INTO sessions(id,player_id,token,csrf_token,ip_address,user_agent,expires_at,active_world_id)VALUES(1,1,?,?,'127.0.0.1','isolated test',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),1)",[str_repeat('a',64),str_repeat('b',64)]);
    $_COOKIE[Session::COOKIE_NAME]=str_repeat('a',64);$session=Session::current();
    verifyAdmin(WorldContext::id()===1&&(int)$session['active_world_id']===1,'session binds owned world');
    verifyAdmin(count(WorldService::state(1)['worlds'])===4,'world picker exposes status and owned worlds');
    $join=['action'=>'join','world_id'=>2,'expected_world_id'=>1,'request_id'=>'world_join_beta_00001'];
    $joined=WorldService::action($session,$join);$city2=(int)$joined['city_id'];
    verifyAdmin($city2!==$city1&&WorldContext::id()===2&&(int)$db->query('SELECT active_world_id FROM sessions WHERE id=1')->fetchColumn()===2,'join creates and persistently selects separate city');
    verifyAdmin((int)$db->query('SELECT COUNT(*) FROM city_buildings WHERE city_id=?',[$city2])->fetchColumn()===count(\Conquer\Game\City\CityState::BUILDING_CODES)&&(int)$db->query('SELECT COUNT(*) FROM shrines WHERE world_id=2')->fetchColumn()===5,'new world receives all canonical buildings and five landmarks');
    verifyAdmin(WorldService::action($session,$join)['duplicate']&&(int)$db->query('SELECT COUNT(*) FROM cities WHERE player_id=1')->fetchColumn()===2,'join retry creates neither extra city nor extra buildings');
    rejectsAdmin(fn()=>WorldService::action($session,array_replace($join,['world_id'=>4])),'operation ID cannot be reused with altered target');
    rejectsAdmin(fn()=>WorldService::action($session,['action'=>'join','world_id'=>4,'expected_world_id'=>1,'request_id'=>'stale_world_000001']),'stale expected world cannot spend or switch');
    rejectsAdmin(fn()=>WorldService::action($session,['action'=>'select','world_id'=>4,'expected_world_id'=>2,'request_id'=>'unowned_world_0001']),'select requires existing owned city');
    rejectsAdmin(fn()=>WorldService::action($session,['action'=>'join','world_id'=>3,'expected_world_id'=>2,'request_id'=>'paused_world_00001']),'paused world rejects new joins');
    WorldService::action($session,['action'=>'select','world_id'=>1,'expected_world_id'=>2,'request_id'=>'back_to_alpha_001']);WorldService::action($session,$join);
    verifyAdmin(WorldContext::id()===1,'replayed old join does not override a newer selection');
    try{WorldContext::run(2,static function(){throw new DomainException('expected');});}catch(DomainException){}
    verifyAdmin(WorldContext::id()===1,'background world override restores context after failure');
    rejectsAdmin(fn()=>WorldContext::current(2),'forged request world rejected');
    $a=KingdomService::action(1,['action'=>'alliance.create','name'=>'Alpha Guild','tag'=>'ALP','description'=>'Alpha']);$aid1=$a['result']['alliance_id'];
    WorldContext::bind(2,1);$b=KingdomService::action(1,['action'=>'alliance.create','name'=>'Beta Guild','tag'=>'BET','description'=>'Beta']);$aid2=$b['result']['alliance_id'];
    verifyAdmin((int)$db->query('SELECT COUNT(*) FROM alliance_members WHERE player_id=1')->fetchColumn()===2&&(int)$b['state']['alliance']['id']===$aid2,'same global player holds independent memberships');
    WorldContext::bind(1,1);verifyAdmin((int)KingdomService::state(1)['alliance']['id']===$aid1,'switch restores original alliance');
    WorldContext::bind(2,2);rejectsAdmin(fn()=>KingdomService::action(2,['action'=>'alliance.join','alliance_id'=>$aid1]),'crossworld alliance join denied');
    // Keep passive production out of the exact spending assertion, including
    // when another local test briefly holds a server-wide advisory lock.
    $db->execute('UPDATE cities SET last_resource_update=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR) WHERE id IN (?,?)',[$city1,$city2]);
    WorldContext::bind(1,1);$res1=$db->query('SELECT food,lumber FROM cities WHERE id=?',[$city1])->fetch();$res2=$db->query('SELECT food,lumber FROM cities WHERE id=?',[$city2])->fetch();
    WorldContext::bind(2,1);MarketService::exchange(1,'food_lumber');
    verifyAdmin((int)$db->query('SELECT food FROM cities WHERE id=?',[$city2])->fetchColumn()===(int)$res2['food']-1000&&(int)$db->query('SELECT food FROM cities WHERE id=?',[$city1])->fetchColumn()===(int)$res1['food'],'market spends selected city resources only');
    verifyAdmin(count(MarketService::state(1)['history'])===1,'market history selected world visible');WorldContext::bind(1,1);verifyAdmin(count(MarketService::state(1)['history'])===0,'other world market history hidden');
    $node=array_key_first(\Conquer\Game\Research\ResearchData::allNodes());$db->execute('INSERT INTO player_research(player_id,world_id,research_code,level)VALUES(1,2,?,2)',[$node]);
    verifyAdmin(KingdomService::state(1)['profile']['stats']['research']===0,'research ranking isolated from other world');WorldContext::bind(2,1);verifyAdmin(KingdomService::state(1)['profile']['stats']['research']===2,'selected world research included');
    $chat=['action'=>'chat.send','channel'=>'world','message'=>'Only Beta sees this','request_id'=>'chat_beta_only_001'];CommunityService::action(1,$chat);
    verifyAdmin(count(CommunityService::state(1)['world_chat'])===1,'world chat stores current world');CommunityService::action(1,$chat);verifyAdmin(count(CommunityService::state(1)['world_chat'])===1,'community replay does not send duplicate');
    WorldContext::bind(1,1);verifyAdmin(count(CommunityService::state(1)['world_chat'])===0,'other world chat private');rejectsAdmin(fn()=>CommunityService::state(1,2),'forged community read rejected');rejectsAdmin(fn()=>CommunityService::action(1,$chat),'community receipt cannot be replayed into another world');
    $raid1=ExpeditionService::action(1,['action'=>'create','name'=>'Alpha Expedition']);$rid1=(int)$raid1['expedition_id'];WorldContext::bind(2,1);$raid2=ExpeditionService::action(1,['action'=>'create','name'=>'Beta Expedition']);$rid2=(int)$raid2['expedition_id'];
    verifyAdmin(count($raid2['state']['expeditions'])===1&&(int)$raid2['state']['expeditions'][0]['id']===$rid2,'expedition state and active limit per world');
    rejectsAdmin(fn()=>ExpeditionService::action(1,['action'=>'cancel','expedition_id'=>$rid1]),'foreign world expedition ID cannot mutate');
    $db->execute("UPDATE expeditions SET phase='victory',completed_at=UTC_TIMESTAMP() WHERE id IN (?,?)",[$rid1,$rid2]);
    $db->execute('INSERT INTO expedition_participants(expedition_id,player_id,alliance_id,city_id,contribution)VALUES(?,1,?,?,100),(?,1,?,?,100)',[$rid1,$aid1,$city1,$rid2,$aid2,$city2]);
    $food1=(int)$db->query('SELECT food FROM cities WHERE id=?',[$city1])->fetchColumn();$food2=(int)$db->query('SELECT food FROM cities WHERE id=?',[$city2])->fetchColumn();ExpeditionService::action(1,['action'=>'claim','expedition_id'=>$rid2]);
    verifyAdmin((int)$db->query('SELECT food FROM cities WHERE id=?',[$city2])->fetchColumn()>$food2&&(int)$db->query('SELECT food FROM cities WHERE id=?',[$city1])->fetchColumn()===$food1,'expedition receipt credits persisted city only');
    rejectsAdmin(fn()=>ExpeditionService::action(1,['action'=>'claim','expedition_id'=>$rid2]),'expedition reward cannot duplicate');WorldContext::bind(1,1);ExpeditionService::action(1,['action'=>'claim','expedition_id'=>$rid1]);verifyAdmin((int)$db->query('SELECT COUNT(*) FROM expedition_rewards WHERE player_id=1')->fetchColumn()===2,'expedition reward cooldown isolated per world');
    $troop=(int)array_key_first(\Conquer\Game\City\TroopData::all());$db->execute("INSERT INTO expedition_missions(expedition_id,player_id,city_id,alliance_id,objective,status,troops_json,potential_damage,arrival_at,return_at)VALUES(?,1,?,?,'boss','returning',?,10,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 MINUTE),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 MINUTE))",[$rid2,$city2,$aid2,json_encode([$troop=>17])]);
    ExpeditionService::tick(1);ExpeditionService::tick(1);
    verifyAdmin(WorldContext::id()===1&&(int)$db->query('SELECT count FROM city_troops WHERE city_id=? AND troop_code=?',[$city2,$troop])->fetchColumn()===17&&!(int)$db->query('SELECT count FROM city_troops WHERE city_id=? AND troop_code=?',[$city1,$troop])->fetchColumn(),'background expedition returns credit origin world exactly once');
    WorldContext::bind(2,1);$purchase=['action'=>'inventory.buy','item_code'=>10102061,'quantity'=>1,'request_id'=>'shield_purchase_001'];$gems=(int)$db->query('SELECT gems FROM players WHERE id=1')->fetchColumn();
    rejectsAdmin(fn()=>KingdomService::action(1,$purchase),'current crystal economy rejects shield purchases');
    verifyAdmin((int)$db->query('SELECT gems FROM players WHERE id=1')->fetchColumn()===$gems,'rejected shield purchase preserves crystals');
    // Shields are earned items now; seed only this disposable player's inventory.
    \Conquer\Game\Inventory\InventoryService::addItems(1,10102061,1);
    \Conquer\Game\Inventory\InventoryService::addItems(1,10102051,1);
    KingdomService::action(1,['action'=>'inventory.use','item_code'=>10102061]);verifyAdmin((bool)$db->query('SELECT shield_expires_at FROM cities WHERE id=?',[$city2])->fetchColumn()&&!$db->query('SELECT shield_expires_at FROM cities WHERE id=?',[$city1])->fetchColumn(),'shield item protects active city only');
    KingdomService::action(1,['action'=>'inventory.use','item_code'=>10102051]);verifyAdmin((bool)$db->query('SELECT anti_spy_until FROM cities WHERE id=?',[$city2])->fetchColumn()&&!$db->query('SELECT anti_spy_until FROM cities WHERE id=?',[$city1])->fetchColumn(),'anti-spy item is city scoped');
    rejectsAdmin(fn()=>\Conquer\Game\Inventory\InventoryService::useItem(1,$city1,10103001)['ok']?null:throw new DomainException('wrong city'),'legacy inventory path rejects foreign world city');
    $db->execute("UPDATE worlds SET status='paused' WHERE id=2");rejectsAdmin(fn()=>MarketService::exchange(1,'food_lumber'),'paused world blocks spending');rejectsAdmin(fn()=>KingdomService::action(1,['action'=>'inventory.buy','item_code'=>10102061,'request_id'=>'paused_buy_000001']),'paused world blocks shop');verifyAdmin(count(WorldService::state(1)['worlds'])===4,'paused owned world remains visible');
    echo "ALL MULTIWORLD CHECKS PASSED\n";
}catch(Throwable $e){$failed=true;fwrite(STDERR,'FAIL '.$e->getMessage().' at '.$e->getFile().':'.$e->getLine()."\n".$e->getTraceAsString()."\n");}
finally{if($created&&preg_match('/^conquer_world_test_[a-f0-9]{12}$/D',$schema))$server->exec('DROP DATABASE `'.$schema.'`');}
exit($failed?1:0);
