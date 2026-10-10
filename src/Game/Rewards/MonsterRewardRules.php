<?php
declare(strict_types=1);
namespace Conquer\Game\Rewards;

/** Canonical shipped rewards for ordinary solo monsters and Deathkar successors. */
final class MonsterRewardRules
{
    public const ALLIANCE_COIN = 10300005;
    public const ALLIANCE_BADGE = 119000002;
    public const SUPPLY_RELIC = 60300105;

    /** Every fought encounter grants personal loot, regardless of the kill outcome. */
    public static function rollEncounter(array $definition): array
    {
        $loot=[];
        $resources=$definition['resource_reward']??['food'=>100,'lumber'=>100,'stone'=>50,'gold'=>50];
        foreach(['food','lumber','stone','gold'] as $resource){
            $loot[$resource]=max(0,(int)($resources[$resource]??0));
        }
        $items=RewardCatalog::rollItems($definition['drops']??[]);
        $fragments=RewardCatalog::rollFragments($definition['fragment_drops']??[]);
        $relics=RewardCatalog::rollRelics($definition['relic_drops']??[]);
        $gems=$definition['gems_drop']??[];
        if(RewardCatalog::roll((float)($gems['chance']??0)))$loot['gems']=max(0,(int)($gems['amount']??0));

        // Keep configured chances intact. An empty roll receives the existing
        // basic resource bundle, including encounters with empty admin overrides.
        if(array_sum($loot)+array_sum($items)+array_sum($fragments)+array_sum($relics)<=0){
            $loot=['food'=>100,'lumber'=>100,'stone'=>50,'gold'=>50];
        }
        return ['loot'=>$loot,'items'=>$items,'fragments'=>$fragments,'relics'=>$relics];
    }

    /** One grey or blue relic per successful fragment roll, with equal rarity shares. */
    public static function fragments(int $level): array
    {
        $chance=round((0.20+0.60*(max(1,min(10,$level))-1)/9)/2,6);
        return array_map(static fn(string $grade):array=>[
            'fragment_grade'=>$grade,'count_min'=>1,'count'=>5,
            'probability'=>$chance,'exclusive_group'=>'monster_fragments',
        ],['normal','rare']);
    }

    public static function farmFragments(int $level): array
    {
        return [['treasure_code'=>self::SUPPLY_RELIC,'count_min'=>1,'count'=>max(1,min(4,$level)),
            'probability'=>$level>=3?0.80:0.40]];
    }

    /** Personal material quantity when the supported rally-monster reward drops. */
    public static function allianceBadgeCount(int $level): int
    {
        if($level<1||$level>10)return 0;
        return (int)ceil($level/2);
    }

    /** Add the normal default once, leaving every other drop and override policy intact. */
    public static function rallyDrops(array $definition,array $drops): array
    {
        if(($definition['type']??'solo')!=='rally')return $drops;
        $count=self::allianceBadgeCount((int)($definition['level']??0));
        if($count===0)return $drops;
        $drops=array_values(array_filter($drops,static fn(array $drop):bool=>(int)$drop['item_code']!==self::ALLIANCE_BADGE));
        $drops[]=['item_code'=>self::ALLIANCE_BADGE,'count'=>$count,'probability'=>0.40];
        $drops=array_values(array_filter($drops,static fn(array $drop):bool=>!in_array((int)$drop['item_code'],[10105001,10300001],true)));
        $drops[]=['item_code'=>10105001,'count'=>1,'probability'=>0.10];
        $drops[]=['item_code'=>10300001,'count'=>1,'probability'=>0.50];
        return $drops;
    }

    public static function apply(array $definition): array
    {
        $level=max(1,(int)($definition['level']??1));
        $type=(string)($definition['type']??'solo');
        $definition['fragment_drops']=self::fragments($level);

        if($type==='solo'){
            $drops=[
                ['item_code'=>10203022,'count'=>$level,'probability'=>1.0],
                ['item_code'=>10203030,'count'=>$level,'probability'=>1.0],
                ['item_code'=>10105001,'count'=>1,'probability'=>0.10],
            ];
            $resourceItem=match((string)($definition['name']??'')){
                'Orc'=>10201001,
                'Skeleton'=>10201008,
                'Golem'=>10201016,
                'Treasure Goblin'=>10201024,
                default=>null,
            };
            if($resourceItem!==null)array_unshift($drops,['item_code'=>$resourceItem,'count'=>5*$level,'probability'=>1.0]);
            if(($definition['name']??'')==='Treasure Goblin'){
                foreach($drops as &$drop)if($drop['item_code']===10105001)$drop['probability']=0.30;
                unset($drop);
                $drops[]=['item_code'=>10105002,'count'=>1,'probability'=>0.10];
                foreach([10201001,10201008,10201016] as $code)$drops[]=['item_code'=>$code,'count'=>$level,'probability'=>1.0];
            }
            $definition['drops']=$drops;
            $definition['resource_reward']=['food'=>0,'lumber'=>0,'stone'=>0,'gold'=>0];
            $definition['gems_drop']=['chance'=>0.25,'amount'=>10*$level];
            return $definition;
        }

        if(($definition['reward_family']??null)==='deathkar'){
            $definition['drops']=[
                ['item_code'=>10201024,'count'=>5*$level,'probability'=>1.0],
                ['item_code'=>10105001,'count'=>1,'probability'=>0.30],
                ['item_code'=>10203022,'count'=>$level,'probability'=>1.0],
                ['item_code'=>10203030,'count'=>$level,'probability'=>1.0],
                ['item_code'=>10300001,'count'=>2,'probability'=>0.35],
                ['item_code'=>self::ALLIANCE_COIN,'count'=>5,'probability'=>0.80],
                ['item_code'=>10106001,'count'=>1,'probability'=>0.25],
            ];
            $definition['resource_reward']=['food'=>0,'lumber'=>0,'stone'=>0,'gold'=>0];
            $definition['gems_drop']=['chance'=>0.30,'amount'=>20*$level];
        }

        if($type==='rally'&&self::allianceBadgeCount((int)($definition['level']??0))>0){
            $definition['drops']=self::rallyDrops($definition,$definition['drops']??[]);
            $definition['gems_drop']=['chance'=>0.30,'amount'=>20*$level];
        }
        return $definition;
    }
}
