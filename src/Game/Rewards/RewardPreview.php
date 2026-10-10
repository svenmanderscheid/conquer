<?php
declare(strict_types=1);

namespace Conquer\Game\Rewards;

use Conquer\Db\Connection;
use Conquer\Game\Inventory\InventoryService;
use Conquer\Game\Locale;
use Conquer\Game\Treasure\TreasureData;

/** Read-only projections for the reward editor. Never rolls or credits rewards. */
final class RewardPreview
{
    /**
     * Builds the expected base payout for 100 monster victories from the same
     * effective rule that new solo attacks and rallies snapshot at dispatch.
     *
     * @return array<string, mixed>
     */
    public static function monster(string $sourceKey, int $scopeWorld = 0): array
    {
        self::assertSource('monster', $sourceKey);
        if ($scopeWorld < 0) {
            throw new \InvalidArgumentException('Ungültiger Geltungsbereich.');
        }

        $config = RewardCatalog::effective('monster', $sourceKey, $scopeWorld);
        $items = [];
        foreach ($config['drops'] ?? [] as $drop) {
            if (!is_array($drop)) {
                continue;
            }
            $code = (int)($drop['item_code'] ?? 0);
            $definition = InventoryService::getItemDef($code);
            if ($definition === null) {
                continue;
            }
            $quantity = max(0, (int)($drop['count'] ?? 0));
            $chance = self::chance($drop['probability'] ?? 0);
            $items[] = [
                'item_code' => $code,
                'name' => (string)($definition['name_de'] ?? $definition['name'] ?? ('Gegenstand '.$code)),
                'quantity_on_drop' => $quantity,
                'quantity_min' => $drop['count_min'] ?? $quantity,
                'chance' => $chance,
                'expected_per_100' => (($drop['count_min']??$quantity)+$quantity)/2 * $chance * 100,
            ];
        }

        $gemRule = is_array($config['gems_drop'] ?? null) ? $config['gems_drop'] : [];
        $fragments=[];
        foreach($config['fragment_drops']??[] as $drop){
            $definition=isset($drop['treasure_code'])?TreasureData::get((int)$drop['treasure_code']):null;
            $quantity=max(0,(int)$drop['count']);$chance=self::chance($drop['probability']);
            $fragments[]=[
                'treasure_code'=>$drop['treasure_code']??null,
                'name'=>$definition?Locale::text($definition['name_de']??$definition['name']):Locale::t('admin.drops.fragment_random_'.$drop['fragment_grade']),
                'quantity_on_drop'=>$quantity,'quantity_min'=>$drop['count_min']??$quantity,'chance'=>$chance,'expected_per_100'=>(($drop['count_min']??$quantity)+$quantity)/2*$chance*100,
            ];
        }
        $relics=[];
        foreach($config['relic_drops']??[] as $drop){
            $definition=TreasureData::get((int)$drop['treasure_code']);
            if(!$definition)continue;
            $quantity=max(0,(int)$drop['count']);$chance=self::chance($drop['probability']);
            $relics[]=[
                'treasure_code'=>(int)$drop['treasure_code'],
                'name'=>Locale::text($definition['name_de']??$definition['name']),
                'quantity_on_drop'=>$quantity,'chance'=>$chance,'expected_per_100'=>$quantity*$chance*100,
            ];
        }
        $gemChance = self::chance($gemRule['chance'] ?? 0);
        $gemAmount = max(0, (int)($gemRule['amount'] ?? 0));
        $resources = [];
        foreach (['food', 'lumber', 'stone', 'gold'] as $resource) {
            $resources[$resource] = max(0, (int)($config['resource_reward'][$resource] ?? 0));
        }

        return [
            'source_type' => 'monster',
            'source_key' => $sourceKey,
            'scope_world_id' => $scopeWorld,
            'resources_per_victory' => $resources,
            'items' => $items,
            'fragments' => $fragments,
            'relics' => $relics,
            'gems' => [
                'quantity_on_drop' => $gemAmount,
                'chance' => $gemChance,
                'expected_per_100' => $gemAmount * $gemChance * 100,
            ],
            'charm' => [
                'guaranteed_per_victory' => 1,
                'expected_per_100' => 100,
            ],
        ];
    }

    /**
     * Returns concise immutable history for exactly one source and scope.
     * Config JSON is intentionally excluded from the projection.
     *
     * @return list<array{revision:int,created_at:string,kind:string}>
     */
    public static function history(string $sourceType, string $sourceKey, int $scopeWorld = 0): array
    {
        self::assertSource($sourceType, $sourceKey);
        if ($scopeWorld < 0 || !Connection::isInitialized()) {
            if ($scopeWorld < 0) {
                throw new \InvalidArgumentException('Ungültiger Geltungsbereich.');
            }
            return [];
        }

        try {
            $rows = Connection::getInstance()->query(
                'SELECT revision,config_json,created_at
                   FROM reward_rule_revisions
                  WHERE scope_world_id=? AND source_type=? AND source_key=?
                  ORDER BY revision DESC,id DESC
                  LIMIT 20',
                [$scopeWorld, $sourceType, $sourceKey],
            )->fetchAll();
        } catch (\PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) !== 1146) {
                throw $e;
            }
            return [];
        }

        return array_map(static fn(array $row): array => [
            'revision' => (int)$row['revision'],
            'created_at' => (string)$row['created_at'],
            'kind' => $row['config_json'] === null ? 'reset' : 'adjustment',
        ], $rows);
    }

    private static function assertSource(string $sourceType, string $sourceKey): void
    {
        if (!isset(RewardCatalog::sources($sourceType)[$sourceKey])) {
            throw new \InvalidArgumentException('Diese Beutequelle wurde nicht gefunden.');
        }
    }

    private static function chance(mixed $value): float
    {
        return max(0.0, min(1.0, is_numeric($value) ? (float)$value : 0.0));
    }
}
