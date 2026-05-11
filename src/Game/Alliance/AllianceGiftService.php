<?php
declare(strict_types=1);

namespace Conquer\Game\Alliance;

use Conquer\Db\Connection;
use Conquer\Game\Inventory\InventoryService;

/**
 * Alliance Gift system.
 *
 * Gifts are triggered by game events (monster kills, rally wins) with a 20%
 * probability. When triggered, a small reward item is made available to all
 * alliance members for 24 hours. Members must explicitly claim their gift.
 *
 * Tables:
 *   alliance_gifts       — the gift record (trigger, item, expiry)
 *   alliance_gift_claims — who has already claimed a given gift
 */
final class AllianceGiftService
{
    private function __construct() {}

    /**
     * Item code for 1-minute generic speedup (used as gift drop).
     * Closest existing item: 10103001 (5-minute generic speedup).
     */
    private const GIFT_ITEM_CODE = 10103001; // Speed Up 5m

    // -------------------------------------------------------------------------
    // Trigger
    // -------------------------------------------------------------------------

    /**
     * Called after a monster kill — 20% chance to create an alliance gift.
     *
     * Safe to call in a fire-and-forget fashion:
     *   try { AllianceGiftService::triggerMonsterKill($pid, $code); } catch (\Throwable) {}
     */
    public static function triggerMonsterKill(int $playerId, int $monsterCode): void
    {
        // 20% chance
        if (random_int(1, 100) > 20) {
            return;
        }

        $db = Connection::getInstance();

        // Player must be in an alliance
        $membership = $db->query(
            'SELECT alliance_id FROM alliance_members WHERE player_id = ?',
            [$playerId],
        )->fetch();

        if ($membership === false) {
            return;
        }

        $allianceId = (int) $membership['alliance_id'];

        $giftJson = json_encode([
            'item_code' => self::GIFT_ITEM_CODE,
            'quantity'  => 1,
        ]);

        $db->execute(
            'INSERT INTO alliance_gifts
                (alliance_id, trigger_type, gift_json, expires_at, created_by)
             VALUES
                (?, \'monster_kill\', ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 24 HOUR), ?)',
            [$allianceId, $giftJson, $playerId],
        );
    }

    // -------------------------------------------------------------------------
    // Claim
    // -------------------------------------------------------------------------

    /**
     * Claim a specific gift for the player.
     *
     * Validates:
     *   - Gift exists and is not expired
     *   - Player is a member of the alliance that owns the gift
     *   - Player has not already claimed this gift
     *
     * @throws \RuntimeException with a human-readable reason on failure
     */
    public static function claimGift(int $playerId, int $giftId): void
    {
        $db = Connection::getInstance();

        // Load gift — must be alive
        $gift = $db->query(
            'SELECT id, alliance_id, gift_json
             FROM   alliance_gifts
             WHERE  id = ? AND expires_at > UTC_TIMESTAMP()',
            [$giftId],
        )->fetch();

        if ($gift === false) {
            throw new \RuntimeException('gift_not_found_or_expired');
        }

        $allianceId = (int) $gift['alliance_id'];

        // Player must be a member of that alliance
        $membership = $db->query(
            'SELECT 1 FROM alliance_members WHERE player_id = ? AND alliance_id = ?',
            [$playerId, $allianceId],
        )->fetch();

        if ($membership === false) {
            throw new \RuntimeException('not_alliance_member');
        }

        // Not already claimed
        $already = $db->query(
            'SELECT 1 FROM alliance_gift_claims WHERE gift_id = ? AND player_id = ?',
            [$giftId, $playerId],
        )->fetch();

        if ($already !== false) {
            throw new \RuntimeException('already_claimed');
        }

        // Parse gift payload
        $payload = json_decode((string) $gift['gift_json'], true);
        $itemCode = (int) ($payload['item_code'] ?? 0);
        $quantity = (int) ($payload['quantity']  ?? 1);

        if ($itemCode <= 0) {
            throw new \RuntimeException('invalid_gift_payload');
        }

        // Insert claim + add item (non-transactional — claim first to avoid doubles)
        $db->execute(
            'INSERT INTO alliance_gift_claims (gift_id, player_id) VALUES (?, ?)',
            [$giftId, $playerId],
        );

        InventoryService::addItems($playerId, $itemCode, $quantity);
    }

    // -------------------------------------------------------------------------
    // Query
    // -------------------------------------------------------------------------

    /**
     * Returns all unexpired alliance gifts that the player has NOT yet claimed.
     *
     * @return list<array<string, mixed>>
     */
    public static function getAvailable(int $playerId): array
    {
        $db = Connection::getInstance();

        // Resolve player's alliance
        $membership = $db->query(
            'SELECT alliance_id FROM alliance_members WHERE player_id = ?',
            [$playerId],
        )->fetch();

        if ($membership === false) {
            return [];
        }

        $allianceId = (int) $membership['alliance_id'];

        try {
            return $db->query(
                'SELECT ag.id, ag.trigger_type, ag.gift_json, ag.expires_at, ag.created_by
                 FROM   alliance_gifts ag
                 WHERE  ag.alliance_id = ?
                   AND  ag.expires_at  > UTC_TIMESTAMP()
                   AND  ag.id NOT IN (
                            SELECT gift_id
                            FROM   alliance_gift_claims
                            WHERE  player_id = ?
                        )
                 ORDER  BY ag.created_at DESC',
                [$allianceId, $playerId],
            )->fetchAll();
        } catch (\PDOException) {
            return [];
        }
    }
}
