<?php
declare(strict_types=1);
namespace Conquer\Game\Map;

use Conquer\Game\City\{BuildingProgression,TroopData};

/** Authoritative monster power thresholds derived from the troop progression. */
final class MonsterPower
{
    private const SOLO_FACTORS=['Treasure Goblin'=>.65,'Orc'=>.75,'Skeleton'=>.82,'Golem'=>.90];
    private const REGIONAL=['Deathkar','Dämmerhorn','Frostgrimm','Sandmaul','Glutramm','Grumwald'];
    private const ENDGAME=['Green Dragon'=>[.85,[8,9,10]],'Red Dragon'=>[.95,[8,9,10]],'Gold Dragon'=>[1.05,[8,9,10]],'Magdar'=>[1.15,[8,9,10]]];

    public static function required(array $definition): int
    {
        if(isset($definition['required_power']))return max(1,(int)$definition['required_power']);
        $name=(string)($definition['name']??'');$level=max(0,(int)($definition['level']??1));
        if($name==='Ork-Späher')return 40;
        if($name==='Orc'&&$level===0)return 200;
        $tier=max(1,min(10,$level));$factor=.9;$rally=false;
        if(isset(self::SOLO_FACTORS[$name]))$factor=self::SOLO_FACTORS[$name];
        elseif(in_array($name,self::REGIONAL,true))$rally=true;
        elseif(isset(self::ENDGAME[$name])){[$factor,$tiers]=self::ENDGAME[$name];$tier=$tiers[max(0,min(2,$level-1))];$rally=true;}
        elseif(($definition['type']??'solo')==='rally')$rally=true;
        $castle=self::unlockCastle($tier);
        $capacity=$rally?self::rallyCapacity($castle):BuildingProgression::atLevels(['castle'=>$castle])['base_march_capacity'];
        return max(1,(int)round($capacity*self::averageTierPower($tier)*$factor));
    }

    public static function currentRequired(array $definition,int $hpCurrent): int
    {
        $maximum=max(1,(int)round((float)($definition['stats']['hp']??1)*max(1,(int)($definition['amount']??1))));
        return max(1,(int)ceil(self::required($definition)*max(0,min(1,$hpCurrent/$maximum))));
    }

    public static function rallyCapacity(int $hall): int
    {
        $hall=max(1,min(30,$hall));$points=[1=>20000,5=>50000,10=>100000,20=>250000,30=>500000];$previous=1;$base=20000;
        foreach($points as$at=>$cap){if($hall>=$at){$previous=$at;$base=$cap;continue;}return $base+(int)floor(($hall-$previous)*($cap-$base)/($at-$previous));}
        return $base;
    }

    private static function unlockCastle(int $tier): int
    {
        $levels=[];foreach(TroopData::all()as$troop)if((int)$troop['tier']===$tier)$levels[]=(int)$troop['unlock_castle'];
        return $levels?max($levels):1;
    }

    private static function averageTierPower(int $tier): float
    {
        $values=[];foreach(TroopData::all()as$troop)if((int)$troop['tier']===$tier)$values[]=(int)$troop['power'];
        return $values?array_sum($values)/count($values):1;
    }
}
