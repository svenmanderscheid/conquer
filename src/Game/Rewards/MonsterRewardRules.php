<?php
declare(strict_types=1);
namespace Conquer\Game\Rewards;

/** Canonical shipped rewards for ordinary solo monsters and Deathkar successors. */
final class MonsterRewardRules
{
    public const ALLIANCE_COIN = 10300005;

    public static function apply(array $definition): array
    {
        $level=max(1,(int)($definition['level']??1));
        $type=(string)($definition['type']??'solo');

        if($type==='solo'){
            $drops=[
                ['item_code'=>10203022,'count'=>$level,'probability'=>1.0],
                ['item_code'=>10203030,'count'=>$level,'probability'=>1.0],
                ['item_code'=>10105001,'count'=>1,'probability'=>0.30],
            ];
            $resourceItem=match((string)($definition['name']??'')){
                'Orc'=>10201001,
                'Skeleton'=>10201008,
                'Golem'=>10201016,
                default=>null,
            };
            if($resourceItem!==null)array_unshift($drops,['item_code'=>$resourceItem,'count'=>5*$level,'probability'=>1.0]);
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
            $definition['gems_drop']=['chance'=>0.25,'amount'=>20*$level];
        }

        return $definition;
    }
}
