<?php
declare(strict_types=1);

namespace Conquer\Api\Handlers;

use Conquer\Api\Response;
use Conquer\Auth\Session;
use Conquer\Db\Connection;
use Conquer\Game\City\CityState;
use Conquer\Game\City\TroopData;
use Conquer\Game\City\TroopTrainer;
use Conquer\Game\City\ResourceTick;

/**
 * Handles /api/troops/* endpoints.
 *
 * GET  /api/troops/list              — current troops + training queue + troop definitions
 * POST /api/troops/train             — enqueue a training batch
 * POST /api/troops/cancel-train/:id  — cancel a training batch (proportional refund)
 * POST /api/troops/speedup-train/:id — speedup training using an item
 * POST /api/troops/promote           — promote T1→T2 (or higher) troops
 * POST /api/troops/heal              — speedup hospital healing using an item
 */
final class TroopHandler
{
    private function __construct() {}

    /**
     * GET /api/troops/list
     *
     * Returns:
     *   troops      — {troop_code: count} for all troops the city has
     *   queue       — active training queue entries
     *   definitions — all troop definitions (for the UI)
     */
    public static function list(array $params): void
    {
        $session = Session::current();
        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }

        $state = CityState::loadForPlayer((int) $session['player_id']);
        if ($state === null) {
            Response::error(404, 'NO_CITY', 'No city found.');
        }

        Response::ok([
            'troops'      => $state['troops'],
            'troop_queue' => $state['troop_queue'],
            'definitions' => array_values(TroopData::all()),
        ]);
    }

    /**
     * POST /api/troops/train
     *
     * Body (JSON): { troop_code: int, count: int, barrack_slot?: int }
     * Header: X-CSRF-Token
     */
    public static function train(array $params): void
    {
        $session = Session::current();
        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }

        // CSRF check
        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($csrf === '' || $csrf !== ($session['csrf_token'] ?? '')) {
            Response::error(403, 'CSRF_INVALID', 'Invalid CSRF token.');
        }

        $body = (string) file_get_contents('php://input');
        $data = json_decode($body, true);

        $troopCode   = (int) ($data['troop_code']   ?? 0);
        $count       = (int) ($data['count']        ?? 0);
        $barrackSlot = (int) ($data['barrack_slot'] ?? 1);

        if ($troopCode === 0 || $count <= 0) {
            Response::error(400, 'INVALID_INPUT', 'troop_code and count are required.');
        }

        $state = CityState::loadForPlayer((int) $session['player_id']);
        if ($state === null) {
            Response::error(404, 'NO_CITY', 'No city found.');
        }

        // Apply current resource tick so the check uses up-to-date resources.
        $city      = ResourceTick::apply($state['city'], $state['buildings']);
        $buildings = $state['buildings'];

        try {
            TroopTrainer::train($city, $buildings, $troopCode, $count, $barrackSlot);
        } catch (\RuntimeException $e) {
            Response::error(400, 'TRAIN_FAILED', $e->getMessage());
        }

        $troop = TroopData::get($troopCode);
        Response::ok([
            'message' => 'Training started: ' . $count . '× ' . ($troop['name'] ?? 'troops'),
        ]);
    }

    /**
     * POST /api/troops/cancel-train/:queue_id
     *
     * Cancels a training batch. Refunds resources proportionally
     * based on how many troops have NOT yet been trained.
     *
     * Proportional refund: (remaining_count / total_count) × full_cost
     * Remaining count is estimated from time elapsed vs total training time.
     */
    public static function cancelTrain(array $params): void
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

        // Load the queue entry — verify ownership via city.
        $entry = $db->query(
            'SELECT tq.id, tq.city_id, tq.troop_code, tq.count,
                    tq.started_at, tq.finishes_at, tq.is_processed,
                    TIMESTAMPDIFF(SECOND, tq.started_at, UTC_TIMESTAMP())  AS secs_elapsed,
                    TIMESTAMPDIFF(SECOND, tq.started_at, tq.finishes_at)   AS total_secs
             FROM   troop_queue tq
             JOIN   cities c ON c.id = tq.city_id
             WHERE  tq.id = ? AND c.player_id = ? AND tq.is_processed = 0',
            [$queueId, $playerId],
        )->fetch();

        if ($entry === false) {
            Response::error(404, 'NOT_FOUND', 'Troop queue entry not found or already completed.');
        }

        $troopCode  = (int) $entry['troop_code'];
        $totalCount = (int) $entry['count'];
        $totalSecs  = max(1, (int) $entry['total_secs']);
        $elapsed    = min($totalSecs, max(0, (int) $entry['secs_elapsed']));
        $cityId     = (int) $entry['city_id'];

        // Estimate remaining troops.
        $trainedFraction  = $elapsed / $totalSecs;
        $remainingCount   = max(0, (int) ceil($totalCount * (1.0 - $trainedFraction)));

        // Compute refund from troop definition costs.
        $troopDef   = TroopData::get($troopCode);
        $refundFood   = (int) round((float) ($troopDef['need_food']   ?? 0) * $remainingCount);
        $refundLumber = (int) round((float) ($troopDef['need_lumber'] ?? 0) * $remainingCount);
        $refundStone  = (int) round((float) ($troopDef['need_stone']  ?? 0) * $remainingCount);
        $refundGold   = (int) round((float) ($troopDef['need_gold']   ?? 0) * $remainingCount);

        $db->transaction(function () use (
            $db, $queueId, $cityId,
            $refundFood, $refundLumber, $refundStone, $refundGold
        ): void {
            // Refund proportional resources.
            $db->execute(
                'UPDATE cities
                 SET food   = food   + :food,
                     lumber = lumber + :lumber,
                     stone  = stone  + :stone,
                     gold   = gold   + :gold
                 WHERE id = :city_id',
                [
                    ':food'    => $refundFood,
                    ':lumber'  => $refundLumber,
                    ':stone'   => $refundStone,
                    ':gold'    => $refundGold,
                    ':city_id' => $cityId,
                ],
            );

            $db->execute('DELETE FROM troop_queue WHERE id = ?', [$queueId]);
        });

        Response::ok([
            'cancelled'       => true,
            'remaining_count' => $remainingCount,
            'refunded_food'   => $refundFood,
            'refunded_lumber' => $refundLumber,
            'refunded_stone'  => $refundStone,
            'refunded_gold'   => $refundGold,
        ]);
    }

    /**
     * POST /api/troops/speedup-train/:queue_id
     *
     * Uses a speedup item (generic or training subcategory) to reduce a troop
     * training timer. If finishes_at moves into the past, the troops are added
     * to city_troops immediately.
     *
     * Body: {"item_code": 10103031}
     */
    public static function speedupTrain(array $params): void
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
        if ($cat !== 'speedup' || !in_array($subcat, ['generic', 'training'], true)) {
            Response::error(400, 'WRONG_ITEM_TYPE', 'This item cannot be used for training speedups.');
        }

        $durationSeconds = (int) ($itemDef['duration_seconds'] ?? 0);
        if ($durationSeconds <= 0) {
            Response::error(400, 'ITEM_NO_DURATION', 'Item has no valid duration.');
        }

        $db = Connection::getInstance();

        $entry = $db->query(
            'SELECT tq.id, tq.city_id, tq.troop_code, tq.count, tq.is_processed,
                    TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), tq.finishes_at) AS secs_remaining
             FROM   troop_queue tq
             JOIN   cities c ON c.id = tq.city_id
             WHERE  tq.id = ? AND c.player_id = ? AND tq.is_processed = 0',
            [$queueId, $playerId],
        )->fetch();

        if ($entry === false) {
            Response::error(404, 'NOT_FOUND', 'Troop queue entry not found or already completed.');
        }

        $invRow = $db->query(
            'SELECT id, quantity FROM player_inventory WHERE player_id = ? AND item_code = ? LIMIT 1',
            [$playerId, $itemCode],
        )->fetch();

        if ($invRow === false || (int) $invRow['quantity'] < 1) {
            Response::error(400, 'NOT_ENOUGH_ITEMS', 'You do not have this speedup item.');
        }

        $cityId    = (int) $entry['city_id'];
        $troopCode = (int) $entry['troop_code'];
        $count     = (int) $entry['count'];
        $secsLeft  = max(0, (int) $entry['secs_remaining']);
        $newSecs   = max(0, $secsLeft - $durationSeconds);
        $isInstant = ($newSecs === 0);
        $entryId   = (int) $entry['id'];

        $db->transaction(function () use (
            $db, $entryId, $cityId, $troopCode, $count,
            $itemCode, $invRow, $durationSeconds, $isInstant
        ): void {
            // Deduct item.
            if ((int) $invRow['quantity'] === 1) {
                $db->execute('DELETE FROM player_inventory WHERE id = ?', [(int) $invRow['id']]);
            } else {
                $db->execute(
                    'UPDATE player_inventory SET quantity = quantity - 1 WHERE id = ?',
                    [(int) $invRow['id']],
                );
            }

            if ($isInstant) {
                // Add troops immediately.
                $db->execute(
                    'INSERT INTO city_troops (city_id, troop_code, count)
                     VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE count = count + ?',
                    [$cityId, $troopCode, $count, $count],
                );
                $db->execute(
                    'UPDATE troop_queue SET is_processed = 1, finishes_at = UTC_TIMESTAMP() WHERE id = ?',
                    [$entryId],
                );
            } else {
                $db->execute(
                    'UPDATE troop_queue
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

    /**
     * POST /api/troops/promote
     *
     * Promotes troops from one tier to the next (T1→T2, T2→T3, etc.).
     * Troop code mapping: tier is encoded in position 4 of the 9-digit code:
     *   50100101 → Infantry T1, 50100201 → Infantry T2, etc.
     * Promote: source_code + 100 (next tier), e.g. 50100101 → 50100201.
     *
     * Body: {"troop_code": 50100101, "count": 100}
     *
     * Cost:   70% of the promoted troop's training cost.
     * Time:   50% of the promoted troop's training time, added as a troop_queue entry.
     * Effect: source troops deducted immediately; promoted troops added after queue finishes.
     */
    public static function promote(array $params): void
    {
        $session = Session::current();
        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }

        $supplied = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($supplied === '' || !hash_equals($session['csrf_token'], $supplied)) {
            Response::error(403, 'CSRF_INVALID', 'CSRF token missing or invalid.');
        }

        $body      = json_decode(file_get_contents('php://input') ?: '', true) ?? [];
        $troopCode = (int) ($body['troop_code'] ?? 0);
        $count     = (int) ($body['count']      ?? 0);
        $playerId  = (int) $session['player_id'];

        if ($troopCode === 0 || $count <= 0) {
            Response::error(400, 'INVALID_INPUT', 'troop_code and count are required.');
        }

        // Source troop definition.
        $sourceDef = TroopData::get($troopCode);
        if ($sourceDef === null) {
            Response::error(400, 'UNKNOWN_TROOP', 'Source troop code ' . $troopCode . ' not found.');
        }

        $sourceTier = (int) ($sourceDef['tier'] ?? 0);
        if ($sourceTier >= 5) {
            Response::error(400, 'MAX_TIER', 'Cannot promote Tier 5 troops — already at maximum tier.');
        }

        // Promoted troop code: same type, next tier.
        // Code pattern: 5TYYYY01 where T=type (1/2/3), Y=tier (01/02/03/04/05).
        // E.g. 50100101 → type 1, tier 1 → promoted: 50100201 (tier 2).
        $promotedCode = $troopCode + 100;
        $promotedDef  = TroopData::get($promotedCode);
        if ($promotedDef === null) {
            Response::error(400, 'UNKNOWN_PROMOTED_TROOP', 'Promoted troop code ' . $promotedCode . ' not found.');
        }

        $db = Connection::getInstance();

        // Get city.
        $cityRow = $db->query(
            'SELECT id, food, lumber, stone, gold FROM cities WHERE player_id = ? LIMIT 1',
            [$playerId],
        )->fetch();

        if ($cityRow === false) {
            Response::error(404, 'NO_CITY', 'No city found.');
        }

        $cityId = (int) $cityRow['id'];

        // Verify enough source troops.
        $troopRow = $db->query(
            'SELECT count FROM city_troops WHERE city_id = ? AND troop_code = ?',
            [$cityId, $troopCode],
        )->fetch();

        $availableTroops = $troopRow !== false ? (int) $troopRow['count'] : 0;
        if ($availableTroops < $count) {
            Response::error(400, 'NOT_ENOUGH_TROOPS',
                'Not enough troops. Have ' . $availableTroops . ', need ' . $count . '.');
        }

        // Promotion cost: 70% of promoted troop training cost.
        $costFood   = (int) round($count * (float) ($promotedDef['need_food']   ?? 0) * 0.7);
        $costLumber = (int) round($count * (float) ($promotedDef['need_lumber'] ?? 0) * 0.7);
        $costStone  = (int) round($count * (float) ($promotedDef['need_stone']  ?? 0) * 0.7);
        $costGold   = (int) round($count * (float) ($promotedDef['need_gold']   ?? 0) * 0.7);

        // Check resources.
        if ((int) $cityRow['food']   < $costFood   ||
            (int) $cityRow['lumber'] < $costLumber  ||
            (int) $cityRow['stone']  < $costStone   ||
            (int) $cityRow['gold']   < $costGold) {
            Response::error(400, 'NOT_ENOUGH_RESOURCES', 'Not enough resources for promotion.');
        }

        // Promotion time: 50% of promoted troop training time × count.
        $timePerTroop    = (int) ($promotedDef['time'] ?? 60);
        $totalDurationSec = (int) max(1, (int) round($count * $timePerTroop * 0.5));

        $db->transaction(function () use (
            $db, $cityId, $playerId, $troopCode, $count, $promotedCode,
            $costFood, $costLumber, $costStone, $costGold, $totalDurationSec
        ): void {
            // Deduct resources.
            $db->execute(
                'UPDATE cities
                 SET food   = food   - :food,
                     lumber = lumber - :lumber,
                     stone  = stone  - :stone,
                     gold   = gold   - :gold
                 WHERE id = :city_id',
                [
                    ':food'    => $costFood,
                    ':lumber'  => $costLumber,
                    ':stone'   => $costStone,
                    ':gold'    => $costGold,
                    ':city_id' => $cityId,
                ],
            );

            // Deduct source troops.
            $db->execute(
                'UPDATE city_troops SET count = count - ? WHERE city_id = ? AND troop_code = ?',
                [$count, $cityId, $troopCode],
            );
            // Clean up zero-count rows.
            $db->execute(
                'DELETE FROM city_troops WHERE city_id = ? AND troop_code = ? AND count <= 0',
                [$cityId, $troopCode],
            );

            // Enqueue promoted troops (they appear after timer finishes).
            $db->execute(
                'INSERT INTO troop_queue
                    (city_id, troop_code, count, barrack_slot, started_at, finishes_at)
                 VALUES
                    (:city_id, :troop_code, :count, 1,
                     UTC_TIMESTAMP(),
                     DATE_ADD(UTC_TIMESTAMP(), INTERVAL :dur SECOND))',
                [
                    ':city_id'    => $cityId,
                    ':troop_code' => $promotedCode,
                    ':count'      => $count,
                    ':dur'        => $totalDurationSec,
                ],
            );
        });

        Response::ok([
            'promoted'         => true,
            'source_code'      => $troopCode,
            'promoted_code'    => $promotedCode,
            'count'            => $count,
            'duration_seconds' => $totalDurationSec,
            'cost_food'        => $costFood,
            'cost_lumber'      => $costLumber,
            'cost_stone'       => $costStone,
            'cost_gold'        => $costGold,
        ]);
    }

    /**
     * POST /api/troops/heal
     *
     * Uses a healing speedup item to reduce the hospital healing timer.
     * Reduces finishes_at of the earliest unfinished hospital_wounded entry
     * for the given troop count.
     *
     * Body: {"item_code": 10103041, "count": 100}
     */
    public static function heal(array $params): void
    {
        $session = Session::current();
        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }

        $supplied = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($supplied === '' || !hash_equals($session['csrf_token'], $supplied)) {
            Response::error(403, 'CSRF_INVALID', 'CSRF token missing or invalid.');
        }

        $body     = json_decode(file_get_contents('php://input') ?: '', true) ?? [];
        $itemCode = (int) ($body['item_code'] ?? 0);
        $playerId = (int) $session['player_id'];

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
        if ($cat !== 'speedup' || !in_array($subcat, ['generic', 'healing'], true)) {
            Response::error(400, 'WRONG_ITEM_TYPE', 'This item cannot be used for healing speedups.');
        }

        $durationSeconds = (int) ($itemDef['duration_seconds'] ?? 0);
        if ($durationSeconds <= 0) {
            Response::error(400, 'ITEM_NO_DURATION', 'Item has no valid duration.');
        }

        $db = Connection::getInstance();

        // Get city.
        $cityRow = $db->query(
            'SELECT id FROM cities WHERE player_id = ? LIMIT 1',
            [$playerId],
        )->fetch();

        if ($cityRow === false) {
            Response::error(404, 'NO_CITY', 'No city found.');
        }

        $cityId = (int) $cityRow['id'];

        // Find active hospital healing entries (healing not yet complete).
        $woundedRows = $db->query(
            'SELECT id, healing_ends_at
             FROM   hospital_wounded
             WHERE  city_id = ? AND healing_ends_at > UTC_TIMESTAMP()
             ORDER  BY healing_ends_at ASC
             LIMIT  10',
            [$cityId],
        )->fetchAll();

        if (empty($woundedRows)) {
            Response::error(400, 'NO_WOUNDED', 'No wounded troops currently being healed.');
        }

        // Check item inventory.
        $invRow = $db->query(
            'SELECT id, quantity FROM player_inventory WHERE player_id = ? AND item_code = ? LIMIT 1',
            [$playerId, $itemCode],
        )->fetch();

        if ($invRow === false || (int) $invRow['quantity'] < 1) {
            Response::error(400, 'NOT_ENOUGH_ITEMS', 'You do not have this healing speedup item.');
        }

        $db->transaction(function () use ($db, $invRow, $woundedRows, $durationSeconds, $cityId): void {
            // Deduct item.
            if ((int) $invRow['quantity'] === 1) {
                $db->execute('DELETE FROM player_inventory WHERE id = ?', [(int) $invRow['id']]);
            } else {
                $db->execute(
                    'UPDATE player_inventory SET quantity = quantity - 1 WHERE id = ?',
                    [(int) $invRow['id']],
                );
            }

            // Apply speedup to the earliest healing entry.
            foreach ($woundedRows as $row) {
                $entryId = (int) $row['id'];
                $db->execute(
                    'UPDATE hospital_wounded
                     SET healing_ends_at = GREATEST(UTC_TIMESTAMP(), DATE_SUB(healing_ends_at, INTERVAL ? SECOND))
                     WHERE id = ?',
                    [$durationSeconds, $entryId],
                );
                break; // apply to the earliest entry only
            }
        });

        Response::ok([
            'heal_speedup_applied' => true,
            'item_code'            => $itemCode,
            'duration_seconds'     => $durationSeconds,
        ]);
    }
}
