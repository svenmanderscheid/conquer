<?php
declare(strict_types=1);
namespace Conquer\Game\World;

use Conquer\Db\Connection;

/** Settings are per world. Percentages are independent density targets, not shares. */
final class WorldSettings
{
    public static function defaults(): array
    {
        return ['enabled'=>true,'interval_minutes'=>60,'window_start'=>'00:00','window_end'=>'00:00',
            'resource_density_pct'=>1.0,'monster_density_pct'=>0.5,'resource_chance_pct'=>100.0,'monster_chance_pct'=>100.0,
            'resource_limit'=>1000,'monster_limit'=>600,'batch_limit'=>120,'resource_lifetime_hours'=>24,'monster_lifetime_hours'=>12,
            'resource_level_min'=>1,'resource_level_max'=>3,'monster_level_min'=>0,'monster_level_max'=>3,
            'alliance_center_radius'=>12,'alliance_outpost_radius'=>6,
            'resource_weights'=>['food'=>30,'lumber'=>30,'stone'=>20,'gold'=>19,'gems'=>1],
            'monster_weights'=>['Orc'=>40,'Skeleton'=>30,'Golem'=>20,'Treasure Goblin'=>5,'Deathkar'=>5,'dragon'=>0,'Magdar'=>0]];
    }

    public static function get(int $worldId): array
    {
        $row=Connection::getInstance()->query('SELECT settings_json,next_run_at,last_run_at,updated_at FROM world_spawn_settings WHERE world_id=?',[$worldId])->fetch();
        $settings=$row?json_decode($row['settings_json'],true,512,JSON_THROW_ON_ERROR):[];
        return ['settings'=>array_replace(self::defaults(),$settings),'next_run_at'=>$row['next_run_at']??null,'last_run_at'=>$row['last_run_at']??null,'updated_at'=>$row['updated_at']??null,'configured'=>(bool)$row];
    }

    public static function validate(array $input): array
    {
        $out=self::defaults();
        $out['enabled']=in_array($input['enabled']??false,[true,1,'1','on'],true);
        foreach(['interval_minutes'=>[1,10080],'resource_limit'=>[0,25000],'monster_limit'=>[0,25000],'batch_limit'=>[1,500],
            'resource_lifetime_hours'=>[1,720],'monster_lifetime_hours'=>[1,720],'resource_level_min'=>[1,10],'resource_level_max'=>[1,10],
            'monster_level_min'=>[0,20],'monster_level_max'=>[0,20],'alliance_center_radius'=>[4,40],'alliance_outpost_radius'=>[2,24]] as $key=>[$min,$max]) {
            $out[$key]=self::integer($input[$key]??$out[$key],$min,$max,$key);
        }
        foreach(['resource_density_pct','monster_density_pct','resource_chance_pct','monster_chance_pct'] as $key) {
            $v=$input[$key]??$out[$key];
            if(!is_scalar($v)||!is_numeric($v)||!is_finite((float)$v)||(float)$v<0||(float)$v>100)throw new \InvalidArgumentException('Prozentwert muss zwischen 0 und 100 liegen: '.$key);
            $out[$key]=round((float)$v,3);
        }
        foreach(['window_start','window_end'] as $key) {
            $v=$input[$key]??$out[$key];
            if(!is_string($v)||!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D',$v))throw new \InvalidArgumentException('Ungültige UTC-Uhrzeit.');
            $out[$key]=$v;
        }
        foreach(['resource','monster'] as $kind) {
            if($out[$kind.'_level_min']>$out[$kind.'_level_max'])throw new \InvalidArgumentException('Die minimale Stufe darf die maximale Stufe nicht überschreiten.');
            $key=$kind.'_weights';$weights=$input[$key]??$out[$key];
            if(!is_array($weights))throw new \InvalidArgumentException('Ungültige Verteilung.');
            foreach($out[$key] as $type=>$value)$out[$key][$type]=self::integer($weights[$type]??$value,0,100,$type);
            if(array_sum($out[$key])!==100)throw new \InvalidArgumentException('Die '.$kind.'-Verteilung muss zusammen genau 100 % ergeben.');
        }
        return $out;
    }

    public static function integer(mixed $value,int $min,int $max,string $label): int
    {
        if(!is_scalar($value)||filter_var($value,FILTER_VALIDATE_INT)===false||(int)$value<$min||(int)$value>$max)throw new \InvalidArgumentException($label.': erlaubt sind ganze Zahlen von '.$min.' bis '.$max.'.');
        return (int)$value;
    }

    public static function inWindow(array $settings,int $timestamp): bool
    {
        $time=gmdate('H:i',$timestamp);$start=$settings['window_start'];$end=$settings['window_end'];
        return $start===$end||($start<$end?($time>=$start&&$time<$end):($time>=$start||$time<$end));
    }

    public static function nextWindow(array $settings,int $timestamp): int
    {
        if(self::inWindow($settings,$timestamp))return $timestamp;
        $start=strtotime(gmdate('Y-m-d',$timestamp).' '.$settings['window_start'].':00 UTC');
        return $start>$timestamp?$start:$start+86400;
    }
}
