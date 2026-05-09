<?php
declare(strict_types=1);

namespace Conquer\Game\Trading;

use Conquer\Db\Connection;

/**
 * Caravan — rotating marketplace that refreshes 3× daily (00:00, 08:00, 16:00 UTC).
 *
 * Uses lazy refresh: the caravan is re-rolled on the first API call after
 * next_refresh_at has passed. No cron required for MVP.
 */
final class CaravanService
{
    // Static-only helper — no instantiation.
    private function __construct() {}

    // -------------------------------------------------------------------------
    // Item pool (hardcoded for MVP)
    // -------------------------------------------------------------------------

    /**
     * @var list<array<string, mixed>>
     */
    private const POOL = [
        ['id' => 'speedup_5min',   'label' => '5min Speedup',        'cat' => 'speedups',       'gems' => 10,  'food' => 50000,  'lumber' => 0,      'stone' => 0,      'gold' => 0],
        ['id' => 'speedup_30min',  'label' => '30min Speedup',        'cat' => 'speedups',       'gems' => 40,  'food' => 200000, 'lumber' => 0,      'stone' => 0,      'gold' => 0],
        ['id' => 'speedup_1h',     'label' => '1h Speedup',           'cat' => 'speedups',       'gems' => 80,  'food' => 400000, 'lumber' => 0,      'stone' => 0,      'gold' => 0],
        ['id' => 'speedup_3h',     'label' => '3h Speedup',           'cat' => 'speedups',       'gems' => 200, 'food' => 0,      'lumber' => 0,      'stone' => 0,      'gold' => 0],
        ['id' => 'food_100k',      'label' => '100k Nahrung',         'cat' => 'resource_packs', 'gems' => 20,  'food' => 0,      'lumber' => 0,      'stone' => 0,      'gold' => 0, 'gives_food'   => 100000],
        ['id' => 'lumber_100k',    'label' => '100k Holz',            'cat' => 'resource_packs', 'gems' => 20,  'food' => 0,      'lumber' => 0,      'stone' => 0,      'gold' => 0, 'gives_lumber' => 100000],
        ['id' => 'stone_100k',     'label' => '100k Stein',           'cat' => 'resource_packs', 'gems' => 20,  'food' => 0,      'lumber' => 0,      'stone' => 0,      'gold' => 0, 'gives_stone'  => 100000],
        ['id' => 'gold_50k',       'label' => '50k Gold',             'cat' => 'resource_packs', 'gems' => 30,  'food' => 0,      'lumber' => 0,      'stone' => 0,      'gold' => 0, 'gives_gold'   => 50000],
        ['id' => 'food_500k',      'label' => '500k Nahrung',         'cat' => 'resource_packs', 'gems' => 80,  'food' => 0,      'lumber' => 0,      'stone' => 0,      'gold' => 0, 'gives_food'   => 500000],
        ['id' => 'lumber_500k',    'label' => '500k Holz',            'cat' => 'resource_packs', 'gems' => 80,  'food' => 0,      'lumber' => 0,      'stone' => 0,      'gold' => 0, 'gives_lumber' => 500000],
        ['id' => 'stone_500k',     'label' => '500k Stein',           'cat' => 'resource_packs', 'gems' => 80,  'food' => 0,      'lumber' => 0,      'stone' => 0,      'gold' => 0, 'gives_stone'  => 500000],
        ['id' => 'boost_prod_1h',  'label' => '1h Produktionsboost',  'cat' => 'boosts',         'gems' => 50,  'food' => 150000, 'lumber' => 0,      'stone' => 0,      'gold' => 0],
        ['id' => 'boost_build_1h', 'label' => '1h Bauzeitboost',      'cat' => 'boosts',         'gems' => 60,  'food' => 0,      'lumber' => 150000, 'stone' => 0,      'gold' => 0],
        ['id' => 'boost_res_1h',   'label' => '1h Forschungsboost',   'cat' => 'boosts',         'gems' => 60,  'food' => 0,      'lumber' => 0,      'stone' => 150000, 'gold' => 0],
        ['id' => 'heal_potion_10', 'label' => '10x Heiltrank',        'cat' => 'specials',       'gems' => 100, 'food' => 200000, 'lumber' => 0,      'stone' => 0,      'gold' => 0],
    ];

    /** Discount values in percent (negative = reduction). */
    private const DISCOUNTS = [-30, -40, -50, -60, -70, -80, -90];

    /**
     * Weights for each discount tier. Must sum to 100.
     * Index corresponds 1:1 with DISCOUNTS.
     */
    private const DISC_WEIGHTS = [10, 15, 30, 15, 15, 10, 5];

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Returns the next caravan refresh time — the next occurrence of
     * 00:00, 08:00 or 16:00 UTC after the current moment.
     */
    public static function nextRefreshTime(): \DateTimeImmutable
    {
        $now       = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $todayDate = $now->format('Y-m-d');

        // Check the three daily refresh windows in order.
        foreach (['00:00:00', '08:00:00', '16:00:00'] as $time) {
            $candidate = new \DateTimeImmutable($todayDate . ' ' . $time, new \DateTimeZone('UTC'));
            if ($candidate > $now) {
                return $candidate;
            }
        }

        // All three have passed today — next is 00:00 UTC tomorrow.
        return new \DateTimeImmutable(
            $now->modify('+1 day')->format('Y-m-d') . ' 00:00:00',
            new \DateTimeZone('UTC'),
        );
    }

    /**
     * Returns the number of caravan slots for the given Trading Post level.
     *
     * Formula: max(5, min(25, 4 + $level))
     * Results: Lv 1-4 → 5, Lv 5 → 9, … Lv 21+ → 25.
     */
    public static function slotCount(int $tradingPostLevel): int
    {
        return max(5, min(25, 4 + $tradingPostLevel));
    }

    /**
     * Loads the player's caravan state, rolling a fresh caravan when stale.
     *
     * @return array{slots: list<array<string, mixed>>, next_refresh_at: string, refreshed_at: string}
     */
    public static function load(int $playerId, int $tradingPostLevel, int $worldId = 1): array
    {
        $db  = Connection::getInstance();
        $now = gmdate('Y-m-d H:i:s');

        $row = $db->query(
            'SELECT slots_json, next_refresh_at, refreshed_at
             FROM   caravan_state
             WHERE  player_id = ? AND world_id = ?',
            [$playerId, $worldId],
        )->fetch();

        // Refresh when: no row exists, or the refresh window has passed.
        $needsRefresh = ($row === false) || ($row['next_refresh_at'] <= $now);

        if ($needsRefresh) {
            return self::roll($db, $playerId, $worldId, $tradingPostLevel);
        }

        /** @var list<array<string, mixed>> $slots */
        $slots = json_decode((string) $row['slots_json'], true, 512, JSON_THROW_ON_ERROR);

        return [
            'slots'           => $slots,
            'next_refresh_at' => $row['next_refresh_at'],
            'refreshed_at'    => $row['refreshed_at'],
        ];
    }

    /**
     * Purchases a slot by index.
     *
     * Deducts the required resources from the player's city and marks the slot
     * as bought. On success returns ['ok' => true, 'slot' => [...]].
     *
     * @param  array<string, mixed> $cityResources  city row (food, lumber, stone, gold); passed by reference so caller can inspect updated values.
     * @throws \RuntimeException When the slot is invalid, already bought, or resources are insufficient.
     */
    public static function buy(int $playerId, int $slotIdx, array &$cityResources, int $worldId = 1): array
    {
        $db  = Connection::getInstance();
        $now = gmdate('Y-m-d H:i:s');

        $row = $db->query(
            'SELECT slots_json, next_refresh_at
             FROM   caravan_state
             WHERE  player_id = ? AND world_id = ?',
            [$playerId, $worldId],
        )->fetch();

        if ($row === false) {
            throw new \RuntimeException('Kein aktiver Caravan. Bitte die Seite neu laden.');
        }

        // Prevent purchases against a stale caravan.
        if ($row['next_refresh_at'] <= $now) {
            throw new \RuntimeException('Der Caravan wurde erneuert. Bitte die Seite neu laden.');
        }

        /** @var list<array<string, mixed>> $slots */
        $slots = json_decode((string) $row['slots_json'], true, 512, JSON_THROW_ON_ERROR);

        if (!isset($slots[$slotIdx])) {
            throw new \RuntimeException('Ungültiger Slot-Index.');
        }

        $slot = $slots[$slotIdx];

        if ((bool) $slot['bought']) {
            throw new \RuntimeException('Dieser Artikel wurde bereits gekauft.');
        }

        $currency = (string) $slot['currency'];
        $price    = (int)   $slot['price'];

        // --- resource check & deduction ---
        if ($currency === 'gems') {
            // Gems are on the players table.
            $playerRow = $db->query(
                'SELECT gems FROM players WHERE id = ?',
                [$playerId],
            )->fetch();

            if ($playerRow === false || (int) $playerRow['gems'] < $price) {
                throw new \RuntimeException(
                    'Nicht genug Gems. Benötigt: ' . $price . ', vorhanden: ' . (int) ($playerRow['gems'] ?? 0) . '.',
                );
            }

            $db->execute(
                'UPDATE players SET gems = gems - ? WHERE id = ? AND gems >= ?',
                [$price, $playerId, $price],
            );
        } else {
            // Resource currency (food / lumber / stone / gold).
            $allowed = ['food', 'lumber', 'stone', 'gold'];
            if (!in_array($currency, $allowed, true)) {
                throw new \RuntimeException('Unbekannte Währung: ' . $currency . '.');
            }

            $have = (int) ($cityResources[$currency] ?? 0);
            if ($have < $price) {
                throw new \RuntimeException(
                    'Nicht genug ' . $currency . '. Benötigt: ' . number_format($price) .
                    ', vorhanden: ' . number_format($have) . '.',
                );
            }

            // Safe deduction: the AND clause prevents going negative even under race conditions.
            $affected = $db->execute(
                'UPDATE cities SET ' . $currency . ' = ' . $currency . ' - ?
                 WHERE  player_id = ? AND world_id = ? AND ' . $currency . ' >= ?',
                [$price, $playerId, $worldId, $price],
            );

            if ($affected === 0) {
                throw new \RuntimeException('Nicht genug Ressourcen (Konflikt). Bitte neu laden.');
            }

            $cityResources[$currency] = $have - $price;
        }

        // --- credit gives_* resources ---
        foreach (['food', 'lumber', 'stone', 'gold'] as $res) {
            $givesKey = 'gives_' . $res;
            $givesAmt = (int) ($slot[$givesKey] ?? 0);
            if ($givesAmt > 0) {
                $db->execute(
                    'UPDATE cities SET ' . $res . ' = ' . $res . ' + ?
                     WHERE  player_id = ? AND world_id = ?',
                    [$givesAmt, $playerId, $worldId],
                );
                $cityResources[$res] = (int) ($cityResources[$res] ?? 0) + $givesAmt;
            }
        }

        // --- mark slot as bought and persist ---
        $slots[$slotIdx]['bought'] = true;

        $db->execute(
            'UPDATE caravan_state SET slots_json = ?
             WHERE  player_id = ? AND world_id = ?',
            [json_encode($slots, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $playerId, $worldId],
        );

        return ['ok' => true, 'slot' => $slots[$slotIdx]];
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Rolls a fresh caravan, persists it, and returns the state array.
     *
     * @return array{slots: list<array<string, mixed>>, next_refresh_at: string, refreshed_at: string}
     */
    private static function roll(Connection $db, int $playerId, int $worldId, int $tradingPostLevel): array
    {
        $count          = self::slotCount($tradingPostLevel);
        $nextRefresh    = self::nextRefreshTime();
        $nextRefreshStr = $nextRefresh->format('Y-m-d H:i:s');
        $nowStr         = gmdate('Y-m-d H:i:s');

        $pool   = self::POOL;
        $slots  = [];

        // Shuffle pool so we can pop items without repetition.
        shuffle($pool);

        for ($i = 0; $i < $count; $i++) {
            // Cycle through pool if count > pool size.
            $item     = $pool[$i % count($pool)];
            $discount = self::rollDiscount();

            // Determine currency (50 % gems, 50 % resource).
            $currency = self::rollCurrency($item);

            // Calculate discounted price.
            $basePriceGems = (int) $item['gems'];
            $baseResource  = self::baseResourcePrice($item, $currency);
            $basePrice     = ($currency === 'gems') ? $basePriceGems : $baseResource;
            $price         = max(1, (int) round($basePrice * (1 + $discount / 100)));

            $slot = [
                'idx'      => $i,
                'item_id'  => $item['id'],
                'label'    => $item['label'],
                'category' => $item['cat'],
                'currency' => $currency,
                'price'    => $price,
                'discount' => $discount,
                'bought'   => false,
            ];

            // Forward any gives_* keys so the buy handler can credit them.
            foreach (['gives_food', 'gives_lumber', 'gives_stone', 'gives_gold'] as $gk) {
                if (isset($item[$gk])) {
                    $slot[$gk] = (int) $item[$gk];
                }
            }

            $slots[] = $slot;
        }

        $slotsJson = json_encode($slots, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $db->execute(
            'INSERT INTO caravan_state (player_id, world_id, refreshed_at, next_refresh_at, slots_json)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                 refreshed_at    = VALUES(refreshed_at),
                 next_refresh_at = VALUES(next_refresh_at),
                 slots_json      = VALUES(slots_json)',
            [$playerId, $worldId, $nowStr, $nextRefreshStr, $slotsJson],
        );

        return [
            'slots'           => $slots,
            'next_refresh_at' => $nextRefreshStr,
            'refreshed_at'    => $nowStr,
        ];
    }

    /**
     * Returns a randomly weighted discount from DISCOUNTS/DISC_WEIGHTS.
     */
    private static function rollDiscount(): int
    {
        $roll    = random_int(1, 100);
        $cumSum  = 0;

        foreach (self::DISC_WEIGHTS as $i => $weight) {
            $cumSum += $weight;
            if ($roll <= $cumSum) {
                return self::DISCOUNTS[$i];
            }
        }

        // Fallback (should not be reached if weights sum to 100).
        return self::DISCOUNTS[count(self::DISCOUNTS) - 1];
    }

    /**
     * Decides currency: 50 % gems, 50 % best available resource currency.
     * If no resource price is defined for the item, falls back to gems.
     *
     * @param array<string, mixed> $item
     */
    private static function rollCurrency(array $item): string
    {
        if (random_int(0, 1) === 0) {
            return 'gems';
        }

        // Pick the first non-zero resource price.
        foreach (['food', 'lumber', 'stone', 'gold'] as $res) {
            if ((int) ($item[$res] ?? 0) > 0) {
                return $res;
            }
        }

        // Item has no resource price — use gems.
        return 'gems';
    }

    /**
     * Returns the base resource price for the given currency from the item pool entry.
     *
     * @param array<string, mixed> $item
     */
    private static function baseResourcePrice(array $item, string $currency): int
    {
        if ($currency === 'gems') {
            return (int) $item['gems'];
        }

        return (int) ($item[$currency] ?? (int) $item['gems']);
    }
}
