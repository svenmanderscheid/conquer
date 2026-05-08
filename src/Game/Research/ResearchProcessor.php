<?php
declare(strict_types=1);

namespace Conquer\Game\Research;

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
    public static function processQueue(int $playerId, int $worldId = 1): void
    {
        $db = Connection::getInstance();

        try {
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
                        // Upsert the player's research level.
                        $db->execute(
                            'INSERT INTO player_research (player_id, world_id, research_code, level)
                             VALUES (?, ?, ?, ?)
                             ON DUPLICATE KEY UPDATE level = ?',
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
}
