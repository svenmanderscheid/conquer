<?php
declare(strict_types=1);
namespace Conquer\Game\Territory;

/** Versioned operational defaults; snapshots make already-started campaigns immutable. */
final class TerritoryRules
{
    public static function defaults(): array
    {
        return ['version'=>1,'canton_limit'=>2,'active_cantons'=>[],
            'pvp_window_start_hour_utc'=>17,'pvp_window_hours'=>4,
            'crown_anchor'=>'2026-09-28 17:00:00','crown_period_days'=>14,'crown_duration_hours'=>4,
            'crown_objectives'=>['gate','arsenal','throne'],'crown_required_objectives'=>3,
            'crown_tie_rule'=>'control_seconds_desc,first_control_at_asc,alliance_id_asc',
            'crown_eligibility'=>'snapshot_at_campaign_start; canton loss does not cancel admitted attacks',
            'canton_eligibility'=>'snapshot_at_campaign_start_with_reserved_slot',
            'npc_troops'=>['commune'=>120,'canton'=>800,'crown'=>1600],
            'income_per_hour'=>1200,'conquest_reward_gold'=>500,'support_cost'=>1000,
            'special_daily_limit'=>5,'rune_daily_charges'=>3,'rune_radius'=>16,
            'office_daily_uses'=>1,'office_resource_grant'=>1000,'office_acceleration_seconds'=>300,
            'regional_supply_percent'=>5,'regional_daily_cap'=>2000,'canton_mission_contributors'=>3,'canton_mission_reward'=>500];
    }

    public static function majority(int $total): int { return intdiv($total,2)+1; }

    /** Always UTC and based on the event time, never the next login time. */
    public static function window(array $rules,int $at,bool $crown=false): array
    {
        if($crown){
            $anchor=strtotime($rules['crown_anchor'].' UTC');$period=max(1,(int)$rules['crown_period_days'])*86400;
            $start=$anchor+(int)floor(($at-$anchor)/$period)*$period;$end=$start+(int)($rules['crown_duration_hours']*3600);
            if($at>=$end){$start+=$period;$end=$start+(int)($rules['crown_duration_hours']*3600);}
        }else{
            $start=strtotime(gmdate('Y-m-d',$at).' 00:00:00 UTC')+(int)$rules['pvp_window_start_hour_utc']*3600;
            $end=$start+(int)($rules['pvp_window_hours']*3600);
            if($at<$start&&$at<$end-86400){$start-=86400;$end-=86400;}
            if($at>=$end){$start+=86400;$end+=86400;}
        }
        return ['starts_at'=>gmdate('Y-m-d\TH:i:s\Z',$start),'ends_at'=>gmdate('Y-m-d\TH:i:s\Z',$end),'start'=>$start,'end'=>$end,'open'=>$at>=$start&&$at<$end];
    }

    /** Explicit reviewed catalog, copied into each world's immutable target metadata. */
    public static function benefit(string $id,string $kind): string
    {
        if($kind!=='commune')return $kind==='canton'?'regional_supply':'crown';
        static $catalog=null;$catalog??=json_decode((string)file_get_contents(dirname(__DIR__,3).'/data/territory_benefits.json'),true,32,JSON_THROW_ON_ERROR)['communes'];
        $type=$catalog[$id]['benefit_type']??null;
        if(!in_array($type,['food','lumber','stone','gold','abbey','rune'],true))throw new \LogicException('Gemeinde fehlt im Gebietsvorteil-Katalog: '.$id);
        return $type;
    }

    public static function income(int $seconds,int $hourly,int $remainder=0): array
    {
        $units=max(0,$seconds)*max(0,$hourly)+$remainder;
        return ['amount'=>intdiv($units,3600),'remainder'=>$units%3600];
    }
}
