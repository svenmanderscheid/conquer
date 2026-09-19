<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
date_default_timezone_set('UTC');define('ROOT_DIR',dirname(__DIR__));
require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';
use Conquer\Db\Connection;
use Conquer\Game\City\CityState;
use Conquer\Game\Hospital\HospitalService;
use Conquer\Game\Inventory\InventoryService;
use Conquer\Game\Kingdom\KingdomInventory;
use Conquer\Game\Trading\TradingShopService as Shop;
use Conquer\Game\World\WorldContext;
$fixture=new \ConquerTests\FeatureDatabase();$db=Connection::getInstance();$checks=0;
function ceCheck(bool $ok,string $message):void{global$checks;if(!$ok)throw new RuntimeException($message);$checks++;}
function ceSnapshot():array{global$db;return [
 $db->query('SELECT gems FROM players WHERE id=1')->fetchColumn(),
 $db->query('SELECT food,lumber,stone,gold FROM cities WHERE id=1')->fetch(),
 $db->query('SELECT * FROM player_inventory WHERE player_id=1 ORDER BY item_code')->fetchAll(),
 $db->query('SELECT * FROM trading_shop_purchases WHERE player_id=1 ORDER BY scope_world_id,shop_mode,rotation,offer_id')->fetchAll(),
 $db->query('SELECT * FROM hospital_wounded WHERE city_id=1 ORDER BY id')->fetchAll(),
 $db->query('SELECT * FROM city_troops WHERE city_id=1 ORDER BY troop_code')->fetchAll(),
];}
function ceDenied(callable $fn):void{$before=ceSnapshot();try{$fn();throw new RuntimeException('Forbidden purchase succeeded');}catch(DomainException){}ceCheck(ceSnapshot()===$before,'Forbidden purchase altered balances, items or healing');}
try{
 $db->execute("UPDATE worlds SET status='open',speed_factor=1 WHERE id=1");
 $db->execute("INSERT INTO players(id,username,email,password_hash,gems,vip_points)VALUES(1,'CrystalFixture','crystals@tests.invalid','unused',100000,3000000)");
 $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,castle_level,food,lumber,stone,gold)VALUES(1,1,1,'Crystals',40,40,30,10000000,10000000,10000000,10000000)");
 foreach(CityState::BUILDING_CODES as$code)$db->execute('INSERT INTO city_buildings(city_id,building_code,level)VALUES(1,?,30)',[$code]);
 WorldContext::bind(1);HospitalService::addWounded(1,[50100101=>1000]);
 $active=HospitalService::execute(1,['action'=>'hospital.heal','troops'=>[50100101=>100],'operation_key'=>'crystal_test_healing','expected_world_id'=>1]);
 $state=Shop::state(1);$catalog=Shop::catalog();
 foreach(['market']as$kind)foreach($catalog[$kind]as$offer){
  if($offer['price']['resource']!=='gems')continue;
  $item=isset($offer['item_code'])?InventoryService::getItemDef((int)$offer['item_code']):null;
  if(($item['category']??'')==='vip_point')continue;
  $mode=$kind==='vip'?'vip':'caravan';$rotation=$mode==='vip'?$state['vip']['rotation']:$state['rotation'];
  ceDenied(fn()=>Shop::buy(1,$mode,$offer['id'],1,$rotation));
  ceCheck(!in_array($offer['id'],array_column($mode==='vip'?$state['vip']['offers']:$state['offers'],'id'),true),'Forbidden crystal offer still advertised');
 }
 ceCheck(count($state['vip']['offers'])===52,'All VIP reference offers are advertised');
 foreach($catalog['vip']as$offer)ceCheck(in_array($offer['id'],array_column($state['vip']['offers'],'id'),true),'VIP offer missing: '.$offer['id']);
 foreach(InventoryService::allDefs()as$item)if(($item['price_gems']??0)>0&&$item['category']!=='vip_point')ceDenied(fn()=>KingdomInventory::buy(1,['item_code'=>$item['code'],'quantity'=>1,'request_id'=>'crystal_test_inventory']));
 ceCheck(array_filter(KingdomInventory::shop(),fn($i)=>$i['category']!=='vip_point')===[],'Inventory shop advertises forbidden purchases');
 // Existing caravan rows cannot bypass the new trade service.
 $slots=[['currency'=>'gems','price'=>1,'bought'=>false,'id'=>'speedup_5min']];
 $db->execute('INSERT INTO caravan_state(player_id,world_id,refreshed_at,next_refresh_at,slots_json)VALUES(1,1,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),?)',[json_encode($slots)]);
 $city=$db->query('SELECT * FROM cities WHERE id=1')->fetch();
 ceDenied(function()use(&$city){\Conquer\Game\Trading\CaravanService::buy(1,0,$city);});
 // Allowed purchases still charge and deliver their real rewards.
 $vip=array_values(array_filter($state['vip']['offers'],fn($o)=>$o['price']['resource']==='gems'&&$o['item']['category']!=='vip_point'))[0];
 $before=(int)$db->query('SELECT gems FROM players WHERE id=1')->fetchColumn();
 Shop::buy(1,'vip',$vip['id'],1,$state['vip']['rotation']);
 ceCheck((int)$db->query('SELECT gems FROM players WHERE id=1')->fetchColumn()===$before-$vip['price']['amount'],'VIP point purchase did not charge correctly');
 ceCheck((int)$db->query('SELECT quantity FROM player_inventory WHERE player_id=1 AND item_code=?',[$vip['item_code']])->fetchColumn()===1,'VIP shop item not delivered');
 $skin=$db->transaction(fn()=>\Conquer\Game\March\MarchSkinService::buy(1,'ironkeep'));
 ceCheck($skin['charged_gems']===1200&&$db->query("SELECT 1 FROM player_march_skins WHERE player_id=1 AND skin_code='ironkeep'")->fetchColumn()!==false,'Skin purchase no longer works');
 // The real HTTP adapters reject old browser requests while preserving authentication and CSRF.
 $token=bin2hex(random_bytes(32));$db->execute("INSERT INTO sessions(player_id,token,csrf_token,ip_address,user_agent,expires_at,active_world_id)VALUES(1,?,'crystal-test','127.0.0.1','test',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 10 MINUTE),1)",[$token]);
 $base=$fixture->serve('$path=parse_url($_SERVER["REQUEST_URI"],PHP_URL_PATH);match($path){"/build"=>\Conquer\Api\Handlers\CityHandler::instantBuild(["queue_id"=>1]),"/research"=>\Conquer\Api\Handlers\ResearchHandler::instant([]),"/instant"=>\Conquer\Api\Handlers\HospitalHandler::instantHeal([]),"/finish"=>\Conquer\Api\Handlers\HospitalHandler::finish([]),"/chest"=>\Conquer\Api\Handlers\TreasureHandler::openChest([])};');
 $http=static function(string $path,int $expected,bool $auth=true,bool $csrf=true)use($base,$token):void{
  $before=ceSnapshot();$headers=['Content-Type: application/json','X-CSRF-Token: '.($csrf?'crystal-test':'invalid')];if($auth)$headers[]='Cookie: conquer_session='.$token;
  $ch=curl_init($base.'/'.$path);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>$headers,CURLOPT_POSTFIELDS=>json_encode(['troops'=>[50100101=>1],'max_gems'=>100000,'chest_type'=>'gold','buy_with_gems'=>true]),CURLOPT_TIMEOUT=>10]);
  $body=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
  ceCheck($status===$expected,$path.' expected '.$expected.', got '.$status.': '.$body);ceCheck(ceSnapshot()===$before,'HTTP '.$path.' changed protected state');
 };
 foreach(['build','research','instant','finish','chest']as$path){$http($path,401,false);$http($path,403,true,false);$http($path,$path==='chest'?400:422);}
 echo "PASS $checks crystal-economy checks: forbidden purchases preserve state; VIP shop, skins, authentication and CSRF verified.\n";
}finally{$fixture->close();}
