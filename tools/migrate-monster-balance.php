<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(1);
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR . '/src/Bootstrap.php';
\Conquer\Bootstrap::init(ROOT_DIR);

$apply = in_array('--apply', $argv, true);
$db = \Conquer\Db\Connection::getInstance();
$marker = 'data:monster-combat-balance-v1';
if ($db->query('SELECT 1 FROM migrations WHERE filename=?', [$marker])->fetchColumn() !== false) {
    echo "Monster combat balance v1 is already applied.\n";
    exit(0);
}
$rows = $db->query('SELECT id,world_id,monster_code,hp_current FROM field_monsters WHERE hp_current>0 ORDER BY world_id,id')->fetchAll();
$updates = [];
foreach ($rows as $row) {
    $definition = \Conquer\Game\World\WorldContext::run((int)$row['world_id'], static fn() => \Conquer\Game\Map\MonsterData::definition((int)$row['monster_code']));
    $hp = max(1, (int)$definition['stats']['hp']);
    $oldMax = $hp * max(1, (int)($definition['source_amount'] ?? $definition['amount']));
    $newMax = $hp * max(1, (int)$definition['amount']);
    $healthRatio = min(1.0, (int)$row['hp_current'] / max(1, $oldMax));
    $newHp = max(1, (int)round($newMax * $healthRatio));
    if ($newHp !== (int)$row['hp_current']) $updates[] = [(int)$row['id'], $newHp];
}

echo ($apply ? 'APPLY' : 'CHECK') . ': ' . count($updates) . ' of ' . count($rows) . " living monsters need HP rescaling.\n";
if (!$apply) {
    echo "Run with --apply to preserve each monster's remaining-health percentage on the new curve.\n";
    exit(0);
}

\Conquer\Game\WorldRules::combatLock(static function () use ($db, $updates, $marker): void {
    $db->transaction(static function (\Conquer\Db\Connection $db) use ($updates, $marker): void {
        foreach ($updates as [$id, $hp]) {
            $db->execute('UPDATE field_monsters SET hp_current=? WHERE id=? AND hp_current>0', [$hp, $id]);
        }
        $db->execute('INSERT IGNORE INTO migrations(filename) VALUES(?)', [$marker]);
    });
});
echo 'Updated ' . count($updates) . " living monsters.\n";
