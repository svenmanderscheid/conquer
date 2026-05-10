<?php
declare(strict_types=1);

namespace Conquer\Game\Quest;

use Conquer\Db\Connection;
use Conquer\Game\Inventory\InventoryService;

/**
 * Daily Quest system — 8 quests reset each UTC day, tracked per player.
 *
 * Quest definitions are loaded from data/daily_quests.json (version 1).
 * The JSON is cached in a static property for the lifetime of the request.
 *
 * DB table: player_daily_quests
 *   player_id, quest_code, quest_date, progress, target, completed, claimed
 *
 * Action → quest_code mapping (used by trackProgress()):
 *   attack_monster   → attack_monster_1, attack_monster_3
 *   upgrade_building → upgrade_building_1
 *   train_troops     → train_troops_100  ($amount = count trained)
 *   research_complete → research_complete_1
 *   gather_resources → collect_resources  ($amount = resource units gathered)
 *   open_chest       → open_chest_1
 *   daily_login      → login_daily        (handled internally by ensureDailyQuests)
 */
final class DailyQuestService
{
    // Static-only helper — no instantiation.
    private function __construct() {}

    // -------------------------------------------------------------------------
    // Data loading
    // -------------------------------------------------------------------------

    /**
     * Parsed quest definitions, keyed by quest code.
     *
     * @var array<string, array<string, mixed>>|null
     */
    private static ?array $definitions = null;

    /**
     * Maps action names to the quest codes they affect.
     *
     * @var array<string, list<string>>
     */
    private const ACTION_QUEST_MAP = [
        'attack_monster'   => ['attack_monster_1', 'attack_monster_3'],
        'upgrade_building' => ['upgrade_building_1'],
        'train_troops'     => ['train_troops_100'],
        'research_complete'=> ['research_complete_1'],
        'gather_resources' => ['collect_resources'],
        'open_chest'       => ['open_chest_1'],
    ];

    /**
     * Loads and caches quest definitions from data/daily_quests.json.
     *
     * @return array<string, array<string, mixed>>  Keyed by quest code.
     * @throws \RuntimeException When the data file is missing or malformed.
     */
    private static function loadDefinitions(): array
    {
        if (self::$definitions !== null) {
            return self::$definitions;
        }

        $path = defined('ROOT_DIR') ? ROOT_DIR . '/data/daily_quests.json' : __DIR__ . '/../../../data/daily_quests.json';

        if (!is_file($path)) {
            throw new \RuntimeException('daily_quests.json not found at: ' . $path);
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new \RuntimeException('Cannot read daily_quests.json.');
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        /** @var list<array<string, mixed>> $quests */
        $quests = $decoded['quests'] ?? [];

        $map = [];
        foreach ($quests as $q) {
            $code = (string) $q['code'];
            $map[$code] = $q;
        }

        self::$definitions = $map;
        return self::$definitions;
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Ensures today's quest rows exist for the player (UTC date).
     *
     * Inserts all quests from the JSON definition for today if they are
     * not yet present. Also auto-completes the `login_daily` quest since
     * the very act of being online counts as a daily login.
     *
     * Safe to call multiple times per day — INSERT IGNORE is idempotent.
     */
    public static function ensureDailyQuests(int $playerId): void
    {
        $db          = Connection::getInstance();
        $definitions = self::loadDefinitions();

        // INSERT IGNORE one row per quest code for today.
        foreach ($definitions as $code => $def) {
            $target = (int) ($def['target'] ?? 1);

            $db->execute(
                'INSERT IGNORE INTO player_daily_quests
                     (player_id, quest_code, quest_date, progress, target, completed, claimed)
                 VALUES (?, ?, UTC_DATE(), 0, ?, 0, 0)',
                [$playerId, $code, $target],
            );
        }

        // Auto-complete the login quest.
        self::autoCompleteLoginQuest($playerId);
    }

    /**
     * Returns all daily quests for today (UTC), enriched with static data.
     *
     * @return list<array<string, mixed>>  Each entry contains:
     *   quest_code, title, description, progress, target, completed, claimed, rewards
     */
    public static function getQuests(int $playerId): array
    {
        self::ensureDailyQuests($playerId);

        $db   = Connection::getInstance();
        $rows = $db->query(
            'SELECT quest_code, progress, target, completed, claimed
             FROM   player_daily_quests
             WHERE  player_id = ? AND quest_date = UTC_DATE()
             ORDER  BY quest_code ASC',
            [$playerId],
        )->fetchAll();

        $definitions = self::loadDefinitions();
        $result      = [];

        foreach ($rows as $row) {
            $code = (string) $row['quest_code'];
            $def  = $definitions[$code] ?? null;

            $result[] = [
                'quest_code'  => $code,
                'title'       => $def !== null ? (string) ($def['title'] ?? $code) : $code,
                'description' => $def !== null ? (string) ($def['description'] ?? '') : '',
                'progress'    => (int) $row['progress'],
                'target'      => (int) $row['target'],
                'completed'   => (bool) $row['completed'],
                'claimed'     => (bool) $row['claimed'],
                'rewards'     => $def !== null ? ($def['rewards'] ?? []) : [],
            ];
        }

        return $result;
    }

    /**
     * Tracks action progress for all matching daily quests.
     *
     * Updates progress for every quest linked to the given action.
     * Automatically marks quests as completed when progress >= target.
     *
     * @param int $amount  Units contributed (defaults to 1). For train_troops
     *                     this is the troop count; for gather_resources it is
     *                     the resource total.
     */
    public static function trackProgress(int $playerId, string $action, int $amount = 1): void
    {
        if ($amount <= 0) {
            return;
        }

        $questCodes = self::ACTION_QUEST_MAP[$action] ?? [];

        if ($questCodes === []) {
            return;
        }

        $db = Connection::getInstance();

        foreach ($questCodes as $code) {
            // Add progress, capped at target — single atomic UPDATE.
            $db->execute(
                'UPDATE player_daily_quests
                 SET    progress = LEAST(progress + ?, target)
                 WHERE  player_id  = ?
                   AND  quest_code = ?
                   AND  quest_date = UTC_DATE()
                   AND  completed  = 0',
                [$amount, $playerId, $code],
            );

            // Mark completed where progress has reached or exceeded target.
            $db->execute(
                'UPDATE player_daily_quests
                 SET    completed = 1
                 WHERE  player_id  = ?
                   AND  quest_code = ?
                   AND  quest_date = UTC_DATE()
                   AND  completed  = 0
                   AND  progress  >= target',
                [$playerId, $code],
            );
        }
    }

    /**
     * Claims the reward for a completed, unclaimed quest.
     *
     * @return list<array<string, mixed>>  The rewards array from the JSON definition.
     * @throws \RuntimeException On validation failure (not completed, already claimed, etc.)
     */
    public static function claimReward(int $playerId, string $questCode): array
    {
        $db = Connection::getInstance();

        // Load the quest row with a lock to prevent double-claiming.
        $row = $db->query(
            'SELECT completed, claimed, target
             FROM   player_daily_quests
             WHERE  player_id  = ?
               AND  quest_code = ?
               AND  quest_date = UTC_DATE()',
            [$playerId, $questCode],
        )->fetch();

        if ($row === false) {
            throw new \RuntimeException('Quest nicht gefunden oder gehört nicht zum heutigen Tag.');
        }

        if (!(bool) $row['completed']) {
            throw new \RuntimeException('Quest noch nicht abgeschlossen.');
        }

        if ((bool) $row['claimed']) {
            throw new \RuntimeException('Belohnung wurde bereits abgeholt.');
        }

        $definitions = self::loadDefinitions();
        $def         = $definitions[$questCode] ?? null;

        if ($def === null) {
            throw new \RuntimeException('Quest-Definition nicht gefunden: ' . $questCode);
        }

        /** @var list<array<string, mixed>> $rewards */
        $rewards = $def['rewards'] ?? [];

        // Mark as claimed first — then distribute rewards (fail-safe ordering).
        $affected = $db->execute(
            'UPDATE player_daily_quests
             SET    claimed = 1
             WHERE  player_id  = ?
               AND  quest_code = ?
               AND  quest_date = UTC_DATE()
               AND  completed  = 1
               AND  claimed    = 0',
            [$playerId, $questCode],
        );

        if ($affected === 0) {
            // Race condition — another request already claimed this.
            throw new \RuntimeException('Belohnung wurde bereits abgeholt (Konflikt).');
        }

        // Distribute rewards.
        self::distributeRewards($playerId, $rewards);

        return $rewards;
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Auto-completes the login_daily quest for today if it exists and is
     * not yet completed.
     */
    private static function autoCompleteLoginQuest(int $playerId): void
    {
        $db = Connection::getInstance();

        // Set progress = target and completed = 1 in one shot.
        $db->execute(
            'UPDATE player_daily_quests
             SET    progress  = target,
                    completed = 1
             WHERE  player_id  = ?
               AND  quest_code = ?
               AND  quest_date = UTC_DATE()
               AND  completed  = 0',
            [$playerId, 'login_daily'],
        );
    }

    /**
     * Distributes a rewards array to the player.
     *
     * Supported reward keys:
     *   gems      — added directly to players.gems
     *   item_code — added to player_inventory via InventoryService
     *
     * @param list<array<string, mixed>> $rewards
     */
    private static function distributeRewards(int $playerId, array $rewards): void
    {
        $db = Connection::getInstance();

        foreach ($rewards as $reward) {
            if (isset($reward['gems'])) {
                $gems = (int) $reward['gems'];
                if ($gems > 0) {
                    $db->execute(
                        'UPDATE players SET gems = gems + ? WHERE id = ?',
                        [$gems, $playerId],
                    );
                }
            }

            if (isset($reward['item_code'])) {
                $itemCode = (int) $reward['item_code'];
                $quantity = (int) ($reward['quantity'] ?? 1);

                if ($itemCode > 0 && $quantity > 0) {
                    InventoryService::addItems($playerId, $itemCode, $quantity);
                }
            }
        }
    }
}
