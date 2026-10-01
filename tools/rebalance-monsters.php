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
define('ROOT_DIR', $root);
require ROOT_DIR.'/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
$catalogue = json_decode((string)file_get_contents($monsterFile), true, 512, JSON_THROW_ON_ERROR);

$changed = 0;
$summary = [];
foreach ($catalogue['monsters'] as &$monster) {
    $name = (string) $monster['name'];
    $level = (int) $monster['level'];
    if ($name === 'Ork-Späher' || ($name === 'Orc' && $level === 0)) continue;

    $profile = \Conquer\Game\Map\MonsterPower::profile($monster);
    $difficulty = $profile['factor'];
    $capacity = $profile['capacity'];
    $averageAttack = $profile['average_attack'];
    $absorptionPerUnit = (float) $monster['stats']['hp'] + (float) $monster['stats']['defense'];
    $balanced = max(10, (int) round(($capacity * $averageAttack * $difficulty / $absorptionPerUnit) / 10) * 10);
    $old = (int) $monster['amount'];
    $monster['source_amount'] ??= $old;
    $monster['amount'] = $balanced;
    if ($old !== $balanced) $changed++;
    $summary[] = [$name, $level, $old, $balanced, round($balanced * $absorptionPerUnit / ($capacity * $averageAttack), 3)];
}
unset($monster);

$catalogue['balance_notes']['combat_amounts'] = 'T1-T5 source troop stats, two monster levels per troop tier; source_amount preserves original NPC counts.';
$catalogue['balance_notes']['solo_target'] = 'Goblin/Orc/Skeleton/Golem: 65/75/82/90 percent of a full reference march; even levels require 25 percent more power.';
$catalogue['balance_notes']['regional_rally_target'] = 'Regional bosses use the same five-tier progression with full alliance rallies.';
$catalogue['balance_notes']['endgame_target'] = 'T5 rallies: Green/Red/Gold/Magdar factors 85/95/105/115 percent, multiplied by 1.15/1.5/2.0 for boss levels 1/2/3.';

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
