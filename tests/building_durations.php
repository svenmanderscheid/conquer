<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR.'/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();

use Conquer\Game\City\BuildingData;

function durationCheck(bool $ok, string $label): void
{
    global $checks;
    if (!$ok) throw new RuntimeException($label);
    $checks++;
}

$checks = 0;
foreach ([0=>3024000,20=>2520000,100=>1512000,180=>1080000,255=>851831] as $bonus=>$expected) {
    durationCheck(BuildingData::getBuildTime('castle',30,['construction_speed'=>$bonus])===$expected, "+$bonus percent increases building speed, even above 100 percent");
}
durationCheck(BuildingData::getBuildTime('castle',30,['construction_speed'=>155,'talent_construction_speed'=>.1])===1078075, 'max VIP10 construction stack keeps the separate talent multiplier');
durationCheck(BuildingData::getBuildTime('castle',31,['construction_speed'=>180])===0, 'nonexistent building level remains unavailable');
$day = 86_400;
durationCheck(BuildingData::getBuildTime('castle',30,['construction_speed'=>25])===28*$day, 'VIP10 alone reduces the approved castle30 base to 28 days');
$catalogue = json_decode((string)file_get_contents(ROOT_DIR.'/data/buildings.json'), true, 512, JSON_THROW_ON_ERROR);
$sourceRoot = ROOT_DIR.'/data/balance-source/';

foreach ($catalogue['buildings'] as $code => $levels) {
    $source = json_decode((string)file_get_contents($sourceRoot.$code.'.json'), true, 512, JSON_THROW_ON_ERROR);
    for ($level = 1; $level <= 20; $level++) {
        durationCheck(BuildingData::getBuildTime($code, $level) === $source[(string)$level]['time'], "$code L$level preserves the source duration");
    }
    for ($level = 21; $level <= 30; $level++) {
        durationCheck(BuildingData::getBuildTime($code, $level) > BuildingData::getBuildTime($code, $level - 1), "$code L$level is slower than its predecessor");
    }
}

durationCheck(BuildingData::getBuildTime('castle', 21) === 586071, 'Castle L21 begins the factor 1.2 curve');
durationCheck(BuildingData::getBuildTime('academy', 21) === 4*$day, 'Academy L21 begins the late-game curve at four days');
durationCheck(BuildingData::getBuildTime('castle', 30) === 35*$day, 'Castle L30 takes exactly thirty-five days');
durationCheck(BuildingData::getBuildTime('academy', 30) === 30*$day, 'Academy L30 takes exactly thirty days');
durationCheck(BuildingData::getBuildTime('barrack', 30) === 10*$day, 'Troop schools share the ten-day L30 curve');
durationCheck(BuildingData::getBuildTime('archery_range', 30) === 10*$day && BuildingData::getBuildTime('stable', 30) === 10*$day, 'All three troop schools use the same L30 duration');
echo "PASS $checks building-duration checks; all L1–20 source times, monotonic L21–30 curves and L30 endpoints verified\n";
