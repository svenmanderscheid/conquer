<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
date_default_timezone_set('UTC');define('ROOT_DIR',dirname(__DIR__));
require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';
use Conquer\Db\Connection;
use Conquer\Game\Premium\{NameFrameService,PreviewPaymentGateway,ThemeBundleCatalog,ThemeBundleService};
use Conquer\Game\World\WorldContext;

$fixture=new \ConquerTests\FeatureDatabase();$db=Connection::getInstance();$checks=0;$secret=str_repeat('test-secret-',4);$gateway=new PreviewPaymentGateway($secret);
function tbCheck(bool $ok,string $message):void{global$checks;if(!$ok)throw new RuntimeException($message);$checks++;}
function tbReject(callable $fn,string $message):void{try{$fn();throw new RuntimeException($message);}catch(DomainException){}}
function tbPay(PreviewPaymentGateway $gateway,string $secret,array $order,string $event,int $amount):array{
    $body=json_encode(['event_id'=>$event,'provider_reference'=>$order['checkout']['provider_reference'],'amount_cents'=>$amount,'currency'=>$order['currency'],'status'=>'paid'],JSON_THROW_ON_ERROR);
    return ThemeBundleService::handleNotification($gateway,$body,['x-conquer-signature'=>hash_hmac('sha256',$body,$secret)]);
}
try{
    $db->execute("INSERT INTO players(id,username,email,password_hash,gems)VALUES(1,'BundleFixture','bundle@tests.invalid','unused',100)");
    $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,castle_level,food,lumber,stone,gold)VALUES(1,1,1,'Bundle city',31,31,5,1000,1000,1000,1000)");
    $db->execute("INSERT INTO kingdom_profiles(player_id,display_name)VALUES(1,'BundleFixture')");WorldContext::bind(1,1);
    $catalog=ThemeBundleCatalog::all();tbCheck($catalog['currency']==='EUR'&&count($catalog['entries'])===51,'catalog must expose three bundles for every premium skin');
    $dragon1=$catalog['entries']['dragon_1'];$dragon2=$catalog['entries']['dragon_2'];$dragon3=$catalog['entries']['dragon_3'];
    tbCheck($dragon1['price_cents']===599&&$dragon2['price_cents']===999&&$dragon3['price_cents']===999,'prices must stay integer cents');
    tbCheck($dragon1['contents']['resources']===['food'=>100000,'lumber'=>100000,'stone'=>50000,'gold'=>25000]&&$dragon1['contents']['gems']===500,'step one balance changed unexpectedly');
    $state=ThemeBundleService::state(1);$first=array_values(array_filter($state['entries'],fn($e)=>$e['id']==='dragon_1'))[0];$second=array_values(array_filter($state['entries'],fn($e)=>$e['id']==='dragon_2'))[0];
    tbCheck(!$state['payment_configured']&&$first['unlocked']&&$first['next']&&!$second['unlocked'],'state exposes provider availability and sequential lock');
    tbReject(fn()=>ThemeBundleService::checkout(1,['bundle_id'=>'dragon_2','operation_key'=>'bundle-operation-0002'],$gateway),'step two bypassed step one');
    $one=$db->transaction(fn()=>ThemeBundleService::checkout(1,['bundle_id'=>'dragon_1','operation_key'=>'bundle-operation-0001'],$gateway));
    $oneAgain=$db->transaction(fn()=>ThemeBundleService::checkout(1,['bundle_id'=>'dragon_1','operation_key'=>'bundle-operation-0001'],$gateway));
    tbCheck($oneAgain['duplicate']&&$oneAgain['order_id']===$one['order_id'],'checkout retry created a second order');
    $oneNewKey=$db->transaction(fn()=>ThemeBundleService::checkout(1,['bundle_id'=>'dragon_1','operation_key'=>'bundle-operation-0099'],$gateway));
    tbCheck($oneNewKey['duplicate']&&$oneNewKey['order_id']===$one['order_id'],'a new browser retry created a second payable order for the same bundle');
    tbReject(fn()=>ThemeBundleService::handleNotification($gateway,'{}',['x-conquer-signature'=>str_repeat('0',64)]),'unsigned provider message was accepted');
    $paid=tbPay($gateway,$secret,$one,'evt-one',599);$paidAgain=tbPay($gateway,$secret,$one,'evt-one',599);
    tbCheck($paid['status']==='fulfilled'&&$paidAgain['duplicate'],'provider retry was not idempotent');
    $balances=$db->query('SELECT food,lumber,stone,gold FROM cities WHERE id=1')->fetch();
    tbCheck($balances===['food'=>101000,'lumber'=>101000,'stone'=>51000,'gold'=>26000]&&(int)$db->query('SELECT gems FROM players WHERE id=1')->fetchColumn()===600,'step one rewards were not credited exactly once');
    tbCheck($db->query("SELECT 1 FROM player_name_frames WHERE player_id=1 AND frame_code='dragon'")->fetchColumn()!==false,'name frame entitlement missing');
    $frame=NameFrameService::equip(1,'dragon');tbCheck($frame['name_frame']==='dragon'&&NameFrameService::state(1)['equipped']==='dragon','owned frame could not be equipped');
    tbReject(fn()=>NameFrameService::equip(1,'phoenix'),'unowned name frame was equipped');
    tbReject(fn()=>ThemeBundleService::checkout(1,['bundle_id'=>'dragon_3','operation_key'=>'bundle-operation-0003'],$gateway),'step three bypassed step two');
    $two=$db->transaction(fn()=>ThemeBundleService::checkout(1,['bundle_id'=>'dragon_2','operation_key'=>'bundle-operation-0002'],$gateway));
    tbReject(fn()=>tbPay($gateway,$secret,$two,'evt-wrong-amount',998),'wrong payment amount was accepted');
    tbCheck((int)$db->query('SELECT gems FROM players WHERE id=1')->fetchColumn()===600,'rejected payment changed rewards');
    tbPay($gateway,$secret,$two,'evt-two',999);
    tbCheck($db->query("SELECT 1 FROM player_march_skins WHERE player_id=1 AND skin_code='dragon'")->fetchColumn()!==false,'march skin entitlement missing');
    $three=$db->transaction(fn()=>ThemeBundleService::checkout(1,['bundle_id'=>'dragon_3','operation_key'=>'bundle-operation-0003'],$gateway));tbPay($gateway,$secret,$three,'evt-three',999);
    tbCheck($db->query("SELECT 1 FROM player_castle_skins WHERE player_id=1 AND skin_code='dragon'")->fetchColumn()!==false,'castle skin entitlement missing');
    tbCheck((int)$db->query('SELECT gems FROM players WHERE id=1')->fetchColumn()===2350,'all three gem grants do not match the catalog');
    $final=ThemeBundleService::state(1);$dragon=array_values(array_filter($final['entries'],fn($e)=>$e['theme_id']==='dragon'));
    tbCheck(count(array_filter($dragon,fn($e)=>$e['owned']))===3&&count(array_filter($dragon,fn($e)=>$e['cosmetic_owned']))===3,'final bundle state does not show all purchases and cosmetics');
    echo "PASS $checks theme-bundle checks: catalog, ordering, cents, HMAC, fulfillment, retry safety and frames verified.\n";
}finally{$fixture->close();}
