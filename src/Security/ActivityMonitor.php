<?php
declare(strict_types=1);
namespace Conquer\Security;

use Conquer\Db\Connection;

/** Review signals, never automatic bans. Only new committed army/economic commands count. */
final class ActivityMonitor
{
    public static function record(int $player, int $world): void
    {
        try {
            $db=Connection::getInstance();
            $now=(int)$db->query('SELECT UNIX_TIMESTAMP(UTC_TIMESTAMP())')->fetchColumn();
            $slot=intdiv($now,900)*900;
            $db->execute('INSERT INTO security_activity(player_id,world_id,slot_start,action_count) VALUES(?,?,?,1) ON DUPLICATE KEY UPDATE action_count=action_count+1',[$player,$world,$slot]);
            $rows=$db->query('SELECT slot_start,action_count FROM security_activity WHERE player_id=? AND world_id=? AND slot_start BETWEEN ? AND ? ORDER BY slot_start',[$player,$world,$slot-31*900,$slot])->fetchAll();
            $total=array_sum(array_column($rows,'action_count'));
            if(count($rows)===32 && $total>=128)self::flag($player,$world,'continuous_activity',['quarters'=>32,'commands'=>$total]);
            $hour=array_filter($rows,static fn($r)=>(int)$r['slot_start']>=$slot-2700);
            $count=array_sum(array_column($hour,'action_count'));
            if($count>=500)self::flag($player,$world,'high_command_volume',['quarters'=>4,'commands'=>$count]);
            if(random_int(1,100)===1)$db->execute('DELETE FROM security_activity WHERE slot_start<? LIMIT 500',[$slot-7*86400]);
        } catch(\Throwable $e) {
            // The command is already committed. Telemetry failure must not turn it into a failed purchase.
            try{\Conquer\Logger::getInstance()->error('SECURITY activity monitoring unavailable: '.$e->getMessage());}catch(\Throwable){}
        }
    }

    private static function flag(int $player,int $world,string $reason,array $evidence): void
    {
        $db=Connection::getInstance();
        $changed=$db->execute('INSERT INTO security_activity_flags(player_id,world_id,flag_day,reason,evidence_json) VALUES(?,?,UTC_DATE(),?,?) ON DUPLICATE KEY UPDATE evidence_json=VALUES(evidence_json),last_seen=UTC_TIMESTAMP()',[$player,$world,$reason,json_encode($evidence,JSON_THROW_ON_ERROR)]);
        if($changed===1)\Conquer\Logger::getInstance()->warn('SECURITY review_required player='.$player.' world='.$world.' reason='.$reason);
    }
}
