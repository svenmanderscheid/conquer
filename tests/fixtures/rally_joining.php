<?php
declare(strict_types=1);

// Invoked only by preview-feature-fixture.php after its disposable database is active.
use Conquer\Game\City\CityState;
use Conquer\Game\Map\{MonsterData, WorldPlacement};
use Conquer\Game\Rally\{MonsterRally, RallyService};
use Conquer\Game\World\WorldContext;

if (!preg_match('/^conquer_feature_test_[a-f0-9]{12}$/D', (string)$db->query('SELECT DATABASE()')->fetchColumn())) {
    throw new RuntimeException('Rally joining preview requires a disposable feature database.');
}
WorldContext::bind(1);
$db->execute("UPDATE worlds SET status='running' WHERE id=1");
foreach ([2 => ['RallyCaptain', 100, 65], 3 => ['ScoutCompanion', 94, 65], 4 => ['OtherCaptain', 105, 72]] as $playerId => [$name, $x, $y]) {
    $db->execute("INSERT INTO players(id,username,email,password_hash,action_points,last_ap_regen) VALUES(?,?,?,'unused',200,UTC_TIMESTAMP())", [$playerId, $name, strtolower($name).'@tests.invalid']);
    $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,castle_level,food,lumber,stone,gold) VALUES(?,?,1,?,?,?,12,100000,100000,100000,100000)", [$playerId, $playerId, $name.' city', $x, $y]);
    foreach (CityState::BUILDING_CODES as $code) $db->execute('INSERT INTO city_buildings(city_id,building_code,level) VALUES(?,?,?)', [$playerId, $code, $code === 'castle' ? 12 : 7]);
    foreach ([50100101, 50200101, 50300101] as $code) $db->execute('INSERT INTO city_troops(city_id,troop_code,count) VALUES(?,?,500)', [$playerId, $code]);
}
$db->execute("INSERT INTO alliances(id,world_id,name,tag,leader_id) VALUES(1,1,'Rally Test Alliance','RTA',2)");
$db->execute("INSERT INTO alliance_members(alliance_id,player_id,world_id,role) VALUES(1,1,1,'member'),(1,2,1,'leader'),(1,3,1,'member'),(1,4,1,'member')");
foreach ([2 => [80, 75], 4 => [86, 78]] as $host => [$x, $y]) {
    $target = $db->transaction(static function ($db) use ($x, $y): array {
        WorldPlacement::lockWorld($db, 1);
        $definition = MonsterData::get(20200501);
        $spot = WorldPlacement::findNear($db, 1, WorldPlacement::monsterKind(20200501), $x, $y);
        if (!$spot) throw new RuntimeException('No rally joining fixture monster position.');
        $db->execute("INSERT INTO field_monsters(world_id,monster_code,coord_x,coord_y,hp_current,monster_type,expires_at) VALUES(1,20200501,?,?,?,'rally',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY))", [$spot[0], $spot[1], $definition['stats']['hp'] * $definition['amount']]);
        return $spot;
    });
    $rally = MonsterRally::start($host, $host, $target[0], $target[1], [50100101 => 123, 50200101 => 87], 30, 'Synthetic rally joining preview');
    if ($host === 2) {
        RallyService::join(3, 3, $rally, [50200101 => 51, 50300101 => 29]);
        $db->execute("UPDATE rally_participants SET arrival_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE rally_id=? AND player_id=3", [$rally]);
        RallyService::tick();
    }
}
