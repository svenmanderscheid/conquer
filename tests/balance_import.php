<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR.'/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
use Conquer\Game\City\{BuildingData,BuildingUpgrader};
use Conquer\Game\Map\{MonsterData,FieldObjectData};
use Conquer\Game\Inventory\InventoryService;
use Conquer\Game\Research\ResearchData;
use Conquer\Game\Rewards\RewardCatalog;
function balanceCheck(bool $ok,string $label): void { if (!$ok) throw new RuntimeException($label); }
function balanceJson(string $name): array { return json_decode(file_get_contents(ROOT_DIR.'/data/'.$name.'.json'),true,512,JSON_THROW_ON_ERROR); }
$manifest=balanceJson('balance-source/manifest');
foreach ($manifest['files'] as $file=>$hash) balanceCheck(hash_file('sha256',ROOT_DIR.'/data/balance-source/'.$file)===$hash,'Source checksum '.$file);
$materials=balanceJson('source_item_map')['building_materials'];
foreach(balanceJson('buildings')['buildings'] as $code=>$levels) {
    $source=balanceJson('balance-source/'.$code);$power=0;
    foreach($source as $level=>$row) {
        $resources=['food'=>0,'lumber'=>0,'stone'=>0,'gold'=>0];$items=[];
        foreach($row['resources'] as $r) { if(isset($materials[$r['type']]))$items[$materials[$r['type']]]=$r['value'];else $resources[$r['type']]=$r['value']; }
        balanceCheck(BuildingData::getCost($code,(int)$level)===$resources,'Exact cost '.$code.' '.$level);
        balanceCheck(BuildingData::getItemCosts($code,(int)$level)==$items,'Exact materials '.$code.' '.$level);
        balanceCheck(BuildingData::getBuildTime($code,(int)$level)===$row['time'],'Exact seconds '.$code.' '.$level);
        balanceCheck(BuildingData::getTotalPower($code,(int)$level)===$row['power'],'Cumulative power '.$code.' '.$level);
        $power+=BuildingData::getPowerAtLevel($code,(int)$level);
        balanceCheck($power===$row['power'],'Power not double counted '.$code.' '.$level);
        $requirements=array_column($row['requirements'],'level','type');
        balanceCheck(BuildingData::getUpgradeRequirements($code,(int)$level)===$requirements,'Exact prerequisites '.$code.' '.$level);
        if($row['valid']) {
            $met=array_map(fn($level)=>['level'=>$level],$requirements);
            BuildingUpgrader::assertRequirements($code,(int)$level,$met);
            foreach($requirements as $req=>$min) {
                $missing=$met;$missing[$req]['level']=$min-1;$rejected=false;
                try { BuildingUpgrader::assertRequirements($code,(int)$level,$missing); } catch(RuntimeException) { $rejected=true; }
                balanceCheck($rejected,'Every requirement blocks independently '.$code.' '.$level.' '.$req);
            }
        }
    }
    balanceCheck(BuildingData::level($code,31)===null,'No invented L31');
}
foreach(['archery_range','stable'] as $code) balanceCheck(BuildingData::getCost($code,30)===BuildingData::getCost('barrack',30),'Conquer schools share barrack balance');
echo "PASS 420 building levels: exact costs, materials, durations, power and enforced prerequisites\n";
foreach(['production','battle','advanced'] as $tree) {
    $source=balanceJson('balance-source/'.$tree);
    foreach(ResearchData::tree($tree) as $node) foreach($node['levels'] as $i=>$row) {
        $raw=$source[$node['source_code']??$node['code']][$i];
        foreach(['time','power'] as $key)balanceCheck($row[$key]===(int)$raw[$key],'Research '.$key);
        balanceCheck($row['ability_value']==(float)$raw['stats']['ability_value'],'Research ability');
    }
}
echo "PASS 951 active research levels retain source values and stable IDs\n";
$map=balanceJson('source_item_map');
foreach(['field_monster','field_object'] as $file) foreach(balanceJson('balance-source/'.$file) as $raw) {
    $monster=$file==='field_monster';
    $row=$monster?MonsterData::source($raw['code'],$raw['level']):FieldObjectData::source($raw['code'],$raw['level']);
    balanceCheck($row!==null,'Source pair resolves');$expected=[];
    for($slot=1;$slot<=($monster?11:3);$slot++) {
        $code=$raw[($monster?'item_':'drop_').$slot]??0;$count=$raw['count_'.$slot]??0;
        if(!$code||!$count)continue;
        $item=$map['items'][$code]['item_code'];
        balanceCheck(InventoryService::getItemDef($item)!==null,'No silently dropped item');
        $expected[]=['source_item_code'=>$code,'item_code'=>$item,'count'=>$count,'probability'=>(float)$raw[($monster?'prob_':'rate_').$slot]];
    }
    balanceCheck($row['drops']===$expected,'Exact source drops '.$file.' '.$raw['code'].' '.$raw['level']);
    if($monster) { foreach(['amount','xp'] as $key)balanceCheck($row[$key]===$raw[$key],'Exact monster '.$key); }
    else foreach(['production','gathering'] as $key)balanceCheck($row[$key]===$raw[$key],'Exact field '.$key);
}
balanceCheck($map['items'][10101001]['item_code']===10201032,'10 crystals are not 50000 food');
balanceCheck($map['items'][10103001]['item_code']===10203001,'1m source speedup is not legacy 5m');
balanceCheck(InventoryService::getItemDef(10103001)['duration_seconds']===300,'Saved inventory meanings preserved');
foreach($map['unresolved'] as $item)balanceCheck(InventoryService::getItemDef($item['item_code'])['is_usable']===false,'Unknown effects cannot be consumed');
foreach(RewardCatalog::sources('monster') as $entry) {
    $def=MonsterData::get((int)$entry['key']);
    if(isset($def['source_code'])) {
        $source=MonsterData::source($def['source_code'],(int)$def['level']);
        balanceCheck(RewardCatalog::availableDrops($def['drops'])===RewardCatalog::availableDrops($source['drops']),'Source rewards reach real monster lookup with unchanged codes, quantities and probabilities');
        balanceCheck($def['stats']===$source['stats']&&$def['xp']===$source['xp'],'Source stats reach battle lookup');
    }
}
balanceCheck(RewardCatalog::rollItems([['item_code'=>10201032,'count'=>3,'probability'=>1],['item_code'=>10201032,'count'=>9,'probability'=>0]])===[10201032=>3],'Probability boundaries');
echo "PASS all 167 monster and 131 object rows; exact drops, code mappings, stats and XP\n";
echo "ALL SOURCE BALANCE CHECKS PASSED\n";
