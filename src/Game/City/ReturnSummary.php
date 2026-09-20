<?php
declare(strict_types=1);
namespace Conquer\Game\City;

use Conquer\Db\Connection;

/** Confirmed completions only; cancelled queues and stationed troops are excluded. */
final class ReturnSummary
{
    public static function since(array $city, int $since, ?int $until = null): array
    {
        $until ??= time();
        $since = max($until - 7 * 86400, min($since, $until));
        $db = Connection::getInstance();
        $range = [gmdate('Y-m-d H:i:s', $since), gmdate('Y-m-d H:i:s', $until)];
        $cityRange = [(int)$city['id'], ...$range];
        $buildings = $db->query('SELECT building_code, MAX(level_to) AS level, COUNT(*) AS count FROM building_queue WHERE city_id=? AND is_processed=1 AND finishes_at>? AND finishes_at<=? GROUP BY building_code', $cityRange)->fetchAll();
        $training = (int)$db->query('SELECT COALESCE(SUM(count),0) FROM troop_queue WHERE city_id=? AND is_processed=1 AND finishes_at>? AND finishes_at<=?', $cityRange)->fetchColumn();
        $research = (int)$db->query('SELECT COUNT(*) FROM research_queue WHERE player_id=? AND world_id=? AND is_processed=1 AND finishes_at>? AND finishes_at<=?', [(int)$city['player_id'], (int)$city['world_id'], ...$range])->fetchColumn();
        $marches = (int)$db->query("SELECT COUNT(*) FROM marches WHERE origin_city_id=? AND player_id=? AND world_id=? AND state='complete' AND return_time>? AND return_time<=? AND (haul_json IS NULL OR JSON_EXTRACT(haul_json,'$.garrisoned') IS NULL)", [(int)$city['id'], (int)$city['player_id'], (int)$city['world_id'], ...$range])->fetchColumn();
        $rallies = (int)$db->query("SELECT COUNT(*) FROM rallies r WHERE r.world_id=? AND r.status='complete' AND r.return_time>? AND r.return_time<=? AND ((r.leader_player_id=? AND r.leader_city_id=?) OR EXISTS(SELECT 1 FROM rally_participants p WHERE p.rally_id=r.id AND p.player_id=? AND p.city_id=? AND p.status='returned'))", [(int)$city['world_id'], ...$range, (int)$city['player_id'], (int)$city['id'], (int)$city['player_id'], (int)$city['id']])->fetchColumn();
        return ['since'=>$since, 'until'=>$until, 'buildings'=>$buildings, 'trained'=>$training, 'research'=>$research, 'marches'=>$marches+$rallies];
    }
}
