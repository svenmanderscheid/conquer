<?php
declare(strict_types=1);
namespace Conquer\Game\Shrine;

/** Independent, deterministic UTC schedule for the four elemental shrines. */
final class ShrineEvent
{
    public static function definition(): array
    {
        static $data;
        return $data ??= json_decode(file_get_contents(dirname(__DIR__,3).'/data/shrine_event.json'),true,32,JSON_THROW_ON_ERROR);
    }

    public static function shrine(string $code): ?array
    {
        foreach(self::definition()['shrines'] as $shrine)if($shrine['code']===$code)return $shrine;
        return null;
    }

    /** starts_at/ends_at identify the current window, or the next one while inactive. */
    public static function state(?int $now=null,?array $definition=null): array
    {
        $now??=time();$definition??=self::definition();$schedule=$definition['schedule'];
        if(($schedule['timezone']??'')!=='UTC'||!is_int($schedule['iso_weekday']??null)||$schedule['iso_weekday']<1||$schedule['iso_weekday']>7
            ||!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D',$schedule['starts_at']??'')
            ||!is_int($schedule['duration_seconds']??null)||$schedule['duration_seconds']<1||$schedule['duration_seconds']>=604800)throw new \RuntimeException('Ungültiger Schrein-Ereigniskalender.');
        $date=(new \DateTimeImmutable('@'.$now))->setTimezone(new \DateTimeZone('UTC'));
        [$hours,$minutes]=array_map('intval',explode(':',$schedule['starts_at']));
        $days=((int)$date->format('N')-$schedule['iso_weekday']+7)%7;
        $start=$date->setTime($hours,$minutes)->modify('-'.$days.' days')->getTimestamp();
        if($start>$now)$start-=604800;
        $end=$start+$schedule['duration_seconds'];$active=$now>=$start&&$now<$end;
        if(!$active){$start+=604800;$end=$start+$schedule['duration_seconds'];}
        return ['name'=>$definition['name'],'active'=>$active,'starts_at'=>gmdate('Y-m-d H:i:s',$start),'ends_at'=>gmdate('Y-m-d H:i:s',$end),
            'next_starts_at'=>gmdate('Y-m-d H:i:s',$active?$start+604800:$start),'instance_id'=>'four-shrines:'.gmdate('Y-m-d\TH:i:s\Z',$start),'timezone'=>'UTC'];
    }

    /** Late processing uses the scheduled arrival, not the viewer's later login time. */
    public static function arrivalAllowed(?string $dispatchedInstance,int $arrival): bool
    {
        $event=self::state($arrival);
        return $event['active']&&$dispatchedInstance!==null&&hash_equals($event['instance_id'],$dispatchedInstance);
    }
}
