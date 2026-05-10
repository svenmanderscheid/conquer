<?php
declare(strict_types=1);

namespace Conquer\Api\Handlers;

use Conquer\Api\Response;
use Conquer\Auth\Session;
use Conquer\Game\Quest\DailyQuestService;

/**
 * Handles /api/quests/* endpoints.
 *
 * GET  /api/quests/daily  — list today's daily quests with progress
 * POST /api/quests/claim  — claim the reward for a completed quest
 */
final class QuestHandler
{
    // Static-only handler — no instantiation.
    private function __construct() {}

    /**
     * GET /api/quests/daily
     *
     * Returns all daily quests for today (UTC) with progress, completion
     * state, and reward definitions. Ensures quest rows are seeded first.
     *
     * @param array<string, mixed> $params  Route params (unused for this endpoint).
     */
    public static function list(array $params): void
    {
        $session = Session::current();
        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }

        $playerId = (int) $session['player_id'];

        DailyQuestService::ensureDailyQuests($playerId);
        $quests = DailyQuestService::getQuests($playerId);

        Response::ok(['quests' => $quests]);
    }

    /**
     * POST /api/quests/claim
     *
     * Claims the reward for a completed, unclaimed daily quest.
     *
     * Body (JSON): {"quest_code": "attack_monster_1"}
     *
     * Returns the rewards array on success so the frontend can animate the
     * reward delivery without a second API call.
     *
     * @param array<string, mixed> $params  Route params (unused for this endpoint).
     */
    public static function claim(array $params): void
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
        $body      = json_decode(file_get_contents('php://input') ?: '', true) ?? [];
        $questCode = trim((string) ($body['quest_code'] ?? ''));

        if ($questCode === '') {
            Response::error(400, 'MISSING_FIELD', 'quest_code is required.');
        }

        $playerId = (int) $session['player_id'];

        try {
            $rewards = DailyQuestService::claimReward($playerId, $questCode);
        } catch (\RuntimeException $e) {
            Response::error(422, 'CLAIM_FAILED', $e->getMessage());
        }

        Response::ok([
            'quest_code' => $questCode,
            'rewards'    => $rewards,
        ]);
    }
}
