<?php
declare(strict_types=1);
define('ROOT_DIR',dirname(__DIR__));
require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
use Conquer\Game\City\TroopData;
use Conquer\Game\Research\ResearchData;
use Conquer\Game\Research\ResearchEffects;
function sourceCheck(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);}
$original=json_decode(file_get_contents(ROOT_DIR.'/data/balance-source/troop.json'),true,512,JSON_THROW_ON_ERROR);
sourceCheck(count(TroopData::all())===15,'Exactly fifteen active units');
$resources=[10100001=>'food',10100002=>'lumber',10100003=>'stone',10100004=>'gold'];
foreach($original as $source){
    $tier=$source['code']%100;$code=50000001+$source['type']*100000+$tier*100;$unit=TroopData::get($code);
    sourceCheck($unit['source_code']===$source['code'],'Explicit stable source mapping');
    foreach(['hp','attack','defense','speed','carry','power','heal_time'] as $key)sourceCheck($unit[$key]===$source[$key],$unit['name'].' original '.$key);
    foreach(range(1,4) as $slot)sourceCheck($unit['need_'.$resources[$source['need_code_'.$slot]]]===$source['need_value_'.$slot],'Original resource cost');
    sourceCheck($unit['time']<= $source['time'],'Training shortened from original');
    if($tier>1){$research=ResearchData::get($unit['unlock_research']);sourceCheck($research!==null&&$research['type']==='unlock','Original unlock exists');}
}
sourceCheck(TroopData::trainingSeconds(50100501,2000)===54000,'2000 T5: exactly 15 hours');
sourceCheck(ResearchEffects::carryPerTroop(50200101,[])===1.5,'Fractional archer carrying capacity retained');
sourceCheck(ResearchEffects::carryCapacity([50200101=>2],[])===3,'Fractions summed before rounding');
foreach([50100601,50200901,50301001] as $code)sourceCheck(!TroopData::isActive($code)&&!TroopData::isUnlocked($code,30,30,30,[]),'Future tiers cannot unlock');
sourceCheck(count(ResearchData::retiredTroopUnlocks())===0,'Restored research never refunded as retired');
echo "PASS: all original troop stats, resource costs, source IDs, fractions, fifteen-hour T5 training and twelve research unlocks.\n";
