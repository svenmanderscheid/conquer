<?php
declare(strict_types=1);

namespace Conquer\Game\Treasure;

/**
 * Loads and caches data/treasures.json.
 *
 * All data access goes through this class — never read the JSON file directly.
 */
final class TreasureData
{
    /** @var array<int, array<string, mixed>>|null Indexed by treasure code. */
    private static ?array $cache = null;

    /** @var array<string, list<int>>|null Grade → list of codes, for random picks. */
    private static ?array $byGrade = null;

    // Static-only helper — no instantiation.
    private function __construct() {}

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Returns all treasures, indexed by code (int).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        if (self::$cache === null) {
            self::load();
        }

        /** @var array<int, array<string, mixed>> */
        return self::$cache;
    }

    /**
     * Returns a single treasure by its numeric code, or null if not found.
     *
     * @return array<string, mixed>|null
     */
    public static function get(int $code): ?array
    {
        $all = self::all();
        return $all[$code] ?? null;
    }

    /**
     * Calculates the effective stat value for a treasure at the given level.
     *
     * Formula: base_value + per_level * (level - 1)
     * Returns 0.0 when the stat type is not present on this treasure.
     *
     * @param array<string, mixed> $treasure  The treasure definition array from TreasureData::get().
     * @param int                  $level     Current treasure level (1–10).
     * @param string               $statType  Stat identifier, e.g. "infantry_attack".
     */
    public static function getStatValue(array $treasure, int $level, string $statType): float
    {
        $level = max(1, min(10, $level));

        /** @var list<array<string, mixed>> $stats */
        $stats = $treasure['stats'] ?? [];

        foreach ($stats as $stat) {
            if ((string) ($stat['type'] ?? '') === $statType) {
                $base     = (float) ($stat['base_value'] ?? 0.0);
                $perLevel = (float) ($stat['per_level']  ?? 0.0);
                return $base + $perLevel * ($level - 1);
            }
        }

        return 0.0;
    }

    /**
     * Returns the number of equip slots unlocked at the given Treasure House level.
     *
     * Thresholds (SPEC §Treasure House):
     *   L1  → 2 slots
     *   L5  → 3 slots
     *   L10 → 4 slots
     *   L20 → 5 slots
     *   L25 → 6 slots
     */
    public static function getUnlockSlots(int $treasureHouseLevel): int
    {
        return match (true) {
            $treasureHouseLevel >= 25 => 6,
            $treasureHouseLevel >= 20 => 5,
            $treasureHouseLevel >= 10 => 4,
            $treasureHouseLevel >= 5  => 3,
            default                   => 2,
        };
    }

    /**
     * Returns a list of treasure codes for a given grade.
     *
     * @return list<int>
     */
    public static function getCodesByGrade(string $grade): array
    {
        if (self::$byGrade === null) {
            self::buildGradeIndex();
        }

        /** @var list<int> */
        return self::$byGrade[$grade] ?? [];
    }

    // -------------------------------------------------------------------------
    // Internal
    // -------------------------------------------------------------------------

    private static function load(): void
    {
        $file = ROOT_DIR . '/data/treasures.json';

        if (!is_file($file)) {
            throw new \RuntimeException('treasures.json not found at: ' . $file);
        }

        $raw = file_get_contents($file);
        if ($raw === false) {
            throw new \RuntimeException('Cannot read treasures.json');
        }

        /** @var array{version: int, treasures: list<array<string, mixed>>}|null $decoded */
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($decoded) || !isset($decoded['treasures'])) {
            throw new \RuntimeException('treasures.json has unexpected structure.');
        }

        self::$cache = [];
        foreach ($decoded['treasures'] as $treasure) {
            $code = (int) ($treasure['code'] ?? 0);
            if ($code > 0) {
                self::$cache[$code] = $treasure;
            }
        }
    }

    private static function buildGradeIndex(): void
    {
        self::$byGrade = [];
        foreach (self::all() as $code => $treasure) {
            $grade = (string) ($treasure['grade'] ?? 'normal');
            self::$byGrade[$grade][] = $code;
        }
    }
}
