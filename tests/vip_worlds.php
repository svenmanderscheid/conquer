<?php
declare(strict_types=1);
/** World VIP migration, item isolation and one-village creation, on disposable data only. */
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));require ROOT_DIR.'/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();date_default_timezone_set('UTC');
use Conquer\Db\{Connection,MigrationSql};
use Conquer\Auth\{OAuth,Session};
use Conquer\Game\City\{CityState,ResourceTick};
use Conquer\Game\Inventory\InventoryService;
use Conquer\Game\Kingdom\KingdomService;
use Conquer\Game\Player\MasteryService;
use Conquer\Game\Research\BuffEngine;
use Conquer\Game\Trading\TradingShopService;
use Conquer\Game\Vip\VipService;
use Conquer\Game\World\{WorldContext,WorldService};

if(($argv[1]??'')==='create'){
    Connection::init($argv[2]);
    try{OAuth::createDefaultCity(Connection::getInstance(),4,'RaceFixture',(int)$argv[3]);echo 'created';}
    catch(DomainException $e){if($e->getCode()!==409)throw $e;echo 'blocked';}
    exit;
}
require __DIR__.'/Support/FeatureDatabase.php';
$fixture=new \ConquerTests\FeatureDatabase();$db=Connection::getInstance();$checks=0;$failed=false;
function wv(bool $condition,string $label):void{global $checks;if(!$condition)throw new RuntimeException($label);$checks++;}
function wvReject(callable $callback,string $label):void{
    try{$callback();}catch(DomainException $e){wv(true,$label);return;}throw new RuntimeException('Allowed: '.$label);
}
try{
    WorldContext::bind(2,1);
    $db->execute("INSERT INTO worlds(id,name,slug,status,map_size,map_seed)VALUES(2,'VIP legacy','vip-legacy','running',256,42),(3,'New world','vip-new','running',256,42)");
    $db->execute("INSERT INTO players(id,username,email,password_hash,gems,vip_points,vip_level,last_vip_login)VALUES(1,'LegacyVIP','legacy-vip@invalid.test','unused',100000,200000,10,UTC_DATE()),(2,'SeparateAccount','separate-vip@invalid.test','unused',10000,20000000,20,NULL),(4,'RaceFixture','race-vip@invalid.test','unused',0,200,1,NULL)");
    // The oldest village is deliberately in world 2, rather than the lowest world ID.
    $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,food,lumber,stone,gold)VALUES(10,1,2,'Older village',40,40,1000000,1000000,1000000,1000000),(20,1,1,'Existing second village',40,40,1000000,1000000,1000000,1000000)");
    foreach([10,20] as $city)foreach(CityState::BUILDING_CODES as $code)$db->execute('INSERT INTO city_buildings(city_id,building_code,level)VALUES(?,?,1)',[$city,$code]);
    $db->execute('INSERT INTO player_inventory(player_id,item_code,quantity)VALUES(1,10106001,3),(1,10103003,2)');
    $rotation=TradingShopService::period('vip')['rotation'];
    $db->execute("INSERT INTO trading_shop_purchases(player_id,scope_world_id,shop_mode,rotation,offer_id,quantity)VALUES(1,0,'vip',?,'legacy-offer',2)",[$rotation]);
    $migration=(string)file_get_contents(ROOT_DIR.'/migrations/0129_world_vip.sql');
    MigrationSql::apply($db->getPdo(),$migration);
    wv(VipService::status(1,2)['points']===200000&&VipService::status(1,2)['daily_claimed'],'legacy progress and daily claim are preserved once');
    wv(VipService::status(1,1)['points']===200&&VipService::status(1,1)['level']===1&&!VipService::status(1,1)['daily_claimed'],'other legacy village starts its own VIP 1');
    wv(InventoryService::quantity(1,10106001,2)===3&&InventoryService::quantity(1,10106001,1)===0,'legacy VIP packs follow the oldest village only');
    wv(InventoryService::quantity(1,10103003,1)===2&&InventoryService::quantity(1,10103003,2)===2,'other inventory retains its previous scope');
    wv((int)$db->query("SELECT scope_world_id FROM trading_shop_purchases WHERE player_id=1 AND shop_mode='vip'")->fetchColumn()===2,'legacy VIP stock belongs to the preserved world');
    wv(VipService::status(1,3)['points']===0,'an unjoined world has no VIP progress');
    wvReject(fn()=>VipService::addPoints(1,100,3),'points cannot create a village in an unjoined world');
    wvReject(fn()=>InventoryService::addItems(1,10106001,1,3),'VIP items cannot be granted in an unjoined world');
    wv(MasteryService::snapshot(1,2)['points_from_vip']===9&&MasteryService::snapshot(1,1)['points_from_vip']===0,'VIP Hunter allowance stays in its world');
    wv(abs(BuffEngine::getBuffs(1,2)['research_speed']-.25)<1e-9&&abs(BuffEngine::getBuffs(1,1)['research_speed'])<1e-9,'background bonus calculations use the explicit world');
    $city=WorldContext::city(1,1);$db->execute('UPDATE cities SET food=0,last_resource_update=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 HOUR) WHERE id=20');
    ResourceTick::persist($city,['farm'=>['level'=>1]]);
    $rate=\Conquer\Game\City\BuildingData::getHourlyRate('farm',1,VipService::bonuses(1));
    wv(abs((float)$db->query('SELECT food FROM cities WHERE id=20')->fetchColumn()-$rate)<2,'inactive-world production uses its own VIP bonus');
    wv(VipService::dailyLogin(1,1)&&!VipService::dailyLogin(1,1)&&!VipService::dailyLogin(1,2),'daily reward is independently limited per world');
    WorldContext::bind(1,1);
    wvReject(fn()=>KingdomService::action(1,['action'=>'inventory.use','item_code'=>10106001]),'VIP packs from another world cannot be used');
    $buy=['action'=>'crystal.buy','item_code'=>10206002,'quantity'=>1,'request_id'=>'world-vip-buy-00001','expected_world_id'=>1];
    $before=(int)$db->query('SELECT gems FROM players WHERE id=1')->fetchColumn();
    KingdomService::action(1,$buy);KingdomService::action(1,$buy);
    wv(InventoryService::quantity(1,10206002,1)===1&&InventoryService::quantity(1,10206002,2)===0,'purchased VIP pack is world-bound and replay grants once');
    wv((int)$db->query('SELECT gems FROM players WHERE id=1')->fetchColumn()===$before-1000,'purchase replay charges only once');
    $use=['action'=>'inventory.use','item_code'=>10206002,'quantity'=>1,'operation_key'=>'world-vip-use-00001','expected_world_id'=>1];
    KingdomService::action(1,$use);KingdomService::action(1,$use);
    wv(VipService::status(1,1)['points']===1210&&VipService::status(1,2)['points']===200000,'item use and its replay never transfer or duplicate VIP points');
    wv(KingdomService::state(1)['profile']['prestige_points']===1210,'own profile uses world VIP balance');
    WorldContext::bind(2,1);wvReject(fn()=>KingdomService::action(1,$use),'old-world command cannot replay into another world');
    // A returning army in world 1 must not credit world 2, which is currently active.
    \Conquer\Logger::init($fixture->sessionPath().'/http.log');
    $db->execute("INSERT INTO marches(player_id,world_id,march_type,origin_city_id,target_x,target_y,target_type,troops_json,haul_json,departure_time,arrival_time,return_time,state)VALUES(1,1,9,20,45,45,5,'{}',?,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 HOUR),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 HOUR),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 MINUTE),'returning')",[json_encode(['items'=>[10206002=>2]])]);
    \Conquer\Game\March\MarchTick::runForPlayer(1);
    wv(InventoryService::quantity(1,10206002,1)===2&&InventoryService::quantity(1,10206002,2)===0,'returning rewards use the saved march world');
    wv(VipService::status(1,1)['points']===1210,'returning VIP items wait for use');
    $db->execute('INSERT INTO player_inventory(player_id,item_code,quantity)VALUES(1,10106001,4)');
    $db->execute("INSERT INTO trading_shop_purchases(player_id,scope_world_id,shop_mode,rotation,offer_id,quantity)VALUES(1,0,'vip',?,'legacy-offer',3)",[$rotation]);
    MigrationSql::apply($db->getPdo(),$migration);
    wv(InventoryService::quantity(1,10106001,2)===7&&(int)$db->query("SELECT quantity FROM trading_shop_purchases WHERE player_id=1 AND scope_world_id=2 AND offer_id='legacy-offer'")->fetchColumn()===5,'resumed migration merges coexisting legacy and world-scoped quantities');
    MigrationSql::apply($db->getPdo(),$migration);
    wv(VipService::status(1,1)['points']===1210&&VipService::status(1,2)['points']===200000&&InventoryService::quantity(1,10106001,2)===7,'migration replay neither overwrites progress nor duplicates packs');
    $db->execute("INSERT INTO sessions(id,player_id,token,csrf_token,ip_address,user_agent,expires_at,active_world_id)VALUES(1,1,?,'world-vip-test','127.0.0.1','fixture',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),2)",[str_repeat('a',64)]);
    $_COOKIE[Session::COOKIE_NAME]=str_repeat('a',64);$session=Session::current();
    wv((int)$session['vip_level']===10,'session VIP follows the active world');
    wv(count(array_filter(WorldService::state(1)['worlds'],fn($w)=>$w['can_join']))===0,'accounts with villages see no new-world join action');
    WorldService::action($session,['action'=>'select','world_id'=>1,'expected_world_id'=>2,'request_id'=>'legacy-select-00001']);
    wv(Session::current()['vip_level']===3,'world selection refreshes cached VIP');
    wvReject(fn()=>WorldService::action($session,['action'=>'join','world_id'=>3,'expected_world_id'=>1,'request_id'=>'deny-extra-village-001']),'server denies a second village');
    wvReject(fn()=>OAuth::createDefaultCity($db,1,'LegacyVIP',3),'direct registration city creation cannot bypass the rule');
    OAuth::createDefaultCity($db,2,'SeparateAccount',3);OAuth::createDefaultCity($db,2,'SeparateAccount',3);
    wv((int)$db->query('SELECT COUNT(*) FROM cities WHERE player_id=2')->fetchColumn()===1&&VipService::status(2,3)['points']===200,'a separate account receives one village and fresh VIP, ignoring archived global totals');
    // Contention across two world targets is serialized by the player row lock.
    $jobs=[];
    foreach([1,3] as $world){$process=proc_open([PHP_BINARY,__FILE__,'create',$fixture->sessionPath(),(string)$world],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,null,['bypass_shell'=>true]);fclose($pipes[0]);$jobs[]=[$process,$pipes];}
    $results=[];foreach($jobs as [$process,$pipes]){$out=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);wv(proc_close($process)===0,'creation worker succeeds: '.$error);$results[]=$out;}
    sort($results);wv($results===['blocked','created']&&(int)$db->query('SELECT COUNT(*) FROM cities WHERE player_id=4')->fetchColumn()===1,'simultaneous requests in different worlds create exactly one village');
    // Real authenticated APIs expose the same scoped state and enforce CSRF.
    $base=$fixture->serve('$path=parse_url($_SERVER["REQUEST_URI"],PHP_URL_PATH);if($path==="/world")\\Conquer\\Api\\Handlers\\WorldHandler::action([]);elseif($path==="/me")\\Conquer\\Api\\Handlers\\PlayerHandler::me([]);else \\Conquer\\Api\\Handlers\\PlayerHandler::profile(["id"=>1]);');
    $http=static function(string $path,?array $body=null,bool $csrf=true,bool $auth=true)use($base):array{
        $ch=curl_init($base.$path);$headers=['Cookie: conquer_session='.($auth?str_repeat('a',64):'invalid')];
        if($csrf)$headers[]='X-CSRF-Token: world-vip-test';
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_HTTPHEADER=>$headers]);
        if($body!==null)curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($body)]);
        $raw=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);return [$status,json_decode((string)$raw,true)];
    };
    [$status,$json]=$http('/me');wv($status===200&&$json['data']['vip_level']===3,'own API profile shows selected-world VIP');
    [$status,$json]=$http('/profile');wv($status===200&&$json['data']['vip_level']===3,'public API profile shows selected-world VIP');
    $body=['action'=>'join','world_id'=>3,'expected_world_id'=>1,'request_id'=>'api-extra-village-001'];
    wv($http('/world',$body,false)[0]===403&&$http('/world',$body,true,false)[0]===401,'world creation requires CSRF and authentication');
    [$status,$json]=$http('/world',$body);wv($status===409&&str_contains($json['error']['message'],'new account'),'authenticated API explains the one-village rule');
    $admin=\Conquer\Auth\AdminAuth::createAdmin('VipWorldAdmin','Vip-world-admin-2026!','superadmin');
    $input=['world_id'=>2,'player_id'=>1,'reason'=>'VIP world scope test','operation_id'=>bin2hex(random_bytes(16)),'gems'=>90000,'vip_points'=>501,'vip_level'=>999];
    $correction=\Conquer\Admin\AdminService::execute($admin,'set-account-values',$input);
    wv($correction['after']['vip_level']===2&&VipService::status(1,2)['points']===501&&VipService::status(1,1)['points']===1210,'admin correction affects only the selected world and derives its level');
    $gift=['world_id'=>2,'player_id'=>1,'reason'=>'VIP world gift test','operation_id'=>bin2hex(random_bytes(16)),'title'=>'World VIP gift','message'=>'Fixture only','item_code'=>10206002,'quantity'=>1];
    \Conquer\Admin\AdminService::execute($admin,'gift',$gift);
    wv(InventoryService::quantity(1,10206002,2)===1&&InventoryService::quantity(1,10206002,1)===2,'admin VIP gift follows its selected world even while another world is active');
    echo "PASS $checks world VIP and one-village checks.\n";
}catch(Throwable $e){$failed=true;fwrite(STDERR,'FAIL '.$e->getMessage()."\n".$e->getTraceAsString()."\n");}
finally{$fixture->close();}exit($failed?1:0);
