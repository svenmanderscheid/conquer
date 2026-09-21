<?php
declare(strict_types=1);
/** Real consumable effects in an isolated database; never grants items to a live player. */
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));require ROOT_DIR.'/src/Bootstrap.php';\Conquer\Bootstrap::init(ROOT_DIR);
require __DIR__.'/Support/FeatureDatabase.php';
use Conquer\Db\Connection;
use Conquer\Game\Kingdom\{KingdomService,KingdomInventory};
use Conquer\Game\Inventory\InventoryService;
use Conquer\Game\Research\{BuffEngine,ResearchEffects};
use Conquer\Game\Treasure\TreasureData;
use Conquer\Game\Map\WorldPlacement;
use Conquer\Game\Map\WorldTerrain;
$fixture=new \ConquerTests\FeatureDatabase();$db=Connection::getInstance();$checks=0;$failed=false;
function itemCheck(bool $ok,string $message):void{global$checks;if(!$ok)throw new RuntimeException($message);$checks++;}
function itemUse(int $code,array $extra=[]):array{return KingdomService::action(1,['action'=>'inventory.use','item_code'=>$code]+$extra)['result'];}
function itemReject(callable $fn,string $message):void{try{$fn();}catch(DomainException $e){itemCheck(true,$message);return;}throw new RuntimeException($message);}
function selectableTeleportTarget(Connection $db,int $worldId,int $cityId,int $x,int $y,int $radius=24):array{return $db->transaction(function()use($db,$worldId,$cityId,$x,$y,$radius){WorldPlacement::lockWorld($db,$worldId);for($r=4;$r<=$radius;$r++)for($dy=-$r;$dy<=$r;$dy++)for($dx=-$r;$dx<=$r;$dx++){if(max(abs($dx),abs($dy))!==$r)continue;$tx=$x+$dx;$ty=$y+$dy;if(WorldPlacement::canPlace($db,$worldId,'city',$tx,$ty,$cityId))return[$tx,$ty];}throw new RuntimeException('no selectable teleport test target');});}
try{
    if(is_file(ROOT_DIR.'/migrations/0075_lord_talents.sql'))\Conquer\Db\MigrationSql::apply($db->getPdo(),file_get_contents(ROOT_DIR.'/migrations/0075_lord_talents.sql'));
    $db->execute("UPDATE worlds SET status='open',speed_factor=1 WHERE id=1");
    $db->execute("INSERT INTO players(id,username,email,password_hash,gems,action_points,vip_points)VALUES(1,'ItemFixture','items@tests.invalid','unused',5000,50,0)");
    $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,food,lumber,stone,gold,castle_level)VALUES(1,1,1,'Fixture',30,40,1000,1000,1000,1000,5)");
    $db->execute("INSERT INTO alliances(id,world_id,name,tag,leader_id)VALUES(1,1,'Fixture Alliance','FIX',1)");
    $db->execute("INSERT INTO alliance_members(alliance_id,player_id,world_id,role)VALUES(1,1,1,'leader')");
    foreach(\Conquer\Game\City\CityState::BUILDING_CODES as$code)$db->execute('INSERT INTO city_buildings(city_id,building_code,level)VALUES(1,?,?)',[$code,$code==='treasure_house'?25:1]);
    $s=KingdomService::state(1);$defs=array_values(InventoryService::allDefs());
    itemCheck(count($s['inventory_catalog'])===count($defs)&&count($defs)>=162,'full catalogue exposed independently of ownership');
    itemCheck(array_sum(array_column($s['inventory_catalog'],'quantity'))===array_sum(array_column($s['inventory'],'quantity')),'catalogue owns nothing beyond real inventory');
    $allCodes=array_column($defs,'code');itemCheck(count(array_unique($allCodes))===count($allCodes),'unique item codes');
    foreach($defs as$d){
        itemCheck(!empty($d['name_de'])&&!empty($d['description_de'])&&!empty($d['rarity']),'complete German display metadata '.$d['code']);
        if(isset($d['icon']))itemCheck(is_file(ROOT_DIR.'/assets/art/items/'.$d['icon']),'existing icon '.$d['icon']);
        InventoryService::addItems(1,(int)$d['code'],2);
    }
    $drops=json_decode(file_get_contents(ROOT_DIR.'/data/chest_drops.json'),true)['chests'];$obtainable=[];
    foreach($drops as$c)foreach($c['drop_table']as$drop)if(isset($drop['item_code'])){$obtainable[]=$drop['item_code'];itemCheck(in_array($drop['item_code'],$allCodes,true)&&$drop['weight']>0,'valid chest entry');}
    foreach($defs as$d)if(!in_array($d['category'],['chest','material'],true))itemCheck(in_array($d['code'],$obtainable,true),'new item is obtainable '.$d['code']);
    foreach($defs as$d){
        $code=(int)$d['code'];$cat=$d['category'];$before=(int)$db->query('SELECT quantity FROM player_inventory WHERE player_id=1 AND item_code=?',[$code])->fetchColumn();
        if($cat==='material'){itemReject(fn()=>itemUse($code),'material cannot be directly consumed '.$code);itemCheck((int)$db->query('SELECT quantity FROM player_inventory WHERE player_id=1 AND item_code=?',[$code])->fetchColumn()===$before,'material keeps its inventory balance '.$code);continue;}
        if($cat==='resource_pack'){
            $resource=$d['resource'];$table=$resource==='gems'?'players':'cities';$old=(int)$db->query("SELECT $resource FROM $table WHERE id=1")->fetchColumn();itemUse($code);$after=(int)$db->query("SELECT $resource FROM $table WHERE id=1")->fetchColumn();itemCheck($after-$old===$d['amount'],'real resource credit '.$code);
        }elseif($cat==='speedup'){
            $queue=$d['subcategory']==='generic'?'building':$d['subcategory'];
            if($queue==='healing'){
                $db->execute('DELETE FROM hospital_wounded WHERE city_id=1');$db->execute("INSERT INTO hospital_wounded(city_id,troop_code,count,healing_count,healing_started_at,healing_batch,healing_ends_at)VALUES(1,50100101,1,1,UTC_TIMESTAMP(),'item_catalog',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 365 DAY))");$old=$db->query('SELECT healing_ends_at FROM hospital_wounded WHERE city_id=1')->fetchColumn();itemUse($code,['queue_type'=>'healing']);$new=$db->query('SELECT healing_ends_at FROM hospital_wounded WHERE city_id=1')->fetchColumn();
            }else{
                $table=['building'=>'building_queue','research'=>'research_queue','training'=>'troop_queue'][$queue];$db->execute('DELETE FROM '.$table);
                if($queue==='building')$db->execute("INSERT INTO building_queue(city_id,building_code,level_to,started_at,finishes_at)VALUES(1,'farm',2,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 365 DAY))");
                elseif($queue==='research')$db->execute("INSERT INTO research_queue(player_id,world_id,research_code,level_to,started_at,finishes_at)VALUES(1,1,'food_production',1,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 365 DAY))");
                else $db->execute('INSERT INTO troop_queue(city_id,troop_code,count,started_at,finishes_at)VALUES(1,50100101,1,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 365 DAY))');
                $id=$db->lastInsertId();$old=$db->query('SELECT finishes_at FROM '.$table.' WHERE id=?',[$id])->fetchColumn();itemUse($code,['queue_type'=>$queue,'queue_id'=>$id]);$new=$db->query('SELECT finishes_at FROM '.$table.' WHERE id=?',[$id])->fetchColumn();
            }
            itemCheck(strtotime($old)-strtotime($new)===$d['duration_seconds'],'exact queue reduction '.$code);
        }elseif($cat==='boost'){
            $db->execute('DELETE FROM player_charms_active');$db->execute('DELETE FROM active_buffs');itemUse($code);$type=$d['boost_type'];
            if(in_array($type,['anti_spy','city_shield'],true)){$column=$type==='anti_spy'?'anti_spy_until':'shield_expires_at';itemCheck(strtotime($db->query('SELECT '.$column.' FROM cities WHERE id=1')->fetchColumn())>time(),'real city protection '.$code);}
            elseif(in_array($type,KingdomInventory::DIRECT_BOOSTS,true)){itemCheck(abs((BuffEngine::getBuffs(1)[$type]??0)-$d['bonus_pct']/100)<0.0001,'direct gameplay bonus '.$code);}
            else{$key=['resource_production'=>'production_boost','research_speed'=>'research_boost','training_speed'=>'training_boost'][$type];itemCheck(abs(\Conquer\Game\Buff\ActiveBuffService::getMultiplier(1,$key)-1-$d['bonus_pct']/100)<0.0001,'legacy gameplay multiplier '.$code);}
        }elseif($cat==='ap_refill'){$db->execute('UPDATE players SET action_points=0,last_ap_regen=UTC_TIMESTAMP() WHERE id=1');itemUse($code);itemCheck((int)$db->query('SELECT action_points FROM players WHERE id=1')->fetchColumn()===$d['ap_amount'],'AP restored '.$code);}
        elseif($cat==='vip_point'){$old=(int)$db->query('SELECT vip_points FROM players WHERE id=1')->fetchColumn();itemUse($code);itemCheck((int)$db->query('SELECT vip_points FROM players WHERE id=1')->fetchColumn()-$old===$d['vip_points'],'prestige credited '.$code);}
        elseif($cat==='resource_box'){$r=itemUse($code);itemCheck(in_array($r['resource'],['food','lumber','stone','gold'],true)&&$r['amount']>=$d['amount_min']&&$r['amount']<=$d['amount_max'],'bounded resource box '.$code);}
        elseif($cat==='fragment_pack'){$old=(int)$db->query('SELECT COALESCE(SUM(fragments),0) FROM player_treasures WHERE player_id=1')->fetchColumn();$r=itemUse($code);$after=(int)$db->query('SELECT COALESCE(SUM(fragments),0) FROM player_treasures WHERE player_id=1')->fetchColumn();itemCheck($after-$old===$d['fragment_amount']&&TreasureData::get($r['drops'][0]['treasure_code'])['grade']===$d['fragment_grade'],'correct fragment grade and count '.$code);}
        elseif($cat==='chest'){$r=itemUse($code);itemCheck(count($r['drops'])===$drops[$d['chest_type']]['rolls'],'chest rolls actual rewards '.$code);}
        elseif($cat==='teleport'){
            $old=$db->query('SELECT coord_x,coord_y FROM cities WHERE id=1')->fetch();$extra=[];
            if(in_array($d['teleport_mode'],['advanced','alliance'],true)){$target=selectableTeleportTarget($db,1,1,(int)$old['coord_x'],(int)$old['coord_y'],$d['teleport_mode']==='alliance'?12:24);$extra=['target_x'=>$target[0],'target_y'=>$target[1]];}
            $r=itemUse($code,$extra);
            $db->transaction(function()use($db,$r){WorldPlacement::lockWorld($db,1);itemCheck(WorldPlacement::canPlace($db,1,'city',$r['coord_x'],$r['coord_y'],1),'teleport uses a dry collision-free full footprint');});
            itemCheck($r['coord_x']!==(int)$old['coord_x']||$r['coord_y']!==(int)$old['coord_y'],'teleport moves city');if(in_array($d['teleport_mode'],['advanced','alliance'],true)){itemCheck([$r['coord_x'],$r['coord_y']]===$target,'selected teleport uses the selected destination');if($d['teleport_mode']==='alliance')itemCheck(max(abs($r['coord_x']-$old['coord_x']),abs($r['coord_y']-$old['coord_y']))<=12,'alliance teleport stays near an allied city');}
        }else throw new RuntimeException('Untested category '.$cat);
        $sameChestRewards=$cat==='chest'?array_sum(array_map(static fn($drop)=>(int)($drop['item_code']??0)===$code?(int)$drop['quantity']:0,$r['drops'])):0;
        $after=(int)$db->query('SELECT quantity FROM player_inventory WHERE player_id=1 AND item_code=?',[$code])->fetchColumn();itemCheck($after===$before-1+$sameChestRewards,'exactly one item consumed '.$code);
    }
    $db->execute('DELETE FROM player_charms_active');$db->execute('DELETE FROM active_buffs');
    $attack=array_values(array_filter($defs,fn($d)=>($d['boost_type']??'')==='troops_atk'));usort($attack,fn($a,$b)=>$a['bonus_pct']<=>$b['bonus_pct']);
    InventoryService::addItems(1,$attack[0]['code'],2);InventoryService::addItems(1,$attack[1]['code'],2);
    itemUse($attack[0]['code']);$expiry=strtotime($db->query("SELECT expires_at FROM player_charms_active WHERE stat_category='troops_atk'")->fetchColumn());itemUse($attack[0]['code']);$extended=strtotime($db->query("SELECT expires_at FROM player_charms_active WHERE stat_category='troops_atk'")->fetchColumn());itemCheck($extended-$expiry===$attack[0]['duration_seconds'],'same strength extends time without multiplying');
    itemUse($attack[1]['code']);itemCheck(abs((BuffEngine::getBuffs(1)['troops_atk']??0)-.2)<.00001,'stronger replaces previous strength');
    $weakBefore=(int)$db->query('SELECT quantity FROM player_inventory WHERE player_id=1 AND item_code=?',[$attack[0]['code']])->fetchColumn();
    itemReject(fn()=>itemUse($attack[0]['code']),'weaker cannot overwrite stronger');
    itemCheck($weakBefore>0&&(int)$db->query('SELECT quantity FROM player_inventory WHERE player_id=1 AND item_code=?',[$attack[0]['code']])->fetchColumn()===$weakBefore&&abs((BuffEngine::getBuffs(1)['troops_atk']??0)-.2)<.00001,'rejected weaker boost preserves stock and stronger effect');
    $teleport=array_values(array_filter($defs,fn($d)=>$d['category']==='teleport'&&$d['teleport_mode']==='alliance'))[0];$position=$db->query('SELECT coord_x,coord_y FROM cities WHERE id=1')->fetch();
    $qty=(int)$db->query('SELECT quantity FROM player_inventory WHERE player_id=1 AND item_code=?',[$teleport['code']])->fetchColumn();
    $outsideX=(int)$position['coord_x']+13<=197?(int)$position['coord_x']+13:(int)$position['coord_x']-13;
    itemReject(fn()=>itemUse($teleport['code'],['target_x'=>$outsideX,'target_y'=>(int)$position['coord_y']]),'destination outside alliance area is rejected');
    itemCheck((int)$db->query('SELECT quantity FROM player_inventory WHERE player_id=1 AND item_code=?',[$teleport['code']])->fetchColumn()===$qty,'invalid selected destination preserves inventory');
    $advanced=array_values(array_filter($defs,fn($d)=>$d['category']==='teleport'&&$d['teleport_mode']==='advanced'))[0];
    $waterTarget=null;for($ty=1;$ty<=253&&$waterTarget===null;$ty++)for($tx=1;$tx<=253;$tx++){if(max(abs($tx-(int)$position['coord_x']),abs($ty-(int)$position['coord_y']))<4)continue;if(!WorldTerrain::isDryRectangle(...WorldPlacement::footprint('city',$tx,$ty))){$waterTarget=[$tx,$ty];break;}}
    itemCheck($waterTarget!==null,'water teleport test destination exists');
    $waterQty=(int)$db->query('SELECT quantity FROM player_inventory WHERE player_id=1 AND item_code=?',[$advanced['code']])->fetchColumn();
    itemReject(fn()=>itemUse($advanced['code'],['target_x'=>$waterTarget[0],'target_y'=>$waterTarget[1]]),'teleport destination touching water is rejected');
    itemCheck((int)$db->query('SELECT quantity FROM player_inventory WHERE player_id=1 AND item_code=?',[$advanced['code']])->fetchColumn()===$waterQty,'water rejection preserves the teleporter');
    $db->execute("INSERT INTO marches(player_id,world_id,march_type,origin_city_id,target_x,target_y,troops_json,departure_time,arrival_time,return_time,state)VALUES(1,1,5,1,?,?,?,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 2 HOUR),'marching')",[$position['coord_x']+6,$position['coord_y'],'{}']);
    $qty=(int)$db->query('SELECT quantity FROM player_inventory WHERE player_id=1 AND item_code=?',[$teleport['code']])->fetchColumn();itemReject(fn()=>itemUse($teleport['code']),'active army blocks teleport');itemCheck((int)$db->query('SELECT quantity FROM player_inventory WHERE player_id=1 AND item_code=?',[$teleport['code']])->fetchColumn()===$qty,'blocked teleport preserves inventory');
    $db->execute('DELETE FROM marches');
    $db->execute("INSERT INTO players(id,username,email,password_hash)VALUES(2,'OtherFixture','other@tests.invalid','unused')");
    $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y)VALUES(2,2,1,'Enemy fixture',15,20)");
    $db->execute("INSERT INTO marches(player_id,world_id,march_type,origin_city_id,target_x,target_y,troops_json,departure_time,arrival_time,return_time,state)VALUES(2,1,7,2,?,?,?,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 2 HOUR),'marching')",[$position['coord_x'],$position['coord_y'],'{}']);
    itemReject(fn()=>itemUse($teleport['code']),'incoming enemy army blocks teleport');
    itemCheck($db->query('SELECT coord_x,coord_y FROM cities WHERE id=1')->fetch()===$position,'failed teleport preserves city coordinates');
    $db->execute('DELETE FROM player_inventory WHERE player_id=1 AND item_code=?',[$teleport['code']]);itemReject(fn()=>itemUse($teleport['code']),'zero ownership is rejected');
    echo "PASS $checks item catalogue/effect checks across ".count($defs)." items (isolated DB).\n";
}catch(Throwable $e){$failed=true;fwrite(STDERR,'FAIL '.$e->getMessage()."\n".$e->getTraceAsString()."\n");}finally{$fixture->close();}exit($failed?1:0);
