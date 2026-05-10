<?php
declare(strict_types=1);

namespace Conquer\Game\Conquest;

use Conquer\Db\Connection;

/**
 * Conquest Event system — stub implementation.
 *
 * Conquest events are scheduled world-wide competitions where alliances
 * race to capture and hold shrines for points. Events progress through
 * four phases that unlock progressively harder shrine tiers:
 *
 *   Phase 1 — C-shrines only (accessible from day 1)
 *   Phase 2 — C + B shrines
 *   Phase 3 — C + B + A shrines
 *   Phase 4 — All shrines including S-tier
 *
 * DB tables:
 *   conquest_events        — one record per event, tracks phase + state
 *   conquest_contributions — per-player point accumulation within an event
 *
 * This is a stub: the tables exist and the read/write paths are implemented,
 * but automated event transitions and full scoring logic are Sprint 5 work.
 */
final class ConquestService
{
    /** Duration of each conquest event in days. */
    private const EVENT_DURATION_DAYS = 7;

    /** Interval between events in days. */
    private const EVENT_INTERVAL_DAYS = 14;

    /** Maximum alliances shown in leaderboard. */
    private const LEADERBOARD_LIMIT = 20;

    private function __construct() {}

    // ── Event queries ─────────────────────────────────────────────────────────

    /**
     * Returns the currently active conquest event for a world, or null.
     *
     * @return array<string, mixed>|null
     */
    public static function getActiveEvent(int $worldId): ?array
    {
        $db = Connection::getInstance();

        $row = $db->query(
            "SELECT *
             FROM   conquest_events
             WHERE  world_id = ?
               AND  state    = 'active'
             LIMIT  1",
            [$worldId],
        )->fetch();

        return ($row !== false) ? $row : null;
    }

    /**
     * Returns the next upcoming conquest event for a world, or null.
     *
     * @return array<string, mixed>|null
     */
    public static function getUpcomingEvent(int $worldId): ?array
    {
        $db = Connection::getInstance();

        $row = $db->query(
            "SELECT *
             FROM   conquest_events
             WHERE  world_id = ?
               AND  state    = 'upcoming'
             ORDER  BY starts_at ASC
             LIMIT  1",
            [$worldId],
        )->fetch();

        return ($row !== false) ? $row : null;
    }

    // ── Leaderboard ───────────────────────────────────────────────────────────

    /**
     * Returns the alliance leaderboard for a conquest event.
     *
     * Rows are ordered by total points descending.
     * Each row includes: alliance_id, alliance_name, alliance_tag, total_points, rank.
     *
     * @return list<array<string, mixed>>
     */
    public static function getLeaderboard(int $eventId): array
    {
        $db = Connection::getInstance();

        $rows = $db->query(
            "SELECT
                 cc.alliance_id,
                 a.name          AS alliance_name,
                 a.tag           AS alliance_tag,
                 SUM(cc.points)  AS total_points
             FROM   conquest_contributions cc
             JOIN   alliances              a  ON a.id = cc.alliance_id
             WHERE  cc.event_id = ?
             GROUP  BY cc.alliance_id
             ORDER  BY total_points DESC
             LIMIT  " . self::LEADERBOARD_LIMIT,
            [$eventId],
        )->fetchAll();

        // Attach rank position
        $rank = 1;
        foreach ($rows as &$row) {
            $row['rank']         = $rank++;
            $row['total_points'] = (int) $row['total_points'];
        }
        unset($row);

        return $rows;
    }

    // ── Contribution tracking ─────────────────────────────────────────────────

    /**
     * Adds points for a player/alliance pair within a conquest event.
     *
     * Uses INSERT ... ON DUPLICATE KEY UPDATE so this is safe to call
     * multiple times for the same player in the same event.
     */
    public static function addContribution(
        int $eventId,
        int $playerId,
        int $allianceId,
        int $points,
    ): void {
        if ($points <= 0) {
            return;
        }

        $db = Connection::getInstance();

        $db->execute(
            "INSERT INTO conquest_contributions
                 (event_id, player_id, alliance_id, points)
             VALUES
                 (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                 points = points + VALUES(points)",
            [$eventId, $playerId, $allianceId, $points],
        );
    }

    // ── Event creation ────────────────────────────────────────────────────────

    /**
     * Creates the next conquest event for a world.
     *
     * Phase is determined by how many events the world has previously run:
     *   0 events → phase 1 (C-shrines)
     *   1 event  → phase 2 (C+B)
     *   2 events → phase 3 (C+B+A)
     *   3+events → phase 4 (all shrines, cycles)
     *
     * The event starts EVENT_INTERVAL_DAYS from now and runs EVENT_DURATION_DAYS.
     */
    public static function createNextEvent(int $worldId): void
    {
        $db = Connection::getInstance();

        // Count previous events to determine phase
        $countRow = $db->query(
            "SELECT COUNT(*) AS total FROM conquest_events WHERE world_id = ?",
            [$worldId],
        )->fetch();

        $previousCount = (int) ($countRow['total'] ?? 0);

        // Phase cycles: 1 → 2 → 3 → 4 → 4 → 4 ...
        $phase = min(4, $previousCount + 1);

        $db->execute(
            "INSERT INTO conquest_events
                 (world_id, phase, starts_at, ends_at, state)
             VALUES
                 (?,
                  ?,
                  DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? DAY),
                  DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? DAY),
                  'upcoming')",
            [
                $worldId,
                $phase,
                self::EVENT_INTERVAL_DAYS,
                self::EVENT_INTERVAL_DAYS + self::EVENT_DURATION_DAYS,
            ],
        );
    }
}
