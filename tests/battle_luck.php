<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR.'/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();

use Conquer\Game\March\BattleLuck;

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
echo "ALL BATTLE LUCK CHECKS PASSED\n";
