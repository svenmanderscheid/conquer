<?php
declare(strict_types=1);
define('ROOT_DIR',dirname(__DIR__));require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
use Conquer\Game\Map\MonsterPower;
use Conquer\Game\March\BattleEngine;
function balanceCheck(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$monsters=json_decode(file_get_contents(ROOT_DIR.'/data/monsters.json'),true)['monsters'];
$old=array_column(json_decode(file_get_contents(ROOT_DIR.'/data/balance-history/monsters-t10-20260929.json'),true)['monsters'],null,'code');
$families=[];$checked=0;
foreach($monsters as $m){
 if($m['name']==='Ork-Späher'||$m['level']===0)continue;
 $profile=MonsterPower::profile($m);$power=MonsterPower::required($m);$hp=(int)round($m['amount']*$m['stats']['hp']);
 balanceCheck($profile['tier']>=1&&$profile['tier']<=5,'No monster requires a future troop tier');
 balanceCheck($m['source_amount']===$old[$m['code']]['source_amount'],'Imported monster quantity remains archived');
 balanceCheck(MonsterPower::currentRequired($m,$hp)===$power,'Full health uses full threshold');
 balanceCheck(abs(MonsterPower::currentRequired($m,intdiv($hp,2))-$power/2)<=2,'Damage preserves proportional power requirement');
 $families[$m['name']][$m['level']]=$power;$checked++;
}
foreach($families as $name=>$levels){ksort($levels);$last=0;foreach($levels as $power){balanceCheck($power>$last,$name.' difficulty strictly increases');$last=$power;}}
$find=static fn($name,$level)=>current(array_filter($monsters,static fn($m)=>$m['name']===$name&&$m['level']===$level));
$fight=static fn($troops,$monster,$buffs=[])=>BattleEngine::previewMonster($troops,['hp_current'=>(int)round($monster['amount']*$monster['stats']['hp'])],$monster,$buffs);
balanceCheck($fight([50100101=>10],$find('Ork-Späher',1))['monster_killed'],'Ten starting infantry beat the tutorial scout');
$golem=$find('Golem',10);
balanceCheck(MonsterPower::required($golem)===1687500,'Level ten Golem needs a developed T5 army');
balanceCheck(!$fight([50100501=>2000],$golem)['monster_killed'],'Two thousand T5 cannot farm the strongest solo monsters');
balanceCheck(!$fight([50100501=>50000],$golem)['monster_killed'],'Maximum base army still needs research for the hardest Golem');
balanceCheck($fight([50100501=>50000],$golem,['troops_atk'=>.4,'troops_def'=>.4,'troops_hp'=>.4])['monster_killed'],'Developed T5 research makes the strongest solo monster reachable');
$boss=$find('Magdar',3);
balanceCheck(!$fight([50100501=>500000],$boss)['monster_killed'],'Endgame bosses require a developed alliance army');
balanceCheck($fight([50100501=>500000],$boss,['troops_atk'=>1.5,'troops_def'=>1.5,'troops_hp'=>1.5])['monster_killed'],'Endgame boss is reachable with T5 and strong alliance research');
balanceCheck($checked===102,'All 102 non-tutorial monsters covered');
echo "PASS: 102 progressively harder monsters, tutorial, weak-army rejection and achievable T5 solo/alliance endgame.\n";

