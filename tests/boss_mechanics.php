<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR.'/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();

use Conquer\Game\City\TroopData;
use Conquer\Game\Map\MonsterData;
use Conquer\Game\March\{BattleEngine, BossMechanics};

$checks = 0;
function checkBoss(bool $ok, string $label): void
{
    if (!$ok) throw new RuntimeException($label);
    $GLOBALS['checks']++;
    echo "PASS $label\n";
}

$grumwald = MonsterData::definition(20202401);
checkBoss($grumwald['boss_mechanic']['version'] === 1, 'current Grumwald definitions carry a versioned server rule');
foreach (range(20202401, 20202410) as $code) {
    checkBoss(MonsterData::definition($code)['boss_mechanic'] === $grumwald['boss_mechanic'] && MonsterData::definition($code)['boss_mechanic']['counter_power_percent'] === 50, 'Grumwald level requires fifty percent ranged base power '.$code);
}
$infantry = (int)TroopData::get(50100101)['power'];
$ranged = (int)TroopData::get(50200101)['power'];
// Cross-multiply unit power so these armies have exactly 50% ranged base power.
$counter = [50100101 => $ranged, 50200101 => $infantry];
$belowCounter = [50100101 => $ranged + 1, 50200101 => $infantry];
$plain = BossMechanics::afterDamage($grumwald, [50100101 => 100], 1000, 500);
checkBoss($plain['hp_restored'] === 60 && $plain['hp_after_regeneration'] === 560, 'a surviving boss restores twelve percent of the fresh damage');
$blocked = BossMechanics::afterDamage($grumwald, $counter, 1000, 500);
checkBoss($blocked['countered'] && $blocked['counter_power_share_percent'] === 50.0 && $blocked['hp_restored'] === 0, 'exactly fifty percent ranged base power suppresses regeneration');
checkBoss(!BossMechanics::afterDamage($grumwald, $belowCounter, 1000, 500)['countered'], 'a formation below the threshold does not round up');
$highTier = [50100501 => 100, 50200101 => 100];
$unequal = BossMechanics::afterDamage($grumwald, $highTier, 1000, 500);
checkBoss(!$unequal['countered'] && $unequal['counter_power_share_percent'] < 50, 'equal headcounts of weak archers and high-tier infantry do not satisfy the power counter');
checkBoss(BossMechanics::afterDamage($grumwald, [50100101 => 100], 1000, 0)['hp_restored'] === 0, 'a killing blow cannot be regenerated');
checkBoss(BossMechanics::afterDamage($grumwald, [50100101 => 1], 1000, 999)['hp_restored'] === 0, 'rounding never erases a tiny damaging hit');
checkBoss(BossMechanics::afterDamage($grumwald, [], 1000, 1000)['hp_restored'] === 0, 'no damage creates no healing');

$legacy = $grumwald;
unset($legacy['boss_mechanic']);
checkBoss(BossMechanics::afterDamage($legacy, [50100101 => 100], 1000, 500) === null, 'a historical order without a mechanic keeps the old rules');
$snapshot = json_decode(json_encode($grumwald, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
checkBoss(BossMechanics::afterDamage($snapshot, [50100101 => 100], 1000, 500) === $plain, 'a serialized dispatch snapshot reproduces its rule');
$oldCounter = [50100101 => 7 * $ranged, 50200101 => 3 * $infantry];
$oldSnapshot = $snapshot; $oldSnapshot['boss_mechanic']['counter_power_percent'] = 30;
$oldSnapshot = json_decode(json_encode($oldSnapshot, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
$oldBlocked = BossMechanics::afterDamage($oldSnapshot, $oldCounter, 1000, 500);
checkBoss($oldBlocked['counter_power_percent'] === 30 && $oldBlocked['countered'] && $oldBlocked['hp_restored'] === 0, 'saved Grumwald thirty-percent rule remains authoritative');
$newUnblocked = BossMechanics::afterDamage($grumwald, $oldCounter, 1000, 500);
checkBoss(!$newUnblocked['countered'] && $newUnblocked['counter_power_share_percent'] === 30.0 && $newUnblocked['hp_restored'] === 60, 'old thirty-percent formation cannot counter current Grumwald');
$future = $snapshot;
$future['boss_mechanic']['version'] = 999;
checkBoss(BossMechanics::afterDamage($future, [50100101 => 100], 1000, 500) === null, 'an unsupported rule is not silently interpreted as version one');

// Use a weak formation against the real full-health boss. The only difference
// from the same archived definition must be the explicitly reported healing.
$monster = ['monster_code' => 20202401, 'hp_current' => (int)($grumwald['amount'] * $grumwald['stats']['hp'])];
$troops = [50100101 => 1000];
$reference = BattleEngine::previewMonster($troops, $monster, $snapshot);
$historical = BattleEngine::previewMonster($troops, $monster, $legacy);
checkBoss(!$reference['monster_killed'] && $reference['report']['boss_mechanic']['hp_restored'] > 0, 'the current real boss applies regeneration in the battle engine');
checkBoss($reference['new_monster_hp'] === $historical['new_monster_hp'] + $reference['report']['boss_mechanic']['hp_restored'], 'regeneration changes settled HP by exactly its saved amount');
checkBoss($reference['attacker_losses'] === $historical['attacker_losses'], 'post-hit healing does not retroactively increase troop wounds');
checkBoss($reference['report']['monster_snapshot']['hp_after'] === $reference['new_monster_hp'], 'the historical report captures the final HP after regeneration');
checkBoss($reference === BattleEngine::previewMonster($troops, $monster, $snapshot), 'repeated previews are deterministic');

$combined = [50100101 => 500 * $ranged, 50200101 => 500 * $infantry];
$armies = [['troops' => [50100101 => $combined[50100101]], 'buffs' => []], ['troops' => [50200101 => $combined[50200101]], 'buffs' => []]];
$rally = BattleEngine::previewMonsterArmies($armies, $monster, $snapshot);
$direct = BattleEngine::previewMonster($combined, $monster, $snapshot);
checkBoss($rally['report']['boss_mechanic']['countered'] && $rally['new_monster_hp'] === $direct['new_monster_hp'], 'the whole arrived rally supplies the counter, not just its leader');
$armies[0]['buffs'] = ['troops_atk' => 2, 'troops_def' => 2, 'troops_hp' => 2];
checkBoss(BattleEngine::previewMonsterArmies($armies, $monster, $snapshot)['report']['boss_mechanic']['counter_power_share_percent'] === 50.0, 'combat buffs never change the base-power composition rule');

// The resolver chooses secret server luck. Reapply the pure mechanic to the
// actual reported damage, instead of predicting or controlling that roll.
for ($i = 0; $i < 8; $i++) {
    $actual = BattleEngine::resolveMonsterArmies([['troops' => $troops, 'buffs' => []]], $monster, $snapshot);
    $effect = $actual['report']['boss_mechanic'];
    $expected = BossMechanics::afterDamage($snapshot, $troops, $monster['hp_current'], $effect['hp_before_regeneration']);
    checkBoss($effect === $expected && $actual['new_monster_hp'] === $expected['hp_after_regeneration'], 'actual combat and preview use the same regeneration rule '.$i);
}

/** Equal base-power shares independent of differences between troop unit powers. */
function bossFormation(array $shares, int $scale = 1): array
{
    $codes = ['infantry'=>50100101, 'ranged'=>50200101, 'cavalry'=>50300101];
    $powers = array_map(static fn(int $code): int => (int)TroopData::get($code)['power'], $codes);
    $common = array_product($powers);
    $troops = [];
    foreach ($codes as $type => $code) if (($shares[$type] ?? 0) > 0) $troops[$code] = (int)$shares[$type] * intdiv($common, $powers[$type]) * $scale;
    return $troops;
}

function withoutBossMetadata(array $result): array
{
    unset($result['report']['boss_mechanic'], $result['report']['required_power_before_mechanic'], $result['report']['army_power_before_mechanic'], $result['report']['monster_snapshot']['required_power_before_mechanic']);
    return $result;
}

$families = [
    20202101 => ['id'=>'frostgrimm_ice_armor', 'counter'=>['infantry'=>50,'ranged'=>50], 'old_counter'=>['infantry'=>30,'ranged'=>70], 'plain'=>['ranged'=>100]],
    20202201 => ['id'=>'sandmaul_sandstorm', 'counter'=>['infantry'=>50,'cavalry'=>50], 'old_counter'=>['infantry'=>70,'cavalry'=>30], 'plain'=>['infantry'=>100]],
    20202301 => ['id'=>'glutramm_ember_backlash', 'counter'=>['infantry'=>50,'ranged'=>50], 'old_counter'=>['infantry'=>70,'ranged'=>30], 'plain'=>['infantry'=>100]],
    20200501 => ['id'=>'daemmerhorn_runic_barrier', 'counter'=>['infantry'=>30,'ranged'=>30,'cavalry'=>40], 'old_counter'=>['infantry'=>20,'ranged'=>20,'cavalry'=>60], 'plain'=>['infantry'=>100]],
];
foreach ($families as $code => $family) {
    $definition = MonsterData::definition($code);
    $rule = $definition['boss_mechanic'];
    checkBoss($rule['id'] === $family['id'] && $rule['version'] === 1, 'current definition carries its own supported skill '.$code);
    $expectedThreshold=$code===20200501?30:50;
    foreach (range($code, $code + 9) as $levelCode) checkBoss(MonsterData::definition($levelCode)['boss_mechanic'] === $rule && MonsterData::definition($levelCode)['boss_mechanic']['counter_power_percent'] === $expectedThreshold, 'all ten levels require the current '.$expectedThreshold.'-percent base-power counter '.$levelCode);
    $definition['stats']['hp'] = 1000; $definition['amount'] = 1; $definition['required_power'] = 1000;
    $row = ['monster_code'=>$code, 'hp_current'=>1000, 'effective_monster_level'=>10];
    $legacyDefinition = $definition; unset($legacyDefinition['boss_mechanic']);
    $plainTroops = bossFormation($family['plain'], 100);
    $counterTroops = bossFormation($family['counter'], 100);
    $current = BattleEngine::previewMonster($plainTroops, $row, $definition, [], 950);
    $old = BattleEngine::previewMonster($plainTroops, $row, $legacyDefinition, [], 950);
    $effect = $current['report']['boss_mechanic'];
    checkBoss($effect['active'] && !$effect['countered'], 'unprepared formation activates the saved skill '.$code);
    checkBoss($current !== $old, 'the skill changes the real battle calculation '.$code);
    checkBoss($current === BattleEngine::previewMonster($plainTroops, $row, $definition, [], 950), 'preview remains deterministic '.$code);
    checkBoss($current['report']['required_power_before_mechanic'] === 1000 && $current['report']['army_power_before_mechanic'] === 950.0, 'reports retain the unchanged comparison base '.$code);
    $counterResult = BattleEngine::previewMonster($counterTroops, $row, $definition, [], 950);
    $counterEffect = $counterResult['report']['boss_mechanic'];
    checkBoss($counterEffect['countered'] && !$counterEffect['active'] && $counterEffect['counter_power_share_percent'] === (float)$rule['counter_power_percent'], 'the exact unrounded counter threshold works '.$code);
    checkBoss(withoutBossMetadata($counterResult) === BattleEngine::previewMonster($counterTroops, $row, $legacyDefinition, [], 950), 'countering restores the unchanged legacy calculation '.$code);
    $counterType = $rule['counter_type'] === 'balanced' ? 'infantry' : $rule['counter_type'];
    $counterCode = ['infantry'=>50100101,'ranged'=>50200101,'cavalry'=>50300101][$counterType];
    $below = $counterTroops; $below[$counterCode]--;
    checkBoss(!BattleEngine::previewMonster($below, $row, $definition, [], 950)['report']['boss_mechanic']['countered'], 'one base-power unit below the threshold is not rounded up '.$code);
    $oldFormation=bossFormation($family['old_counter'],100);
    $oldThreshold=$code===20200501?20:30;
    $oldSnapshot=$definition;$oldSnapshot['boss_mechanic']['counter_power_percent']=$oldThreshold;
    $oldSnapshot=json_decode(json_encode($oldSnapshot,JSON_THROW_ON_ERROR),true,512,JSON_THROW_ON_ERROR);
    $savedResult=BattleEngine::previewMonster($oldFormation,$row,$oldSnapshot,[],950);
    $savedRule=$savedResult['report']['boss_mechanic'];
    checkBoss($savedRule['counter_power_percent']===$oldThreshold && $savedRule['counter_power_share_percent']===(float)$oldThreshold && $savedRule['countered'] && !$savedRule['active'], 'saved old '.$oldThreshold.'-percent rule remains authoritative '.$code);
    checkBoss(withoutBossMetadata($savedResult)===BattleEngine::previewMonster($oldFormation,$row,$legacyDefinition,[],950), 'historical counter still suppresses the whole skill '.$code);
    $oldAgainstNew=BattleEngine::previewMonster($oldFormation,$row,$definition,[],950)['report']['boss_mechanic'];
    checkBoss(!$oldAgainstNew['countered'] && $oldAgainstNew['active'] && $oldAgainstNew['counter_power_share_percent']===(float)$oldThreshold, 'old formation cannot counter the current stricter skill '.$code);
    $wrongId = $definition; $wrongId['boss_mechanic']['id'] = $code === 20202101 ? 'sandmaul_sandstorm' : 'frostgrimm_ice_armor';
    $invalid = ['wrong family'=>$wrongId];
    foreach (['version'=>999, 'counter_type'=>'siege', 'counter_power_percent'=>0] as $parameter=>$value) {$bad=$definition;$bad['boss_mechanic'][$parameter]=$value;$invalid[$parameter]=$bad;}
    foreach ($rule as $parameter=>$value) {
        if (!is_int($value)) continue;
        $bad=$definition;unset($bad['boss_mechanic'][$parameter]);$invalid['missing '.$parameter]=$bad;
        foreach ([(string)$value,-1,101] as $badValue) {$bad=$definition;$bad['boss_mechanic'][$parameter]=$badValue;$invalid[$parameter.' '.json_encode($badValue)]=$bad;}
    }
    $bad=$definition;$bad['type']='solo';$invalid['wrong monster kind']=$bad;
    $bad=$definition;$bad['boss_mechanic']['active']=true;$invalid['forged resolved field']=$bad;
    foreach ($invalid as $label=>$bad) {
        $bare=$bad;unset($bare['boss_mechanic']);
        checkBoss(BattleEngine::previewMonster($plainTroops,$row,$bad,[],950) === BattleEngine::previewMonster($plainTroops,$row,$bare,[],950), 'invalid saved rule is inert: '.$code.' '.$label);
    }
    $frozen=json_decode(json_encode($definition,JSON_THROW_ON_ERROR),true,512,JSON_THROW_ON_ERROR);
    checkBoss($current === BattleEngine::previewMonster($plainTroops,$row,$frozen,[],950), 'serialized encounter preserves the same skill '.$code);
    $armies=[];foreach($counterTroops as $troopCode=>$count)$armies[]=['troops'=>[$troopCode=>$count],'buffs'=>[]];
    $rally=BattleEngine::previewMonsterArmies($armies,$row,$definition);
    $direct=BattleEngine::previewMonster($counterTroops,$row,$definition);
    checkBoss($rally['report']['boss_mechanic']['countered'] && $rally['new_monster_hp']===$direct['new_monster_hp'], 'combined rally arrivals provide the counter '.$code);
    $armies[0]['buffs']=['troops_atk'=>2,'troops_def'=>2,'troops_hp'=>2];
    $buffed=BattleEngine::previewMonsterArmies($armies,$row,$definition);
    checkBoss($buffed['report']['boss_mechanic']['type_power_share_percent']===$counterEffect['type_power_share_percent'], 'owner buffs never alter the base-power counter '.$code);
    $actual=BattleEngine::resolveMonster($plainTroops,$row,$definition,[],950);
    $actualRule=$actual['report']['boss_mechanic'];
    $expected=$code===20202301
        ? BossMechanics::afterInjuries($definition,$plainTroops,$actualRule['injury_ratio_before'],$actual['monster_killed'])
        : BossMechanics::beforeBattle($definition,$plainTroops,1000,1000,950);
    checkBoss($actualRule===$expected, 'actual server-luck battle uses the same rule as preview '.$code);
    if ($code===20202101 || $code===20200501) {
        $required=$code===20202101?1120:1150;
        checkBoss($current['report']['required_power']===$required && $current['report']['monster_snapshot']['required_power']===$required && $effect['required_power_after']===$required, 'effective armor threshold reaches battle and report '.$code);
        checkBoss($current['new_monster_hp']>$old['new_monster_hp'], 'armor prevents real monster damage '.$code);
    } elseif ($code===20202201) {
        checkBoss($effect['army_power_before']===950.0 && $effect['army_power_after']===855.0 && $current['report']['army_power_before_luck']===855.0 && $current['report']['army_power']===855.0, 'sandstorm reduces the actual effective army power before luck');
        checkBoss($current['new_monster_hp']>$old['new_monster_hp'], 'sandstorm prevents real monster damage');
    } else {
        checkBoss(abs($effect['injury_ratio_after']-$effect['injury_ratio_before']*1.2)<1e-12 && array_sum($current['attacker_losses'])>array_sum($old['attacker_losses']), 'ember backlash adds twenty percent relative wounds');
        checkBoss($current['new_monster_hp']===$old['new_monster_hp'] && $current['outcome']===$old['outcome'], 'ember backlash never changes damage or outcome');
        $cap=BattleEngine::previewMonster($plainTroops,$row,$definition,[],1);
        checkBoss($cap['report']['boss_mechanic']['injury_ratio_after']===.35 && $cap['report']['attacker_injury_ratio']===.35, 'ember backlash never exceeds the existing thirty-five percent cap');
        $win=BattleEngine::previewMonster($plainTroops,$row,$definition,[],1050);
        checkBoss($win['monster_killed'] && !$win['report']['boss_mechanic']['active'] && withoutBossMetadata($win)===BattleEngine::previewMonster($plainTroops,$row,$legacyDefinition,[],1050), 'a killing blow suppresses backlash even when the normal battle has wounds');
    }
}

$dusk=MonsterData::definition(20200501);$dusk['stats']['hp']=1000;$dusk['amount']=1;
foreach ([499=>false,500=>false,501=>true] as $hp=>$active) {
    $phase=BossMechanics::beforeBattle($dusk,bossFormation(['infantry'=>100]),$hp,1000,900);
    checkBoss($phase['phase_active']===$active && $phase['active']===$active, 'runic barrier uses strict pre-battle fifty-percent HP threshold '.$hp);
}
$balanced=BossMechanics::beforeBattle($dusk,bossFormation(['infantry'=>30,'ranged'=>40,'cavalry'=>30]),1000,1000,900);
checkBoss($balanced['countered'] && $balanced['counter_power_share_percent']===30.0 && $balanced['type_power_share_percent']===['infantry'=>30.0,'ranged'=>40.0,'cavalry'=>30.0], 'balanced counter reports the weakest of all three base-power shares');
foreach(['infantry'=>50100101,'ranged'=>50200101,'cavalry'=>50300101] as $type=>$code){
    $shares=['infantry'=>35,'ranged'=>35,'cavalry'=>35];$shares[$type]=30;
    $formation=bossFormation($shares,100);
    $exact=BossMechanics::beforeBattle($dusk,$formation,1000,1000,900);
    checkBoss($exact['countered'] && $exact['type_power_share_percent'][$type]===30.0, 'each troop type independently meets the exact runic thirty-percent boundary '.$type);
    $formation[$code]--;
    checkBoss(!BossMechanics::beforeBattle($dusk,$formation,1000,1000,900)['countered'], 'one troop below the runic threshold fails for each required type '.$type);
}
$missing=BossMechanics::beforeBattle($dusk,bossFormation(['infantry'=>50,'ranged'=>50]),1000,1000,900);
checkBoss(!$missing['countered'] && $missing['counter_power_share_percent']===0.0, 'two troop types cannot counter the runic barrier');
$changed=MonsterData::definition(20202101);$changed['boss_mechanic']['required_power_percent']=25;
checkBoss(BossMechanics::beforeBattle($changed,bossFormation(['ranged'=>100]),1000,1000,900)['required_power_after']===1250, 'a saved valid parameter is honored instead of being replaced by current catalog defaults');
$glut=MonsterData::definition(20202301);
checkBoss(!BossMechanics::afterInjuries($glut,bossFormation(['infantry'=>100]),.35,false)['active'], 'a fully capped injury ratio reports no further actual effect');

// Integer HP rounding cannot bypass the power threshold or leave a defeated
// result pointing to a zero-HP target that can no longer be fought or rewarded.
foreach ([20200101,20202301,20202401] as $code) {
    foreach ([1,100] as $hp) {
        $definition=MonsterData::definition($code);
        $definition['stats']['hp']=$hp;$definition['amount']=1;$definition['required_power']=1000;
        $row=['monster_code'=>$code,'hp_current'=>$hp,'effective_monster_level'=>10];
        $formation=bossFormation(['infantry'=>100],100);
        $near=BattleEngine::previewMonster($formation,$row,$definition,[],999);
        checkBoss(!$near['monster_killed'] && $near['outcome']==='defender_wins' && $near['new_monster_hp']>=1, 'a rounded sub-threshold hit leaves the defender alive '.$code.' HP '.$hp);
        checkBoss($near['report']['monster_snapshot']['hp_after']===$near['new_monster_hp'] && $near['report']['monster_hp_after']===$near['new_monster_hp'] && !$near['report']['monster_killed'], 'near-threshold HP and outcome agree throughout the report '.$code.' HP '.$hp);
        if($code===20202301){
            $effect=$near['report']['boss_mechanic'];
            checkBoss($effect['active'] && abs($effect['injury_ratio_before']-.0503)<1e-12 && abs($effect['injury_ratio_after']-.06036)<1e-12, 'a surviving ember boss consistently applies relative wounds at the rounding boundary HP '.$hp);
        }elseif($code===20202401){
            $effect=$near['report']['boss_mechanic'];
            checkBoss($effect['hp_before_regeneration']===1 && $effect['hp_after_regeneration']===$near['new_monster_hp'], 'Grumwald regeneration receives living pre-heal HP at the rounding boundary HP '.$hp);
        }else{
            checkBoss($near['new_monster_hp']===1 && $near['report']['attacker_injury_ratio']===.0503, 'normal monster keeps ordinary wounds and one remaining HP '.$hp);
        }
        $kill=BattleEngine::previewMonster($formation,$row,$definition,[],1000);
        checkBoss($kill['monster_killed'] && $kill['outcome']==='attacker_wins' && $kill['new_monster_hp']===0 && $kill['report']['attacker_injury_ratio']===.03, 'reaching the exact threshold remains a consistent killing blow '.$code.' HP '.$hp);
        if($code===20202301)checkBoss(!$kill['report']['boss_mechanic']['active'] && $kill['report']['boss_mechanic']['injury_ratio_before']===$kill['report']['boss_mechanic']['injury_ratio_after'], 'a killed ember boss cannot apply backlash at exact threshold HP '.$hp);
        if($code===20202401)checkBoss($kill['report']['boss_mechanic']['hp_restored']===0, 'a killed Grumwald cannot heal at exact threshold HP '.$hp);
    }
}

foreach ([20200101,20200201,20200301,20200401,20200601,20200701,20200801,20200901,20209901] as $code) {
    $definition=MonsterData::definition($code);$row=['monster_code'=>$code,'hp_current'=>1000];
    checkBoss(!isset($definition['boss_mechanic']), 'normal and dormant families receive no current skill '.$code);
    $unchanged=BattleEngine::previewMonster([50100101=>100],$row,$definition);
    foreach ($families as $bossCode=>$family) {
        $forged=$definition;$forged['boss_mechanic']=MonsterData::definition($bossCode)['boss_mechanic'];
        checkBoss($unchanged===BattleEngine::previewMonster([50100101=>100],$row,$forged), 'foreign rule cannot change normal or dormant combat '.$code.' '.$family['id']);
    }
}

foreach ([20200501, 20202101, 20202201, 20202301, 20209901] as $code) {
    $definition = MonsterData::definition($code);
    unset($definition['boss_mechanic']);
    $row = ['monster_code' => $code, 'hp_current' => 1000];
    $current = BattleEngine::previewMonster($troops, $row, $definition);
    $forged = $definition;
    $forged['boss_mechanic'] = $snapshot['boss_mechanic'];
    checkBoss($current === BattleEngine::previewMonster($troops, $row, $forged), 'a Grumwald rule cannot leak into a different species '.$code);
}
echo "ALL $checks BOSS MECHANIC CHECKS PASSED\n";
