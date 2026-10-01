<?php
declare(strict_types=1);
namespace Conquer\Admin;

use Conquer\Db\Connection;

/** Read-only alpha measurements from existing server receipts; no client event claims. */
final class AlphaPlaytestAnalytics
{
    public static function snapshot(int $world, int $days = 7, ?int $now = null): array
    {
        $db=Connection::getInstance();$now??=time();$days=max(1,min(90,$days));
        $until=gmdate('Y-m-d H:i:s',$now);$since=gmdate('Y-m-d H:i:s',$now-$days*86400);
        $activity=(bool)$db->query("SHOW TABLES LIKE 'player_activity_minutes'")->fetchColumn();
        $retention=[];
        if($activity){
            // The cohort starts at first RECORDED world activity, not account creation.
            // Include only fully observed UTC return days; today's partial day is excluded.
            foreach([1,7] as $offset){
                $cohortSince=gmdate('Y-m-d 00:00:00',$now-($days+$offset)*86400);
                $cohort=$db->query('SELECT player_id,MIN(minute_slot) AS first_seen FROM player_activity_minutes WHERE world_id=? AND minute_slot<=? GROUP BY player_id HAVING first_seen>=? AND DATE(first_seen)<DATE_SUB(DATE(?),INTERVAL '.$offset.' DAY)',[$world,$until,$cohortSince,$until])->fetchAll();
                $eligible=count($cohort);$returned=0;
                if($eligible){
                    $rows=$db->query('SELECT DISTINCT a.player_id FROM player_activity_minutes a JOIN (SELECT player_id,MIN(minute_slot) first_seen FROM player_activity_minutes WHERE world_id=? AND minute_slot<=? GROUP BY player_id) f ON f.player_id=a.player_id WHERE a.world_id=? AND f.first_seen>=? AND DATE(f.first_seen)<DATE_SUB(DATE(?),INTERVAL '.$offset.' DAY) AND a.minute_slot>=DATE_ADD(DATE(f.first_seen),INTERVAL '.$offset.' DAY) AND a.minute_slot<DATE_ADD(DATE(f.first_seen),INTERVAL '.($offset+1).' DAY)',[$world,$until,$world,$cohortSince,$until])->fetchAll();
                    $returned=count($rows);
                }
                $retention[$offset]=['eligible'=>$eligible,'returned'=>$returned,'percent'=>$eligible?round(100*$returned/$eligible,1):null];
            }
        }
        $cities=(int)$db->query('SELECT COUNT(*) FROM cities WHERE world_id=?',[$world])->fetchColumn();
        $milestones=[
            'castle'=>(int)$db->query('SELECT COUNT(*) FROM cities WHERE world_id=? AND castle_level>=2',[$world])->fetchColumn(),
            'training'=>(int)$db->query('SELECT COUNT(DISTINCT c.player_id) FROM cities c JOIN troop_queue q ON q.city_id=c.id WHERE c.world_id=? AND q.is_processed=1',[$world])->fetchColumn(),
            'hunt'=>(int)$db->query("SELECT COUNT(*) FROM cities c WHERE c.world_id=? AND (EXISTS(SELECT 1 FROM monster_kill_receipts k WHERE k.world_id=c.world_id AND k.winner_player_id=c.player_id AND k.created_at<=?) OR EXISTS(SELECT 1 FROM battle_reports r WHERE r.world_id=c.world_id AND r.attacker_id=c.player_id AND r.target_type=3 AND r.outcome='attacker_wins' AND r.created_at<=?))",[$world,$until,$until])->fetchColumn(),
            'alliance'=>(int)$db->query('SELECT COUNT(DISTINCT m.player_id) FROM alliance_members m JOIN cities c ON c.player_id=m.player_id AND c.world_id=m.world_id WHERE m.world_id=?',[$world])->fetchColumn()
        ];
        $wounded=(int)$db->query('SELECT COALESCE(SUM(h.count),0) FROM hospital_wounded h JOIN cities c ON c.id=h.city_id WHERE c.world_id=?',[$world])->fetchColumn();
        $resources=$db->query('SELECT COALESCE(AVG(food),0) food,COALESCE(AVG(lumber),0) lumber,COALESCE(AVG(stone),0) stone,COALESCE(AVG(gold),0) gold FROM cities WHERE world_id=?',[$world])->fetch();
        $territory=(bool)$db->query("SHOW TABLES LIKE 'territory_targets'")->fetchColumn();$control=[];$supporters=0;$conquests=0;
        if($territory){
            $control=$db->query("SELECT a.name,COUNT(*) communes FROM territory_targets t JOIN alliances a ON a.id=t.owner_alliance_id AND a.world_id=t.world_id WHERE t.world_id=? AND t.kind='commune' GROUP BY a.id,a.name ORDER BY communes DESC,a.id",[$world])->fetchAll();
            $supporters=(int)$db->query('SELECT COUNT(DISTINCT player_id) FROM territory_support WHERE world_id=? AND created_at BETWEEN ? AND ?',[$world,$since,$until])->fetchColumn();
            $conquests=(int)$db->query('SELECT COUNT(*) FROM territory_history WHERE world_id=? AND new_alliance_id IS NOT NULL AND occurred_at BETWEEN ? AND ?',[$world,$since,$until])->fetchColumn();
        }
        return ['world_id'=>$world,'since'=>$since,'until'=>$until,'cities'=>$cities,'milestones'=>$milestones,'activity_available'=>$activity,'retention'=>$retention,'wounded'=>$wounded,'average_resources'=>array_map('floatval',$resources),
            'territory_control'=>$control,'territory_supporters'=>$supporters,'conquests'=>$conquests];
    }
}
