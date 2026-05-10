<?php
declare(strict_types=1);

namespace Conquer\Api\Handlers;

use Conquer\Api\Response;
use Conquer\Game\Conquest\ConquestService;

/**
 * Handles /api/conquest/* endpoints.
 *
 * GET /api/conquest/event       — current or upcoming conquest event
 * GET /api/conquest/leaderboard — alliance leaderboard for the active event
 */
final class ConquestHandler
{
    private function __construct() {}

    // ── GET /api/conquest/event ───────────────────────────────────────────────

    /**
     * Returns the active conquest event. Falls back to the upcoming event
     * if no event is currently active. Returns null in the data payload when
     * neither exists.
     *
     * @param array<string, mixed> $session
     */
    public static function current(array $session): void
    {
        if (empty($session)) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }

        // World ID is always 1 for the initial release.
        $worldId = 1;

        $event = ConquestService::getActiveEvent($worldId)
              ?? ConquestService::getUpcomingEvent($worldId);

        Response::ok([
            'event' => $event,
        ]);
    }

    // ── GET /api/conquest/leaderboard ─────────────────────────────────────────

    /**
     * Returns the alliance leaderboard for the currently active conquest event.
     * Returns an empty leaderboard when no event is active.
     *
     * @param array<string, mixed> $session
     */
    public static function leaderboard(array $session): void
    {
        if (empty($session)) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }

        $worldId = 1;

        $event = ConquestService::getActiveEvent($worldId);

        if ($event === null) {
            Response::ok([
                'event_id'    => null,
                'leaderboard' => [],
            ]);
        }

        $eventId     = (int) $event['id'];
        $leaderboard = ConquestService::getLeaderboard($eventId);

        Response::ok([
            'event_id'    => $eventId,
            'leaderboard' => $leaderboard,
        ]);
    }
}
