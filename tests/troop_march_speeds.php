<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR.'/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();

use Conquer\Game\City\TroopData;
use Conquer\Game\March\MarchSpeed;

function speedCheck(bool $ok, string $label): void
{
    if (!$ok) throw new RuntimeException($label);
    echo "PASS $label\n";
}

for ($tier = 1; $tier <= 10; $tier++) {
    $infantry = 50100001 + $tier * 100;
    $archers = 50200001 + $tier * 100;
    $cavalry = 50300001 + $tier * 100;
    $infantrySpeed = (int)TroopData::get($infantry)['march_speed'];
    $archerSpeed = (int)TroopData::get($archers)['march_speed'];
    $cavalrySpeed = (int)TroopData::get($cavalry)['march_speed'];
    speedCheck($cavalrySpeed > $archerSpeed && $archerSpeed > $infantrySpeed, "T$tier cavalry > archers > infantry");

    $buffs = ['march_speed'=>.10, 'talent_hunt_march'=>.25];
    speedCheck(MarchSpeed::monster($cavalry, $buffs) > MarchSpeed::monster($archers, $buffs)
        && MarchSpeed::monster($archers, $buffs) > MarchSpeed::monster($infantry, $buffs), "T$tier monster march preserves the class order");
}
echo "ALL TROOP MARCH SPEED CHECKS PASSED\n";
