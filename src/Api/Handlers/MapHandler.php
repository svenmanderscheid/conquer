<?php
declare(strict_types=1);

namespace Conquer\Api\Handlers;

use Conquer\Api\Response;
use Conquer\Auth\Session;
use Conquer\Db\Connection;

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

        $world = $db->query(
            'SELECT id, map_seed, map_size FROM worlds WHERE id = 1'
        )->fetch();

        $city = $db->query(
            'SELECT coord_x, coord_y, name FROM cities WHERE player_id = ? AND world_id = 1',
            [(int) $session['player_id']]
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
     * Returns all dynamic entities (cities, monsters, resource nodes) in the
     * requested tile region. Viewport is clamped to 100×100 tiles to prevent
     * abuse. Terrain is generated client-side from the world seed.
     */
    public static function tiles(array $params): void
    {
        $session = Session::current();
        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }

        $xMin = max(0,   (int) ($_GET['x_min'] ?? 0));
        $yMin = max(0,   (int) ($_GET['y_min'] ?? 0));
        $xMax = min(255, (int) ($_GET['x_max'] ?? 50));
        $yMax = min(255, (int) ($_GET['y_max'] ?? 50));

        if ($xMax - $xMin > 100) $xMax = $xMin + 100;
        if ($yMax - $yMin > 100) $yMax = $yMin + 100;

        $db       = Connection::getInstance();
        $entities = [];

        // Player cities (2×2 footprint — anchor tile represents the city)
        $rows = $db->query('
            SELECT c.coord_x, c.coord_y, c.name, c.castle_level, p.username
            FROM cities c
            JOIN players p ON c.player_id = p.id
            WHERE c.world_id = 1
              AND c.coord_x BETWEEN :x1 AND :x2
              AND c.coord_y BETWEEN :y1 AND :y2
              AND c.is_hidden = 0
        ', [':x1' => $xMin, ':x2' => $xMax, ':y1' => $yMin, ':y2' => $yMax])->fetchAll();

        foreach ($rows as $row) {
            $entities[] = [
                'type'   => 'city',
                'x'      => (int) $row['coord_x'],
                'y'      => (int) $row['coord_y'],
                'name'   => $row['name'],
                'level'  => (int) $row['castle_level'],
                'player' => $row['username'],
            ];
        }

        // Field monsters
        $rows = $db->query('
            SELECT coord_x, coord_y, monster_code, hp_current
            FROM field_monsters
            WHERE world_id = 1
              AND coord_x BETWEEN :x1 AND :x2
              AND coord_y BETWEEN :y1 AND :y2
        ', [':x1' => $xMin, ':x2' => $xMax, ':y1' => $yMin, ':y2' => $yMax])->fetchAll();

        foreach ($rows as $row) {
            $entities[] = [
                'type'         => 'monster',
                'x'            => (int) $row['coord_x'],
                'y'            => (int) $row['coord_y'],
                'monster_code' => (int) $row['monster_code'],
                'hp_current'   => (int) $row['hp_current'],
            ];
        }

        // Field objects (resource nodes)
        $rows = $db->query('
            SELECT coord_x, coord_y, object_code, remaining
            FROM field_objects
            WHERE world_id = 1
              AND coord_x BETWEEN :x1 AND :x2
              AND coord_y BETWEEN :y1 AND :y2
        ', [':x1' => $xMin, ':x2' => $xMax, ':y1' => $yMin, ':y2' => $yMax])->fetchAll();

        foreach ($rows as $row) {
            $entities[] = [
                'type'        => 'resource',
                'x'           => (int) $row['coord_x'],
                'y'           => (int) $row['coord_y'],
                'object_code' => (int) $row['object_code'],
                'remaining'   => (int) $row['remaining'],
            ];
        }

        // Shrines
        $rows = $db->query('
            SELECT coord_x, coord_y, shrine_code, tier, owner_alliance_id
            FROM shrines
            WHERE world_id = 1
              AND coord_x BETWEEN :x1 AND :x2
              AND coord_y BETWEEN :y1 AND :y2
        ', [':x1' => $xMin, ':x2' => $xMax, ':y1' => $yMin, ':y2' => $yMax])->fetchAll();

        foreach ($rows as $row) {
            $entities[] = [
                'type'             => 'shrine',
                'x'                => (int) $row['coord_x'],
                'y'                => (int) $row['coord_y'],
                'shrine_code'      => $row['shrine_code'],
                'tier'             => $row['tier'],
                'owner_alliance_id'=> $row['owner_alliance_id'],
            ];
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

        $x = max(0, min(255, (int) ($params['x'] ?? 0)));
        $y = max(0, min(255, (int) ($params['y'] ?? 0)));

        $db  = Connection::getInstance();
        $occ = null;

        $city = $db->query('
            SELECT c.name, c.castle_level, c.power, p.username
            FROM cities c
            JOIN players p ON c.player_id = p.id
            WHERE c.world_id = 1 AND c.coord_x = ? AND c.coord_y = ? AND c.is_hidden = 0
        ', [$x, $y])->fetch();

        if ($city !== false) {
            $occ = [
                'type'   => 'city',
                'name'   => $city['name'],
                'player' => $city['username'],
                'level'  => (int) $city['castle_level'],
                'power'  => (int) $city['power'],
            ];
        }

        if ($occ === null) {
            $monster = $db->query(
                'SELECT monster_code, hp_current FROM field_monsters WHERE world_id = 1 AND coord_x = ? AND coord_y = ?',
                [$x, $y]
            )->fetch();

            if ($monster !== false) {
                $occ = [
                    'type'         => 'monster',
                    'monster_code' => (int) $monster['monster_code'],
                    'hp_current'   => (int) $monster['hp_current'],
                ];
            }
        }

        if ($occ === null) {
            $obj = $db->query(
                'SELECT object_code, remaining FROM field_objects WHERE world_id = 1 AND coord_x = ? AND coord_y = ?',
                [$x, $y]
            )->fetch();

            if ($obj !== false) {
                $occ = [
                    'type'        => 'resource',
                    'object_code' => (int) $obj['object_code'],
                    'remaining'   => (int) $obj['remaining'],
                ];
            }
        }

        if ($occ === null) {
            $shrine = $db->query(
                'SELECT shrine_code, tier, owner_alliance_id FROM shrines WHERE world_id = 1 AND coord_x = ? AND coord_y = ?',
                [$x, $y]
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

        Response::ok(['x' => $x, 'y' => $y, 'occupant' => $occ]);
    }
}
