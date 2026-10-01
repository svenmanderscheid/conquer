<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR.'/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();

use Conquer\Game\March\BattleLuck;
use Conquer\Game\City\TroopData;

function checkLuck(bool $condition, string $label): void
{
    if (!$condition) throw new RuntimeException($label);
    echo "PASS $label\n";
}

checkLuck(BattleLuck::factor(-10) === .9, 'minimum luck reduces attacker strength by ten percent');
checkLuck(BattleLuck::factor(10) === 1.1, 'maximum luck raises attacker strength by ten percent');
checkLuck(BattleLuck::factor(-99) === .9 && BattleLuck::factor(99) === 1.1, 'luck factor clamps external values');
for ($i=0; $i<1000; $i++) {
    $luck=BattleLuck::roll();
    if (!($luck >= -10 && $luck <= 10 && abs($luck*10-round($luck*10)) < .00001)) {
        throw new RuntimeException('server roll stays in range and uses tenths');
    }
}
checkLuck(true, 'server roll stays in range and uses tenths');
$troops=[50100101=>100];
$basePower=100*(float)TroopData::get(50100101)['power'];
$definition=['required_power'=>(int)$basePower,'amount'=>100,'level'=>1,'stats'=>['hp'=>1,'attack'=>1,'defense'=>0]];
$target=['hp_current'=>100,'luck_percent'=>10,'seed'=>123];
$seen=[];
for($i=0;$i<32;$i++){
    $actual=\Conquer\Game\March\BattleEngine::resolveMonster($troops,$target,$definition,['luck_percent'=>10,'seed'=>123]);
    $report=$actual['report'];$seen[(string)$report['luck_percent']]=true;
    if($report['army_power']!==round($basePower*BattleLuck::factor($report['luck_percent'])))throw new RuntimeException('reported server luck must match settled power');
    if($actual['monster_killed']!==($report['luck_percent']>=0))throw new RuntimeException('threshold outcome must use actual server luck');
}
checkLuck(count($seen)>1,'real combat keeps independently rolled luck despite injected luck and seed keys');
$reference=\Conquer\Game\March\BattleEngine::previewMonster($troops,$target,$definition);
checkLuck($reference['report']['luck_percent']===0.0&&$reference['report']['army_power']===$basePower&&$reference['monster_killed'],'zero-luck reference matches exact power threshold');
checkLuck($reference===\Conquer\Game\March\BattleEngine::previewMonster($troops,$target,$definition),'repeat reference is stable');
foreach([100,1] as $hp){
    $nearDefinition=$definition;$nearDefinition['amount']=$hp;
    $near=\Conquer\Game\March\BattleEngine::previewMonster($troops,['hp_current'=>$hp],$nearDefinition,[],$basePower-.1);
    checkLuck(!$near['monster_killed']&&$near['outcome']==='defender_wins'&&$near['new_monster_hp']===1,'HP rounding below the exact power threshold cannot kill a '.$hp.'-HP target');
    $exact=\Conquer\Game\March\BattleEngine::previewMonster($troops,['hp_current'=>$hp],$nearDefinition,[],$basePower);
    checkLuck($exact['monster_killed']&&$exact['outcome']==='attacker_wins'&&$exact['new_monster_hp']===0,'reaching the exact power threshold kills a '.$hp.'-HP target');
}
echo "ALL BATTLE LUCK CHECKS PASSED\n";
