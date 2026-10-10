<?php
declare(strict_types=1);
/** Transactional reward receipts and rollback-independent invalid attempts, isolated schema only. */
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));define('APP_BASE','/conquer');
require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';date_default_timezone_set('UTC');
\Conquer\Logger::init(sys_get_temp_dir().'/conquer-reward-ledger-tests.log','ERROR');
set_error_handler(static function(int $severity,string $message,string $file,int $line):never{throw new ErrorException($message,0,$severity,$file,$line);});
use Conquer\Admin\RewardLedger as L;
use Conquer\Db\Connection;
use Conquer\Game\Inventory\InventoryService as I;
use Conquer\Game\Kingdom\KingdomService;
use Conquer\Game\Rewards\RewardCatalog as R;
use Conquer\Game\Treasure\TreasureService as T;
use Conquer\Game\World\WorldContext;
use Conquer\Observability\EventLog;
$fixture=null;$exit=0;$checks=0;
function ckL(bool $ok,string $message):void{global $checks;if(!$ok)throw new RuntimeException($message);++$checks;echo 'PASS '.$message."\n";}
function rejectL(callable $fn,string $message):void{try{$fn();}catch(DomainException|InvalidArgumentException){ckL(true,$message);return;}throw new RuntimeException('Accepted invalid reward: '.$message);}
function countL(string $where='1=1'):int{return (int)Connection::getInstance()->query('SELECT COUNT(*) FROM reward_grant_ledger WHERE '.$where)->fetchColumn();}
try{
    $fixture=new \ConquerTests\FeatureDatabase();$db=Connection::getInstance();
    foreach(['0142_observability.sql','0144_reward_ledger.sql'] as $migration)\Conquer\Db\MigrationSql::apply($db->getPdo(),(string)file_get_contents(ROOT_DIR.'/migrations/'.$migration));
    EventLog::init($fixture->sessionPath(),$fixture->sessionPath());
    $db->execute("INSERT INTO players(id,username,email,password_hash,gems) VALUES(1,'RewardLedgerFixture','ledger@example.invalid','unused',100000)");
    $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y) VALUES(1,1,1,'Ledger City',30,40)");
    foreach(\Conquer\Game\City\CityState::BUILDING_CODES as $code)$db->execute('INSERT INTO city_buildings(city_id,building_code,level) VALUES(1,?,1)',[$code]);
    $db->execute("INSERT INTO kingdom_profiles(player_id,display_name,welcome_claimed) VALUES(1,'RewardLedgerFixture',1)");
    WorldContext::bind(1,1);
    $context=L::ruleContext('monster','20209901',1,'march:42',['drops'=>[['item_code'=>10103001,'count'=>3,'probability'=>1]]]);
    I::addItems(1,10103001,3,1,$context);
    $row=$db->query('SELECT * FROM reward_grant_ledger')->fetch();
    ckL(I::quantity(1,10103001)===3&&$row['source_type']==='monster'&&$row['source_reference']==='march:42'&&$row['inventory_world_id']===0&&$row['world_id']===1,'Committed item credit records world, source, reference and inventory scope');
    ckL($row['rule_revision']===$context['rule_revision'],'Actual receipt keeps the source rule fingerprint');
    try{$db->transaction(static function(){I::addItems(1,10103001,8,1);throw new RuntimeException('Rollback fixture');});}catch(RuntimeException){}
    ckL(I::quantity(1,10103001)===3&&countL()===1,'Rolled-back inventory mutation leaves no confirmed reward receipt');
    rejectL(fn()=>$db->transaction(fn()=>I::addItems(1,99999999,7,1,['source_type'=>'chest','source_key'=>'silver','operation_id'=>'unknown-reward-once'])),'Unknown item is rejected inside the gameplay transaction');
    ckL((int)$db->query("SELECT COUNT(*) FROM operational_events WHERE code='REWARD_UNKNOWN_ITEM' AND outcome='rejected'")->fetchColumn()===1,'Rejected unknown item event survives gameplay rollback');
    ckL(I::quantity(1,99999999)===0&&countL()===1,'Unknown item is neither credited nor recorded as a successful grant');
    rejectL(fn()=>I::addItems(1,10103002,1,1,$context),'Known item outside the authoritative saved source pool is rejected');
    foreach([0,-1,4294967296] as $quantity)rejectL(fn()=>I::addItems(1,10103001,$quantity,1),'Invalid item quantity '.$quantity.' is rejected');
    ckL((int)$db->query("SELECT COUNT(*) FROM operational_events WHERE code='REWARD_INVALID_QUANTITY'")->fetchColumn()===3,'All invalid amounts have separate rejected records');
    $db->execute("INSERT INTO reward_overrides(source_type,source_key,config_json) VALUES('monster','20209901',?)",[json_encode(array_replace(R::defaults('monster','20209901'),['drops'=>[['item_code'=>10103002,'count'=>1,'probability'=>1]]]))]);R::resetCache();
    I::addItems(1,10103001,3,1,$context);
    ckL(I::quantity(1,10103001)===6,'Frozen source context still pays an earned reward after current rules change');
    $db->execute("CREATE TRIGGER fixture_reward_receipt_failure BEFORE INSERT ON reward_grant_ledger FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fixture receipt failure'");
    try{I::addItems(1,10103001,5,1);throw new RuntimeException('Receipt failure was ignored');}catch(PDOException){}
    $db->execute('DROP TRIGGER fixture_reward_receipt_failure');
    ckL(I::quantity(1,10103001)===6&&countL()===2,'Failure to persist a receipt rolls back its inventory credit');
    T::addFragments(1,60100001,3,['source_type'=>'farm','source_key'=>'20100101.1','world_id'=>1]);
    T::addRelics(1,60100001,2,['source_type'=>'chest','source_key'=>'silver']);
    $relic=$db->query("SELECT * FROM reward_grant_ledger WHERE reward_kind='relic'")->fetch();$result=json_decode($relic['result_json'],true);
    ckL(countL("reward_kind='fragment'")===1&&$result['newly_unlocked']&&$result['duplicate_relics']===1&&$result['fragments_added']===T::UNLOCK_COST,'Relics and fragments stay distinct, with exact duplicate conversion results');
    $db->transaction(fn()=>L::resources(1,1,['gems'=>13,'food'=>0],['source_type'=>'expedition','reference'=>'expedition:20']));
    ckL(countL("reward_kind='resource' AND resource_code='gems' AND quantity=13")===1,'Resource rewards have their own kind and exact positive amount');
    $db->execute("INSERT INTO reward_overrides(source_type,source_key,config_json) VALUES('chest','silver',?)",[json_encode(['rolls'=>1,'drop_table'=>[['item_code'=>10103001,'quantity'=>2,'weight'=>1]]])]);R::resetCache();
    $item=array_values(array_filter(I::allDefs(),static fn($d)=>($d['category']??'')==='chest'&&($d['chest_type']??'')==='silver'))[0];
    I::addItems(1,(int)$item['code'],2);
    $body=['action'=>'inventory.use','item_code'=>(int)$item['code'],'use_all'=>true,'operation_key'=>'reward-ledger-chest-once','expected_world_id'=>1];
    KingdomService::action(1,$body);$count=countL();$amount=I::quantity(1,10103001);KingdomService::action(1,$body);
    ckL(countL()===$count&&I::quantity(1,10103001)===$amount,'Replayed bulk chest receipt creates no second grant or audit receipt');
    $actual=L::history(['world_id'=>1,'source_type'=>'chest']);$invalid=L::history(['world_id'=>1,'item_code'=>99999999],true);
    ckL($actual['available']&&count($actual['rows'])===2&&$invalid['rows'][0]['item_code']===99999999,'History filters isolate sources and rejected item IDs');
    ckL(L::history(['world_id'=>999])['rows']===[]&&L::history(['player_id'=>999],true)['rows']===[],'World and player filters do not leak unrelated reward records');
    // Repeat-grant sources may legitimately share an operation; dedup belongs to the action owner.
    I::addItems(1,10103001,1,1,['source_type'=>'chest','operation_id'=>'multi-roll']);I::addItems(1,10103001,1,1,['source_type'=>'chest','operation_id'=>'multi-roll']);
    ckL(countL("operation_id='multi-roll'")===2,'Multiple legitimate rolls in one operation remain separately recorded');
    $db->execute("INSERT INTO admin_users(id,username,password_hash,role) VALUES(1,'LedgerAdmin',?,'superadmin')",[password_hash('Fixture-Ledger-123!',PASSWORD_DEFAULT)]);
    $giftInput=['world_id'=>1,'player_id'=>1,'title'=>'Ledger fixture gift','message'=>'Isolated test receipt','reason'=>'Verify atomic gift reward receipts',
        'food'=>11,'lumber'=>13,'stone'=>17,'gold'=>19,'gems'=>23,'item_code'=>10103001,'quantity'=>5];
    $giftBalances=static function()use($db):array{return array_map('intval',$db->query('SELECT food,lumber,stone,gold FROM cities WHERE id=1')->fetch())+
        ['gems'=>(int)$db->query('SELECT gems FROM players WHERE id=1')->fetchColumn(),'item'=>I::quantity(1,10103001)];};
    $beforeGift=$giftBalances();$beforeGiftReceipts=countL();
    foreach([-2,'not-a-number',['5'],100001] as $index=>$badQuantity){
        $operation=str_repeat((string)($index+1),32);
        rejectL(fn()=>\Conquer\Admin\AdminService::execute(1,'gift',array_replace($giftInput,['quantity'=>$badQuantity,'operation_id'=>$operation])), 'Malformed admin gift quantity is rejected before grant: '.$index);
        $event=$db->query("SELECT * FROM operational_events WHERE category='reward' AND operation_id=?",[$operation])->fetch();
        $detail=$event?json_decode($event['context_json'],true):[];
        ckL($event&&$event['code']==='REWARD_INVALID_QUANTITY'&&$event['outcome']==='rejected'&&$detail['source_type']==='admin_gift'&&$detail['item_id']===10103001,'Malformed admin gift records a durable invalid reward event: '.$index);
        ckL((int)$db->query('SELECT COUNT(*) FROM admin_operations WHERE operation_id=?',[$operation])->fetchColumn()===0,'Rejected gift leaves no success operation receipt: '.$index);
    }
    ckL($giftBalances()===$beforeGift&&countL()===$beforeGiftReceipts&&(int)$db->query('SELECT COUNT(*) FROM admin_gifts')->fetchColumn()===0,'Malformed gifts leave resources, inventory and confirmed histories unchanged');
    $operation=str_repeat('a',32);$validGift=$giftInput+['operation_id'=>$operation];
    $firstGift=\Conquer\Admin\AdminService::execute(1,'gift',$validGift);$afterGift=$giftBalances();
    $expectedGift=$beforeGift;foreach(['food','lumber','stone','gold','gems'] as $resource)$expectedGift[$resource]+=$giftInput[$resource];$expectedGift['item']+=5;
    ckL(!$firstGift['duplicate']&&$afterGift===$expectedGift,'Valid admin gift commits exact item and resource credits');
    $giftRows=$db->query("SELECT * FROM reward_grant_ledger WHERE source_type='admin_gift' AND operation_id=? ORDER BY id",[$operation])->fetchAll();
    $giftLedger=[];foreach($giftRows as $entry){$key=$entry['reward_kind']==='item'?'item':$entry['resource_code'];$giftLedger[$key]=(int)$entry['quantity'];}
    ckL(count($giftRows)===6&&$giftLedger===['item'=>5,'food'=>11,'lumber'=>13,'stone'=>17,'gold'=>19,'gems'=>23], 'Admin gift ledger matches the item and all five resource amounts');
    ckL(count(array_filter($giftRows,static fn($entry)=>(int)$entry['player_id']===1&&(int)$entry['world_id']===1&&$entry['source_reference']==='admin-operation:'.$operation))===6,'Gift receipts preserve recipient, world and their shared operation reference');
    $giftState=static function()use($db):array{return [(int)$db->query('SELECT COUNT(*) FROM admin_gifts')->fetchColumn(),(int)$db->query("SELECT COUNT(*) FROM admin_audit_log WHERE action='admin.gift'")->fetchColumn(),(int)$db->query("SELECT COUNT(*) FROM notifications WHERE type='admin_gift'")->fetchColumn(),(int)$db->query("SELECT COUNT(*) FROM admin_operations WHERE action='gift'")->fetchColumn()];};
    ckL($giftState()===[1,1,1,1],'Gift, admin audit, notification and operation receipt commit together');
    $secondGift=\Conquer\Admin\AdminService::execute(1,'gift',$validGift);
    ckL($secondGift['duplicate']&&$giftBalances()===$afterGift&&countL()===$beforeGiftReceipts+6&&$giftState()===[1,1,1,1],'Retry replays the admin gift receipt without duplicate credits or ledger records');
    $db->execute("CREATE TRIGGER fixture_gift_receipt_failure BEFORE INSERT ON reward_grant_ledger FOR EACH ROW BEGIN IF NEW.source_type='admin_gift' AND NEW.resource_code='gems' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fixture late gift receipt failure'; END IF; END");
    $failedOperation=str_repeat('b',32);
    try{\Conquer\Admin\AdminService::execute(1,'gift',$giftInput+['operation_id'=>$failedOperation]);throw new RuntimeException('Late gift receipt failure was ignored');}catch(PDOException){}
    $db->execute('DROP TRIGGER fixture_gift_receipt_failure');
    ckL($giftBalances()===$afterGift&&countL()===$beforeGiftReceipts+6&&$giftState()===[1,1,1,1],'Late gift receipt failure rolls back resources, items, earlier ledger rows and all success receipts');
    if(in_array('--browser',$argv,true)){
        $routes= <<<'PHP'
        define('APP_BASE','');$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
        if(str_starts_with($path,'/assets/')){$file=realpath(ROOT_DIR.$path);$assets=realpath(ROOT_DIR.'/assets').DIRECTORY_SEPARATOR;if(!$file||!str_starts_with($file,$assets)||!is_file($file)){http_response_code(404);exit;}$ext=pathinfo($file,PATHINFO_EXTENSION);header('Content-Type: '.(['css'=>'text/css','js'=>'application/javascript','svg'=>'image/svg+xml','png'=>'image/png','webp'=>'image/webp'][$ext]??'application/octet-stream'));readfile($file);exit;}
        session_name('conquer_ledger_fixture');session_start();
        if($path==='/admin/login'){if($_SERVER['REQUEST_METHOD']==='POST')\Conquer\Admin\AdminController::loginPost();else \Conquer\Admin\AdminController::loginPage();}
        elseif($path==='/admin/rewards')\Conquer\Admin\AdminController::rewards();
        elseif($path==='/admin')echo 'Isolated fixture';
        else http_response_code(404);
        PHP;
        $url=$fixture->serve($routes);
        $process=proc_open(['node',ROOT_DIR.'/tests/admin_reward_ledger.cjs',$url],[0=>['pipe','r'],1=>STDOUT,2=>STDERR],$pipes,ROOT_DIR,null,['bypass_shell'=>true]);
        if(!is_resource($process))throw new RuntimeException('Browser fixture did not start.');fclose($pipes[0]);
        if(proc_close($process)!==0)throw new RuntimeException('Reward history browser checks failed.');
    }
    echo "ALL $checks REWARD LEDGER CHECKS PASSED\n";
}catch(Throwable $error){$exit=1;fwrite(STDERR,$error->getMessage()."\n".$error->getTraceAsString()."\n");}
finally{if($fixture)$fixture->close();}
exit($exit);
