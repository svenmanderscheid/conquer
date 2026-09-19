<?php
declare(strict_types=1);

namespace Conquer\Api\Handlers;

use Conquer\Api\Response;
use Conquer\Auth\Session;
use Conquer\Db\Connection;
use Conquer\Game\World\{LandAccessPolicy, LandProgressService, WorldContext};

/**
 * Handles /api/map/* endpoints.
 *
 * GET /api/map/info         — world seed + authenticated player's city coords
 * GET /api/map/tiles        — dynamic entities in a viewport region
 * GET /api/map/tile/:x/:y   — detailed info for one tile
 */
final class MapHandler
{
    private function __construct() {}

    /**
     * GET /api/map/info
     *
     * Returns the world seed (for client-side terrain generation) and
     * the player's city coordinates so the map can center on spawn.
     */
    public static function info(array $params): void
    {
        $session = Session::current();
        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }

        $db = Connection::getInstance();
        $world = self::world();
        $worldId = (int) $world['id'];

        $city = $db->query(
            'SELECT coord_x, coord_y, name FROM cities WHERE player_id = ? AND world_id = ?',
            [(int) $session['player_id'], $worldId]
        )->fetch();

        Response::ok([
            'world_id' => (int) $world['id'],
            'map_seed' => (int) $world['map_seed'],
            'map_size' => (int) $world['map_size'],
            'my_city'  => $city !== false ? [
                'x'    => (int) $city['coord_x'],
                'y'    => (int) $city['coord_y'],
                'name' => $city['name'],
            ] : null,
        ]);
    }

    /**
     * GET /api/map/tiles?x_min=&y_min=&x_max=&y_max=
     *
     * Returns all dynamic entities (cities, monsters, resource nodes, rallies) in the
     * requested tile region. Viewport is clamped to 100×100 tiles to prevent
     * abuse. Terrain is generated client-side from the world seed.
     */
    public static function tiles(array $params): void
    {
        $session = Session::current();
        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }

        $db       = Connection::getInstance();
        $world = self::world();
        $worldId = (int) $world['id'];
        [$xMin, $yMin, $xMax, $yMax] = self::viewport((int) $world['map_size']);
        $entities = [];

        // Player cities — includes alliance tag + active emoji
        $rows = $db->query('
            SELECT c.coord_x, c.coord_y, c.name, c.castle_level,
                   p.id AS player_id, p.username, COALESCE(k.display_name,p.username) AS display_name,
                   COALESCE(k.name_frame,\'default\') AS name_frame, COALESCE(k.city_skin,\'default\') AS city_skin,
                   am.alliance_id, a.tag AS alliance_tag,
                   CASE WHEN pe.expires_at > UTC_TIMESTAMP() THEN pe.emoji_code ELSE NULL END AS emoji_code
            FROM cities c
            JOIN players p ON c.player_id = p.id
            LEFT JOIN kingdom_profiles k ON k.player_id = p.id
            LEFT JOIN alliance_members am ON am.player_id = p.id
            LEFT JOIN alliances a ON a.id = am.alliance_id
            LEFT JOIN player_emojis pe ON pe.player_id = p.id
            WHERE c.world_id = :world
              AND c.coord_x BETWEEN :x1 AND :x2
              AND c.coord_y BETWEEN :y1 AND :y2
              AND c.is_hidden = 0
        ', [':world' => $worldId, ':x1' => $xMin, ':x2' => $xMax, ':y1' => $yMin, ':y2' => $yMax])->fetchAll();

        foreach ($rows as $row) {
            if (!self::targetOpen($worldId, $row)) continue;
            $entities[] = [
                'type'         => 'city',
                'x'            => (int) $row['coord_x'],
                'y'            => (int) $row['coord_y'],
                'name'         => $row['name'],
                'level'        => (int) $row['castle_level'],
                'player'       => $row['username'],
                'display_name' => $row['display_name'],
                'name_frame'   => $row['name_frame'],
                'city_skin'    => $row['city_skin'],
                'player_id'    => (int) $row['player_id'],
                'alliance_id'  => $row['alliance_id'] ? (int) $row['alliance_id'] : null,
                'alliance_tag' => $row['alliance_tag'],
                'emoji_code'   => $row['emoji_code'],
            ];
        }

        // Field monsters
        $rows = $db->query('
            SELECT coord_x, coord_y, monster_code, hp_current' . (LandProgressService::available() ? ', effective_monster_level, regional_level_at_spawn' : '') . '
            FROM field_monsters
            WHERE world_id = :world
              AND coord_x BETWEEN :x1 AND :x2
              AND coord_y BETWEEN :y1 AND :y2
        ', [':world' => $worldId, ':x1' => $xMin, ':x2' => $xMax, ':y1' => $yMin, ':y2' => $yMax])->fetchAll();

        foreach ($rows as $row) {
            if (!self::targetOpen($worldId, $row)) continue;
            $entities[] = [
                'type'         => 'monster',
                'x'            => (int) $row['coord_x'],
                'y'            => (int) $row['coord_y'],
                'monster_code' => (int) $row['monster_code'],
                'hp_current'   => (int) $row['hp_current'],
                'level'        => isset($row['effective_monster_level']) ? (int) $row['effective_monster_level'] : null,
                'land_level_at_spawn' => isset($row['regional_level_at_spawn']) ? (int) $row['regional_level_at_spawn'] : null,
            ];
        }

        // Field objects (resource nodes)
        try {
            $rows = $db->query('
                SELECT id, coord_x, coord_y, object_type, level, resource_amount, resource_max, gatherer_march_id
                FROM field_objects
                WHERE world_id = :world AND expires_at > UTC_TIMESTAMP()
                  AND coord_x BETWEEN :x1 AND :x2
                  AND coord_y BETWEEN :y1 AND :y2
            ', [':world' => $worldId, ':x1' => $xMin, ':x2' => $xMax, ':y1' => $yMin, ':y2' => $yMax])->fetchAll();

            $typeNames = [1 => 'farm', 2 => 'lumber', 3 => 'quarry', 4 => 'gold_mine', 5 => 'gem_node'];
            foreach ($rows as $row) {
                if (!self::targetOpen($worldId, $row)) continue;
                $entities[] = [
                    'type'            => 'field_object',
                    'x'               => (int) $row['coord_x'],
                    'y'               => (int) $row['coord_y'],
                    'id'              => (int) $row['id'],
                    'object_type'     => (int) $row['object_type'],
                    'object_name'     => $typeNames[(int) $row['object_type']] ?? 'unknown',
                    'level'           => (int) $row['level'],
                    'resource_amount' => (int) $row['resource_amount'],
                    'resource_max'    => (int) $row['resource_max'],
                    'is_occupied'     => $row['gatherer_march_id'] !== null,
                ];
            }
        } catch (\PDOException $e) {
            if (!self::missingTable($e)) throw $e;
        }

        // Shrines
        $rows = $db->query('
            SELECT coord_x, coord_y, shrine_code, tier, owner_alliance_id
            FROM shrines
            WHERE world_id = :world
              AND coord_x BETWEEN :x1 AND :x2
              AND coord_y BETWEEN :y1 AND :y2
        ', [':world' => $worldId, ':x1' => $xMin, ':x2' => $xMax, ':y1' => $yMin, ':y2' => $yMax])->fetchAll();

        foreach ($rows as $row) {
            if (!self::targetOpen($worldId, $row)) continue;
            $entities[] = [
                'type'             => 'shrine',
                'x'                => (int) $row['coord_x'],
                'y'                => (int) $row['coord_y'],
                'shrine_code'      => $row['shrine_code'],
                'tier'             => $row['tier'],
                'owner_alliance_id'=> $row['owner_alliance_id'],
            ];
        }

        // Uncollected charms (table may not exist on older installs)
        try {
            $rows = $db->query('
                SELECT id, coord_x, coord_y, stat_category, grade, charm_code, expires_at
                FROM map_charms
                WHERE world_id = :world
                  AND collected_by IS NULL
                  AND expires_at > UTC_TIMESTAMP()
                  AND coord_x BETWEEN :x1 AND :x2
                  AND coord_y BETWEEN :y1 AND :y2
            ', [':world' => $worldId, ':x1' => $xMin, ':x2' => $xMax, ':y1' => $yMin, ':y2' => $yMax])->fetchAll();

            foreach ($rows as $row) {
                if (!self::targetOpen($worldId, $row)) continue;
                $entities[] = [
                    'type'          => 'charm',
                    'id'            => (int) $row['id'],
                    'x'             => (int) $row['coord_x'],
                    'y'             => (int) $row['coord_y'],
                    'stat_category' => $row['stat_category'],
                    'grade'         => $row['grade'],
                    'charm_code'    => (int) $row['charm_code'],
                    'expires_at'    => $row['expires_at'],
                ];
            }
        } catch (\PDOException $e) {
            if (!self::missingTable($e)) throw $e;
        }

        // Active rallies — visible to all as a map entity (table may not exist yet)
        try {
            $rows = $db->query('
                SELECT r.id, r.target_x, r.target_y, r.leader_player_id, r.launch_at,
                       p.username AS leader_name,
                       (SELECT COUNT(*) FROM rally_participants rp WHERE rp.rally_id = r.id) AS participant_count
                FROM rallies r
                JOIN players p ON p.id = r.leader_player_id
                WHERE r.world_id = :world AND r.status = "gathering"
                  AND r.launch_at > UTC_TIMESTAMP()
                  AND r.target_x BETWEEN :x1 AND :x2
                  AND r.target_y BETWEEN :y1 AND :y2
            ', [':world' => $worldId, ':x1' => $xMin, ':x2' => $xMax, ':y1' => $yMin, ':y2' => $yMax])->fetchAll();

            foreach ($rows as $row) {
                if (!self::targetOpen($worldId, ['coord_x'=>$row['target_x'], 'coord_y'=>$row['target_y']])) continue;
                $entities[] = [
                    'type'              => 'rally',
                    'x'                 => (int) $row['target_x'],
                    'y'                 => (int) $row['target_y'],
                    'rally_id'          => (int) $row['id'],
                    'leader_player_id'  => (int) $row['leader_player_id'],
                    'leader_name'       => $row['leader_name'],
                    'launch_at'         => $row['launch_at'],
                    'participant_count' => (int) $row['participant_count'],
                ];
            }
        } catch (\PDOException $e) {
            if (!self::missingTable($e)) throw $e;
        }

        Response::ok([
            'entities' => $entities,
            'viewport' => ['x_min' => $xMin, 'y_min' => $yMin, 'x_max' => $xMax, 'y_max' => $yMax],
        ]);
    }

    /**
     * GET /api/map/tile/:x/:y
     *
     * Returns the occupant of a single tile (city / monster / resource / null).
     * Used by the map click handler to show a tooltip with details.
     */
    public static function tile(array $params): void
    {
        $session = Session::current();
        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }

        $db  = Connection::getInstance();
        $world = self::world();
        $worldId = (int) $world['id'];
        $x = self::tileCoordinate($params['x'] ?? null, (int) $world['map_size']);
        $y = self::tileCoordinate($params['y'] ?? null, (int) $world['map_size']);
        $land = LandProgressService::available() ? LandProgressService::at($worldId, $x, $y) : null;
        if ($land !== null && !$land['open']) {
            Response::ok(['x'=>$x, 'y'=>$y, 'occupant'=>null, 'accessible'=>false, 'land'=>$land]);
        }
        $occ = null;

        $city = $db->query('
            SELECT c.name, c.castle_level, c.power,
                   p.id AS player_id, p.username, p.lord_level, p.kill_count,
                   COALESCE(k.display_name,p.username) AS display_name, COALESCE(k.name_frame,\'default\') AS name_frame,
                   COALESCE(k.city_skin,\'default\') AS city_skin,
                   am.alliance_id, a.tag AS alliance_tag, a.name AS alliance_name,
                   CASE WHEN pe.expires_at > UTC_TIMESTAMP() THEN pe.emoji_code ELSE NULL END AS emoji_code
            FROM cities c
            JOIN players p ON c.player_id = p.id
            LEFT JOIN kingdom_profiles k ON k.player_id = p.id
            LEFT JOIN alliance_members am ON am.player_id = p.id
            LEFT JOIN alliances a ON a.id = am.alliance_id
            LEFT JOIN player_emojis pe ON pe.player_id = p.id
            WHERE c.world_id = ? AND c.coord_x = ? AND c.coord_y = ? AND c.is_hidden = 0
        ', [$worldId, $x, $y])->fetch();

        if ($city !== false) {
            $occ = [
                'type'          => 'city',
                'name'          => $city['name'],
                'player'        => $city['username'],
                'display_name'  => $city['display_name'],
                'name_frame'    => $city['name_frame'],
                'city_skin'     => $city['city_skin'],
                'player_id'     => (int) $city['player_id'],
                'level'         => (int) $city['castle_level'],
                'power'         => (int) $city['power'],
                'lord_level'    => \Conquer\Game\Player\LordLevel::snapshot((int)$city['player_id'],$worldId)['level'],
                'kill_count'    => (int) $city['kill_count'],
                'alliance_id'   => $city['alliance_id'] ? (int) $city['alliance_id'] : null,
                'alliance_tag'  => $city['alliance_tag'],
                'alliance_name' => $city['alliance_name'],
                'emoji_code'    => $city['emoji_code'],
            ];
        }

        if ($occ === null) {
            $monster = $db->query(
                'SELECT monster_code, hp_current' . (LandProgressService::available() ? ', effective_monster_level, regional_level_at_spawn' : '') . ' FROM field_monsters WHERE world_id = ? AND coord_x = ? AND coord_y = ?',
                [$worldId, $x, $y]
            )->fetch();

            if ($monster !== false) {
                $code  = (int) $monster['monster_code'];
                $stats = \Conquer\Game\Map\MonsterData::get($code);
                $name = $stats['name'];
                $level = (int)($monster['effective_monster_level'] ?? $stats['level']);
                $hpMax = max((int)$monster['hp_current'], (int)round($stats['stats']['hp'] * $stats['amount']));
                $drops = [];
                foreach ($stats['drops'] ?? [] as $drop) {
                    $item = \Conquer\Game\Inventory\InventoryService::getItemDef((int)$drop['item_code']);
                    $label = $item['name_de'] ?? $item['name'] ?? 'Gegenstand';
                    $drops[] = $drop['count'].'× '.$label.' ('.round($drop['probability']*100,2).'%)';
                }

                $occ = [
                    'type'       => 'monster',
                    'name'       => $name,
                    'level'      => $level,
                    'hp_current' => (int) $monster['hp_current'],
                    'hp_max'     => $hpMax,
                    'attack'     => $stats['stats']['attack']  ?? null,
                    'defense'    => $stats['stats']['defense'] ?? null,
                    'amount'     => $stats['amount']           ?? null,
                    'drops'      => $drops,
                    'land_level_at_spawn' => isset($monster['regional_level_at_spawn']) ? (int) $monster['regional_level_at_spawn'] : null,
                ];
            }
        }

        if ($occ === null) {
            $obj = $db->query(
                'SELECT id, object_code, object_type, level, resource_amount, resource_max, gatherer_march_id
                 FROM field_objects WHERE world_id = ? AND coord_x = ? AND coord_y = ? AND expires_at > UTC_TIMESTAMP()',
                [$worldId, $x, $y]
            )->fetch();

            if ($obj !== false) {
                $type = (int) $obj['object_type'];
                $typeNames = [1=>'farm', 2=>'lumber', 3=>'quarry', 4=>'gold_mine', 5=>'gem_node'];
                $code = (int)$obj['object_code'];$labels = self::fieldObjectLabels();
                $occ = [
                    'type'            => 'resource',
                    'id'              => (int) $obj['id'],
                    'object_code'     => $code,
                    'object_type'     => $type,
                    'object_name'     => $typeNames[$type] ?? 'unknown',
                    'label'           => $labels[$code] ?? ucfirst(str_replace('_',' ',$typeNames[$type] ?? 'resource node')),
                    'level'           => (int) $obj['level'],
                    'resource_amount' => (int) $obj['resource_amount'],
                    'remaining'       => (int) $obj['resource_amount'],
                    'resource_max'    => (int) $obj['resource_max'],
                    'is_occupied'     => $obj['gatherer_march_id'] !== null,
                ];
            }
        }

        if ($occ === null) {
            $shrine = $db->query(
                'SELECT shrine_code, tier, owner_alliance_id FROM shrines WHERE world_id = ? AND coord_x = ? AND coord_y = ?',
                [$worldId, $x, $y]
            )->fetch();

            if ($shrine !== false) {
                $occ = [
                    'type'              => 'shrine',
                    'shrine_code'       => $shrine['shrine_code'],
                    'tier'              => $shrine['tier'],
                    'owner_alliance_id' => $shrine['owner_alliance_id'],
                ];
            }
        }

        if ($occ === null) {
            try {
                $charm = $db->query(
                    'SELECT id, stat_category, grade, charm_code, expires_at
                     FROM map_charms
                     WHERE world_id = ? AND coord_x = ? AND coord_y = ?
                       AND collected_by IS NULL AND expires_at > UTC_TIMESTAMP()',
                    [$worldId, $x, $y],
                )->fetch();

                if ($charm !== false) {
                    $bonusPct = match($charm['grade']) {
                        'epic'      => 6.0,
                        'legendary' => 10.0,
                        default     => 3.0,
                    };
                    $occ = [
                        'type'          => 'charm',
                        'id'            => (int) $charm['id'],
                        'stat_category' => $charm['stat_category'],
                        'grade'         => $charm['grade'],
                        'charm_code'    => (int) $charm['charm_code'],
                        'bonus_pct'     => $bonusPct,
                        'expires_at'    => $charm['expires_at'],
                    ];
                }
            } catch (\PDOException $e) {
                if (!self::missingTable($e)) throw $e;
            }
        }

        Response::ok(['x'=>$x, 'y'=>$y, 'occupant'=>$occ, 'accessible'=>true, 'land'=>$land]);
    }

    /**
     * GET /api/map/field-object/:id
     *
     * Returns detailed information about a single field object.
     */
    public static function fieldObject(array $session, int $id): void
    {
        if (!$session) Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        if ($id <= 0) {
            Response::error(400, 'INVALID_INPUT', 'Ungültige Field Object ID.');
        }

        $db = Connection::getInstance();
        $world = self::world();
        $worldId = (int) $world['id'];

        try {
            $fo = $db->query(
                'SELECT id, coord_x, coord_y, object_type, level, resource_amount, resource_max,
                        gatherer_march_id, expires_at
                 FROM field_objects
                 WHERE id = ? AND world_id = ? AND expires_at > UTC_TIMESTAMP()',
                [$id, $worldId],
            )->fetch();
        } catch (\PDOException) {
            Response::error(500, 'DB_ERROR', 'Datenbankfehler.');
        }

        if ($fo === false) {
            Response::error(404, 'NOT_FOUND', 'Field Object nicht gefunden oder abgelaufen.');
        }
        try { LandAccessPolicy::assertTargetOpen($worldId, (int)$fo['coord_x'], (int)$fo['coord_y']); }
        catch (\DomainException $e) { Response::error(409, 'LAND_LOCKED', $e->getMessage()); }

        $typeNames = [1 => 'farm', 2 => 'lumber', 3 => 'quarry', 4 => 'gold_mine', 5 => 'gem_node'];
        $objectType = (int) $fo['object_type'];

        Response::ok([
            'id'              => (int) $fo['id'],
            'x'               => (int) $fo['coord_x'],
            'y'               => (int) $fo['coord_y'],
            'object_type'     => $objectType,
            'object_name'     => $typeNames[$objectType] ?? 'unknown',
            'resource_type'   => \Conquer\Game\Map\FieldObjectService::RESOURCE_BY_TYPE[$objectType] ?? null,
            'level'           => (int) $fo['level'],
            'resource_amount' => (int) $fo['resource_amount'],
            'resource_max'    => (int) $fo['resource_max'],
            'is_occupied'     => $fo['gatherer_march_id'] !== null,
            'expires_at'      => $fo['expires_at'],
        ]);
    }

    /**
     * Load field_object labels once per request.
     * Returns code → label string (e.g. 20100101 → "Farm Lv 1").
     */
    private static function fieldObjectLabels(): array
    {
        static $cache = null;
        if ($cache !== null) return $cache;

        $spawnCfg = json_decode((string) file_get_contents(ROOT_DIR . '/data/world_spawn.json'), true);
        $labels   = [];
        foreach ($spawnCfg['field_objects'] as $obj) {
            $labels[(int) $obj['code']] = $obj['label'];
        }
        $cache = $labels;
        return $cache;
    }

    /** Resolve the session-bound world and reject forged read scopes. */
    private static function world(): array
    {
        try { $worldId = WorldContext::current($_GET['world_id'] ?? null); }
        catch (\DomainException $e) { Response::error(409, 'WORLD_MISMATCH', $e->getMessage()); }
        $world = Connection::getInstance()->query('SELECT id,map_seed,map_size FROM worlds WHERE id=?', [$worldId])->fetch();
        if ($world === false) Response::error(404, 'WORLD_NOT_FOUND', 'Welt nicht gefunden.');
        return $world;
    }

    /** @return array{int,int,int,int} */
    private static function viewport(int $mapSize): array
    {
        $last = max(0, $mapSize - 1);
        $xMin = max(0, min($last, (int)($_GET['x_min'] ?? 0)));
        $yMin = max(0, min($last, (int)($_GET['y_min'] ?? 0)));
        $xMax = max($xMin, min($last, (int)($_GET['x_max'] ?? min(50, $last))));
        $yMax = max($yMin, min($last, (int)($_GET['y_max'] ?? min(50, $last))));
        if ($xMax - $xMin > 100) $xMax = $xMin + 100;
        if ($yMax - $yMin > 100) $yMax = $yMin + 100;
        return [$xMin, $yMin, $xMax, $yMax];
    }

    private static function tileCoordinate(mixed $value, int $mapSize): int
    {
        if ((!is_int($value) && !is_string($value))
            || filter_var($value, FILTER_VALIDATE_INT, ['options'=>['min_range'=>0, 'max_range'=>$mapSize-1]]) === false) {
            Response::error(422, 'INVALID_COORDINATE', 'Diese Koordinate liegt außerhalb der Welt.');
        }
        return (int)$value;
    }

    private static function targetOpen(int $worldId, array $row): bool
    {
        return LandAccessPolicy::isOpen($worldId, (int)$row['coord_x'], (int)$row['coord_y']);
    }

    private static function missingTable(\PDOException $e): bool
    {
        return (int)($e->errorInfo[1] ?? 0) === 1146;
    }
}
