<?php
declare(strict_types=1);

namespace Conquer\Game\Research;

use Conquer\Game\World\WorldContext;

use Conquer\Db\Connection;

/**
 * Lazy queue processor for finished research jobs.
 *
 * Called on every research state load so the player never needs a cron job
 * to see completed research — the same pattern used by BuildingUpgrader and
 * TroopTrainer.
 */
final class ResearchProcessor
{
    private function __construct() {}

    /**
     * Applies all completed research queue entries for a player.
     *
     * For each entry whose finishes_at is in the past (and is_processed = 0):
     *  1. Upsert player_research with the new level.
     *  2. Mark the queue entry as processed.
     *
     * Wraps each entry in its own transaction so a single failure does not
     * block other pending entries from being applied.
     */
    public static function processQueue(int $playerId, ?int $worldId = null): void
    {
        $worldId??=WorldContext::id();
        $db = Connection::getInstance();

        // Troop tiers now unlock through buildings. Release an old research slot
        // and return its paid resources in the original world, exactly once.
        try {
            self::settleRetiredTroopUnlocks($playerId, $worldId);
            $finished = $db->query(
                'SELECT id, research_code, level_to
                 FROM   research_queue
                 WHERE  player_id    = ?
                   AND  world_id     = ?
                   AND  is_processed = 0
                   AND  finishes_at <= UTC_TIMESTAMP()
                 ORDER BY finishes_at ASC',
                [$playerId, $worldId],
            )->fetchAll();
        } catch (\PDOException) {
            // Table may not exist yet during early migrations — silently skip.
            return;
        }

        foreach ($finished as $entry) {
            $entryId      = (int) $entry['id'];
            $researchCode = (string) $entry['research_code'];
            $levelTo      = (int) $entry['level_to'];

            try {
                $db->transaction(
                    function () use ($db, $entryId, $playerId, $worldId, $researchCode, $levelTo): void {
                        if($db->execute('UPDATE research_queue SET is_processed=1 WHERE id=? AND player_id=? AND world_id=? AND is_processed=0',[$entryId,$playerId,$worldId])!==1)return;
                        // Upsert the player's research level.
                        $db->execute(
                            'INSERT INTO player_research (player_id, world_id, research_code, level)
                             VALUES (?, ?, ?, ?)
                             ON DUPLICATE KEY UPDATE level = GREATEST(level,?)',
                            [$playerId, $worldId, $researchCode, $levelTo, $levelTo],
                        );

                        // Mark queue entry as processed.
                        $db->execute(
                            'UPDATE research_queue SET is_processed = 1 WHERE id = ?',
                            [$entryId],
                        );
                    },
                );
            } catch (\Throwable) {
                // Do not let one bad entry block the rest; log if a logger is available.
                // Errors here are exceptional (e.g. DB constraint violations after a
                // duplicate processing attempt) and safe to swallow in the lazy-tick path.
            }
        }
    }

    private static function settleRetiredTroopUnlocks(int $playerId, int $worldId): void
    {
        $db = Connection::getInstance();
        $retired = ResearchData::retiredTroopUnlocks();
        $marks = implode(',', array_fill(0, count($retired), '?'));
        $rows = $db->query("SELECT id,research_code,level_to FROM research_queue WHERE player_id=? AND world_id=? AND is_processed=0 AND research_code IN ($marks)", [$playerId,$worldId,...array_keys($retired)])->fetchAll();
        foreach ($rows as $row) {
            $cost = $retired[$row['research_code']]['levels'][(int)$row['level_to']-1]['resources'] ?? null;
            if (!$cost) continue;
            $db->transaction(static function () use ($db,$playerId,$worldId,$row,$cost): void {
                $cityId = $db->query('SELECT id FROM cities WHERE player_id=? AND world_id=? FOR UPDATE', [$playerId,$worldId])->fetchColumn();
                if ($cityId === false) return;
                if ($db->execute('UPDATE research_queue SET is_processed=1 WHERE id=? AND player_id=? AND world_id=? AND is_processed=0', [$row['id'],$playerId,$worldId]) !== 1) return;
                $db->execute('UPDATE cities SET food=food+?,lumber=lumber+?,stone=stone+?,gold=gold+? WHERE id=?', [$cost['food'],$cost['lumber'],$cost['stone'],$cost['gold'],$cityId]);
            });
        }
    }
}
