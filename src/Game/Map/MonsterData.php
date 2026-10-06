<?php
declare(strict_types=1);
namespace Conquer\Game\Map;

/** Shared monster definitions for the world UI and battle engine. */
final class MonsterData
{
    /** Full source catalogue, including event families which have no active Conquer mode. */
    public static function source(int $code, int $level): ?array
    {
        static $rows = null;
        if ($rows === null) {
            $rows = [];
            $data = json_decode((string)file_get_contents(ROOT_DIR.'/data/reference_monsters.json'), true, 512, JSON_THROW_ON_ERROR);
            foreach ($data['monsters'] as $row) $rows[$row['source_code']][$row['level']] = $row;
        }
        return $rows[$code][$level] ?? null;
    }

    /** Resolve historical spawn codes without rewards, so editors and spawners agree. */
    public static function definition(int $code): array
    {
        static $definitions = null;
        if ($definitions === null) {
            $root = dirname(__DIR__, 3);
            $data = json_decode((string) file_get_contents($root . '/data/monsters.json'), true, 512, JSON_THROW_ON_ERROR);
            $definitions = array_column($data['monsters'], null, 'code');
            $byName = [];
            foreach ($data['monsters'] as $entry) { $byName[$entry['name'] . '_' . $entry['level']] = $entry; }
            // Older world codes use one-based levels; keep their established
            // battle stats while showing the exact same definition in the UI.
            $spawn = json_decode((string) file_get_contents($root . '/data/world_spawn.json'), true, 512, JSON_THROW_ON_ERROR);
            foreach ($spawn['monsters'] as $entry) {
                $match = $byName[$entry['monster'] . '_' . $entry['level']] ?? null;
                if ($match) { $definitions[$entry['code']] = $match; }
            }
        }
        $definition=$definitions[$code] ?? ['name'=>'Unbekanntes Monster','level'=>1,'type'=>'solo','stats'=>['hp'=>100,'attack'=>50,'defense'=>30],'amount'=>10];
        $definition=\Conquer\Game\Rewards\MonsterRewardRules::apply($definition);
        $definition=\Conquer\Game\March\BossMechanics::currentDefinition($definition);
        $definition['spawn_code']=$code;
        $definition['required_power']=MonsterPower::required($definition);
        return $definition;
    }

    /** Shared world/search payload, including the complete health pool used by spawns. */
    public static function mapData(array $monster): array
    {
        $definition = self::get((int)$monster['monster_code']);
        $monster['hp_max'] = max(1, (int)$monster['hp_current'], (int)round((float)($definition['stats']['hp'] ?? 0) * (int)($definition['amount'] ?? 0)));
        $monster['required_power']=(int)$definition['required_power'];
        $monster['required_power_current']=MonsterPower::currentRequired($definition,(int)$monster['hp_current']);
        $monster['definition'] = array_intersect_key($definition, array_flip(['name','title','biome','art','footprint','level','type','stats','amount','required_power','resource_reward','drops','fragment_drops','relic_drops','gems_drop','action_point_cost','boss_mechanic']));
        return $monster;
    }

    public static function isActive(int $code): bool
    {
        $definition=self::definition($code);
        return isset($definition['code']) && !in_array($definition['name'],['Deathkar','Magdar','Green Dragon','Gold Dragon','Red Dragon'],true);
    }

    public static function get(int $code): array
    {
        $definition=self::definition($code);
        if(($definition['type']??'solo')==='rally'){
            $definition['drops']=array_map(static function(array $drop):array{
                $item=\Conquer\Game\Inventory\InventoryService::getItemDef((int)$drop['item_code']);
                return $drop+['label'=>$item['name_de']??$item['name']??'Gegenstand'];
            },\Conquer\Game\Rally\MonsterRally::drops($definition));
        }
        $definition=\Conquer\Game\Rewards\RewardCatalog::monster($definition);
        foreach ($definition['drops'] ?? [] as $i=>$drop) {
            $definition['drops'][$i]=$drop+\Conquer\Game\Rewards\RewardPresentation::item((int)$drop['item_code']);
        }
        foreach($definition['fragment_drops']??[] as $i=>$drop){
            $definition['fragment_drops'][$i]=$drop+(isset($drop['treasure_code'])
                ?\Conquer\Game\Rewards\RewardPresentation::fragment((int)$drop['treasure_code'])
                :['name'=>\Conquer\Game\Locale::t('admin.drops.fragment_random_'.$drop['fragment_grade']),'icon'=>'fragment-'.$drop['fragment_grade'].'.svg','grade'=>$drop['fragment_grade']]);
        }
        foreach($definition['relic_drops']??[] as $i=>$drop){
            $definition['relic_drops'][$i]=$drop+\Conquer\Game\Rewards\RewardPresentation::relic((int)$drop['treasure_code']);
        }
        return $definition;
    }
}
