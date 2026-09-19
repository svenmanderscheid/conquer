<?php
declare(strict_types=1);
namespace Conquer\Game\Rewards;

use Conquer\Game\Inventory\InventoryService;
use Conquer\Game\Treasure\TreasureData;

/** Display metadata comes from the awarded code, never from a name heuristic. */
final class RewardPresentation
{
    /** Icon paths are relative to assets/art/items, matching the inventory catalog. */
    public static function item(int $code): array
    {
        $def = InventoryService::getItemDef($code);
        if ($def === null) return [];
        return [
            'item_code'=>$code,
            'name'=>$def['name_de'] ?? $def['name'],
            'label'=>$def['name_de'] ?? $def['name'],
            'icon'=>$def['icon'] ?? 'pouch.svg',
            'icon_framed'=>(bool)($def['icon_framed'] ?? false),
            'rarity'=>$def['rarity'] ?? 'normal',
            'grade'=>$def['rarity'] ?? 'normal',
        ];
    }

    public static function fragment(int $code): array
    {
        $def = TreasureData::get($code);
        if ($def === null) return [];
        return [
            'treasure_code'=>$code,
            'name'=>$def['name_de'] ?? $def['name'],
            'icon'=>$def['icon'] ?? 'fragment.svg',
            'icon_framed'=>(bool)($def['icon_framed'] ?? false),
            'rarity'=>$def['grade'] ?? 'normal',
            'grade'=>$def['grade'] ?? 'normal',
        ];
    }

    /** Add display fields to historical mail without changing combat or payout values. */
    public static function report(array $details): array
    {
        if (empty($details['item_rewards']) && !empty($details['items'])) {
            foreach ($details['items'] as $code=>$count) {
                if (is_numeric($code) && is_numeric($count) && (int)$count>0) {
                    $details['item_rewards'][]=['code'=>(int)$code,'count'=>(int)$count];
                }
            }
        }
        foreach ($details['item_rewards'] ?? [] as $i=>$reward) {
            $details['item_rewards'][$i] += self::item((int)($reward['code'] ?? $reward['item_code'] ?? 0));
        }
        return $details;
    }
}
