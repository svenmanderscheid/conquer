<?php
declare(strict_types=1);
namespace Conquer\Game\Rally;

use Conquer\Db\Connection;
use Conquer\Game\Research\BuffEngine;

/** Shared Hall of Alliance capacity for new rallies and their upgrade previews. */
final class RallyCapacity
{
    public static function base(int $hallLevel): int
    {
        $level=max(1,min(30,$hallLevel));
        if($level<=9)return 50000+($level-1)*25000;
        if($level<=16)return 300000+($level-10)*50000;
        if($level<30)return 600000+($level-16)*100000;
        return 2500000;
    }

    public static function describe(int $hallLevel,float $researchBonus=0): array
    {
        $level=max(1,min(30,$hallLevel));$bonus=max(0,$researchBonus);
        $base=self::base($level);$next=$level<30?$level+1:null;
        return ['hall_level'=>$level,'base'=>$base,'research_bonus'=>$bonus,
            'research_bonus_percent'=>round($bonus*100,4),'total'=>self::total($base,$bonus),
            'next_hall_level'=>$next,'next_base'=>$next===null?null:self::base($next),
            'next_total'=>$next===null?null:self::total(self::base($next),$bonus)];
    }

    /** A city identifies both the owner and the world whose research applies. */
    public static function forCity(int $playerId,int $cityId): array
    {
        $city=Connection::getInstance()->query("SELECT c.world_id,COALESCE(b.level,1) AS hall_level FROM cities c LEFT JOIN city_buildings b ON b.city_id=c.id AND b.building_code='hall_of_alliance' WHERE c.id=? AND c.player_id=?",[$cityId,$playerId])->fetch();
        if(!$city)throw new \RuntimeException('Diese Stadt gehört dir nicht.');
        $buffs=BuffEngine::getBuffs($playerId,(int)$city['world_id']);
        return self::describe((int)$city['hall_level'],(float)($buffs['rally_attack_amount']??0));
    }

    private static function total(int $base,float $bonus): int
    {
        return (int)floor($base*(1+$bonus)+1e-8);
    }
}
