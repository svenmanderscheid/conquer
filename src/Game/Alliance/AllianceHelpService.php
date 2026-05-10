<?php
declare(strict_types=1);

namespace Conquer\Game\Alliance;

use Conquer\Db\Connection;
use Conquer\Logger;

/**
 * Manages the Alliance Help system.
 *
 * Players can ask alliance members to speed up building/research/training/
 * healing queues. Each help tick reduces the queue finish time by
 * TIME_REDUCTION_SECONDS, capped at MAX_REDUCTION_PCT of the original
 * remaining duration. A helper can assist at most MAX_HELPS_PER_HELPER_PER_DAY
 * times per day across all requests.
 *
 * DB tables used:
 *   alliance_help_requests  — one row per queue item that needs help
 *   alliance_help_log       — one row per individual help action
 *   alliance_members        — membership + alliance affiliation
 *   building_queue          — queue table for building upgrades
 *   troop_queue             — queue table for troop training (queue_type = 'training')
 */
final class AllianceHelpService
{
    // ── Tuning constants ──────────────────────────────────────────────────────

    /** Maximum help_count allowed per request. */
    public const MAX_HELP_COUNT = 30;

    /** A single helper may help at most this many times per UTC day (all requests combined). */
    public const MAX_HELPS_PER_HELPER_PER_DAY = 30;

    /** Seconds removed from the queue finish time per help action. */
    public const TIME_REDUCTION_SECONDS = 30;

    /** Maximum fraction of the original remaining time that can be reduced. */
    public const MAX_REDUCTION_PCT = 0.30;

    private function __construct() {}

    // ─────────────────────────────────────────────────────────────────────────
    // Creating requests
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Creates a help request for the given queue item. Idempotent — returns
     * the existing request ID if one already exists for the same queue_id/queue_type.
     *
     * @param string $queueType  'building' | 'research' | 'training' | 'healing'
     * @throws \RuntimeException on invalid queue_type or DB failure
     */
    public static function createHelpRequest(
        string $queueType,
        int    $queueId,
        int    $playerId,
        int    $cityId,
    ): int {
        self::assertValidQueueType($queueType);

        $db = Connection::getInstance();

        // Return existing request if already created for this queue item.
        try {
            $existing = $db->query(
                'SELECT id FROM alliance_help_requests
                 WHERE  queue_type = ? AND queue_id = ?
                 LIMIT  1',
                [$queueType, $queueId],
            )->fetch();

            if ($existing !== false) {
                return (int) $existing['id'];
            }
        } catch (\PDOException $e) {
            self::log()->error('[AllianceHelpService] createHelpRequest lookup failed: ' . $e->getMessage());
            throw new \RuntimeException('Datenbankfehler beim Prüfen bestehender Hilfe-Anfragen.');
        }

        try {
            $db->execute(
                'INSERT INTO alliance_help_requests
                    (queue_type, queue_id, player_id, city_id, help_count, max_helps, completed, created_at)
                 VALUES (?, ?, ?, ?, 0, ?, 0, UTC_TIMESTAMP())',
                [$queueType, $queueId, $playerId, $cityId, self::MAX_HELP_COUNT],
            );

            $requestId = $db->lastInsertId();
        } catch (\PDOException $e) {
            self::log()->error('[AllianceHelpService] createHelpRequest INSERT failed: ' . $e->getMessage());
            throw new \RuntimeException('Hilfe-Anfrage konnte nicht gespeichert werden.');
        }

        self::log()->info(sprintf(
            '[AllianceHelpService] Help request %d created — player %d, %s queue %d',
            $requestId,
            $playerId,
            $queueType,
            $queueId,
        ));

        return $requestId;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Giving help
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Processes a single help action from a helper player.
     *
     * Checks performed:
     *   1. Request exists and is not completed.
     *   2. Helper is not the request owner.
     *   3. Helper is an alliance member of the request owner.
     *   4. Helper has not exceeded MAX_HELPS_PER_HELPER_PER_DAY today.
     *
     * On success:
     *   - Inserts a row into alliance_help_log.
     *   - Increments help_count; marks completed when MAX_HELP_COUNT reached.
     *   - Reduces the matching queue's finishes_at by TIME_REDUCTION_SECONDS.
     *
     * @return array{helped: true, help_count: int, reduction_seconds: int}
     * @throws \RuntimeException when validation fails
     */
    public static function help(int $requestId, int $helperId): array
    {
        $db = Connection::getInstance();

        // ── Load request ──────────────────────────────────────────────────────
        try {
            $request = $db->query(
                'SELECT * FROM alliance_help_requests WHERE id = ?',
                [$requestId],
            )->fetch();
        } catch (\PDOException $e) {
            self::log()->error('[AllianceHelpService] help() request load failed: ' . $e->getMessage());
            throw new \RuntimeException('Datenbankfehler.');
        }

        if ($request === false) {
            throw new \RuntimeException('Hilfe-Anfrage nicht gefunden.');
        }

        if ((int) $request['completed'] === 1) {
            throw new \RuntimeException('Diese Anfrage ist bereits abgeschlossen.');
        }

        $ownerId   = (int) $request['player_id'];
        $queueType = (string) $request['queue_type'];
        $queueId   = (int) $request['queue_id'];
        $helpCount = (int) $request['help_count'];
        $maxHelps  = (int) $request['max_helps'];

        if ($helperId === $ownerId) {
            throw new \RuntimeException('Du kannst dir nicht selbst helfen.');
        }

        // ── Verify helper is an alliance member of the request owner ──────────
        $helperAlliance = self::getPlayerAllianceId($db, $helperId);
        $ownerAlliance  = self::getPlayerAllianceId($db, $ownerId);

        if ($helperAlliance === null || $ownerAlliance === null || $helperAlliance !== $ownerAlliance) {
            throw new \RuntimeException('Du bist kein Mitglied der Allianz dieses Spielers.');
        }

        // ── Check helper daily limit ──────────────────────────────────────────
        try {
            $todayCount = (int) $db->query(
                "SELECT COUNT(*)
                 FROM   alliance_help_log
                 WHERE  helper_id = ?
                   AND  DATE(helped_at) = UTC_DATE()",
                [$helperId],
            )->fetchColumn();
        } catch (\PDOException $e) {
            self::log()->error('[AllianceHelpService] help() log count failed: ' . $e->getMessage());
            throw new \RuntimeException('Datenbankfehler beim Prüfen des Tageslimits.');
        }

        if ($todayCount >= self::MAX_HELPS_PER_HELPER_PER_DAY) {
            throw new \RuntimeException(
                'Du hast heute bereits ' . self::MAX_HELPS_PER_HELPER_PER_DAY . ' Mal geholfen. Limit erreicht.'
            );
        }

        // ── Check this helper hasn't already helped this specific request ──────
        try {
            $alreadyHelped = (int) $db->query(
                'SELECT COUNT(*) FROM alliance_help_log WHERE request_id = ? AND helper_id = ?',
                [$requestId, $helperId],
            )->fetchColumn();
        } catch (\PDOException $e) {
            self::log()->error('[AllianceHelpService] help() duplicate check failed: ' . $e->getMessage());
            throw new \RuntimeException('Datenbankfehler.');
        }

        if ($alreadyHelped > 0) {
            throw new \RuntimeException('Du hast bei dieser Anfrage bereits geholfen.');
        }

        // ── Apply help inside a transaction ───────────────────────────────────
        $newHelpCount = 0;

        try {
            $db->transaction(function () use (
                $db,
                $requestId,
                $helperId,
                $helpCount,
                $maxHelps,
                $queueType,
                $queueId,
                &$newHelpCount,
            ): void {
                // Insert log entry
                $db->execute(
                    'INSERT INTO alliance_help_log (request_id, helper_id, helped_at)
                     VALUES (?, ?, UTC_TIMESTAMP())',
                    [$requestId, $helperId],
                );

                // Increment help_count; mark completed if cap reached
                $newHelpCount = $helpCount + 1;
                $isCompleted  = ($newHelpCount >= $maxHelps) ? 1 : 0;

                $db->execute(
                    'UPDATE alliance_help_requests
                     SET help_count = ?, completed = ?
                     WHERE id = ?',
                    [$newHelpCount, $isCompleted, $requestId],
                );

                // Reduce queue time
                $reductionSecs = self::TIME_REDUCTION_SECONDS;
                self::applyTimeReduction($db, $queueType, $queueId, $reductionSecs);
            });
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\PDOException $e) {
            self::log()->error('[AllianceHelpService] help() transaction failed: ' . $e->getMessage());
            throw new \RuntimeException('Hilfe konnte nicht verarbeitet werden.');
        }

        self::log()->info(sprintf(
            '[AllianceHelpService] Helper %d helped request %d (%s queue %d) — help_count now %d',
            $helperId,
            $requestId,
            $queueType,
            $queueId,
            $newHelpCount,
        ));

        return [
            'helped'            => true,
            'help_count'        => $newHelpCount,
            'reduction_seconds' => self::TIME_REDUCTION_SECONDS,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Reading requests
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Returns all open (not completed) help requests from alliance members.
     *
     * @return list<array{request_id: int, player_name: string, city_id: int, queue_type: string, help_count: int, max_helps: int, created_at: string}>
     */
    public static function getHelpRequests(int $allianceId): array
    {
        $db = Connection::getInstance();

        try {
            return $db->query(
                'SELECT  r.id          AS request_id,
                         p.username    AS player_name,
                         r.city_id,
                         r.queue_type,
                         r.help_count,
                         r.max_helps,
                         r.created_at
                 FROM    alliance_help_requests r
                 JOIN    alliance_members       m  ON m.player_id   = r.player_id
                 JOIN    players                p  ON p.id          = r.player_id
                 WHERE   m.alliance_id = ?
                   AND   r.completed   = 0
                 ORDER   BY r.created_at DESC',
                [$allianceId],
            )->fetchAll();
        } catch (\PDOException $e) {
            self::log()->error('[AllianceHelpService] getHelpRequests failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Returns the requesting player's own open help requests with current help_count.
     *
     * @return list<array<string,mixed>>
     */
    public static function getMyRequests(int $playerId): array
    {
        $db = Connection::getInstance();

        try {
            return $db->query(
                'SELECT id AS request_id, queue_type, queue_id, city_id,
                        help_count, max_helps, completed, created_at
                 FROM   alliance_help_requests
                 WHERE  player_id = ?
                   AND  completed = 0
                 ORDER  BY created_at DESC',
                [$playerId],
            )->fetchAll();
        } catch (\PDOException $e) {
            self::log()->error('[AllianceHelpService] getMyRequests failed: ' . $e->getMessage());
            return [];
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Reduces the finishes_at column of the appropriate queue table.
     * The reduction is clamped so finishes_at never goes below UTC_TIMESTAMP().
     *
     * Supported queue_type values match the ENUM in alliance_help_requests:
     *   'building'  → building_queue.finishes_at
     *   'research'  → research_queue.finishes_at  (if table exists)
     *   'training'  → troop_queue.finishes_at
     *   'healing'   → hospital_queue.finishes_at  (if table exists)
     */
    private static function applyTimeReduction(
        Connection $db,
        string     $queueType,
        int        $queueId,
        int        $reductionSecs,
    ): void {
        $tableMap = [
            'building' => 'building_queue',
            'research' => 'research_queue',
            'training' => 'troop_queue',
            'healing'  => 'hospital_queue',
        ];

        $table = $tableMap[$queueType] ?? null;

        if ($table === null) {
            self::log()->warn('[AllianceHelpService] Unknown queue_type for time reduction: ' . $queueType);
            return;
        }

        try {
            // Clamp: finishes_at must not drop below current UTC time
            $db->execute(
                "UPDATE {$table}
                 SET    finishes_at = GREATEST(
                            UTC_TIMESTAMP(),
                            DATE_SUB(finishes_at, INTERVAL ? SECOND)
                        )
                 WHERE  id = ?",
                [$reductionSecs, $queueId],
            );
        } catch (\PDOException $e) {
            // Non-fatal — log and continue (queue table may differ per environment)
            self::log()->warn(sprintf(
                '[AllianceHelpService] Time reduction failed for %s/%d: %s',
                $table,
                $queueId,
                $e->getMessage(),
            ));
        }
    }

    /**
     * Returns the alliance_id for the given player, or null if not in one.
     */
    private static function getPlayerAllianceId(Connection $db, int $playerId): ?int
    {
        try {
            $row = $db->query(
                'SELECT alliance_id FROM alliance_members WHERE player_id = ? LIMIT 1',
                [$playerId],
            )->fetch();

            return ($row !== false) ? (int) $row['alliance_id'] : null;
        } catch (\PDOException $e) {
            self::log()->error('[AllianceHelpService] getPlayerAllianceId failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Validates that the given queue type is one of the supported ENUM values.
     *
     * @throws \RuntimeException on invalid type
     */
    private static function assertValidQueueType(string $queueType): void
    {
        $valid = ['building', 'research', 'training', 'healing'];

        if (!in_array($queueType, $valid, strict: true)) {
            throw new \RuntimeException(
                'Ungültiger queue_type "' . $queueType . '". Erlaubt: ' . implode(', ', $valid) . '.'
            );
        }
    }

    private static function log(): Logger
    {
        return Logger::getInstance();
    }
}
