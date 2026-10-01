<?php
// Synthetic preview data only. Every skill result is produced by the real engine.
use Conquer\Game\Map\{MonsterData, WorldPlacement};
use Conquer\Game\March\{BattleEngine, MonsterReport};

$db->transaction(static function ($db): void {
    WorldPlacement::lockWorld($db, 1);
    $definition = MonsterData::get(20200501);
    $spot = WorldPlacement::findNear($db, 1, WorldPlacement::monsterKind(20200501), 75, 75);
    if (!$spot) throw new RuntimeException('No preview position for Dawnhorn.');
    $db->execute("INSERT INTO field_monsters(world_id,monster_code,coord_x,coord_y,hp_current,monster_type,expires_at) VALUES(1,20200501,?,?,?,'rally',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY))", [$spot[0], $spot[1], $definition['stats']['hp'] * $definition['amount']]);
});
$source = MonsterReport::capture(1, 1, 1);
$balanced = [50100101 => 400, 50200101 => 400, 50300101 => 400];
foreach ([20202401 => 50200101, 20202101 => 50100101, 20202201 => 50300101, 20202301 => 50200101, 20200501 => null] as $code => $counter) {
    $definition = MonsterData::get($code);
    $target = $db->query('SELECT * FROM field_monsters WHERE world_id=1 AND monster_code=? ORDER BY id LIMIT 1', [$code])->fetch();
    if (!$target) throw new RuntimeException('Missing skill preview target '.$code);
    $uncountered = [$counter === 50100101 ? 50200101 : 50100101 => 500];
    $formations = [$uncountered, $counter ? [$counter => 500] : $balanced];
    if ($code === 20200501) $formations[] = $uncountered;
    foreach ($formations as $index => $troops) {
        $battleTarget = $target;
        if ($index === 2) $battleTarget['hp_current'] = (int)floor($target['hp_current'] / 2);
        $result = BattleEngine::previewMonsterArmies([['troops' => $troops, 'buffs' => []]], $battleTarget, $definition);
        $detail = $result['report'] + ['type' => 'monster_rally', 'loot' => [], 'item_rewards' => [], 'lord_xp' => 0];
        $detail['source_snapshot'] = $source;
        $detail['rally_combat_snapshot'] = $detail['combat_snapshot'];
        $db->execute('INSERT INTO battle_reports(world_id,attacker_id,attacker_city_id,target_type,target_id,target_x,target_y,outcome,data_json) VALUES(1,1,1,3,?,?,?,?,?)', [$target['id'], $target['coord_x'], $target['coord_y'], $result['outcome'], json_encode($detail, JSON_THROW_ON_ERROR)]);
    }
}
