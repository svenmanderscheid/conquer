<?php
declare(strict_types=1);

define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR . '/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR . '/src'))->register();

function balanceCheck(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$monsters = json_decode((string) file_get_contents(ROOT_DIR . '/data/monsters.json'), true, 512, JSON_THROW_ON_ERROR)['monsters'];
$troops = json_decode((string) file_get_contents(ROOT_DIR . '/data/troops.json'), true, 512, JSON_THROW_ON_ERROR)['troops'];
$tiers = [];
foreach ($troops as $troop) {
    $tier = (int) $troop['tier'];
    $tiers[$tier]['attacks'][] = (int) $troop['attack'];
    $tiers[$tier]['powers'][] = (int) $troop['power'];
    $tiers[$tier]['castle'] = max((int) ($tiers[$tier]['castle'] ?? 1), (int) $troop['unlock_castle']);
}
$march = static fn(int $castle): int => 5000 + (int) floor(45000 * ($castle - 1) / 29);
$rally = static function (int $hall): int {
    $points = [1 => 20000, 5 => 50000, 10 => 100000, 20 => 250000, 30 => 500000];
    $previous = 1; $base = 20000;
    foreach ($points as $at => $cap) {
        if ($hall >= $at) { $previous = $at; $base = $cap; continue; }
        return $base + (int) floor(($hall - $previous) * ($cap - $base) / ($at - $previous));
    }
    return $base;
};
$solo = ['Treasure Goblin' => 0.65, 'Orc' => 0.75, 'Skeleton' => 0.82, 'Golem' => 0.90];
$regional = ['Deathkar', 'Dämmerhorn', 'Frostgrimm', 'Sandmaul', 'Glutramm', 'Grumwald'];
$endgame = ['Green Dragon' => [0.85, [8,9,10]], 'Red Dragon' => [0.95, [8,9,10]], 'Gold Dragon' => [1.05, [8,9,10]], 'Magdar' => [1.15, [8,9,10]]];
$checked = 0;
foreach ($monsters as $monster) {
    $name = $monster['name']; $level = (int) $monster['level'];
    if ($name === 'Ork-Späher' || ($name === 'Orc' && $level === 0)) continue;
    $tier = $level; $target = null; $capacity = null;
    if (isset($solo[$name])) { $target = $solo[$name]; $capacity = $march($tiers[$tier]['castle']); }
    elseif (in_array($name, $regional, true)) { $target = 0.90; $capacity = $rally($tiers[$tier]['castle']); }
    elseif (isset($endgame[$name])) { [$target, $map] = $endgame[$name]; $tier = $map[$level - 1]; $capacity = $rally($tiers[$tier]['castle']); }
    else continue;
    $averageAttack = array_sum($tiers[$tier]['attacks']) / count($tiers[$tier]['attacks']);
    $ratio = $monster['amount'] * ($monster['stats']['hp'] + $monster['stats']['defense']) / ($capacity * $averageAttack);
    balanceCheck(abs($ratio - $target) <= 0.05, "$name L$level has combat ratio $ratio, expected $target");
    balanceCheck(isset($monster['source_amount']) && $monster['source_amount'] >= $monster['amount'], "$name L$level must preserve its imported amount");
    if (($monster['type'] ?? 'solo') === 'solo') balanceCheck($ratio <= 0.95, "$name L$level must be solo-killable without buffs");
    $expectedPower=(int)round($capacity*(array_sum($tiers[$tier]['powers'])/count($tiers[$tier]['powers']))*$target);
    balanceCheck(\Conquer\Game\Map\MonsterPower::required($monster)===$expectedPower,"$name L$level must use the progression power threshold");
    $checked++;
}

$scout = current(array_filter($monsters, static fn(array $m): bool => $m['name'] === 'Ork-Späher'));
$t1Infantry = current(array_filter($troops, static fn(array $t): bool => (int)$t['tier'] === 1 && (int)$t['type'] === 1));
balanceCheck(10 * $t1Infantry['attack'] >= $scout['amount'] * ($scout['stats']['hp'] + $scout['stats']['defense']), '10 starter infantry must defeat the tutorial scout');
balanceCheck(\Conquer\Game\Map\MonsterPower::required($scout)===10*$t1Infantry['power'],'tutorial power threshold matches ten starter infantry');
balanceCheck(\Conquer\Game\Map\MonsterPower::required(current(array_filter($monsters,static fn(array $m):bool=>$m['name']==='Golem'&&(int)$m['level']===10)))>4000000,'level 10 solo monster requires a multi-million T10 army, not 2,000 troops');
balanceCheck($checked === 102, "Expected 102 balanced combat definitions, got $checked");
echo "Monster balance OK: $checked progression definitions plus tutorial scout.\n";
