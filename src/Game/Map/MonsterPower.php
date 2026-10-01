<?php
declare(strict_types=1);
namespace Conquer\Game\Map;

use Conquer\Game\City\{BuildingProgression,TroopData};

/** One T1–T5 progression curve for combat thresholds and generated monster sizes. */
final class MonsterPower
{
    private const SOLO_FACTORS=['Treasure Goblin'=>.65,'Orc'=>.75,'Skeleton'=>.82,'Golem'=>.90];
    private const REGIONAL=['Deathkar','Dämmerhorn','Frostgrimm','Sandmaul','Glutramm','Grumwald'];
    private const ENDGAME=['Green Dragon'=>.85,'Red Dragon'=>.95,'Gold Dragon'=>1.05,'Magdar'=>1.15];
    private const CASTLES=[1=>1,2=>6,3=>10,4=>13,5=>16,6=>20,7=>23,8=>26,9=>30,10=>30];

    /** Two monster levels per tier; the second asks for a larger, researched army. */
    public static function profile(array $definition): array
    {
        $name=(string)($definition['name']??'');
        $level=max(1,min(10,(int)($definition['level']??1)));
        $tier=(int)ceil($level/2);
        $castle=self::CASTLES[$level];
        $factor=(self::SOLO_FACTORS[$name]??.9)*($level%2===0?1.25:1);
        $rally=in_array($name,self::REGIONAL,true)||($definition['type']??'solo')==='rally';
        if(isset(self::ENDGAME[$name])){
            $tier=TroopData::MAX_TIER;$castle=30;$rally=true;
            $factor=self::ENDGAME[$name]*[1=>1.15,2=>1.5,3=>2.0][max(1,min(3,$level))];
        }
        $units=array_values(array_filter(TroopData::all(),static fn(array $t):bool=>(int)$t['tier']===$tier));
        $capacity=$rally?self::balanceReferenceCapacity($castle):BuildingProgression::atLevels(['castle'=>$castle])['base_march_capacity'];
        return ['tier'=>$tier,'castle'=>$castle,'capacity'=>$capacity,'factor'=>$factor,'rally'=>$rally,
            'average_attack'=>array_sum(array_column($units,'attack'))/count($units),
            'average_power'=>array_sum(array_column($units,'power'))/count($units)];
    }

    public static function required(array $definition): int
    {
        if(isset($definition['required_power']))return max(1,(int)$definition['required_power']);
        $name=(string)($definition['name']??'');
        if($name==='Ork-Späher')return 10*(int)TroopData::get(50100101)['power'];
        if($name==='Orc'&&(int)($definition['level']??1)===0)return 200;
        $profile=self::profile($definition);
        return max(1,(int)round($profile['capacity']*$profile['average_power']*$profile['factor']));
    }

    public static function currentRequired(array $definition,int $hpCurrent): int
    {
        $maximum=max(1,(int)round((float)($definition['stats']['hp']??1)*max(1,(int)($definition['amount']??1))));
        return max(1,(int)ceil(self::required($definition)*max(0,min(1,$hpCurrent/$maximum))));
    }

    /** Frozen monster balance reference, independent of player Hall capacity. */
    private static function balanceReferenceCapacity(int $hall): int
    {
        $hall=max(1,min(30,$hall));$points=[1=>20000,5=>50000,10=>100000,20=>250000,30=>500000];$previous=1;$base=20000;
        foreach($points as$at=>$cap){if($hall>=$at){$previous=$at;$base=$cap;continue;}return $base+(int)floor(($hall-$previous)*($cap-$base)/($at-$previous));}
        return $base;
    }
}
