<?php
declare(strict_types=1);
/** Bulk consumption and receipt replay only against a disposable database. */
if (PHP_SAPI !== 'cli') exit(1);
date_default_timezone_set('UTC');
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR.'/src/Bootstrap.php';
\Conquer\Bootstrap::init(ROOT_DIR);
require __DIR__.'/Support/FeatureDatabase.php';

use Conquer\Db\Connection;
use Conquer\Game\Inventory\InventoryService;
use Conquer\Game\Kingdom\KingdomService;
use Conquer\Game\Treasure\ChestService;

$fixture = new \ConquerTests\FeatureDatabase();
$db = Connection::getInstance();
$checks = 0;
function checkBulk(bool $ok, string $label): void { global $checks; if (!$ok) throw new RuntimeException($label); $checks++; }
function rejectBulk(callable $fn, string $label): void { try { $fn(); } catch (DomainException) { checkBulk(true, $label); return; } throw new RuntimeException('Accepted: '.$label); }
function stockBulk(int $code): int { return (int)Connection::getInstance()->query('SELECT quantity FROM player_inventory WHERE player_id=1 AND item_code=?', [$code])->fetchColumn(); }
function useBulk(int $code, string $key, array $extra=[]): array { return KingdomService::action(1, $extra + ['action'=>'inventory.use','item_code'=>$code,'use_all'=>true,'operation_key'=>'test_'.$key,'expected_world_id'=>1])['result']; }
function findBulk(string $category, ?string $subtype=null): array {
    foreach (InventoryService::allDefs() as $def) if ($def['category']===$category && ($subtype===null || ($def['boost_type']??$def['subcategory']??$def['resource']??'')===$subtype) && ($def['is_usable']??true)) return $def;
    throw new RuntimeException('No definition: '.$category.' '.$subtype);
}
try {
    $db->execute("UPDATE worlds SET status='open',speed_factor=1 WHERE id=1");
    $db->execute("INSERT INTO players(id,username,email,password_hash,action_points,last_ap_regen)VALUES(1,'BulkFixture','bulk@tests.invalid','unused',0,UTC_TIMESTAMP())");
    $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y)VALUES(1,1,1,'Bulk city',30,40)");
    foreach (\Conquer\Game\City\CityState::BUILDING_CODES as $code) $db->execute('INSERT INTO city_buildings(city_id,building_code,level)VALUES(1,?,?)', [$code,in_array($code,['castle','treasure_house'],true)?1:0]);
    $db->execute("INSERT INTO kingdom_profiles(player_id,display_name,welcome_claimed)VALUES(1,'BulkFixture',1)");
    KingdomService::state(1);

    $food=findBulk('resource_pack','food');$code=(int)$food['code'];
    InventoryService::addItems(1,$code,10003);
    InventoryService::addItems(1,10101011,7);
    $before=(int)$db->query('SELECT food FROM cities WHERE id=1')->fetchColumn();
    $result=useBulk($code,'bulk_resource_once');
    checkBulk($result['quantity']===10003 && $result['amount']===$food['amount']*10003, 'whole stack above 10000 is credited');
    checkBulk(stockBulk($code)===0 && stockBulk(10101011)===7, 'only the selected stack is consumed');
    checkBulk((int)$db->query('SELECT food FROM cities WHERE id=1')->fetchColumn()===$before+$result['amount'], 'all resource value arrives in the city');
    InventoryService::addItems(1,$code,5);
    checkBulk(useBulk($code,'bulk_resource_once')===$result && stockBulk($code)===5, 'retry replays without consuming newly acquired stock');
    rejectBulk(fn()=>useBulk($code,'bulk_bad_type',['use_all'=>'true']), 'use_all must be boolean');
    rejectBulk(fn()=>useBulk($code,'bulk_ambiguous_count',['quantity'=>2]), 'all and explicit quantity cannot be combined');
    rejectBulk(fn()=>KingdomService::action(1,['action'=>'inventory.use','item_code'=>$code,'use_all'=>true]), 'bulk requires an operation key');
    rejectBulk(fn()=>useBulk($code,'bulk_resource_once',['use_all'=>false]), 'receipt binds bulk mode');
    checkBulk(stockBulk($code)===5, 'invalid commands preserve stock');
    useBulk($code,'bulk_resource_remainder');
    rejectBulk(fn()=>useBulk($code,'bulk_empty_stack'), 'empty stack is rejected');

    foreach (['resource_pack'=>'gems','vip_point'=>null] as $category=>$subtype) {
        $def=findBulk($category,$subtype);$code=(int)$def['code'];$field=$category==='vip_point'?'vip_points':'gems';
        InventoryService::addItems(1,$code,4);
        $before=(int)$db->query("SELECT $field FROM players WHERE id=1")->fetchColumn();
        $result=useBulk($code,'bulk_'.$field.'_once');
        $amount=(int)($def['vip_points']??$def['amount'])*4;
        checkBulk(stockBulk($code)===0 && (int)$db->query("SELECT $field FROM players WHERE id=1")->fetchColumn()===$before+$amount, 'full '.$field.' stack is credited');
    }

    foreach (['construction_speed','city_shield','anti_spy'] as $type) {
        $def=findBulk('boost',$type);$code=(int)$def['code'];InventoryService::addItems(1,$code,3);
        $start=time();$result=useBulk($code,'bulk_boost_'.$type);
        $expires=$type==='city_shield'?$db->query('SELECT shield_expires_at FROM cities WHERE id=1')->fetchColumn():($type==='anti_spy'?$db->query('SELECT anti_spy_until FROM cities WHERE id=1')->fetchColumn():$db->query('SELECT expires_at FROM player_charms_active WHERE player_id=1 AND stat_category=?',[$type])->fetchColumn());
        checkBulk(abs(strtotime($expires)-$start-3*(int)$def['duration_seconds'])<=2 && stockBulk($code)===0, $type.' duration includes all three items');
        checkBulk(useBulk($code,'bulk_boost_'.$type)===$result, $type.' replay is safe');
    }

    $def=findBulk('ap_refill');$code=(int)$def['code'];InventoryService::addItems(1,$code,300);
    $result=useBulk($code,'bulk_ap_once');
    checkBulk($result['quantity']===(int)ceil(200/$def['ap_amount']) && stockBulk($code)===300-$result['quantity'], 'AP preserves items beyond the full bar');
    checkBulk((int)$db->query('SELECT action_points FROM players WHERE id=1')->fetchColumn()===200, 'AP is filled');
    rejectBulk(fn()=>useBulk($code,'bulk_ap_full'), 'full AP does not consume more');

    $cache=new ReflectionProperty(ChestService::class,'dropTableCache');
    $cache->setValue(null,['chests'=>[
        'silver'=>['rolls'=>2,'drop_table'=>[['item_code'=>10105001,'quantity'=>3,'weight'=>1]]],
        'gold'=>['rolls'=>2,'drop_table'=>[['fragment_grade'=>'epic','quantity'=>5,'weight'=>1]]],
        'platinum'=>['rolls'=>1,'drop_table'=>[['item_code'=>99999999,'quantity'=>1,'weight'=>1]]],
    ]]);
    InventoryService::addItems(1,10105001,3);
    $result=useBulk(10105001,'bulk_chest_same_drop');
    checkBulk($result['quantity']===3 && stockBulk(10105001)===18, 'all uses original stock, leaving newly rewarded chests');
    checkBulk(count($result['drops'])===1 && $result['drops'][0]['quantity']===18 && !empty($result['drops'][0]['icon']), 'duplicate chest rewards are aggregated with metadata');
    checkBulk(useBulk(10105001,'bulk_chest_same_drop')===$result && stockBulk(10105001)===18, 'chest retry keeps the exact aggregated reward');
    InventoryService::addItems(1,10105002,4);
    $before=(int)$db->query('SELECT COALESCE(SUM(fragments),0) FROM player_treasures WHERE player_id=1')->fetchColumn();
    $result=useBulk(10105002,'bulk_chest_fragments');
    checkBulk(array_sum(array_column($result['drops'],'quantity'))===40 && stockBulk(10105002)===0, 'every chest roll grants fragments');
    checkBulk((int)$db->query('SELECT SUM(fragments) FROM player_treasures WHERE player_id=1')->fetchColumn()===$before+40, 'fragment totals match actual credit');
    checkBulk(useBulk(10105002,'bulk_chest_fragments')===$result, 'fragment retries preserve random picks');
    InventoryService::addItems(1,10105003,2);
    rejectBulk(fn()=>useBulk(10105003,'bulk_invalid_loot'), 'invalid loot fails atomically');
    checkBulk(stockBulk(10105003)===2, 'failed chest bulk preserves stock');
    checkBulk((int)$db->query("SELECT COUNT(*) FROM game_operation_receipts WHERE operation_key='test_bulk_invalid_loot'")->fetchColumn()===0, 'failure creates no success receipt');

    foreach (['resource_box'=>10205001,'fragment_pack'=>10300003] as $category=>$code) {
        InventoryService::addItems(1,$code,5);$result=useBulk($code,'bulk_'.$category);
        checkBulk(stockBulk($code)===0 && $result['quantity']===5 && count($result['drops'])>0, $category.' consumes the whole stack');
        checkBulk(useBulk($code,'bulk_'.$category)===$result, $category.' replays the same drops');
    }

    $def=findBulk('speedup','building');$code=(int)$def['code'];InventoryService::addItems(1,$code,20);
    $db->execute("INSERT INTO building_queue(city_id,building_code,level_to,started_at,finishes_at)VALUES(1,'farm',1,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? SECOND))",[(int)$def['duration_seconds']*3-5]);
    $id=$db->lastInsertId();$extra=['queue_type'=>'building','queue_id'=>$id];
    $result=useBulk($code,'bulk_speedup_once',$extra);
    checkBulk($result['quantity']===3 && stockBulk($code)===17, 'speedup uses only items needed to finish the chosen job');
    checkBulk(useBulk($code,'bulk_speedup_once',$extra)===$result && stockBulk($code)===17, 'finished job replay does not spend again');
    rejectBulk(fn()=>useBulk($code,'bulk_speedup_finished',$extra), 'finished job rejects a new use');

    $def=findBulk('speedup','healing');$code=(int)$def['code'];InventoryService::addItems(1,$code,20);
    $db->execute("INSERT INTO hospital_wounded(city_id,troop_code,count,healing_count,healing_started_at,healing_ends_at,healing_batch)VALUES(1,50100101,12,12,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? SECOND),'bulk_healing_batch')",[(int)$def['duration_seconds']*2-5]);
    $extra=['queue_type'=>'healing','batch_id'=>'bulk_healing_batch'];
    rejectBulk(fn()=>useBulk($code,'bulk_healing_wrong',['queue_type'=>'healing','batch_id'=>'old_healing_batch']), 'healing bulk binds the current batch');
    checkBulk(stockBulk($code)===20, 'wrong healing batch preserves stock');
    $result=useBulk($code,'bulk_healing_once',$extra);
    checkBulk($result['quantity']===2 && stockBulk($code)===18, 'healing bulk only consumes the required items');
    checkBulk((int)$db->query('SELECT count FROM city_troops WHERE city_id=1 AND troop_code=50100101')->fetchColumn()===12, 'bulk healing returns troops once');
    checkBulk(useBulk($code,'bulk_healing_once',$extra)===$result && stockBulk($code)===18, 'healing bulk replays after completion');

    foreach ([119000001,findBulk('teleport')['code']] as $code) {
        InventoryService::addItems(1,(int)$code,2);
        rejectBulk(fn()=>useBulk((int)$code,'bulk_not_direct_'.$code), 'unsupported bulk item is rejected');
        checkBulk(stockBulk((int)$code)===2, 'unsupported item is preserved');
    }
    echo "PASS $checks bulk inventory checks (isolated database).\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage()."\n".$error->getTraceAsString()."\n");
    $failed=true;
} finally { $fixture->close(); }
exit(isset($failed)?1:0);
