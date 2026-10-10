<?php
declare(strict_types=1);

namespace Conquer\Game\Alliance;

use Conquer\Game\Inventory\InventoryService;

/** One small, usable item per member; never a chest or a multiplied source gift. */
final class RallyGiftRewards
{
    private const ITEM_CODES = [
        10201001, 10201002, 10201003, // Food: 1,000 / 5,000 / 10,000
        10201008, 10201009, 10201010, // Lumber
        10201016, 10201017, 10201018, // Stone
        10201024, 10201025, 10201026, // Gold
        10201032, 10101041,           // Crystals: 10 / 50
        10203008, 10203009, 10203010, // Building: 5 / 10 / 30 minutes
        10203015, 10203016, 10203017, // Research
        10203022, 10203023, 10203024, // Training
        10203030, 10203031, 10203032, // Healing
    ];

    private function __construct() {}

    /** @return list<array{item_code:int,quantity:int}> */
    public static function pool(): array
    {
        $pool = [];
        foreach (self::ITEM_CODES as $code) {
            $item = InventoryService::getItemDef($code);
            $valid = false;
            if ($item && InventoryService::isDropEligible($code) && ($item['is_usable'] ?? true)) {
                if (($item['category'] ?? '') === 'resource_pack') {
                    $amount = (int)($item['amount'] ?? 0);
                    $valid = ($item['resource'] ?? '') === 'gems'
                        ? $amount >= 10 && $amount <= 50
                        : in_array($item['resource'] ?? '', ['food','lumber','stone','gold'], true)
                            && $amount >= 1000 && $amount <= 10000;
                } elseif (($item['category'] ?? '') === 'speedup') {
                    $seconds = (int)($item['duration_seconds'] ?? 0);
                    $valid = in_array($item['subcategory'] ?? '', ['building','research','training','healing'], true)
                        && $seconds >= 300 && $seconds <= 1800;
                }
            }
            // Catalogue changes must not silently introduce a larger reward.
            if (!$valid) throw new \LogicException('Invalid small rally gift item: '.$code);
            $pool[] = ['item_code'=>$code, 'quantity'=>1];
        }
        return $pool;
    }

    /** Equal chance per item; the result is saved once with the kill receipt. */
    public static function roll(): array
    {
        $pool = self::pool();
        return $pool[random_int(0, count($pool) - 1)];
    }
}
