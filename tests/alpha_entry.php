<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit(1);
define('ROOT_DIR',dirname(__DIR__));require ROOT_DIR.'/src/Bootstrap.php';\Conquer\Bootstrap::init(ROOT_DIR);
require __DIR__.'/Support/FeatureDatabase.php';
use Conquer\Db\Connection;
use Conquer\Game\World\{AlphaRealm,WorldEntry,WorldContext,LuxembourgGeography,WorldSpawnService};
use Conquer\Game\City\{BuildingData,CityState,TroopData};
use Conquer\Game\Quest\{DailyQuestService,StarterMissionService};
use Conquer\Game\Inventory\InventoryService;
use Conquer\Game\Rewards\RewardCatalog;
$fixture=new \ConquerTests\FeatureDatabase(); $db=Connection::getInstance();$checks=0;$failed=false;
function alphaCheck(bool $ok,string $label): void {global $checks;if (!$ok) throw new RuntimeException($label);$checks++;}
function alphaReject(callable $fn,string $label): void {try {$fn();}catch (DomainException $e) {alphaCheck(true,$label);return;}throw new RuntimeException($label);}
try {
    $before=$db->query('SELECT * FROM worlds WHERE id=1')->fetch();
    $realm=AlphaRealm::setup();$world=(int)$realm['world']['id'];
    alphaCheck($realm['created'] && $realm['map']['key']==='luxembourg','new Luxembourg world');
    alphaCheck($realm['world']['speed_factor']==2 && $realm['world']['gather_factor']==2,'2x build, production, training and gathering factors');
    alphaCheck($before===$db->query('SELECT * FROM worlds WHERE id=1')->fetch(),'existing world unchanged');
    alphaCheck(WorldEntry::defaultWorld()===$world,'new registrations target Alpha');
    alphaCheck(!AlphaRealm::setup()['created'],'setup replay never duplicates or resets the world');
    alphaCheck((int)$db->query('SELECT COUNT(*) FROM field_objects WHERE world_id=? AND object_type=5',[$world])->fetchColumn()>=2,'nearby Crystal farms');
    $positions=[];
    for ($player=1;$player<=12;$player++) {
        $db->execute('INSERT INTO players(id,username,email,password_hash) VALUES(?,?,?,?)',[$player,'AlphaFixture'.$player,'alpha'.$player.'@tests.invalid','unused']);
        \Conquer\Auth\OAuth::createDefaultCity($db,$player,'AlphaFixture'.$player);
        $city=$db->query('SELECT * FROM cities WHERE player_id=?',[$player])->fetch();$positions[]=[(int)$city['coord_x'],(int)$city['coord_y']];
        alphaCheck((int)$city['world_id']===$world,'registration world '.$player);
        foreach (['food','lumber','stone','gold'] as $resource) alphaCheck((int)$city[$resource]===500000,'500k '.$resource);
        alphaCheck((LuxembourgGeography::at((int)$city['coord_x'],(int)$city['coord_y'])['canton_id']??null)==='08','same canton '.$player);
        alphaCheck(max(abs($city['coord_x']-170),abs($city['coord_y']-370))<=64,'compact entry '.$player);
    }
    for ($i=0;$i<count($positions);$i++) for ($j=$i+1;$j<count($positions);$j++)
        alphaCheck(!\Conquer\Game\Map\WorldPlacement::conflicts('city',...array_merge($positions[$i],['city'], $positions[$j])),'non-overlapping cities');
    $caps=BuildingData::getStorageCaps(array_fill_keys(CityState::BUILDING_CODES,['level'=>1]));
    alphaCheck(min($caps)>=600000,'starter stock and reward headroom');
    WorldContext::bind($world,1);$city=WorldContext::city(1);$cityId=(int)$city['id'];
    alphaCheck(count(StarterMissionService::quests(1))===7,'seven permanent missions');
    alphaReject(fn()=>DailyQuestService::claimReward(1,'starter_barrack_2'),'incomplete mission rejected');
    $db->execute("UPDATE city_buildings SET level=2 WHERE city_id=? AND building_code='barrack'",[$cityId]);
    $food=(int)$db->query('SELECT food FROM cities WHERE id=?',[$cityId])->fetchColumn();
    $reward=DailyQuestService::claimReward(1,'starter_barrack_2');
    alphaCheck($reward===[['resources'=>['food'=>20000,'lumber'=>20000,'stone'=>20000,'gold'=>20000]]],'server-owned reward');
    alphaCheck((int)$db->query('SELECT food FROM cities WHERE id=?',[$cityId])->fetchColumn()===$food+20000,'actual resource credit');
    alphaReject(fn()=>DailyQuestService::claimReward(1,'starter_barrack_2'),'duplicate claim rejected');
    alphaCheck((int)$db->query('SELECT food FROM cities WHERE id=?',[$cityId])->fetchColumn()===$food+20000,'duplicate keeps balances');
    $t2=array_values(array_filter(TroopData::all(),fn($row)=>(int)$row['tier']===2))[0]['code'];
    $db->execute('INSERT INTO troop_queue(city_id,troop_code,count,started_at,finishes_at,is_processed) VALUES(?,?,500,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),0)',[$cityId,$t2]);
    alphaReject(fn()=>StarterMissionService::claim(1,'starter_train_100'),'queued training gives no reward');
    $db->execute('UPDATE troop_queue SET is_processed=1,finishes_at=UTC_TIMESTAMP() WHERE city_id=?',[$cityId]);
    foreach (['starter_train_100','starter_train_500','starter_train_tier2'] as $code) {StarterMissionService::claim(1,$code);alphaCheck(true,'completed training rewarded '.$code);}
    $db->execute("INSERT INTO defense_promotions(player_id,city_id,source_code,target_code,count,cost_json,started_at,finishes_at,state) VALUES(1,?,50100101,?,20,'{}',UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),'training')",[$cityId,$t2]);
    alphaReject(fn()=>StarterMissionService::claim(1,'starter_promote_20'),'pending promotion gives no reward');
    $db->execute("UPDATE defense_promotions SET state='complete' WHERE city_id=?",[$cityId]);
    $food=(int)$db->query('SELECT food FROM cities WHERE id=?',[$cityId])->fetchColumn();
    StarterMissionService::claim(1,'starter_promote_20');
    alphaCheck((int)$db->query('SELECT food FROM cities WHERE id=?',[$cityId])->fetchColumn()===$food+30000,'completed promotion pays resources');
    alphaReject(fn()=>StarterMissionService::claim(1,'starter_promote_20'),'promotion reward cannot be collected twice');
    alphaReject(fn()=>\Conquer\Auth\OAuth::createDefaultCity($db,1,'AlphaFixture1',1),'existing Alpha account cannot create a second village');WorldContext::bind(1,1);
    alphaReject(fn()=>StarterMissionService::claim(1,'starter_train_100'),'other world cannot claim Alpha progress');
    WorldContext::bind($world,1);
    foreach (InventoryService::allDefs() as $def) if (str_starts_with($def['name_de']??'','Unzugeordneter Gegenstand')) {
        $code=(int)$def['code']; InventoryService::addItems(1,$code,10);
        alphaCheck(!$db->query('SELECT quantity FROM player_inventory WHERE player_id=1 AND item_code=?',[$code])->fetchColumn(),'placeholder not granted '.$code);
        alphaCheck(RewardCatalog::availableDrops([['item_code'=>$code,'count'=>1,'probability'=>1]])===[],'placeholder preview filtered');
    }
    foreach (['silver','gold','platinum'] as $type) for ($n=0;$n<50;$n++) foreach (\Conquer\Game\Treasure\ChestService::rollDropTable($type) as $drop)
        alphaCheck(!isset($drop['item_code'])||InventoryService::isDropEligible((int)$drop['item_code']),'no placeholder chest roll');
    alphaCheck(!\Conquer\Game\Premium\PaymentGatewayFactory::configured()->isAvailable(),'payment provider unavailable');
    $db->execute("UPDATE worlds SET status='paused' WHERE id=?",[$world]);
    alphaReject(fn()=>WorldEntry::defaultWorld(),'paused Alpha cannot route into old world');
    $db->execute("UPDATE worlds SET status='open' WHERE id=?",[$world]);
    $run=WorldSpawnService::tick($world,true,'test');alphaCheck($run[$world]['status']==='completed','Alpha background population');
    echo "PASS $checks Alpha entry, mission, loot and payment checks\n";
} catch (Throwable $e) {$failed=true;fwrite(STDERR,'FAIL '.$e->getMessage().' at '.$e->getFile().':'.$e->getLine()."\n");}
finally {$fixture->close();}
exit($failed?1:0);
