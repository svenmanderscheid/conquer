<?php
declare(strict_types=1);

/**
 * Rebuild the combat-facing monster amounts from the actual troop curve.
 *
 * Source imports remain visible as source_amount. The playable amount is sized
 * against a mixed army of the matching tier and the capacity available when
 * that tier unlocks. Run without --apply for a preview.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

$root = dirname(__DIR__);
$apply = in_array('--apply', $argv, true);
$monsterFile = $root . '/data/monsters.json';
$troopFile = $root . '/data/troops.json';
$catalogue = json_decode((string) file_get_contents($monsterFile), true, 512, JSON_THROW_ON_ERROR);
$troops = json_decode((string) file_get_contents($troopFile), true, 512, JSON_THROW_ON_ERROR)['troops'];

$tiers = [];
foreach ($troops as $troop) {
    $tier = (int) $troop['tier'];
    $tiers[$tier]['attacks'][] = (int) $troop['attack'];
    $tiers[$tier]['castle'] = max((int) ($tiers[$tier]['castle'] ?? 1), (int) $troop['unlock_castle']);
}

$baseMarchCapacity = static fn(int $castle): int => 5000 + (int) floor(45000 * ($castle - 1) / 29);
$rallyCapacity = static function (int $hall): int {
    $points = [1 => 20000, 5 => 50000, 10 => 100000, 20 => 250000, 30 => 500000];
    $previous = 1;
    $base = 20000;
    foreach ($points as $at => $cap) {
        if ($hall >= $at) {
            $previous = $at;
            $base = $cap;
            continue;
        }
        return $base + (int) floor(($hall - $previous) * ($cap - $base) / ($at - $previous));
    }
    return $base;
};

$soloDifficulty = ['Treasure Goblin' => 0.65, 'Orc' => 0.75, 'Skeleton' => 0.82, 'Golem' => 0.90];
$regional = ['Deathkar', 'Frostgrimm', 'Sandmaul', 'Glutramm', 'Grumwald'];
$endgame = [
    'Green Dragon' => [0.85, [8, 9, 10]],
    'Red Dragon'   => [0.95, [8, 9, 10]],
    'Gold Dragon'  => [1.05, [8, 9, 10]],
    'Magdar'       => [1.15, [8, 9, 10]],
];

$changed = 0;
$summary = [];
foreach ($catalogue['monsters'] as &$monster) {
    $name = (string) $monster['name'];
    $level = (int) $monster['level'];
    if ($name === 'Ork-Späher' || ($name === 'Orc' && $level === 0)) continue;

    $tier = $level;
    $difficulty = null;
    $capacity = null;
    if (isset($soloDifficulty[$name])) {
        $difficulty = $soloDifficulty[$name];
        $capacity = $baseMarchCapacity($tiers[$tier]['castle']);
    } elseif (in_array($name, $regional, true)) {
        $difficulty = 0.90;
        $capacity = $rallyCapacity($tiers[$tier]['castle']);
    } elseif (isset($endgame[$name])) {
        [$difficulty, $tierMap] = $endgame[$name];
        $tier = $tierMap[$level - 1];
        $capacity = $rallyCapacity($tiers[$tier]['castle']);
    } else {
        continue;
    }

    $averageAttack = array_sum($tiers[$tier]['attacks']) / count($tiers[$tier]['attacks']);
    $absorptionPerUnit = (float) $monster['stats']['hp'] + (float) $monster['stats']['defense'];
    $balanced = max(10, (int) round(($capacity * $averageAttack * $difficulty / $absorptionPerUnit) / 10) * 10);
    $old = (int) $monster['amount'];
    $monster['source_amount'] ??= $old;
    $monster['amount'] = $balanced;
    if ($old !== $balanced) $changed++;
    $summary[] = [$name, $level, $old, $balanced, round($balanced * $absorptionPerUnit / ($capacity * $averageAttack), 3)];
}
unset($monster);

$catalogue['balance_notes']['combat_amounts'] = 'Playable amount is derived from matching troop tiers and real solo/rally capacity; source_amount preserves imported NPC counts.';
$catalogue['balance_notes']['solo_target'] = 'Treasure Goblin 65%, Orc 75%, Skeleton 82%, Golem 90% of an unbuffed full mixed march.';
$catalogue['balance_notes']['regional_rally_target'] = 'Regional bosses use 90% of an unbuffed full mixed rally at the matching progression tier.';
$catalogue['balance_notes']['endgame_target'] = 'Green/Red/Gold/Magdar use 85%/95%/105%/115% of T8-T10 endgame rallies.';

echo ($apply ? 'APPLY' : 'CHECK') . ": {$changed} monster amounts differ.\n";
foreach ($summary as [$name, $level, $old, $balanced, $ratio]) {
    printf("%-18s L%2d %8d -> %6d  target %.1f%%\n", $name, $level, $old, $balanced, $ratio * 100);
}

if ($apply) {
    $json = json_encode($catalogue, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    // PHP indents JSON with four spaces; the repository catalogue uses two.
    $json = preg_replace_callback('/^( +)/m', static fn(array $m): string => str_repeat(' ', intdiv(strlen($m[1]), 2)), $json) . "\n";
    if (file_put_contents($monsterFile, $json) === false) throw new RuntimeException('Could not write monsters.json.');
    echo "Updated data/monsters.json.\n";
} else {
    echo "Run with --apply to write the balanced catalogue.\n";
}
