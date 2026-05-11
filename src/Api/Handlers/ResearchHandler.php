<?php
declare(strict_types=1);

namespace Conquer\Api\Handlers;

use Conquer\Api\Response;
use Conquer\Auth\Session;
use Conquer\Db\Connection;
use Conquer\Game\Research\BuffEngine;
use Conquer\Game\Research\ResearchData;
use Conquer\Game\Research\ResearchProcessor;

/**
 * Handles /api/research/* endpoints.
 *
 * GET  /api/research/state   — full research snapshot (levels, active queue, buffs)
 * POST /api/research/start   — enqueue a new research
 * POST /api/research/instant — finish active research immediately using GEMS
 */
final class ResearchHandler
{
    private function __construct() {}

    // -------------------------------------------------------------------------
    // GET /api/research/state
    // -------------------------------------------------------------------------

    /**
     * Returns the player's current research levels, active queue entry, and
     * computed buff values.
     *
     * Also triggers the lazy queue processor so any finished research is
     * applied before the snapshot is built.
     *
     * Response shape:
     * {
     *   "research": {"infantry_hp": 3, "troops_atk": 1, ...},
     *   "queue":    {"id": 5, "code": "ranged_hp", "level_to": 2, "finishes_at": "..."} | null,
     *   "buffs":    {"infantry_atk": 0.03, "troops_hp": 0.01, ...}
     * }
     */
    public static function state(array $params): void
    {
        $session = Session::current();
        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }

        $playerId = (int) $session['player_id'];
        $worldId  = 1;

        // Lazy tick — apply any completed research before reading state.
        ResearchProcessor::processQueue($playerId, $worldId);

        $db = Connection::getInstance();

        // Load current research levels.
        $researchRows = $db->query(
            'SELECT research_code, level
             FROM   player_research
             WHERE  player_id = ? AND world_id = ?',
            [$playerId, $worldId],
        )->fetchAll();

        $research = [];
        foreach ($researchRows as $row) {
            $research[(string) $row['research_code']] = (int) $row['level'];
        }

        // Load active (unprocessed) queue entry.
        $queueRow = $db->query(
            'SELECT id, research_code, level_to, started_at, finishes_at
             FROM   research_queue
             WHERE  player_id = ? AND world_id = ? AND is_processed = 0
             ORDER BY id ASC
             LIMIT 1',
            [$playerId, $worldId],
        )->fetch();

        $queue = null;
        if ($queueRow !== false) {
            $queue = [
                'id'          => (int) $queueRow['id'],
                'code'        => (string) $queueRow['research_code'],
                'level_to'    => (int) $queueRow['level_to'],
                'started_at'  => $queueRow['started_at'],
                'finishes_at' => $queueRow['finishes_at'],
            ];
        }

        // Academy level.
        $academyRow = $db->query(
            "SELECT cb.level FROM city_buildings cb
             JOIN cities c ON c.id = cb.city_id
             WHERE c.player_id = ? AND cb.building_code = 'academy' LIMIT 1",
            [$playerId],
        )->fetch();
        $academyLevel = $academyRow ? (int) $academyRow['level'] : 0;

        // Compute effective buff totals.
        $buffs = BuffEngine::getBuffs($playerId, $worldId);

        Response::ok([
            'research'      => $research,
            'queue'         => $queue,
            'buffs'         => $buffs,
            'academy_level' => $academyLevel,
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /api/research/start
    // -------------------------------------------------------------------------

    /**
     * Starts a research job for the authenticated player.
     *
     * Expected JSON body: {"code": "infantry_hp", "level_to": 2}
     *
     * Validates:
     *  - code exists in research data
     *  - level_to = current_level + 1 (sequential upgrades only)
     *  - level_to <= max_level
     *  - Academy level satisfies the requirements of this level entry
     *  - Prerequisite research codes are at level >= 1
     *  - No active research queue entry
     *  - Player has enough resources
     *
     * On success: deducts resources, inserts research_queue row.
     * The research_speed buff reduces the duration before insertion.
     */
    public static function start(array $params): void
    {
        $session = Session::current();
        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }

        // CSRF check.
        $supplied = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($supplied === '' || !hash_equals($session['csrf_token'], $supplied)) {
            Response::error(403, 'CSRF_INVALID', 'CSRF token missing or invalid.');
        }

        $body    = json_decode(file_get_contents('php://input') ?: '', true) ?? [];
        $code    = trim((string) ($body['code']     ?? ''));
        $levelTo = (int) ($body['level_to'] ?? 0);

        if ($code === '') {
            Response::error(400, 'MISSING_FIELD', 'code is required.');
        }
        if ($levelTo < 1) {
            Response::error(400, 'INVALID_FIELD', 'level_to must be >= 1.');
        }

        $node = ResearchData::get($code);
        if ($node === null) {
            Response::error(400, 'UNKNOWN_RESEARCH', 'Research code "' . $code . '" does not exist.');
        }

        if ($levelTo > (int) $node['max_level']) {
            Response::error(400, 'MAX_LEVEL_REACHED',
                'Maximum level for "' . $node['name'] . '" is ' . $node['max_level'] . '.');
        }

        $playerId = (int) $session['player_id'];
        $worldId  = 1;
        $db       = Connection::getInstance();

        // Apply any finished research before validating current levels.
        ResearchProcessor::processQueue($playerId, $worldId);

        // Load current level for this research.
        $currentRow = $db->query(
            'SELECT level FROM player_research
             WHERE  player_id = ? AND world_id = ? AND research_code = ?',
            [$playerId, $worldId, $code],
        )->fetch();

        $currentLevel = $currentRow !== false ? (int) $currentRow['level'] : 0;

        // Must upgrade one level at a time.
        if ($levelTo !== $currentLevel + 1) {
            Response::error(400, 'WRONG_LEVEL_SEQUENCE',
                'Expected level_to ' . ($currentLevel + 1) . ', got ' . $levelTo . '.');
        }

        // Locate the level entry.
        $levelEntry = null;
        foreach ($node['levels'] as $entry) {
            if ((int) $entry['level'] === $levelTo) {
                $levelEntry = $entry;
                break;
            }
        }

        if ($levelEntry === null) {
            Response::error(500, 'DATA_ERROR',
                'Level entry missing in research data for "' . $code . '" level ' . $levelTo . '.');
        }

        // Academy level check — read from city_buildings.
        $cityRow = $db->query(
            'SELECT id FROM cities WHERE player_id = ? LIMIT 1',
            [$playerId],
        )->fetch();

        if ($cityRow === false) {
            Response::error(404, 'NO_CITY', 'Player has no city.');
        }

        $cityId = (int) $cityRow['id'];

        $academyRow = $db->query(
            'SELECT level FROM city_buildings WHERE city_id = ? AND building_code = ?',
            [$cityId, 'academy'],
        )->fetch();

        $academyLevel = $academyRow !== false ? (int) $academyRow['level'] : 0;

        // Check academy requirement from this level's requirements array.
        $requiredAcademy = ResearchData::academyLevelForUpgrade($node, $levelTo);
        if ($academyLevel < $requiredAcademy) {
            Response::error(400, 'ACADEMY_TOO_LOW',
                '"' . $node['name'] . '" level ' . $levelTo
                . ' requires Academy level ' . $requiredAcademy
                . ' (current: ' . $academyLevel . ').');
        }

        // Prerequisite research codes check.
        $prerequisites = ResearchData::prerequisites($node);
        foreach ($prerequisites as $preCode) {
            $preRow = $db->query(
                'SELECT level FROM player_research
                 WHERE  player_id = ? AND world_id = ? AND research_code = ?',
                [$playerId, $worldId, $preCode],
            )->fetch();

            $preLevel = $preRow !== false ? (int) $preRow['level'] : 0;
            if ($preLevel < 1) {
                $preNode = ResearchData::get($preCode);
                $preName = $preNode !== null ? $preNode['name'] : $preCode;
                Response::error(400, 'PREREQUISITE_NOT_MET',
                    '"' . $node['name'] . '" requires "' . $preName . '" to be researched first.');
            }
        }

        // Check that no research is already active.
        $activeQueue = $db->query(
            'SELECT id FROM research_queue
             WHERE  player_id = ? AND world_id = ? AND is_processed = 0 LIMIT 1',
            [$playerId, $worldId],
        )->fetch();

        if ($activeQueue !== false) {
            Response::error(400, 'QUEUE_BUSY', 'A research is already in progress.');
        }

        $cost        = $levelEntry['resources'];
        $durationSec = (int) $levelEntry['time'];

        // Apply research_speed buff to reduce duration.
        $buffs         = BuffEngine::getBuffs($playerId, $worldId);
        $speedReduction = (float) ($buffs['research_speed'] ?? 0.0);
        if ($speedReduction > 0.0) {
            $durationSec = (int) max(1, (int) round($durationSec * (1.0 - $speedReduction)));
        }

        // Load city resources.
        $city = $db->query(
            'SELECT food, lumber, stone, gold FROM cities WHERE id = ?',
            [$cityId],
        )->fetch();

        if ($city === false) {
            Response::error(500, 'DB_ERROR', 'Failed to load city resources.');
        }

        $needFood   = (int) ($cost['food']   ?? 0);
        $needLumber = (int) ($cost['lumber'] ?? 0);
        $needStone  = (int) ($cost['stone']  ?? 0);
        $needGold   = (int) ($cost['gold']   ?? 0);

        if ((int) $city['food']   < $needFood   ||
            (int) $city['lumber'] < $needLumber  ||
            (int) $city['stone']  < $needStone   ||
            (int) $city['gold']   < $needGold) {
            Response::error(400, 'NOT_ENOUGH_RESOURCES',
                'Not enough resources to start this research.');
        }

        // Deduct resources and enqueue in a single transaction.
        $db->transaction(
            function () use ($db, $cityId, $playerId, $worldId, $code, $levelTo, $durationSec,
                             $needFood, $needLumber, $needStone, $needGold): void {
                $db->execute(
                    'UPDATE cities SET
                        food   = food   - :food,
                        lumber = lumber - :lumber,
                        stone  = stone  - :stone,
                        gold   = gold   - :gold
                     WHERE id = :id',
                    [
                        ':food'   => $needFood,
                        ':lumber' => $needLumber,
                        ':stone'  => $needStone,
                        ':gold'   => $needGold,
                        ':id'     => $cityId,
                    ],
                );

                $db->execute(
                    'INSERT INTO research_queue
                        (player_id, world_id, research_code, level_to, started_at, finishes_at)
                     VALUES
                        (:player_id, :world_id, :code, :level_to,
                         UTC_TIMESTAMP(),
                         DATE_ADD(UTC_TIMESTAMP(), INTERVAL :dur SECOND))',
                    [
                        ':player_id' => $playerId,
                        ':world_id'  => $worldId,
                        ':code'      => $code,
                        ':level_to'  => $levelTo,
                        ':dur'       => $durationSec,
                    ],
                );
            },
        );

        // Return the freshly-inserted queue entry.
        $queueEntry = $db->query(
            'SELECT id, research_code, level_to, started_at, finishes_at
             FROM   research_queue
             WHERE  player_id = ? AND world_id = ? AND is_processed = 0
             ORDER  BY id DESC LIMIT 1',
            [$playerId, $worldId],
        )->fetch();

        Response::ok([
            'queue_entry' => $queueEntry !== false ? [
                'id'          => (int) $queueEntry['id'],
                'code'        => (string) $queueEntry['research_code'],
                'level_to'    => (int) $queueEntry['level_to'],
                'started_at'  => $queueEntry['started_at'],
                'finishes_at' => $queueEntry['finishes_at'],
            ] : null,
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /api/research/cancel/:queue_id
    // -------------------------------------------------------------------------

    /**
     * Cancels the active research queue entry.
     * Per spec: no resource refund for research cancellation.
     */
    public static function cancel(array $params): void
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
        $worldId  = 1;

        if ($queueId <= 0) {
            Response::error(400, 'INVALID_INPUT', 'Invalid queue_id.');
        }

        $db = Connection::getInstance();

        $entry = $db->query(
            'SELECT id FROM research_queue
             WHERE id = ? AND player_id = ? AND world_id = ? AND is_processed = 0',
            [$queueId, $playerId, $worldId],
        )->fetch();

        if ($entry === false) {
            Response::error(404, 'NOT_FOUND', 'Research queue entry not found or already completed.');
        }

        // Delete (no refund — per spec).
        $db->execute('DELETE FROM research_queue WHERE id = ?', [$queueId]);

        Response::ok(['cancelled' => true]);
    }

    // -------------------------------------------------------------------------
    // POST /api/research/speedup/:queue_id
    // -------------------------------------------------------------------------

    /**
     * Applies a speedup item to the active research queue entry.
     * Accepts generic and research-specific speedup items.
     * If finishes_at moves into the past, research is completed immediately.
     *
     * Body: {"item_code": 10103021}
     */
    public static function speedup(array $params): void
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
        $worldId  = 1;

        if ($queueId <= 0) {
            Response::error(400, 'INVALID_INPUT', 'Invalid queue_id.');
        }

        $body     = json_decode(file_get_contents('php://input') ?: '', true) ?? [];
        $itemCode = (int) ($body['item_code'] ?? 0);

        if ($itemCode <= 0) {
            Response::error(400, 'MISSING_FIELD', 'item_code is required.');
        }

        // Load item definition.
        $itemsJson = file_get_contents(ROOT_DIR . '/data/items.json') ?: '{}';
        $itemsData = json_decode($itemsJson, true) ?? [];
        $itemDef   = null;
        foreach ($itemsData['items'] ?? [] as $item) {
            if ((int) $item['code'] === $itemCode) {
                $itemDef = $item;
                break;
            }
        }

        if ($itemDef === null) {
            Response::error(400, 'UNKNOWN_ITEM', 'Item code ' . $itemCode . ' not found.');
        }

        $cat    = $itemDef['category']    ?? '';
        $subcat = $itemDef['subcategory'] ?? '';
        if ($cat !== 'speedup' || !in_array($subcat, ['generic', 'research'], true)) {
            Response::error(400, 'WRONG_ITEM_TYPE', 'This item cannot be used for research speedups.');
        }

        $durationSeconds = (int) ($itemDef['duration_seconds'] ?? 0);
        if ($durationSeconds <= 0) {
            Response::error(400, 'ITEM_NO_DURATION', 'Item has no valid duration.');
        }

        $db = Connection::getInstance();

        $entry = $db->query(
            'SELECT id, research_code, level_to,
                    TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), finishes_at) AS secs_remaining
             FROM   research_queue
             WHERE  id = ? AND player_id = ? AND world_id = ? AND is_processed = 0',
            [$queueId, $playerId, $worldId],
        )->fetch();

        if ($entry === false) {
            Response::error(404, 'NOT_FOUND', 'Research queue entry not found or already completed.');
        }

        $invRow = $db->query(
            'SELECT id, quantity FROM player_inventory WHERE player_id = ? AND item_code = ? LIMIT 1',
            [$playerId, $itemCode],
        )->fetch();

        if ($invRow === false || (int) $invRow['quantity'] < 1) {
            Response::error(400, 'NOT_ENOUGH_ITEMS', 'You do not have this speedup item.');
        }

        $secsLeft  = max(0, (int) $entry['secs_remaining']);
        $newSecs   = max(0, $secsLeft - $durationSeconds);
        $isInstant = ($newSecs === 0);
        $resCode   = (string) $entry['research_code'];
        $levelTo   = (int) $entry['level_to'];
        $entryId   = (int) $entry['id'];

        $db->transaction(function () use (
            $db, $entryId, $playerId, $worldId, $resCode, $levelTo,
            $itemCode, $invRow, $durationSeconds, $isInstant
        ): void {
            // Deduct item from inventory.
            if ((int) $invRow['quantity'] === 1) {
                $db->execute('DELETE FROM player_inventory WHERE id = ?', [(int) $invRow['id']]);
            } else {
                $db->execute(
                    'UPDATE player_inventory SET quantity = quantity - 1 WHERE id = ?',
                    [(int) $invRow['id']],
                );
            }

            if ($isInstant) {
                $db->execute(
                    'INSERT INTO player_research (player_id, world_id, research_code, level)
                     VALUES (?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE level = ?',
                    [$playerId, $worldId, $resCode, $levelTo, $levelTo],
                );
                $db->execute(
                    'UPDATE research_queue
                     SET is_processed = 1, finishes_at = UTC_TIMESTAMP()
                     WHERE id = ?',
                    [$entryId],
                );
            } else {
                $db->execute(
                    'UPDATE research_queue
                     SET finishes_at = DATE_SUB(finishes_at, INTERVAL ? SECOND)
                     WHERE id = ?',
                    [$durationSeconds, $entryId],
                );
            }
        });

        Response::ok([
            'speedup_applied'    => true,
            'item_code'          => $itemCode,
            'duration_seconds'   => $durationSeconds,
            'instantly_finished' => $isInstant,
            'secs_remaining'     => $isInstant ? 0 : $newSecs,
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /api/research/instant
    // -------------------------------------------------------------------------

    /**
     * Instantly completes the active research queue entry using GEMS.
     *
     * Cost: 1 GEM per minute remaining (minimum 1).
     */
    public static function instant(array $params): void
    {
        $session = Session::current();
        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }

        $supplied = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($supplied === '' || !hash_equals($session['csrf_token'], $supplied)) {
            Response::error(403, 'CSRF_INVALID', 'CSRF token missing or invalid.');
        }

        $playerId = (int) $session['player_id'];
        $worldId  = 1;
        $db       = Connection::getInstance();

        // Load the active queue entry for this player.
        $entry = $db->query(
            'SELECT id, research_code, level_to, finishes_at,
                    TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), finishes_at) AS secs_remaining
             FROM   research_queue
             WHERE  player_id = ? AND world_id = ? AND is_processed = 0
             ORDER BY id ASC
             LIMIT 1',
            [$playerId, $worldId],
        )->fetch();

        if ($entry === false) {
            Response::error(404, 'NO_ACTIVE_RESEARCH', 'No research is currently in progress.');
        }

        $secsRemaining = max(0, (int) $entry['secs_remaining']);
        $gemCost       = max(1, (int) ceil($secsRemaining / 60));
        $playerGems    = (int) $session['gems'];

        if ($playerGems < $gemCost) {
            Response::error(400, 'NOT_ENOUGH_GEMS',
                'Need ' . $gemCost . ' GEMS, have ' . $playerGems . '.');
        }

        $entryId      = (int) $entry['id'];
        $researchCode = (string) $entry['research_code'];
        $levelTo      = (int) $entry['level_to'];

        $db->transaction(
            function () use ($db, $entryId, $playerId, $worldId, $researchCode, $levelTo, $gemCost): void {
                // Deduct GEMS.
                $db->execute(
                    'UPDATE players SET gems = gems - ? WHERE id = ?',
                    [$gemCost, $playerId],
                );

                // Apply research immediately.
                $db->execute(
                    'INSERT INTO player_research (player_id, world_id, research_code, level)
                     VALUES (?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE level = ?',
                    [$playerId, $worldId, $researchCode, $levelTo, $levelTo],
                );

                // Mark queue entry as processed (backdated to now).
                $db->execute(
                    'UPDATE research_queue
                     SET is_processed = 1, finishes_at = UTC_TIMESTAMP()
                     WHERE id = ?',
                    [$entryId],
                );
            },
        );

        Response::ok([
            'gems_spent'    => $gemCost,
            'research_code' => $researchCode,
            'level_to'      => $levelTo,
        ]);
    }
}
