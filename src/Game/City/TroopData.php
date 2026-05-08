<?php
declare(strict_types=1);

namespace Conquer\Game\City;

/**
 * Static helper for troop data loaded from data/troops.json.
 *
 * All public methods are static; the JSON is parsed once per request and cached.
 */
final class TroopData
{
    private function __construct() {}

    /**
     * Returns all 15 troops indexed by code.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        static $cache = null;
        if ($cache !== null) return $cache;

        $json  = (string) file_get_contents(ROOT_DIR . '/data/troops.json');
        $data  = json_decode($json, true);
        $cache = [];
        foreach ($data['troops'] as $t) {
            $cache[(int) $t['code']] = $t;
        }
        return $cache;
    }

    /**
     * Returns one troop definition or null if the code is unknown.
     *
     * @return array<string, mixed>|null
     */
    public static function get(int $code): ?array
    {
        return self::all()[$code] ?? null;
    }

    /**
     * Returns all T1 troops (the three types always available).
     *
     * @return list<array<string, mixed>>
     */
    public static function t1(): array
    {
        return array_values(array_filter(
            self::all(),
            static fn (array $t): bool => (int) $t['tier'] === 1,
        ));
    }

    /**
     * Returns the resource cost to train $count of a given troop.
     *
     * @return array{food: int, lumber: int, stone: int, gold: int}
     */
    public static function trainingCost(int $code, int $count): array
    {
        $t = self::get($code);
        if ($t === null) {
            return ['food' => 0, 'lumber' => 0, 'stone' => 0, 'gold' => 0];
        }
        return [
            'food'   => (int) $t['need_food']   * $count,
            'lumber' => (int) $t['need_lumber']  * $count,
            'stone'  => (int) $t['need_stone']   * $count,
            'gold'   => (int) $t['need_gold']    * $count,
        ];
    }

    /**
     * Returns training duration in seconds for $count troops.
     */
    public static function trainingSeconds(int $code, int $count): int
    {
        $t = self::get($code);
        if ($t === null) return 0;
        return (int) $t['time'] * $count;
    }

    /**
     * Returns whether a troop is unlocked given the current barrack + academy level.
     * Research unlock is not yet implemented (always treated as met for T1).
     */
    public static function isUnlocked(int $code, int $barrackLevel, int $academyLevel): bool
    {
        $t = self::get($code);
        if ($t === null) return false;
        return $barrackLevel >= 1 && $academyLevel >= (int) $t['unlock_academy'];
    }
}
