<?php
declare(strict_types=1);

namespace Conquer\Game\Alliance;

use Conquer\Db\Connection;

/**
 * Alliance Research system.
 *
 * 8 research nodes across 3 trees, 10 levels each.
 * Costs are drawn from the alliance treasury (lumber + stone + gold).
 * Only one research can be active per alliance at a time.
 */
final class AllianceResearchService
{
    private const MAX_LEVEL = 10;

    /** Cost per resource per level: 500 × level² */
    private const COST_FACTOR = 500;

    /** Base duration in minutes per level: 5 × level */
    private const DURATION_MINUTES_PER_LEVEL = 5;

    private function __construct() {}

    // -------------------------------------------------------------------------
    // Node definitions
    // -------------------------------------------------------------------------

    /**
     * Returns all 8 research node definitions.
     *
     * @return array<string, array{code: string, name: string, tree: string, description: string, bonus_label: string, bonus_per_level: float}>
     */
    public static function getData(): array
    {
        return [
            // Battle tree
            'ally_troops_hp' => [
                'code'             => 'ally_troops_hp',
                'name'             => 'Truppenstärke',
                'tree'             => 'battle',
                'description'      => 'Erhöht die Trefferpunkte aller Truppen in der Allianz.',
                'bonus_label'      => '+{n}% HP für alle Truppen',
                'bonus_per_level'  => 2.0,
            ],
            'ally_troops_atk' => [
                'code'             => 'ally_troops_atk',
                'name'             => 'Truppenangriff',
                'tree'             => 'battle',
                'description'      => 'Erhöht den Angriffswert aller Truppen in der Allianz.',
                'bonus_label'      => '+{n}% ATK für alle Truppen',
                'bonus_per_level'  => 2.0,
            ],
            'ally_troops_def' => [
                'code'             => 'ally_troops_def',
                'name'             => 'Truppenverteidigung',
                'tree'             => 'battle',
                'description'      => 'Erhöht den Verteidigungswert aller Truppen in der Allianz.',
                'bonus_label'      => '+{n}% DEF für alle Truppen',
                'bonus_per_level'  => 2.0,
            ],

            // Production tree
            'ally_food_prod' => [
                'code'             => 'ally_food_prod',
                'name'             => 'Allianz-Nahrung',
                'tree'             => 'production',
                'description'      => 'Steigert die Nahrungsproduktion aller Mitglieder.',
                'bonus_label'      => '+{n}% Nahrungsproduktion',
                'bonus_per_level'  => 3.0,
            ],
            'ally_lumber_prod' => [
                'code'             => 'ally_lumber_prod',
                'name'             => 'Allianz-Holz',
                'tree'             => 'production',
                'description'      => 'Steigert die Holzproduktion aller Mitglieder.',
                'bonus_label'      => '+{n}% Holzproduktion',
                'bonus_per_level'  => 3.0,
            ],
            'ally_stone_prod' => [
                'code'             => 'ally_stone_prod',
                'name'             => 'Allianz-Stein',
                'tree'             => 'production',
                'description'      => 'Steigert die Steinproduktion aller Mitglieder.',
                'bonus_label'      => '+{n}% Steinproduktion',
                'bonus_per_level'  => 3.0,
            ],
            'ally_gold_prod' => [
                'code'             => 'ally_gold_prod',
                'name'             => 'Allianz-Gold',
                'tree'             => 'production',
                'description'      => 'Steigert die Goldproduktion aller Mitglieder.',
                'bonus_label'      => '+{n}% Goldproduktion',
                'bonus_per_level'  => 2.0,
            ],

            // Special tree
            'ally_march_limit' => [
                'code'             => 'ally_march_limit',
                'name'             => 'Marsch-Kapazität',
                'tree'             => 'special',
                'description'      => 'Erhöht das Marsch-Limit aller Mitglieder (alle 5 Level +1 Slot, max +2).',
                'bonus_label'      => '+{n} Marsch-Slot(s)',
                'bonus_per_level'  => 0.2, // +1 per 5 levels
            ],
        ];
    }

    /**
     * Returns current research levels for an alliance.
     *
     * @return array<string, array{code: string, level: int, in_queue: bool, finishes_at: string|null}>
     */
    public static function getState(int $allianceId): array
    {
        $db = Connection::getInstance();

        // Load current levels
        $rows = $db->query(
            'SELECT research_code, level FROM alliance_research WHERE alliance_id = ?',
            [$allianceId],
        )->fetchAll();

        $levels = [];
        foreach ($rows as $row) {
            $levels[$row['research_code']] = (int) $row['level'];
        }

        // Check active queue
        $queue = $db->query(
            'SELECT research_code, level_to, finishes_at
             FROM alliance_research_queue
             WHERE alliance_id = ? AND is_processed = 0
             ORDER BY started_at ASC LIMIT 1',
            [$allianceId],
        )->fetch();

        $activeCode      = $queue !== false ? $queue['research_code'] : null;
        $activeFinishes  = $queue !== false ? $queue['finishes_at']   : null;

        $definitions = self::getData();
        $state = [];
        foreach ($definitions as $code => $def) {
            $level = $levels[$code] ?? 0;
            $state[$code] = [
                'code'        => $code,
                'name'        => $def['name'],
                'tree'        => $def['tree'],
                'description' => $def['description'],
                'bonus_label' => $def['bonus_label'],
                'level'       => $level,
                'max_level'   => self::MAX_LEVEL,
                'cost_next'   => $level < self::MAX_LEVEL ? self::cost($level + 1) : null,
                'in_queue'    => ($activeCode === $code),
                'finishes_at' => ($activeCode === $code) ? $activeFinishes : null,
            ];
        }

        return [
            'nodes'       => $state,
            'active_code' => $activeCode,
            'finishes_at' => $activeFinishes,
        ];
    }

    /**
     * Returns true when the alliance treasury has enough resources for the next level.
     */
    public static function canStart(int $allianceId, string $code): bool
    {
        $db  = Connection::getInstance();
        $def = self::getData()[$code] ?? null;
        if ($def === null) return false;

        $current = (int) ($db->query(
            'SELECT level FROM alliance_research WHERE alliance_id = ? AND research_code = ?',
            [$allianceId, $code],
        )->fetchColumn() ?: 0);

        if ($current >= self::MAX_LEVEL) return false;

        // Check no active research
        $hasQueue = (bool) $db->query(
            'SELECT id FROM alliance_research_queue WHERE alliance_id = ? AND is_processed = 0 LIMIT 1',
            [$allianceId],
        )->fetch();

        if ($hasQueue) return false;

        $cost    = self::cost($current + 1);
        $balance = TreasuryService::getBalance($allianceId);

        return ($balance['lumber'] ?? 0) >= $cost['lumber']
            && ($balance['stone']  ?? 0) >= $cost['stone']
            && ($balance['gold']   ?? 0) >= $cost['gold'];
    }

    /**
     * Starts a research. Returns the new queue entry ID.
     *
     * @throws \RuntimeException when preconditions are not met.
     */
    public static function start(int $allianceId, int $playerId, string $code): int
    {
        $db  = Connection::getInstance();
        $def = self::getData()[$code] ?? null;
        if ($def === null) {
            throw new \RuntimeException('Unbekannter Research-Code: ' . $code);
        }

        // Load current level
        $current = (int) ($db->query(
            'SELECT level FROM alliance_research WHERE alliance_id = ? AND research_code = ?',
            [$allianceId, $code],
        )->fetchColumn() ?: 0);

        if ($current >= self::MAX_LEVEL) {
            throw new \RuntimeException('Research bereits auf Maximalstufe.');
        }

        // Process any finished queue items first
        self::processTick($allianceId);

        // Check no active research
        $hasQueue = (bool) $db->query(
            'SELECT id FROM alliance_research_queue WHERE alliance_id = ? AND is_processed = 0 LIMIT 1',
            [$allianceId],
        )->fetch();

        if ($hasQueue) {
            throw new \RuntimeException('Es läuft bereits eine Allianz-Forschung.');
        }

        $levelTo = $current + 1;
        $cost    = self::cost($levelTo);

        // Deduct from treasury
        TreasuryService::spend($allianceId, 0, $cost['lumber'], $cost['stone'], $cost['gold']);

        // Calculate finish time
        $durationMinutes = self::DURATION_MINUTES_PER_LEVEL * $levelTo;

        $queueId = $db->transaction(function (Connection $db) use (
            $allianceId, $code, $levelTo, $playerId, $durationMinutes,
        ): int {
            $db->execute(
                'INSERT INTO alliance_research_queue
                    (alliance_id, research_code, level_to, started_by, finishes_at)
                 VALUES (?, ?, ?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? MINUTE))',
                [$allianceId, $code, $levelTo, $playerId, $durationMinutes],
            );
            return (int) $db->lastInsertId();
        });

        return $queueId;
    }

    /**
     * Processes finished queue entries for an alliance (or all alliances when null).
     * Called lazily on state load.
     */
    public static function processTick(?int $allianceId = null): void
    {
        $db = Connection::getInstance();

        $params = ['finishes_at' => 'UTC_TIMESTAMP()'];
        $where  = 'is_processed = 0 AND finishes_at <= UTC_TIMESTAMP()';
        if ($allianceId !== null) {
            $where = 'alliance_id = ' . (int) $allianceId . ' AND ' . $where;
        }

        try {
            $finished = $db->query(
                "SELECT id, alliance_id, research_code, level_to
                 FROM alliance_research_queue
                 WHERE {$where}
                 ORDER BY finishes_at ASC
                 LIMIT 20",
            )->fetchAll();
        } catch (\PDOException) {
            return;
        }

        foreach ($finished as $entry) {
            $db->transaction(function (Connection $db) use ($entry): void {
                $db->execute(
                    'INSERT INTO alliance_research (alliance_id, research_code, level)
                     VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE level = VALUES(level)',
                    [(int) $entry['alliance_id'], $entry['research_code'], (int) $entry['level_to']],
                );
                $db->execute(
                    'UPDATE alliance_research_queue SET is_processed = 1 WHERE id = ?',
                    [(int) $entry['id']],
                );
            });
        }
    }

    /**
     * Returns the production bonuses granted by research for a given alliance.
     * Keys: food_pct, lumber_pct, stone_pct, gold_pct
     *
     * @return array{food_pct: float, lumber_pct: float, stone_pct: float, gold_pct: float}
     */
    public static function getProductionBonuses(int $allianceId): array
    {
        $db = Connection::getInstance();

        $bonuses = ['food_pct' => 0.0, 'lumber_pct' => 0.0, 'stone_pct' => 0.0, 'gold_pct' => 0.0];

        try {
            $rows = $db->query(
                "SELECT research_code, level FROM alliance_research
                 WHERE alliance_id = ? AND research_code IN
                     ('ally_food_prod','ally_lumber_prod','ally_stone_prod','ally_gold_prod')",
                [$allianceId],
            )->fetchAll();
        } catch (\PDOException) {
            return $bonuses;
        }

        foreach ($rows as $row) {
            $level = (int) $row['level'];
            switch ($row['research_code']) {
                case 'ally_food_prod':   $bonuses['food_pct']   += $level * 3.0; break;
                case 'ally_lumber_prod': $bonuses['lumber_pct'] += $level * 3.0; break;
                case 'ally_stone_prod':  $bonuses['stone_pct']  += $level * 3.0; break;
                case 'ally_gold_prod':   $bonuses['gold_pct']   += $level * 2.0; break;
            }
        }

        return $bonuses;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Returns the cost for a given level (1-based).
     *
     * @return array{lumber: int, stone: int, gold: int}
     */
    private static function cost(int $level): array
    {
        $cost = self::COST_FACTOR * ($level ** 2);
        return ['lumber' => $cost, 'stone' => $cost, 'gold' => $cost];
    }
}
