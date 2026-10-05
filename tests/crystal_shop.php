<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));require ROOT_DIR.'/src/Bootstrap.php';\Conquer\Bootstrap::init(ROOT_DIR);require __DIR__.'/Support/FeatureDatabase.php';
use Conquer\Db\Connection;
use Conquer\Game\Trading\CrystalShop;
use Conquer\Game\Trading\TradingShopService;
use Conquer\Game\Kingdom\KingdomInventory;
use Conquer\Game\Kingdom\KingdomService;
use Conquer\Game\Vip\VipService;
use Conquer\Game\World\WorldContext;
$fixture=new \ConquerTests\FeatureDatabase();$db=Connection::getInstance();$checks=0;$failed=false;
function crystalCheck(bool $ok,string $message):void{global $checks;if(!$ok)throw new RuntimeException($message);$checks++;}
function crystalReject(callable $fn,string $message):void{try{$fn();}catch(DomainException $e){crystalCheck(true,$message);return;}throw new RuntimeException($message);}
try{
    $db->execute("UPDATE worlds SET status='open' WHERE id=1");
    $db->execute("INSERT INTO players(id,username,email,password_hash,gems,vip_points)VALUES(1,'CrystalFixture','crystal@tests.invalid','unused',10000000,0)");
    $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,castle_level)VALUES(1,1,1,'CrystalFixture',30,40,1)");
    foreach(\Conquer\Game\City\CityState::BUILDING_CODES as $code)$db->execute('INSERT INTO city_buildings(city_id,building_code,level)VALUES(1,?,?)',[$code,$code==='trading_post'?0:1]);
    WorldContext::bind(1);
    $offers=CrystalShop::offers();$categories=array_unique(array_column(array_column($offers,'item'),'category'));sort($categories);
    crystalCheck($categories===['boost','resource_pack','speedup','teleport','vip_point'],'five independent product categories');
    $vipOffers=array_values(array_filter($offers,static fn(array $offer):bool=>$offer['item']['category']==='vip_point'));
    crystalCheck(array_column(array_column($vipOffers,'item'),'vip_points')===[10,100,500,1000,10000],'exact requested VIP pack sizes');
    foreach($vipOffers as $offer)crystalCheck($offer['price']===['resource'=>'gems','amount'=>$offer['item']['vip_points']],'one Crystal per VIP point '.$offer['item_code']);
    crystalCheck(TradingShopService::state(1)['crystals']['offers']===$offers,'shop exposed without VIP or built market');
    foreach($offers as $offer){
        $code=(int)$offer['item_code'];$body=['item_code'=>$code,'quantity'=>2,'request_id'=>'crystal-test-'.$code.'-first'];
        $balance=(int)$db->query('SELECT gems FROM players WHERE id=1')->fetchColumn();
        $before=(int)$db->query('SELECT quantity FROM player_inventory WHERE player_id=1 AND item_code=?',[$code])->fetchColumn();
        $buy=fn()=> $db->transaction(fn()=>KingdomInventory::buy(1,$body,true));
        $result=$buy();crystalCheck($result['cost_gems']===$offer['price']['amount']*2,'regular server price '.$code);
        crystalCheck((int)$db->query('SELECT gems FROM players WHERE id=1')->fetchColumn()===$balance-$result['cost_gems'],'exact debit '.$code);
        crystalCheck((int)$db->query('SELECT quantity FROM player_inventory WHERE player_id=1 AND item_code=?',[$code])->fetchColumn()===$before+2,'inventory grant '.$code);
        crystalCheck($buy()['duplicate']===true,'duplicate purchase receipt '.$code);
        crystalCheck((int)$db->query('SELECT gems FROM players WHERE id=1')->fetchColumn()===$balance-$result['cost_gems'],'retry never double charges '.$code);
        crystalCheck(CrystalShop::offers()===$offers,'no weekly stock depletion '.$code);
        crystalReject(fn()=> $db->transaction(fn()=>KingdomInventory::buy(1,array_replace($body,['quantity'=>3]),true)),'receipt rejects changed quantity '.$code);
    }
    crystalCheck(VipService::status(1)['points']===0,'purchased VIP packs wait in inventory');
    foreach($vipOffers as $offer){
        $code=(int)$offer['item_code'];$points=VipService::status(1)['points'];
        $body=['action'=>'inventory.use','item_code'=>$code,'quantity'=>2,'operation_key'=>'crystal-vip-use-'.$code,'vip_points'=>999999];
        $used=KingdomService::action(1,$body)['result'];
        crystalCheck(VipService::status(1)['points']===$points+2*$offer['item']['vip_points'],'VIP use grants catalog points '.$code);
        crystalCheck(VipService::status(1)['level']===VipService::levelForPoints(VipService::status(1)['points']),'VIP level follows used points '.$code);
        crystalCheck((int)$db->query('SELECT quantity FROM player_inventory WHERE player_id=1 AND item_code=?',[$code])->fetchColumn()===0,'VIP packs consumed '.$code);
        crystalCheck(KingdomService::action(1,$body)['result']===$used,'VIP use supports safe replay '.$code);
        crystalCheck(VipService::status(1)['points']===$points+2*$offer['item']['vip_points'],'VIP use retry never credits twice '.$code);
    }
    crystalCheck((int)$db->query("SELECT COUNT(*) FROM trading_shop_purchases WHERE shop_mode='vip'")->fetchColumn()===0,'no VIP stock touched');
    $body=['action'=>'crystal.buy','item_code'=>10208002,'quantity'=>1,'request_id'=>'crystal-action-test-0001','expected_world_id'=>1,'price_crystals'=>1];
    $result=KingdomService::action(1,$body);crystalCheck($result['result']['cost_gems']===500,'real action ignores client price');
    crystalCheck(KingdomService::action(1,$body)['result']['duplicate']===true,'real action supports safe replay');
    crystalReject(fn()=>KingdomService::action(1,array_replace($body,['expected_world_id'=>2])),'wrong world rejected');
    foreach([['item_code'=>10106003],['item_code'=>10206003],['item_code'=>999],['quantity'=>0],['quantity'=>101],['quantity'=>1.5],['request_id'=>'bad']] as $bad)crystalReject(fn()=>KingdomService::action(1,array_replace($body,$bad)),'invalid purchase rejected');
    $db->execute('UPDATE players SET gems=0 WHERE id=1');
    $before=(int)$db->query('SELECT quantity FROM player_inventory WHERE player_id=1 AND item_code=10208002')->fetchColumn();
    crystalReject(fn()=>KingdomService::action(1,array_replace($body,['request_id'=>'crystal-insufficient-0001'])),'insufficient crystals rejected');
    crystalCheck((int)$db->query('SELECT quantity FROM player_inventory WHERE player_id=1 AND item_code=10208002')->fetchColumn()===$before,'failed purchase grants nothing');
    foreach($vipOffers as $offer){
        $code=(int)$offer['item_code'];$points=VipService::status(1)['points'];
        crystalReject(fn()=>KingdomService::action(1,array_replace($body,['item_code'=>$code,'request_id'=>'crystal-vip-insufficient-'.$code,'price_crystals'=>0,'vip_points'=>999999])),'insufficient crystals reject VIP pack '.$code);
        crystalCheck((int)$db->query('SELECT quantity FROM player_inventory WHERE player_id=1 AND item_code=?',[$code])->fetchColumn()===0&&VipService::status(1)['points']===$points,'failed VIP purchase grants nothing '.$code);
    }
    $db->execute("UPDATE worlds SET status='paused' WHERE id=1");crystalReject(fn()=>KingdomService::action(1,array_replace($body,['request_id'=>'crystal-paused-test-0001'])),'paused world rejected');
    echo "PASS $checks Crystal Shop checks: independent catalogue, regular prices, inventory, replay and validation.\n";
}catch(Throwable $e){$failed=true;fwrite(STDERR,'FAIL '.$e->getMessage()."\n".$e->getTraceAsString()."\n");}finally{$fixture->close();}exit($failed?1:0);
