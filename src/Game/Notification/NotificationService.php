<?php
declare(strict_types=1);

namespace Conquer\Game\Notification;

use Conquer\Db\Connection;

/**
 * In-game notification inbox.
 *
 * Notifications are lightweight server-push messages shown in the HUD bell.
 * They are inserted by game services (BuildingUpgrader, MarchTick, etc.)
 * and read by the frontend via polling (GET /api/notifications/poll).
 *
 * DB table: notifications (player_id, type, data_json, read_at, created_at)
 *
 * The poll() method is the single aggregation endpoint the frontend calls
 * every ~10 seconds to stay in sync without WebSockets.
 */
final class NotificationService
{
    // Static-only helper — no instantiation.
    private function __construct() {}

    // -------------------------------------------------------------------------
    // Notification type constants
    // -------------------------------------------------------------------------

    public const TYPE_BUILD_COMPLETE    = 'build_complete';
    public const TYPE_RESEARCH_COMPLETE = 'research_complete';
    public const TYPE_TRAIN_COMPLETE    = 'train_complete';
    public const TYPE_MARCH_RETURNED    = 'march_returned';
    public const TYPE_BATTLE_INCOMING   = 'battle_incoming';
    public const TYPE_BATTLE_REPORT     = 'battle_report';
    public const TYPE_HELP_RECEIVED     = 'help_received';

    /** How many days to keep notifications before pruning. */
    private const RETENTION_DAYS = 7;

    /** Maximum notifications returned per poll call. */
    private const POLL_LIMIT = 50;

    // -------------------------------------------------------------------------
    // Write
    // -------------------------------------------------------------------------

    /**
     * Inserts a new notification for a player.
     *
     * Failures are silently swallowed — notifications are non-critical and
     * should never interrupt the main game flow.
     *
     * @param array<string, mixed> $data  Arbitrary payload stored as JSON.
     *                                    Typical keys: building_code, level, march_id, etc.
     */
    public static function push(int $playerId, string $type, array $data = []): void
    {
        try {
            $db       = Connection::getInstance();
            $dataJson = $data !== [] ? json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : null;

            $db->execute(
                'INSERT INTO notifications (player_id, type, data_json)
                 VALUES (?, ?, ?)',
                [$playerId, $type, $dataJson],
            );
        } catch (\Throwable) {
            // Non-critical — log silently and continue.
        }
    }

    // -------------------------------------------------------------------------
    // Read
    // -------------------------------------------------------------------------

    /**
     * Returns up to 50 unread notifications for the player, newest first.
     *
     * @return list<array<string, mixed>>  Each entry: {id, type, data, created_at}
     */
    public static function getPending(int $playerId): array
    {
        $db   = Connection::getInstance();
        $rows = $db->query(
            'SELECT id, type, data_json, created_at
             FROM   notifications
             WHERE  player_id = ? AND read_at IS NULL
             ORDER  BY created_at DESC
             LIMIT  ' . self::POLL_LIMIT,
            [$playerId],
        )->fetchAll();

        return array_map(
            static function (array $row): array {
                $data = null;
                if ($row['data_json'] !== null) {
                    try {
                        $data = json_decode((string) $row['data_json'], true, 512, JSON_THROW_ON_ERROR);
                    } catch (\JsonException) {
                        $data = null;
                    }
                }

                return [
                    'id'         => (int) $row['id'],
                    'type'       => (string) $row['type'],
                    'data'       => $data,
                    'created_at' => (string) $row['created_at'],
                ];
            },
            $rows,
        );
    }

    /**
     * Returns the count of unread notifications for a player.
     */
    public static function getUnreadCount(int $playerId): int
    {
        $db = Connection::getInstance();

        $row = $db->query(
            'SELECT COUNT(*) AS cnt
             FROM   notifications
             WHERE  player_id = ? AND read_at IS NULL',
            [$playerId],
        )->fetch();

        return $row !== false ? (int) $row['cnt'] : 0;
    }

    // -------------------------------------------------------------------------
    // Mark read
    // -------------------------------------------------------------------------

    /**
     * Marks a list of notification IDs as read for a player.
     *
     * The player_id constraint prevents one player marking another's notifications.
     *
     * @param list<int> $ids
     */
    public static function markRead(int $playerId, array $ids): void
    {
        if ($ids === []) {
            return;
        }

        // Validate — only accept positive integers.
        $cleanIds = array_values(
            array_filter($ids, static fn (mixed $v): bool => is_int($v) && $v > 0),
        );

        if ($cleanIds === []) {
            return;
        }

        $db          = Connection::getInstance();
        $placeholders = implode(',', array_fill(0, count($cleanIds), '?'));

        $db->execute(
            'UPDATE notifications
             SET    read_at = UTC_TIMESTAMP()
             WHERE  player_id = ?
               AND  read_at  IS NULL
               AND  id IN (' . $placeholders . ')',
            [$playerId, ...$cleanIds],
        );
    }

    // -------------------------------------------------------------------------
    // Poll — aggregated status endpoint
    // -------------------------------------------------------------------------

    /**
     * Aggregates all relevant game state updates in a single DB round-trip batch.
     *
     * Called by GET /api/notifications/poll every ~10 seconds. Returns everything
     * the frontend needs to stay in sync: pending notifications, active build/
     * research queue entries, active marches, and unread battle report count.
     *
     * Also prunes notifications older than RETENTION_DAYS to keep the table lean.
     *
     * @return array{
     *   notifications: list<array<string, mixed>>,
     *   unread_battle_reports: int,
     *   active_builds: list<array<string, mixed>>,
     *   active_research: list<array<string, mixed>>,
     *   marches: list<array<string, mixed>>
     * }
     */
    public static function poll(int $playerId, int $cityId): array
    {
        $db = Connection::getInstance();

        // ---- 1. Pending notifications ----------------------------------------
        $notifications = self::getPending($playerId);

        // ---- 2. Unread battle reports ----------------------------------------
        $brRow = $db->query(
            'SELECT COUNT(*) AS cnt
             FROM   battle_reports
             WHERE  attacker_id = ? AND attacker_read = 0
             UNION ALL
             SELECT COUNT(*) AS cnt
             FROM   battle_reports
             WHERE  defender_id = ? AND defender_read = 0',
            [$playerId, $playerId],
        )->fetchAll();

        $unreadBattleReports = 0;
        foreach ($brRow as $r) {
            $unreadBattleReports += (int) $r['cnt'];
        }

        // ---- 3. Active building queue ----------------------------------------
        $buildRows = $db->query(
            'SELECT id, building_code, level_to, started_at, finishes_at,
                    GREATEST(0, TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), finishes_at)) AS secs_remaining
             FROM   building_queue
             WHERE  city_id      = ?
               AND  is_processed = 0
             ORDER  BY finishes_at ASC',
            [$cityId],
        )->fetchAll();

        $activeBuilds = array_map(
            static fn (array $r): array => [
                'id'            => (int) $r['id'],
                'building_code' => (string) $r['building_code'],
                'level_to'      => (int) $r['level_to'],
                'started_at'    => (string) $r['started_at'],
                'finishes_at'   => (string) $r['finishes_at'],
                'secs_remaining'=> (int) $r['secs_remaining'],
            ],
            $buildRows,
        );

        // ---- 4. Active research ----------------------------------------------
        $researchRows = $db->query(
            'SELECT id, research_code, started_at, finishes_at,
                    GREATEST(0, TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), finishes_at)) AS secs_remaining
             FROM   research_queue
             WHERE  player_id    = ?
               AND  is_processed = 0
             ORDER  BY finishes_at ASC',
            [$playerId],
        )->fetchAll();

        $activeResearch = array_map(
            static fn (array $r): array => [
                'id'            => (int) $r['id'],
                'research_code' => (string) $r['research_code'],
                'started_at'    => (string) $r['started_at'],
                'finishes_at'   => (string) $r['finishes_at'],
                'secs_remaining'=> (int) $r['secs_remaining'],
            ],
            $researchRows,
        );

        // ---- 5. Active marches -----------------------------------------------
        $marchRows = $db->query(
            'SELECT id, march_type, state, target_x, target_y, arrives_at, returns_at,
                    GREATEST(0, TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(),
                        CASE state WHEN \'marching\' THEN arrives_at ELSE returns_at END
                    )) AS secs_remaining
             FROM   marches
             WHERE  player_id = ?
               AND  state     IN (\'marching\', \'returning\')
             ORDER  BY id ASC',
            [$playerId],
        )->fetchAll();

        $marches = array_map(
            static fn (array $r): array => [
                'id'            => (int) $r['id'],
                'march_type'    => (string) $r['march_type'],
                'state'         => (string) $r['state'],
                'target_x'      => (int) $r['target_x'],
                'target_y'      => (int) $r['target_y'],
                'arrives_at'    => (string) ($r['arrives_at'] ?? ''),
                'returns_at'    => (string) ($r['returns_at'] ?? ''),
                'secs_remaining'=> (int) $r['secs_remaining'],
            ],
            $marchRows,
        );

        // ---- 6. Prune old notifications --------------------------------------
        try {
            $db->execute(
                'DELETE FROM notifications
                 WHERE  player_id  = ?
                   AND  created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY)',
                [$playerId, self::RETENTION_DAYS],
            );
        } catch (\Throwable) {
            // Non-critical cleanup — ignore failures.
        }

        return [
            'notifications'        => $notifications,
            'unread_battle_reports'=> $unreadBattleReports,
            'active_builds'        => $activeBuilds,
            'active_research'      => $activeResearch,
            'marches'              => $marches,
        ];
    }
}
