<?php
declare(strict_types=1);
namespace Conquer\Game\Territory;
use Conquer\Db\Connection;
use Conquer\Game\Rally\RallyService;

/** Returning joiners remain real detached armies after their parent rally has departed or cancelled. */
final class TerritoryArmyReturn
{
    public static function dispatch(array $r,array $p,int $at): void
    {
        $db=Connection::getInstance();$home=$db->query('SELECT coord_x,coord_y FROM cities WHERE id=?',[$p['city_id']])->fetch();$host=$db->query('SELECT coord_x,coord_y FROM cities WHERE id=?',[$r['leader_city_id']])->fetch();
        $whole=max(1,TerritoryService::timestamp($p['arrival_time'])-TerritoryService::timestamp($p['joined_at']));
        $elapsed=max(1,min($whole,$at-TerritoryService::timestamp($p['joined_at'])));$fraction=$elapsed/$whole;
        $tx=(int)round($home['coord_x']+($host['coord_x']-$home['coord_x'])*$fraction);$ty=(int)round($home['coord_y']+($host['coord_y']-$home['coord_y'])*$fraction);
        $continent=TerritoryService::target((int)$r['world_id'],$r['target_territory_id'])['continent_id'];
        $db->execute('INSERT IGNORE INTO territory_army_returns(world_id,continent_id,rally_id,participant_id,player_id,city_id,troops_json,turn_x,turn_y,turns_at,returns_at)VALUES(?,?,?,?,?,?,?,?,?,?,?)',[$r['world_id'],$continent,$r['id'],$p['id'],$p['player_id'],$p['city_id'],$p['troops_json'],$tx,$ty,TerritoryService::date($at),TerritoryService::date($at+$elapsed)]);
        $db->execute("UPDATE rally_participants SET status='cancelled' WHERE id=?",[$p['id']]);
    }
    public static function tick(int $world,int $at,int $limit=100): void
    {
        $db=Connection::getInstance();foreach($db->query("SELECT * FROM territory_army_returns WHERE world_id=? AND status='returning' AND returns_at<=? ORDER BY returns_at,id LIMIT ".max(1,min(1000,$limit)).' FOR UPDATE',[$world,TerritoryService::date($at)])->fetchAll() as $r){
            RallyService::refund([['player_id'=>(int)$r['player_id'],'city_id'=>(int)$r['city_id'],'troops'=>json_decode($r['troops_json'],true)]]);
            $db->execute("UPDATE territory_army_returns SET status='returned',troops_json='{}' WHERE id=?",[$r['id']]);
            $db->execute("UPDATE rally_participants SET status='returned' WHERE id=?",[$r['participant_id']]);
        }
    }
    public static function activeMarches(int $player,int $world): array
    {
        $rows=Connection::getInstance()->query("SELECT r.*,c.coord_x,c.coord_y FROM territory_army_returns r JOIN cities c ON c.id=r.city_id WHERE r.player_id=? AND r.world_id=? AND r.status='returning'",[$player,$world])->fetchAll();
        return array_map(fn($r)=>['id'=>'territory-return:'.$r['id'],'march_type'=>'rally_join','state'=>'returning','target_type'=>2,'origin_x'=>(int)$r['coord_x'],'origin_y'=>(int)$r['coord_y'],'target_x'=>(int)$r['turn_x'],'target_y'=>(int)$r['turn_y'],'departure_time'=>TerritoryService::date(TerritoryService::timestamp($r['turns_at'])-(TerritoryService::timestamp($r['returns_at'])-TerritoryService::timestamp($r['turns_at']))),'arrival_time'=>$r['turns_at'],'return_time'=>$r['returns_at'],'troops_json'=>$r['troops_json']],$rows);
    }
}
