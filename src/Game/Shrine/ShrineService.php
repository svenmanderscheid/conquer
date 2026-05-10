<?php
declare(strict_types=1);

namespace Conquer\Game\Shrine;

use Conquer\Db\Connection;
use Conquer\Game\City\TroopData;

/**
 * Shrine capture and garrison system.
 *
 * Shrines are world-map structures that alliances can contest and secure to earn
 * passive bonuses. Each shrine progresses through three ownership states:
 *
 *   npc       — controlled by the NPC garrison defined in NPC_GARRISON
 *   contested — an alliance attacked and won; a 1-hour hold timer is running
 *   secured   — the hold timer expired; the alliance now owns the shrine and
 *               its bonus is active
 *
 * DB tables used:
 *   shrines          — static data (coord, tier, name)
 *   shrine_captures  — one row per shrine (ownership + timers)
 *   shrine_garrisons — player garrison troops for a secured shrine
 */
final class ShrineService
{
    // ── Tier constants ────────────────────────────────────────────────────────

    public const TIER_C = 'C';
    public const TIER_B = 'B';
    public const TIER_A = 'A';
    public const TIER_S = 'S';

    /** Hold timer in seconds before a contested shrine becomes secured. */
    public const CONTEST_DURATION_SECONDS = 3600;

    /**
     * NPC garrison troops per tier.
     * Keys are troop codes (string for JSON compatibility), values are counts.
     *
     * @var array<string, array<string, int>>
     */
    public const NPC_GARRISON = [
        'C' => ['50100101' => 50_000,  '50200101' => 50_000],
        'B' => ['50100301' => 100_000, '50200301' => 100_000],
        'A' => ['50100401' => 200_000, '50200401' => 200_000],
        'S' => ['50100401' => 500_000, '50200401' => 500_000, '50300401' => 500_000],
    ];

    /**
     * Passive bonuses granted when an alliance secures a shrine.
     * A shrine grants all bonuses listed for its tier simultaneously.
     *
     * @var array<string, array<string, float>>
     */
    public const SHRINE_BONUSES = [
        'C' => ['resource_production' => 5.0],
        'B' => ['all_attack' => 10.0, 'research_speed' => 10.0],
        'A' => ['all_hp' => 20.0, 'construction_speed' => 20.0],
        'S' => ['all_attack' => 50.0, 'all_defense' => 50.0, 'all_hp' => 50.0],
    ];

    private function __construct() {}

    // ── Public read API ───────────────────────────────────────────────────────

    /**
     * Returns all shrines in a world with their current capture state.
     *
     * Each row includes:
     *   id, coord_x, coord_y, shrine_tier, name,
     *   alliance_id, alliance_tag,
     *   state ('npc'|'contested'|'secured'),
     *   contested_until, secured_at
     *
     * @return list<array<string, mixed>>
     */
    public static function getAllShrines(int $worldId): array
    {
        $db = Connection::getInstance();

        $rows = $db->query(
            "SELECT
                 s.id,
                 s.coord_x,
                 s.coord_y,
                 s.shrine_tier,
                 s.name,
                 sc.alliance_id,
                 a.tag            AS alliance_tag,
                 sc.contested_until,
                 sc.secured_at
             FROM  shrines          s
             LEFT JOIN shrine_captures sc ON sc.shrine_id = s.id
             LEFT JOIN alliances     a  ON a.id = sc.alliance_id
             WHERE  s.world_id = ?
             ORDER BY s.shrine_tier ASC, s.id ASC",
            [$worldId],
        )->fetchAll();

        foreach ($rows as &$row) {
            $row['state'] = self::deriveState($row);
        }
        unset($row);

        return $rows;
    }

    /**
     * Returns a single shrine with full detail, or null if not found.
     *
     * @return array<string, mixed>|null
     */
    public static function getShrine(int $shrineId): ?array
    {
        $db = Connection::getInstance();

        $row = $db->query(
            "SELECT
                 s.id,
                 s.world_id,
                 s.coord_x,
                 s.coord_y,
                 s.shrine_tier,
                 s.name,
                 sc.alliance_id,
                 a.name           AS alliance_name,
                 a.tag            AS alliance_tag,
                 sc.captured_at,
                 sc.contested_until,
                 sc.secured_at,
                 sc.garrison_troops_json
             FROM  shrines          s
             LEFT JOIN shrine_captures sc ON sc.shrine_id = s.id
             LEFT JOIN alliances     a  ON a.id = sc.alliance_id
             WHERE  s.id = ?",
            [$shrineId],
        )->fetch();

        if ($row === false) {
            return null;
        }

        $row['state']          = self::deriveState($row);
        $row['bonuses']        = self::SHRINE_BONUSES[$row['shrine_tier']] ?? [];
        $row['npc_garrison']   = self::NPC_GARRISON[$row['shrine_tier']]   ?? [];

        // Decode garrison JSON for convenience
        $row['garrison_troops'] = json_decode(
            (string) ($row['garrison_troops_json'] ?? '{}'),
            true,
        ) ?: [];
        unset($row['garrison_troops_json']);

        return $row;
    }

    // ── Attack / capture ──────────────────────────────────────────────────────

    /**
     * Attempts to capture a shrine on behalf of an alliance.
     *
     * Algorithm:
     *  1. If the shrine is already secured by the same alliance → early return (already owned).
     *  2. Load the current defending garrison (NPC or player garrison troops).
     *  3. Compute attacker power vs defender power (same formula as BattleEngine).
     *  4. Victory  → upsert shrine_captures with the alliance, set contested_until = +1h.
     *  5. Defeat   → calculate proportional attacker losses, return defeat result.
     *
     * @param array<int|string, int> $attackerTroops  troop_code → count
     *
     * @return array{
     *   outcome: 'already_owned'|'victory'|'defeat',
     *   losses: array<int, int>,
     *   contested_until: string|null,
     *   message: string
     * }
     */
    public static function attackShrine(
        int   $allianceId,
        int   $shrineId,
        array $attackerTroops,
    ): array {
        $db     = Connection::getInstance();
        $shrine = self::getShrine($shrineId);

        if ($shrine === null) {
            throw new \RuntimeException('Shrine #' . $shrineId . ' nicht gefunden.');
        }

        // ── Already owned by same alliance ───────────────────────────────────
        if (
            $shrine['state'] === 'secured' &&
            (int) ($shrine['alliance_id'] ?? 0) === $allianceId
        ) {
            return [
                'outcome'        => 'already_owned',
                'losses'         => [],
                'contested_until' => null,
                'message'        => 'Dieser Shrine gehört bereits deiner Allianz.',
            ];
        }

        // ── Build defender troop pool ─────────────────────────────────────────
        // Base: NPC garrison or the capture record's garrison_troops_json
        $tier           = (string) $shrine['shrine_tier'];
        $defenderTroops = (array) ($shrine['garrison_troops'] ?? []);

        if (empty($defenderTroops)) {
            // Fall back to NPC default for this tier
            $defenderTroops = self::NPC_GARRISON[$tier] ?? [];
        }

        // Add player garrison troops (alliance members defending)
        $garrisonRows = $db->query(
            'SELECT troops_json FROM shrine_garrisons WHERE shrine_id = ?',
            [$shrineId],
        )->fetchAll();

        foreach ($garrisonRows as $gRow) {
            $gTroops = json_decode((string) $gRow['troops_json'], true) ?: [];
            foreach ($gTroops as $code => $count) {
                $intCode = (int) $code;
                $defenderTroops[$intCode] = ($defenderTroops[$intCode] ?? 0) + (int) $count;
            }
        }

        // ── Power calculation ─────────────────────────────────────────────────
        $attackerPower  = self::computePower($attackerTroops);
        $defenderPower  = self::computeDefensePower($defenderTroops);

        $victory = $attackerPower >= $defenderPower;

        // ── Calculate attacker losses ─────────────────────────────────────────
        $losses = [];

        if ($victory) {
            // Win: minor losses — ratio of defender power / attacker absorption
            $attackerAbsorption = self::computeAbsorption($attackerTroops);
            $lossRatio          = min(0.5, $defenderPower / max(1.0, $attackerAbsorption));
        } else {
            // Defeat: significant losses — how badly outmatched
            $attackerAbsorption = self::computeAbsorption($attackerTroops);
            $lossRatio          = min(0.8, $defenderPower / max(1.0, $attackerAbsorption) * 0.6);
        }

        foreach ($attackerTroops as $code => $count) {
            $killed = (int) round((int) $count * $lossRatio);
            if ($killed > 0) {
                $losses[(int) $code] = $killed;
            }
        }

        // ── Persist on victory ────────────────────────────────────────────────
        $contestedUntil = null;

        if ($victory) {
            $contestedUntil = $db->transaction(function (Connection $db) use ($shrineId, $allianceId, $tier): string {
                // Determine new garrison for defenders (empty on NPC capture, keep on alliance flip)
                $newGarrisonJson = '{}';

                $db->execute(
                    "INSERT INTO shrine_captures
                         (shrine_id, alliance_id, captured_at, contested_until, secured_at, garrison_troops_json)
                     VALUES
                         (?, ?, UTC_TIMESTAMP(), DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? SECOND), NULL, ?)
                     ON DUPLICATE KEY UPDATE
                         alliance_id           = VALUES(alliance_id),
                         captured_at           = VALUES(captured_at),
                         contested_until       = VALUES(contested_until),
                         secured_at            = NULL,
                         garrison_troops_json  = VALUES(garrison_troops_json)",
                    [$shrineId, $allianceId, self::CONTEST_DURATION_SECONDS, $newGarrisonJson],
                );

                // Remove all previous garrisons — shrine changed hands
                $db->execute(
                    'DELETE FROM shrine_garrisons WHERE shrine_id = ?',
                    [$shrineId],
                );

                // Return the contested_until timestamp
                $row = $db->query(
                    'SELECT contested_until FROM shrine_captures WHERE shrine_id = ?',
                    [$shrineId],
                )->fetch();

                return (string) ($row['contested_until'] ?? '');
            });
        }

        return [
            'outcome'         => $victory ? 'victory' : 'defeat',
            'losses'          => $losses,
            'contested_until' => $contestedUntil,
            'message'         => $victory
                ? 'Shrine erfolgreich angegriffen. Halte ihn für ' . (self::CONTEST_DURATION_SECONDS / 60) . ' Minuten.'
                : 'Angriff fehlgeschlagen. Die Verteidiger waren zu stark.',
        ];
    }

    // ── Tick / automation ─────────────────────────────────────────────────────

    /**
     * Promotes contested shrines to secured when their hold timer has expired.
     *
     * Call this from a cron tick (e.g. every 60 s).
     */
    public static function checkSecured(): void
    {
        $db = Connection::getInstance();

        $db->execute(
            "UPDATE shrine_captures
             SET    secured_at = UTC_TIMESTAMP()
             WHERE  contested_until <= UTC_TIMESTAMP()
               AND  secured_at      IS NULL
               AND  alliance_id     IS NOT NULL",
            [],
        );
    }

    // ── Bonus aggregation ─────────────────────────────────────────────────────

    /**
     * Returns the summed active shrine bonuses for an alliance.
     * Only secured shrines (secured_at IS NOT NULL) count.
     *
     * Example output:
     *   ['resource_production' => 10.0, 'all_attack' => 10.0]
     *
     * @return array<string, float>
     */
    public static function getAllianceBonuses(int $allianceId): array
    {
        $db = Connection::getInstance();

        $rows = $db->query(
            "SELECT s.shrine_tier
             FROM   shrine_captures sc
             JOIN   shrines          s  ON s.id = sc.shrine_id
             WHERE  sc.alliance_id = ?
               AND  sc.secured_at IS NOT NULL",
            [$allianceId],
        )->fetchAll();

        $totals = [];

        foreach ($rows as $row) {
            $tier    = (string) $row['shrine_tier'];
            $bonuses = self::SHRINE_BONUSES[$tier] ?? [];

            foreach ($bonuses as $key => $value) {
                $totals[$key] = ($totals[$key] ?? 0.0) + $value;
            }
        }

        return $totals;
    }

    // ── Garrison management ───────────────────────────────────────────────────

    /**
     * Sends troops from a player's city to garrison a shrine.
     *
     * Requires the shrine to be secured by the player's alliance.
     * Uses INSERT ... ON DUPLICATE KEY UPDATE to replace any existing garrison
     * from the same player at the same shrine.
     *
     * @param array<int|string, int> $troops  troop_code → count
     */
    public static function sendGarrison(
        int   $playerId,
        int   $cityId,
        int   $shrineId,
        array $troops,
    ): bool {
        $db = Connection::getInstance();

        // ── Verify shrine is secured by the player's alliance ─────────────────
        $allianceRow = $db->query(
            'SELECT alliance_id FROM alliance_members WHERE player_id = ?',
            [$playerId],
        )->fetch();

        if ($allianceRow === false) {
            throw new \RuntimeException('Du bist in keiner Allianz.');
        }

        $allianceId = (int) $allianceRow['alliance_id'];

        $capture = $db->query(
            "SELECT alliance_id, secured_at
             FROM   shrine_captures
             WHERE  shrine_id = ?",
            [$shrineId],
        )->fetch();

        if (
            $capture === false ||
            (int) $capture['alliance_id'] !== $allianceId ||
            $capture['secured_at'] === null
        ) {
            throw new \RuntimeException('Dieser Shrine ist nicht von deiner Allianz gesichert.');
        }

        // ── Validate & clean troops ───────────────────────────────────────────
        $cleanTroops = self::cleanTroops($troops);

        if (empty($cleanTroops)) {
            throw new \RuntimeException('Mindestens eine Truppeneinheit muss ausgewählt werden.');
        }

        // ── Deduct from city & upsert garrison ────────────────────────────────
        $db->transaction(function (Connection $db) use ($playerId, $cityId, $shrineId, $cleanTroops): void {
            // Verify availability
            foreach ($cleanTroops as $code => $count) {
                $row = $db->query(
                    'SELECT count FROM city_troops WHERE city_id = ? AND troop_code = ?',
                    [$cityId, $code],
                )->fetch();

                $available = ($row !== false) ? (int) $row['count'] : 0;

                if ($available < $count) {
                    $def  = TroopData::get($code);
                    $name = $def['name'] ?? ('Code ' . $code);
                    throw new \RuntimeException(
                        'Nicht genug ' . $name . '. Vorhanden: ' . $available . ', benötigt: ' . $count . '.'
                    );
                }
            }

            // Deduct troops from city
            foreach ($cleanTroops as $code => $count) {
                $db->execute(
                    'UPDATE city_troops SET count = count - ? WHERE city_id = ? AND troop_code = ?',
                    [$count, $cityId, $code],
                );
            }

            // Upsert garrison record
            $db->execute(
                "INSERT INTO shrine_garrisons
                     (shrine_id, player_id, city_id, troops_json, sent_at)
                 VALUES
                     (?, ?, ?, ?, UTC_TIMESTAMP())
                 ON DUPLICATE KEY UPDATE
                     city_id     = VALUES(city_id),
                     troops_json = VALUES(troops_json),
                     sent_at     = VALUES(sent_at)",
                [$shrineId, $playerId, $cityId, json_encode($cleanTroops)],
            );
        });

        return true;
    }

    /**
     * Recalls a player's garrison from a shrine, returning troops to their city.
     */
    public static function recallGarrison(int $playerId, int $shrineId): bool
    {
        $db = Connection::getInstance();

        $garrison = $db->query(
            'SELECT id, city_id, troops_json FROM shrine_garrisons WHERE shrine_id = ? AND player_id = ?',
            [$shrineId, $playerId],
        )->fetch();

        if ($garrison === false) {
            throw new \RuntimeException('Keine Garnison an diesem Shrine gefunden.');
        }

        $cityId = (int) $garrison['city_id'];
        $troops = json_decode((string) $garrison['troops_json'], true) ?: [];

        $db->transaction(function (Connection $db) use ($playerId, $shrineId, $cityId, $troops): void {
            // Return troops to city
            foreach ($troops as $code => $count) {
                $intCode = (int) $code;
                $intCount = (int) $count;

                if ($intCount <= 0) {
                    continue;
                }

                $db->execute(
                    "INSERT INTO city_troops (city_id, troop_code, count)
                     VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE count = count + VALUES(count)",
                    [$cityId, $intCode, $intCount],
                );
            }

            // Remove garrison record
            $db->execute(
                'DELETE FROM shrine_garrisons WHERE shrine_id = ? AND player_id = ?',
                [$shrineId, $playerId],
            );
        });

        return true;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Derives the display state string from a shrine_captures row.
     *
     * @param array<string, mixed> $row
     */
    private static function deriveState(array $row): string
    {
        if (($row['alliance_id'] ?? null) === null) {
            return 'npc';
        }

        if (($row['secured_at'] ?? null) !== null) {
            return 'secured';
        }

        return 'contested';
    }

    /**
     * Computes total attack damage power for a troop set.
     *
     * @param array<int|string, int> $troops
     */
    private static function computePower(array $troops): float
    {
        $power = 0.0;

        foreach ($troops as $code => $count) {
            $def = TroopData::get((int) $code);
            if ($def === null || (int) $count <= 0) {
                continue;
            }
            $power += (float) $def['attack'] * (int) $count;
        }

        return max(1.0, $power);
    }

    /**
     * Computes total defensive staying power (HP + defense) for a troop set.
     *
     * @param array<int|string, int> $troops
     */
    private static function computeDefensePower(array $troops): float
    {
        $power = 0.0;

        foreach ($troops as $code => $count) {
            $def = TroopData::get((int) $code);
            if ($def === null || (int) $count <= 0) {
                continue;
            }
            $power += ((float) $def['hp'] + (float) $def['defense']) * (int) $count;
        }

        return max(1.0, $power);
    }

    /**
     * Computes total absorption (HP + defense) for loss ratio calculations.
     *
     * @param array<int|string, int> $troops
     */
    private static function computeAbsorption(array $troops): float
    {
        return self::computeDefensePower($troops);
    }

    /**
     * Cleans and casts a raw troop input array.
     *
     * @param array<int|string, int> $troops
     * @return array<int, int>
     */
    private static function cleanTroops(array $troops): array
    {
        $clean = [];

        foreach ($troops as $code => $count) {
            $intCode  = (int) $code;
            $intCount = (int) $count;

            if ($intCount <= 0) {
                continue;
            }

            $clean[$intCode] = $intCount;
        }

        return $clean;
    }
}
