<?php
declare(strict_types=1);

namespace Conquer\Api\Handlers;

use Conquer\Api\Response;
use Conquer\Auth\Session;
use Conquer\Db\Connection;
use Conquer\Game\Hospital\HospitalService;

/**
 * Handles /api/hospital/* endpoints.
 *
 * GET  /api/hospital/status       — current wounded troops + capacity
 * POST /api/hospital/instant-heal — instantly heal all wounded (costs GEMS)
 */
final class HospitalHandler
{
    private function __construct() {}

    // -------------------------------------------------------------------------
    // GET /api/hospital/status
    // -------------------------------------------------------------------------

    /**
     * Returns the current hospital status for the authenticated player's city.
     *
     * Runs a lazy tick first so any fully-healed batches are credited before
     * the status snapshot is taken.
     */
    public static function status(array $params): void
    {
        $session = Session::current();
        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }

        $cityId = (int) ($session['city_id'] ?? 0);

        if ($cityId <= 0) {
            Response::error(400, 'NO_CITY', 'No city associated with this session.');
        }

        // Flush all healed troops before reading the current state
        HospitalService::processHealed($cityId);

        $status = HospitalService::getStatus($cityId);

        Response::ok($status);
    }

    // -------------------------------------------------------------------------
    // POST /api/hospital/instant-heal
    // -------------------------------------------------------------------------

    /**
     * Instantly heals all wounded troops in exchange for GEMS.
     *
     * Cost: HospitalService::INSTANT_HEAL_GEM_COST (50 GEMS) flat, regardless
     * of how many troops are wounded. This matches the pattern used by
     * instant-build in CityHandler.
     *
     * Header: X-CSRF-Token
     */
    public static function instantHeal(array $params): void
    {
        $session = Session::current();
        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }

        // CSRF validation
        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($csrf === '' || !hash_equals((string) $session['csrf_token'], $csrf)) {
            Response::error(403, 'CSRF_INVALID', 'CSRF token missing or invalid.');
        }

        $playerId   = (int) $session['player_id'];
        $cityId     = (int) ($session['city_id'] ?? 0);
        $playerGems = (int) ($session['gems']    ?? 0);

        if ($cityId <= 0) {
            Response::error(400, 'NO_CITY', 'No city associated with this session.');
        }

        $gemCost = HospitalService::INSTANT_HEAL_GEM_COST;

        if ($playerGems < $gemCost) {
            Response::error(400, 'NOT_ENOUGH_GEMS',
                'Not enough GEMS. Need ' . $gemCost . ', have ' . $playerGems . '.');
        }

        $db = Connection::getInstance();

        // Verify there are actually wounded troops before charging the player
        try {
            $woundedCount = (int) $db->query(
                'SELECT COALESCE(SUM(count), 0) FROM hospital_wounded WHERE city_id = ?',
                [$cityId],
            )->fetchColumn();
        } catch (\PDOException) {
            $woundedCount = 0;
        }

        if ($woundedCount === 0) {
            Response::error(400, 'NO_WOUNDED', 'There are no wounded troops to heal.');
        }

        // Deduct GEMS and perform instant heal atomically
        $db->transaction(function (Connection $db) use ($playerId, $gemCost): void {
            $db->execute(
                'UPDATE players SET gems = gems - ? WHERE id = ? AND gems >= ?',
                [$gemCost, $playerId, $gemCost],
            );
        });

        // Run the instant heal — sets all timers to now then flushes
        $healed = HospitalService::instantHeal($cityId);

        if (!$healed) {
            // Refund gems if something went wrong after deduction
            $db->execute(
                'UPDATE players SET gems = gems + ? WHERE id = ?',
                [$gemCost, $playerId],
            );

            Response::error(500, 'HEAL_FAILED', 'Instant heal failed. GEMS refunded.');
        }

        Response::ok([
            'gems_spent'    => $gemCost,
            'troops_healed' => $woundedCount,
            'message'       => 'All ' . $woundedCount . ' wounded troops have been healed.',
        ]);
    }
}
