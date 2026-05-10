<?php
declare(strict_types=1);

namespace Conquer\Api\Handlers;

use Conquer\Api\Response;
use Conquer\Auth\Session;
use Conquer\Db\Connection;
use Conquer\Game\City\BuildingData;
use Conquer\Game\City\BuildingUpgrader;
use Conquer\Game\City\CityState;

/**
 * Handles /api/city/* endpoints.
 */
final class CityHandler
{
    private function __construct() {}

    /**
     * GET /api/city/state
     *
     * Returns a full city snapshot: resources, buildings, and build queue.
     * The frontend polls this endpoint to keep the city view up to date.
     */
    public static function state(array $params): void
    {
        $session = Session::current();
        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }

        $state = CityState::loadForPlayer((int) $session['player_id']);

        if ($state === null) {
            Response::error(404, 'NO_CITY', 'Player has no city yet.');
        }

        Response::ok($state);
    }

    /**
     * POST /api/city/upgrade-building
     *
     * Body: {"building_code": "farm"}
     *
     * Validates resources and requirements, deducts resources, enqueues the upgrade.
     */
    public static function upgradeBuilding(array $params): void
    {
        $session = Session::current();
        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }

        // Verify CSRF.
        $supplied = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($supplied === '' || !hash_equals($session['csrf_token'], $supplied)) {
            Response::error(403, 'CSRF_INVALID', 'CSRF token missing or invalid.');
        }

        // Parse JSON body.
        $body = json_decode(file_get_contents('php://input') ?: '', true) ?? [];
        $code = trim((string) ($body['building_code'] ?? ''));

        if ($code === '') {
            Response::error(400, 'MISSING_FIELD', 'building_code is required.');
        }

        $state = CityState::loadForPlayer((int) $session['player_id']);
        if ($state === null) {
            Response::error(404, 'NO_CITY', 'Player has no city.');
        }

        $city      = $state['city'];
        $buildings = $state['buildings'];
        $cityId    = (int) $city['id'];

        $vipBonuses = $state['vip']['bonuses'] ?? [];

        try {
            $entry = BuildingUpgrader::start(
                $cityId,
                $code,
                $city,
                $buildings,
                (int) $session['vip_level'],
                $vipBonuses,
            );
        } catch (\RuntimeException $e) {
            Response::error(422, 'UPGRADE_FAILED', $e->getMessage());
        }

        Response::ok(['queue_entry' => $entry]);
    }

    /**
     * POST /api/city/wall-repair
     *
     * Repairs wall HP at a cost of 100 stone + 50 wood per 1 000 HP repaired.
     * The requested HP amount is rounded to the nearest 1 000.
     * Repair is capped at wall_hp_max.
     *
     * Body: { "hp_amount": int }  — HP to repair (will be rounded to nearest 1 000)
     */
    public static function repairWall(array $session): void
    {
        $supplied = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($supplied === '' || !hash_equals($session['csrf_token'], $supplied)) {
            Response::error(403, 'CSRF_INVALID', 'CSRF token missing or invalid.');
        }

        $body     = json_decode(file_get_contents('php://input') ?: '', true) ?? [];
        $hpRaw    = (int) ($body['hp_amount'] ?? 0);

        if ($hpRaw <= 0) {
            Response::error(400, 'MISSING_FIELD', 'hp_amount muss größer als 0 sein.');
        }

        // Round to nearest 1 000
        $hpAmount = (int) round($hpRaw / 1000) * 1000;
        if ($hpAmount <= 0) {
            $hpAmount = 1000; // minimum 1 chunk
        }

        $db       = Connection::getInstance();
        $playerId = (int) $session['player_id'];

        // Load city — verify ownership
        $city = $db->query(
            'SELECT id, stone, wood, wall_hp_current, wall_hp_max
             FROM   cities
             WHERE  player_id = ?
             LIMIT 1',
            [$playerId],
        )->fetch();

        if ($city === false) {
            Response::error(404, 'NO_CITY', 'Keine Stadt gefunden.');
        }

        $cityId        = (int) $city['id'];
        $hpCurrent     = (int) $city['wall_hp_current'];
        $hpMax         = (int) $city['wall_hp_max'];
        $stoneAvail    = (int) $city['stone'];
        $woodAvail     = (int) $city['wood'];

        $missing = $hpMax - $hpCurrent;
        if ($missing <= 0) {
            Response::error(400, 'WALL_FULL', 'Die Mauer ist bereits vollständig repariert.');
        }

        // Cap repair to what is actually missing
        $hpToRepair = min($hpAmount, $missing);

        // Round hpToRepair up to nearest 1 000 for cost calculation, but never exceed missing
        $chunks     = (int) ceil($hpToRepair / 1000);
        $stoneCost  = $chunks * 100;
        $woodCost   = $chunks * 50;

        if ($stoneAvail < $stoneCost) {
            Response::error(400, 'NOT_ENOUGH_STONE',
                'Nicht genug Stein. Benötigt: ' . $stoneCost . ', vorhanden: ' . $stoneAvail . '.');
        }

        if ($woodAvail < $woodCost) {
            Response::error(400, 'NOT_ENOUGH_WOOD',
                'Nicht genug Holz. Benötigt: ' . $woodCost . ', vorhanden: ' . $woodAvail . '.');
        }

        $db->execute(
            'UPDATE cities
             SET    stone            = stone - ?,
                    wood             = wood - ?,
                    wall_hp_current  = LEAST(wall_hp_max, wall_hp_current + ?)
             WHERE  id = ?',
            [$stoneCost, $woodCost, $hpToRepair, $cityId],
        );

        // Re-read final HP for the response
        $newHp = (int) $db->query(
            'SELECT wall_hp_current FROM cities WHERE id = ?',
            [$cityId],
        )->fetchColumn();

        Response::ok([
            'hp_repaired'       => $hpToRepair,
            'stone_spent'       => $stoneCost,
            'wood_spent'        => $woodCost,
            'wall_hp_current'   => $newHp,
            'wall_hp_max'       => $hpMax,
        ]);
    }

    /**
     * POST /api/city/instant-build/:queue_id
     *
     * Instantly completes a building upgrade using GEMS.
     * Cost: 1 GEMS per minute remaining (minimum 1).
     */
    public static function instantBuild(array $params): void
    {
        $session = Session::current();
        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }

        $supplied = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($supplied === '' || !hash_equals($session['csrf_token'], $supplied)) {
            Response::error(403, 'CSRF_INVALID', 'CSRF token missing or invalid.');
        }

        $queueId  = (int) ($params['queue_id'] ?? 0);
        $playerId = (int) $session['player_id'];

        if ($queueId <= 0) {
            Response::error(400, 'INVALID_INPUT', 'Invalid queue_id.');
        }

        $db = Connection::getInstance();

        // Load the queue entry — must belong to this player's city.
        $entry = $db->query(
            'SELECT bq.id, bq.city_id, bq.building_code, bq.level_to,
                    bq.finishes_at, bq.is_processed,
                    TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), bq.finishes_at) AS secs_remaining
             FROM   building_queue bq
             JOIN   cities c ON c.id = bq.city_id
             WHERE  bq.id = ? AND c.player_id = ? AND bq.is_processed = 0',
            [$queueId, $playerId],
        )->fetch();

        if ($entry === false) {
            Response::error(404, 'NOT_FOUND', 'Queue entry not found or already completed.');
        }

        $secsRemaining = max(0, (int) $entry['secs_remaining']);
        $gemCost       = max(1, (int) ceil($secsRemaining / 60));
        $playerGems    = (int) $session['gems'];

        if ($playerGems < $gemCost) {
            Response::error(400, 'NOT_ENOUGH_GEMS',
                'Not enough GEMS. Need ' . $gemCost . ', have ' . $playerGems . '.');
        }

        $cityId      = (int) $entry['city_id'];
        $buildCode   = $entry['building_code'];
        $levelTo     = (int) $entry['level_to'];

        $db->transaction(function () use ($db, $queueId, $cityId, $buildCode, $levelTo, $playerId, $gemCost): void {
            // Deduct GEMS from player.
            $db->execute(
                'UPDATE players SET gems = gems - ? WHERE id = ?',
                [$gemCost, $playerId],
            );

            // Apply the upgrade immediately.
            $db->execute(
                'INSERT INTO city_buildings (city_id, building_code, level)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE level = ?',
                [$cityId, $buildCode, $levelTo, $levelTo],
            );

            // Keep castle_level in sync.
            if ($buildCode === 'castle') {
                $db->execute(
                    'UPDATE cities SET castle_level = ? WHERE id = ?',
                    [$levelTo, $cityId],
                );
            }

            // Mark queue entry processed.
            $db->execute(
                'UPDATE building_queue SET is_processed = 1, finishes_at = UTC_TIMESTAMP() WHERE id = ?',
                [$queueId],
            );
        });

        // Recalculate city power.
        $state = CityState::loadForPlayer($playerId);

        Response::ok([
            'gems_spent'    => $gemCost,
            'building_code' => $buildCode,
            'level_to'      => $levelTo,
        ]);
    }
}
