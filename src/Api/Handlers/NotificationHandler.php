<?php
declare(strict_types=1);

namespace Conquer\Api\Handlers;

use Conquer\Api\Response;
use Conquer\Auth\Session;
use Conquer\Db\Connection;
use Conquer\Game\Notification\NotificationService;

/**
 * Handles /api/notifications/* endpoints.
 *
 * GET  /api/notifications/poll  — aggregated game-state + pending notifications
 * POST /api/notifications/read  — mark one or more notifications as read
 *
 * The poll endpoint is the heartbeat of the frontend — called every ~10 seconds
 * to keep the HUD (build queue, march timers, battle reports, bell count) in sync
 * without WebSockets.
 */
final class NotificationHandler
{
    // Static-only handler — no instantiation.
    private function __construct() {}

    /**
     * GET /api/notifications/poll
     *
     * Returns a full game-state snapshot for the player's current city:
     *   - pending (unread) notifications
     *   - unread battle report count
     *   - active build queue entries (with seconds remaining)
     *   - active research queue entries (with seconds remaining)
     *   - active / returning marches (with seconds remaining)
     *
     * Also prunes notifications older than 7 days.
     *
     * @param array<string, mixed> $params  Route params (unused).
     */
    public static function poll(array $params): void
    {
        $session = Session::current();
        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }

        $playerId = (int) $session['player_id'];

        // Resolve the player's primary city ID for queue lookups.
        $cityId = self::resolveCityId($playerId);

        if ($cityId === null) {
            // Player has no city yet — return an empty state rather than an error
            // so the frontend can still receive the notification bell count.
            Response::ok([
                'notifications'         => [],
                'unread_battle_reports' => 0,
                'active_builds'         => [],
                'active_research'       => [],
                'marches'               => [],
            ]);
        }

        $data = NotificationService::poll($playerId, $cityId);

        Response::ok($data);
    }

    /**
     * POST /api/notifications/read
     *
     * Marks one or more notification IDs as read.
     *
     * Body (JSON): {"ids": [1, 2, 3]}
     *
     * Returns the number of rows actually updated.
     *
     * @param array<string, mixed> $params  Route params (unused).
     */
    public static function markRead(array $params): void
    {
        $session = Session::current();
        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }

        // Verify CSRF token.
        $supplied = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($supplied === '' || !hash_equals((string) $session['csrf_token'], $supplied)) {
            Response::error(403, 'CSRF_INVALID', 'CSRF token missing or invalid.');
        }

        // Parse JSON body.
        $body = json_decode(file_get_contents('php://input') ?: '', true) ?? [];
        $rawIds = $body['ids'] ?? [];

        if (!is_array($rawIds)) {
            Response::error(400, 'INVALID_INPUT', 'ids must be an array of notification IDs.');
        }

        // Sanitise: only accept positive integers.
        /** @var list<int> $ids */
        $ids = array_values(
            array_filter(
                array_map(static fn (mixed $v): int => (int) $v, $rawIds),
                static fn (int $v): bool => $v > 0,
            ),
        );

        if ($ids === []) {
            Response::error(400, 'MISSING_FIELD', 'At least one valid notification ID is required.');
        }

        $playerId = (int) $session['player_id'];

        NotificationService::markRead($playerId, $ids);

        Response::ok([
            'marked_ids'   => $ids,
            'unread_count' => NotificationService::getUnreadCount($playerId),
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Resolves the primary city ID for a player from the cities table.
     * Returns null when the player has no city yet.
     */
    private static function resolveCityId(int $playerId): ?int
    {
        $db  = Connection::getInstance();
        $row = $db->query(
            'SELECT id FROM cities WHERE player_id = ? LIMIT 1',
            [$playerId],
        )->fetch();

        return $row !== false ? (int) $row['id'] : null;
    }
}
