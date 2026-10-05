<?php
declare(strict_types=1);
namespace Conquer\Game\Rally;

use Conquer\Db\Connection;
use Conquer\Game\WorldRules;

/** Virtual reinforcements never own accounts, cities, rewards or ranking entries. */
final class RallySupport
{
    public static function policy(): array { return require dirname(__DIR__,3).'/config/rally_support.php'; }

    public static function tick(): void
    {
        $db=Connection::getInstance();
        $ids=$db->query("SELECT id FROM rallies WHERE target_kind='monster' AND status='gathering' ORDER BY id LIMIT 100")->fetchAll(\PDO::FETCH_COLUMN);
        if(!$ids)return;
        WorldRules::combatLock(function()use($db,$ids):void{
            foreach($ids as $id)$db->transaction(function()use($db,$id):void{
                $r=$db->query('SELECT * FROM rallies WHERE id=? FOR UPDATE',[$id])->fetch();
                if($r)self::refresh($r);
            });
        });
    }

    /** Called with the rally row locked; recomputes rather than accumulating units. */
    public static function refresh(array $r): array
    {
        if(($r['target_kind']??'city')!=='monster'||$r['status']!=='gathering')return $r;
        $db=Connection::getInstance();$policy=self::policy();$meta=json_decode($r['result_json']??'{}',true)?:[];
        $alliance=(int)($meta['alliance_id']??0);$world=(int)$r['world_id'];
        $clock=$db->query('SELECT UTC_TIMESTAMP() AS now')->fetchColumn();
        $old=$meta['ai_support']??[];$meta['ai_support']=[];
        $status=$db->query('SELECT status FROM worlds WHERE id=?',[$world])->fetchColumn();
        $leaderAlliance=WorldRules::alliance((int)$r['leader_player_id'],$world);
        if($policy['enabled']&&in_array($status,['open','running'],true)&&$alliance>0&&$leaderAlliance===$alliance&&strtotime($clock.' UTC')-strtotime($r['created_at'].' UTC')>=(int)$policy['wait_seconds']){
            // Sessions are touched on authenticated requests; login is a fallback.
            $active="(p.last_login>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY) OR EXISTS(SELECT 1 FROM sessions s WHERE s.player_id=p.id AND s.active_world_id=c.world_id AND s.last_active>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY)))";
            $worldActive=(int)$db->query("SELECT COUNT(DISTINCT c.player_id) FROM cities c JOIN players p ON p.id=c.player_id WHERE c.world_id=? AND $active",[$world])->fetchColumn();
            $allianceActive=(int)$db->query("SELECT COUNT(DISTINCT c.player_id) FROM cities c JOIN players p ON p.id=c.player_id JOIN alliance_members am ON am.player_id=p.id AND am.world_id=c.world_id WHERE c.world_id=? AND am.alliance_id=? AND $active",[$world,$alliance])->fetchColumn();
            if($worldActive<=(int)$policy['max_world_active']&&$allianceActive<=(int)$policy['max_alliance_active']){
                $arrived=json_decode($r['troops_json'],true)?:[];$reserved=array_sum($arrived);
                foreach($db->query("SELECT troops_json,status FROM rally_participants WHERE rally_id=? AND status IN ('joining','pending')",[$r['id']])->fetchAll() as $p){
                    $troops=json_decode($p['troops_json'],true)?:[];$reserved+=array_sum($troops);
                    if($p['status']==='pending')foreach($troops as $code=>$count)$arrived[$code]=($arrived[$code]??0)+(int)$count;
                }
                $units=min(array_sum($arrived),(int)$policy['max_units'],max(0,(int)($meta['capacity']??0)-$reserved));
                if($units>0){
                    $troops=\Conquer\Game\March\BattleEngine::splitAmount($units,$arrived);
                    $troops=array_filter($troops,static fn($count)=>$count>0);
                    $meta['ai_support']=[['player_id'=>-1,'city_id'=>0,'is_ai'=>true,'name_key'=>'rally.ai.name','username'=>'Royal Vanguard (AI)','troops'=>$troops,'buffs'=>[],'march_speed_bonus_pct'=>0]];
                }
            }
        }
        if($old!==$meta['ai_support']){
            $r['result_json']=json_encode($meta,JSON_THROW_ON_ERROR);
            $db->execute('UPDATE rallies SET result_json=? WHERE id=?',[$r['result_json'],$r['id']]);
        }
        return $r;
    }

    public static function participants(array $r): array
    {
        if(($r['target_kind']??'city')!=='monster'||!in_array($r['status'],['gathering','marching','returning'],true))return [];
        $meta=json_decode($r['result_json']??'{}',true)?:[];$rows=$meta['ai_support']??[];
        foreach($rows as &$p){$p['status']=$r['status']==='gathering'?'pending':'marching';$p['profile']=null;}unset($p);
        return $rows;
    }
}
