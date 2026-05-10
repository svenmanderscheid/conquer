<?php
declare(strict_types=1);

namespace Conquer\Game\Tutorial;

use Conquer\Db\Connection;
use Conquer\Game\Inventory\InventoryService;

/**
 * Tutorial progression system — 12 ordered steps that guide a new player
 * through the core game loops (building, troops, research, map, combat).
 *
 * DB table: tutorial_progress (player_id, current_step, completed_at, skipped_at)
 *
 * Step flow:
 *   Steps 1 and 12 are auto-completed (welcome + finish).
 *   Steps 2-11 require the player to perform a specific action.
 *   advance() is called by other services whenever a relevant action happens.
 */
final class TutorialService
{
    // Static-only helper — no instantiation.
    private function __construct() {}

    // -------------------------------------------------------------------------
    // Step definitions
    // -------------------------------------------------------------------------

    /**
     * The 12 tutorial steps.
     *
     * Keys inside each entry:
     *   title    — human-readable label shown in the UI
     *   action   — machine-readable identifier used to match advance() calls
     *   auto     — (optional) true means the step completes without player input
     *   building — (optional) building_code required for upgrade_building steps
     *   target_level — (optional) minimum level the building must reach
     *   min_count    — (optional) minimum troop count for train_troops step
     *
     * @var array<int, array<string, mixed>>
     */
    public const STEPS = [
        1  => ['title' => 'Willkommen',                          'action' => 'welcome',          'auto' => true],
        2  => ['title' => 'Castle upgraden',                     'action' => 'upgrade_building',  'building' => 'castle',      'target_level' => 2],
        3  => ['title' => 'Lumber Camp upgraden',                'action' => 'upgrade_building',  'building' => 'lumber_camp', 'target_level' => 2],
        4  => ['title' => 'Farm upgraden',                       'action' => 'upgrade_building',  'building' => 'farm',        'target_level' => 2],
        5  => ['title' => 'Quarry upgraden',                     'action' => 'upgrade_building',  'building' => 'quarry',      'target_level' => 2],
        6  => ['title' => 'Gold Mine upgraden',                  'action' => 'upgrade_building',  'building' => 'gold_mine',   'target_level' => 2],
        7  => ['title' => 'Barrack upgraden + Truppen trainieren', 'action' => 'train_troops',    'min_count' => 10],
        8  => ['title' => 'Academy upgraden + Forschen',         'action' => 'research_start'],
        9  => ['title' => 'Karte öffnen',                        'action' => 'view_map'],
        10 => ['title' => 'Monster angreifen',                   'action' => 'attack_monster'],
        11 => ['title' => 'Schatz anlegen',                      'action' => 'equip_treasure'],
        12 => ['title' => 'Tutorial abgeschlossen!',             'action' => 'complete',          'auto' => true],
    ];

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Returns the tutorial progress for a player.
     *
     * Initialises a row if none exists yet (step 1, then immediately
     * advances past the auto-complete welcome step to step 2).
     *
     * @return array{current_step: int, completed: bool, skipped: bool, steps: array<int, array<string, mixed>>}
     */
    public static function getProgress(int $playerId): array
    {
        self::ensureInitialized($playerId);

        $db  = Connection::getInstance();
        $row = $db->query(
            'SELECT current_step, completed_at, skipped_at
             FROM   tutorial_progress
             WHERE  player_id = ?',
            [$playerId],
        )->fetch();

        if ($row === false) {
            // Should not happen after ensureInitialized, but handle gracefully.
            return [
                'current_step' => 1,
                'completed'    => false,
                'skipped'      => false,
                'steps'        => self::STEPS,
            ];
        }

        return [
            'current_step' => (int) $row['current_step'],
            'completed'    => $row['completed_at'] !== null,
            'skipped'      => $row['skipped_at']   !== null,
            'steps'        => self::STEPS,
        ];
    }

    /**
     * Attempts to advance the tutorial when the player performs an action.
     *
     * Returns true when the tutorial step was advanced, false otherwise
     * (wrong action for current step, already completed, or context mismatch).
     *
     * @param array<string, mixed> $context  Optional action context:
     *   - building_code  (string) for upgrade_building actions
     *   - level_to       (int)    the building level that was reached
     *   - troop_count    (int)    number of troops trained in train_troops actions
     */
    public static function advance(int $playerId, string $action, array $context = []): bool
    {
        $db = Connection::getInstance();

        $row = $db->query(
            'SELECT current_step, completed_at, skipped_at
             FROM   tutorial_progress
             WHERE  player_id = ?',
            [$playerId],
        )->fetch();

        if ($row === false) {
            self::ensureInitialized($playerId);
            return false;
        }

        // Already finished — nothing to do.
        if ($row['completed_at'] !== null || $row['skipped_at'] !== null) {
            return false;
        }

        $currentStep = (int) $row['current_step'];

        if (!isset(self::STEPS[$currentStep])) {
            return false;
        }

        $step = self::STEPS[$currentStep];

        // Action must match the expected action for this step.
        if ($step['action'] !== $action) {
            return false;
        }

        // Extra validation for upgrade_building steps.
        if ($action === 'upgrade_building') {
            $requiredBuilding = (string) ($step['building'] ?? '');
            $targetLevel      = (int)   ($step['target_level'] ?? 1);
            $contextBuilding  = (string) ($context['building_code'] ?? '');
            $contextLevel     = (int)    ($context['level_to'] ?? 0);

            if ($requiredBuilding !== '' && $contextBuilding !== $requiredBuilding) {
                return false;
            }

            if ($contextLevel < $targetLevel) {
                return false;
            }
        }

        // Extra validation for train_troops steps.
        if ($action === 'train_troops') {
            $minCount     = (int) ($step['min_count'] ?? 1);
            $troopCount   = (int) ($context['troop_count'] ?? 0);

            if ($troopCount < $minCount) {
                return false;
            }
        }

        // Move to the next step.
        $nextStep = $currentStep + 1;

        if ($nextStep > count(self::STEPS)) {
            $nextStep = count(self::STEPS);
        }

        // If step 12 is reached: mark tutorial completed and grant rewards.
        if ($nextStep === 12) {
            $db->execute(
                'UPDATE tutorial_progress
                 SET    current_step = ?, completed_at = UTC_TIMESTAMP()
                 WHERE  player_id = ?',
                [$nextStep, $playerId],
            );
            self::grantCompletionRewards($playerId);
            return true;
        }

        $db->execute(
            'UPDATE tutorial_progress
             SET    current_step = ?
             WHERE  player_id = ?',
            [$nextStep, $playerId],
        );

        // If the next step is also auto-complete, advance again immediately.
        if (!empty(self::STEPS[$nextStep]['auto'])) {
            return self::advance($playerId, (string) self::STEPS[$nextStep]['action']);
        }

        return true;
    }

    /**
     * Skips all remaining tutorial steps and grants completion rewards.
     *
     * Sets skipped_at = NOW(), current_step = 12, completed_at = NOW().
     * Players who skip still receive the tutorial completion rewards.
     */
    public static function skip(int $playerId): void
    {
        self::ensureInitialized($playerId);

        $db = Connection::getInstance();

        $db->execute(
            'UPDATE tutorial_progress
             SET    current_step = 12,
                    completed_at = UTC_TIMESTAMP(),
                    skipped_at   = UTC_TIMESTAMP()
             WHERE  player_id = ?
               AND  completed_at IS NULL',
            [$playerId],
        );

        self::grantCompletionRewards($playerId);
    }

    /**
     * Grants rewards for tutorial completion (or skip).
     *
     * Rewards:
     *   - 100 GEMS (credited directly to players table)
     *   - 2x Speed Up 1h (item_code 10103003)
     *   - 1x Resource Production Boost 8h (item_code 10102001)
     */
    public static function grantCompletionRewards(int $playerId): void
    {
        $db = Connection::getInstance();

        // Credit GEMS directly — fast path, no inventory row needed.
        $db->execute(
            'UPDATE players SET gems = gems + 100 WHERE id = ?',
            [$playerId],
        );

        // Add items to inventory one by one (addItems signature: playerId, itemCode, quantity).
        InventoryService::addItems($playerId, 10103003, 2); // Speed Up 1h ×2
        InventoryService::addItems($playerId, 10102001, 1); // Resource Production Boost 8h ×1
    }

    /**
     * Ensures a tutorial_progress row exists for the player.
     *
     * On first insert, auto-advances past step 1 (welcome) to step 2
     * since the welcome step has no player action requirement.
     */
    public static function ensureInitialized(int $playerId): void
    {
        $db = Connection::getInstance();

        $affected = $db->execute(
            'INSERT IGNORE INTO tutorial_progress (player_id, current_step)
             VALUES (?, 1)',
            [$playerId],
        );

        // If a new row was inserted, advance past the auto-complete welcome step.
        if ($affected > 0) {
            self::advance($playerId, 'welcome');
        }
    }
}
