<?php
declare(strict_types=1);
/** Pure rule checks using the actual complete research and troop catalogues; no database access. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
define('ROOT_DIR',dirname(__DIR__));
require ROOT_DIR.'/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
use Conquer\Game\Research\{ResearchData,ResearchEffects};
use Conquer\Game\City\TroopData;
function effectCheck(bool $ok,string $label): void { if(!$ok)throw new RuntimeException($label);echo "PASS $label\n"; }
function closeTo(float $actual,float $expected): bool { return abs($actual-$expected)<0.0000001; }
function researchValue(string $code,int $level=1): float {
    $node=ResearchData::get($code);
    if(!$node)throw new RuntimeException('Missing real research definition '.$code);
    foreach($node['levels'] as $entry)if((int)$entry['level']===$level)return (float)$entry['ability_value'];
    throw new RuntimeException('Missing level');
}
function sourceBuff(string $code,int $level=1): array {
    return [ResearchData::buffKey(ResearchData::get($code))=>researchValue($code,$level)];
}
$infantry=50100101;
$ranged=(int)array_values(array_filter(TroopData::t1(),fn($troop)=>(int)$troop['type']===2))[0]['code'];
$cavalry=(int)array_values(array_filter(TroopData::t1(),fn($troop)=>(int)$troop['type']===3))[0]['code'];
$economic=ResearchEffects::normalize(array_merge(sourceBuff('wood_production'),sourceBuff('advanced_wood_production'),sourceBuff('food_production'),sourceBuff('resource_production'),sourceBuff('advanced_research_speed'),sourceBuff('research_speed')));
effectCheck(closeTo($economic['lumber_production'],.14) && closeTo($economic['food_production'],.12) && closeTo($economic['stone_production'],.1),'base, advanced and realm production combine with the canonical lumber alias');
effectCheck(closeTo($economic['research_speed'],.02) && !isset($economic['advanced_research_speed']),'advanced and base research speeds affect the same clock');
$capacity=ResearchEffects::normalize(array_merge(sourceBuff('wood_capacity'),sourceBuff('advanced_wood_capacity')));
effectCheck(closeTo($capacity['lumber_capacity'],.04),'advanced resource capacity maps to the real resource');
$trainingBuffs=array_merge(sourceBuff('infantry_training_cost'),sourceBuff('infantry_training_speed'),sourceBuff('infantry_training_amount'));
$training=ResearchEffects::training($infantry,$trainingBuffs,1.25);
effectCheck(closeTo($training['cost']['food'],49.5) && closeTo($training['cost']['lumber'],29.7),'negative one-percent training research preserves fractional per-unit resource costs');
effectCheck((int)ceil($training['cost']['food']*10)===495,'ten discounted troops round the total cost once rather than rounding every troop');
effectCheck(closeTo($training['speed_multiplier'],1.2625) && $training['max_count']===505,'training type speed, temporary boost and additional batch places combine');
$unaffected=ResearchEffects::training($ranged,$trainingBuffs);
effectCheck(closeTo($unaffected['speed_multiplier'],1) && $unaffected['max_count']===500,'infantry-only training research does not improve archers');
$bounded=ResearchEffects::training($infantry,['infantry_training_cost'=>-1.5,'infantry_training_speed'=>-2]);
effectCheck(closeTo($bounded['cost']['food'],2.5) && closeTo($bounded['speed_multiplier'],.05),'training retains positive lower bounds for cost and speed');
$limits=ResearchEffects::limits(['march_size'=>researchValue('march_size',5),'march_limit'=>researchValue('march_limit')]);
effectCheck($limits['march_capacity']===5750 && $limits['march_slots']===4,'fifteen-percent army size produces exactly 5750 places and the unlock adds one march');
$carryBuffs=array_merge(sourceBuff('troops_storage'),sourceBuff('infantry_storage'));
effectCheck(closeTo(ResearchEffects::carryPerTroop($infantry,$carryBuffs),110.16) && closeTo(ResearchEffects::carryPerTroop($ranged,$carryBuffs),109.08),'carry combines army and type-specific source keys');
effectCheck(ResearchEffects::carryCapacity([$infantry=>10,$ranged=>10],$carryBuffs)===2192,'a mixed army carries its actual weighted resource load');
effectCheck(ResearchEffects::carryCapacity([$infantry=>1000],$carryBuffs)===110160 && ResearchEffects::carryCapacity([],$carryBuffs)===0,'research scales catalog troop carry without inventing load for an empty march');
foreach (['infantry'=>[$infantry,'infantrys','infantry'],'ranged'=>[$ranged,'archers','archer'],'cavalry'=>[$cavalry,'cavalrys','cavalry']] as $type=>[$troop,$source,$composition]) {
    foreach(['hp','def','atk'] as $stat) {
        $compositionCode=$source.'_'.$stat.'_when_composed_of_'.$composition.'_only';
        $rallyCode=$source.'_'.$stat.'_when_participating_a_rally';
        $buffs=array_merge(sourceBuff($compositionCode),sourceBuff($rallyCode),[$type.'_'.$stat=>.1]);
        $solo=ResearchEffects::armyBuffs($buffs,[$troop=>5]);
        $rally=ResearchEffects::armyBuffs($buffs,[$troop=>5],true);
        $mixed=ResearchEffects::armyBuffs($buffs,[$infantry=>5,$ranged=>5,$cavalry=>5]);
        effectCheck(closeTo($solo[$type.'_'.$stat],.13) && closeTo($rally[$type.'_'.$stat],.16) && closeTo($mixed[$type.'_'.$stat],.1),$type.' '.$stat.' composition and rally effects follow their exact catalogue keys and army composition');
        effectCheck(closeTo($buffs[$type.'_'.$stat],.1),'combat calculation leaves its supplied '.$type.' '.$stat.' buff snapshot unchanged');
    }
}
echo "ALL RESEARCH EFFECT CHECKS PASSED\n";
