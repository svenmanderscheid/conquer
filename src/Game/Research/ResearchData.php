<?php
declare(strict_types=1);

namespace Conquer\Game\Research;

/**
 * Static data loader for the three research trees.
 *
 * JSON source files:
 *   data/research/battle.json
 *   data/research/production.json
 *   data/research/advanced.json
 *
 * JSON node schema (v1):
 *   code           — unique string identifier, e.g. "infantry_hp"
 *   name           — display name
 *   category       — logical grouping: "infantry"|"ranged"|"cavalry"|"general"|"production"|...
 *   stat           — the stat affected, e.g. "hp"|"atk"|"march_size"|"hospital_capacity"
 *   type           — "buff" | "unlock"
 *   max_level      — maximum level
 *   levels[]       — array of level entries, each containing:
 *     level        — level number (1-based)
 *     ability_value — cumulative stat value at this level
 *     time         — research duration in seconds
 *     power        — power contribution
 *     resources    — {food, lumber, stone, gold}
 *     requirements — [{type: "academy"|"research", level: int, code?: str}]
 *
 * The "buff key" for a node is derived as: category + "_" + stat
 * (e.g. category="infantry", stat="hp" → buff key "infantry_hp").
 * For general flat bonuses (march_size, hospital_capacity, etc.) the key
 * equals the stat name directly (category="general").
 *
 * All data is parsed once per request and held in static caches.
 * Access is O(1) by code via the flat $nodes map.
 */
final class ResearchData
{
    private function __construct() {}

    /** Flat map: research_code => node definition (merged from all trees). */
    private static ?array $nodes = null;

    /** Tree map: tree_name => [node, ...] (preserves original order). */
    private static ?array $trees = null;

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Returns the full node definition for a research code, or null if unknown.
     *
     * @return array<string, mixed>|null
     */
    public static function get(string $code): ?array
    {
        self::load();
        return self::$nodes[$code] ?? null;
    }

    /**
     * Returns all nodes for a named tree in definition order.
     *
     * @param string $treeName  'battle' | 'production' | 'advanced'
     * @return list<array<string, mixed>>
     */
    public static function tree(string $treeName): array
    {
        self::load();
        return self::$trees[$treeName] ?? [];
    }

    /**
     * Returns all nodes across all trees, indexed by code.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function allNodes(): array
    {
        self::load();
        return self::$nodes;
    }

    /**
     * Short combat stats that, when appearing in the "general" category,
     * must be prefixed with "troops_" to form their buff key.
     * Example: category=general, stat=atk → troops_atk
     */
    private const GENERAL_COMBAT_STATS = [
        'hp'      => true,
        'atk'     => true,
        'def'     => true,
        'spd'     => true,
        'storage' => true,
    ];

    /**
     * Derives the buff-map key for a node.
     *
     * Rules:
     *  - category "unlock"  → no buff key (returns empty string)
     *  - category "general" with a short combat stat (hp/atk/def/spd/storage)
     *                       → "troops_" . stat
     *  - category "general" with a full stat name (march_size, hospital_capacity, etc.)
     *                       → stat  (already fully qualified)
     *  - category "production", "counter", "castle_defense", "composed", "rally"
     *                       → stat  (already fully qualified)
     *  - all other categories (infantry, ranged, cavalry, …)
     *                       → category . "_" . stat
     *
     * @return string  Empty string when the node produces no numeric buff.
     */
    public static function buffKey(array $node): string
    {
        $category = (string) ($node['category'] ?? '');
        $stat     = (string) ($node['stat']     ?? '');

        if ($category === 'unlock' || $stat === '' || $category === '') {
            return '';
        }

        if ($category === 'general') {
            // Short combat stats are prefixed with "troops_".
            if (isset(self::GENERAL_COMBAT_STATS[$stat])) {
                return 'troops_' . $stat;
            }
            // Full-name stats are used as-is.
            return $stat;
        }

        if ($category === 'production' || $category === 'counter'
            || $category === 'castle_defense' || $category === 'composed' || $category === 'rally'
            || $category === 'training' || $category === 'gathering') {
            // Stat is already fully qualified for these categories.
            return $stat;
        }

        // "infantry_hp", "ranged_atk", "cavalry_def", etc.
        return $category . '_' . $stat;
    }

    /**
     * Returns the minimum academy level required for a node.
     * Derived from the requirements of level 1 (first level entry).
     */
    public static function minAcademyLevel(array $node): int
    {
        foreach ($node['levels'] as $entry) {
            foreach (($entry['requirements'] ?? []) as $req) {
                if ((string) ($req['type'] ?? '') === 'academy') {
                    return (int) $req['level'];
                }
            }
            // Only look at the first level entry.
            break;
        }
        return 1;
    }

    /**
     * Returns the minimum academy level required for a specific level upgrade.
     */
    public static function academyLevelForUpgrade(array $node, int $levelTo): int
    {
        foreach ($node['levels'] as $entry) {
            if ((int) $entry['level'] === $levelTo) {
                foreach (($entry['requirements'] ?? []) as $req) {
                    if ((string) ($req['type'] ?? '') === 'academy') {
                        return (int) $req['level'];
                    }
                }
            }
        }
        return 1;
    }

    /**
     * Returns prerequisite research codes for a node.
     * Derived from requirements of type "research" on level 1.
     *
     * @return list<string>
     */
    public static function prerequisites(array $node): array
    {
        $prereqs = [];
        foreach ($node['levels'] as $entry) {
            foreach (($entry['requirements'] ?? []) as $req) {
                if ((string) ($req['type'] ?? '') === 'research' && isset($req['code'])) {
                    $prereqs[] = (string) $req['code'];
                }
            }
            // Only check level 1 for prerequisites.
            break;
        }
        return array_unique($prereqs);
    }

    // -------------------------------------------------------------------------
    // Internal loader
    // -------------------------------------------------------------------------

    private static function load(): void
    {
        if (self::$nodes !== null) {
            return;
        }

        self::$nodes = [];
        self::$trees = [];

        $files = ['battle', 'production', 'advanced'];

        foreach ($files as $treeName) {
            $path = ROOT_DIR . '/data/research/' . $treeName . '.json';

            if (!is_file($path)) {
                continue;
            }

            $json = file_get_contents($path);
            if ($json === false) {
                continue;
            }

            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            $treeNodes = [];
            foreach ($data['nodes'] as $node) {
                // Attach the tree name so callers know which tree a node belongs to.
                $node['tree']               = $treeName;
                self::$nodes[$node['code']] = $node;
                $treeNodes[]                = $node;
            }

            self::$trees[$treeName] = $treeNodes;
        }
    }
}
