<?php
declare(strict_types=1);

namespace Conquer\Game\Vip;

use Conquer\Db\Connection;
use Conquer\Game\World\WorldContext;

/**
 * VIP system — level thresholds, passive bonuses, daily login reward.
 *
 * Each player's world has its own 20-level VIP system.
 * Players earn VIP points through daily logins and item use.
 * Higher levels grant passive percentage bonuses to construction,
 * research, production, gathering, training, army capacities and action points.
 */
final class VipService
{
    private function __construct() {}

    // -------------------------------------------------------------------------
    // Level thresholds (cumulative points required)
    // Index = VIP level, value = points needed to reach that level.
    // -------------------------------------------------------------------------

    /**
     * Cumulative VIP points required to reach each level.
     * Index = VIP level (1-20), value = points needed.
     * VIP 1 starts at zero; VIP 20 requires 12,000,000 points.
     *
     * @var array<int, int>
     */
    private const THRESHOLDS = [
        1 => 0, 200, 500, 1_000, 5_000, 10_000, 20_000, 50_000, 100_000,
        150_000, 200_000, 250_000, 500_000, 1_000_000, 1_500_000,
        2_000_000, 3_000_000, 4_000_000, 8_000_000, 12_000_000,
    ];

    /**
     * Approved VIP table, indexed by level 1-20 in each row.
     * Percentages are percentage points; capacity, AP and queues are flat counts.
     *
     * @var array<string, list<int>>
     */
    private const BONUSES = [
        'rally_troop_capacity' => [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,5,10,10,15,20],
        'troop_training_cost' => [0,0,0,0,0,0,0,0,-5,-5,-5,-10,-10,-10,-15,-15,-20,-20,-25,-25],
        'troop_training_speed' => [0,0,0,0,0,0,0,0,5,5,5,10,10,10,15,15,20,20,25,25],
        'troop_training_amount' => [0,0,0,0,0,0,0,0,5,5,5,10,10,10,15,15,20,20,25,25],
        'action_points' => [0,0,0,0,0,0,0,10,10,10,20,20,20,30,30,40,50,60,70,80],
        'mortality_reduction' => [0,0,0,0,0,0,5,5,5,10,10,10,15,15,15,20,20,25,25,30],
        'troop_limit' => [0,0,0,0,0,5,5,5,10,10,10,15,15,15,20,20,25,25,30,30],
        'marching_troop_capacity' => [0,0,0,0,0,5000,5000,5000,10000,10000,10000,15000,15000,15000,20000,25000,30000,35000,40000,45000],
        'troop_dispatch_queue' => [0,0,0,0,0,1,1,1,1,1,1,1,1,1,1,1,1,2,2,2],
        'additional_building_queue' => [0,0,0,0,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1],
        'action_point_regeneration' => [0,0,0,3,6,9,12,15,18,21,25,30,35,40,45,50,55,60,65,70],
        'research_speed' => [0,0,3,6,9,12,15,18,21,25,30,35,40,45,50,55,60,70,80,100],
        'construction_speed' => [0,0,3,6,9,12,15,18,21,25,30,35,40,45,50,55,60,70,80,100],
        'gathering_speed' => [0,6,9,12,15,18,21,24,27,30,35,40,45,50,55,60,65,70,80,100],
        'resource_production' => [3,3,9,12,15,18,21,24,27,30,35,40,45,50,55,60,65,70,80,100],
    ];

    private const MAX_LEVEL = 20;
    public const DAILY_POINTS = 10;

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Calculates VIP level for a given cumulative point total.
     * Returns 1–20, including zero-point starting profiles.
     */
    public static function levelForPoints(int $points): int
    {
        $level = 1;

        foreach (self::THRESHOLDS as $index => $threshold) {
            if ($points >= $threshold) {
                $level = $index;
            } else {
                break;
            }
        }

        // Check if player has exceeded the last threshold (VIP 20)
        return min($level, self::MAX_LEVEL);
    }

    /**
     * Returns the bonus array for a given VIP level.
     *
     * @return array<string, int>
     */
    public static function bonuses(int $level): array
    {
        $level  = max(0, min(self::MAX_LEVEL, $level));
        $result = [];
        foreach (self::BONUSES as $key => $values) $result[$key] = $level > 0 ? $values[$level - 1] : 0;
        $result['hunter_points'] = self::hunterPoints($level);
        return $result;
    }

    public static function buildingSlots(int $level): int
    {
        return 1 + self::bonuses($level)['additional_building_queue'];
    }

    /** Total extra spendable Hunter points at this VIP level, not a per-level grant. */
    public static function hunterPoints(int $level): int
    {
        return max(0, min(self::MAX_LEVEL, $level) - 1);
    }

    /** Create only the starting state for an existing world profile; never copy account points. */
    public static function ensure(int $playerId, ?int $worldId = null): void
    {
        $worldId ??= WorldContext::id();
        Connection::getInstance()->execute(
            'INSERT IGNORE INTO player_world_vip(player_id,world_id,vip_points,vip_level)
             SELECT player_id,world_id,0,1 FROM cities WHERE player_id=? AND world_id=?',
            [$playerId,$worldId],
        );
    }

    /** Returns this world's full status; a player without a village receives zero perks. */
    public static function status(int $playerId, ?int $worldId = null): array
    {
        $db = Connection::getInstance();
        $worldId ??= WorldContext::id();
        $row = $db->query(
            'SELECT vip_points, vip_level, last_vip_login FROM player_world_vip WHERE player_id=? AND world_id=?',
            [$playerId,$worldId],
        )->fetch();

        if($row===false){
            self::ensure($playerId,$worldId);
            $row=$db->query('SELECT vip_points, vip_level, last_vip_login FROM player_world_vip WHERE player_id=? AND world_id=?',[$playerId,$worldId])->fetch();
        }

        $hasProfile = $row !== false;
        if (!$hasProfile) $row=['vip_points'=>0,'vip_level'=>0,'last_vip_login'=>null];

        $points = max(0, (int) $row['vip_points']);
        $level  = $hasProfile ? self::levelForPoints($points) : 0;

        // Points to next level: threshold for level+1, or 0 if already at max
        $nextLevelPoints = ($level < self::MAX_LEVEL)
            ? (self::THRESHOLDS[$level + 1] ?? 0)
            : 0;

        return [
            'world_id'          => $worldId,
            'level'             => $level,
            'points'            => $points,
            'next_level_points' => $nextLevelPoints,
            'bonuses'           => self::bonuses($level),
            'passive_bonuses'    => self::bonuses($level),
            'level_points'      => self::THRESHOLDS[$level] ?? 0,
            'points_remaining'  => max(0, $nextLevelPoints - $points),
            'progress_points'   => $points - (self::THRESHOLDS[$level] ?? 0),
            'progress_required' => $level < self::MAX_LEVEL ? $nextLevelPoints - (self::THRESHOLDS[$level] ?? 0) : 0,
            'max_level'         => self::MAX_LEVEL,
            'is_max'            => $level === self::MAX_LEVEL,
            'next_bonuses'      => $level < self::MAX_LEVEL ? self::bonuses($level + 1) : null,
            'building_slots'    => self::buildingSlots($level),
            'daily_points'      => self::DAILY_POINTS,
            'daily_claimed'     => $row['last_vip_login'] === gmdate('Y-m-d'),
            'daily_resets_at'   => gmdate('Y-m-d\T00:00:00\Z', strtotime('tomorrow UTC')),
            'levels'            => self::levels(),
        ];
    }

    public static function levels(): array
    {
        $levels=[];
        foreach(self::THRESHOLDS as $level=>$points) {
            $levels[]=['level'=>$level,'points'=>$points,'bonuses'=>self::bonuses($level),'building_slots'=>self::buildingSlots($level)];
        }
        return $levels;
    }

    /**
     * Awards +10 VIP points for daily login. Maximum once per calendar day (UTC).
     *
     * Returns true if points were awarded, false if already claimed today.
     */
    public static function dailyLogin(int $playerId, ?int $worldId = null): bool
    {
        $db = Connection::getInstance();
        $worldId ??= WorldContext::id();
        $claim=static function()use($db,$playerId,$worldId):bool{
            WorldContext::city($playerId,$worldId,true);
            self::ensure($playerId,$worldId);
            $row=$db->query('SELECT vip_points,last_vip_login FROM player_world_vip WHERE player_id=? AND world_id=? FOR UPDATE',[$playerId,$worldId])->fetch();
            if(!$row||($row['last_vip_login']!==null&&$row['last_vip_login']>=gmdate('Y-m-d')))return false;
            self::addPoints($playerId,self::DAILY_POINTS,$worldId);
            $db->execute('UPDATE player_world_vip SET last_vip_login=? WHERE player_id=? AND world_id=?',[gmdate('Y-m-d'),$playerId,$worldId]);
            return true;
        };
        return $db->getPdo()->inTransaction()?$claim():$db->transaction($claim);
    }

    public static function claimDaily(int $playerId): array
    {
        if(!self::dailyLogin($playerId))throw new \DomainException(\Conquer\Game\Locale::t('vip.already_claimed'));
        return ['message'=>\Conquer\Game\Locale::t('vip.daily_received',['points'=>self::DAILY_POINTS]),'points'=>self::DAILY_POINTS];
    }

    /**
     * Adds arbitrary VIP points to a player (e.g. from item use).
     * Recomputes and persists the level.
     */
    public static function addPoints(int $playerId, int $points, ?int $worldId = null): void
    {
        if ($points > 0) self::updatePoints($playerId,$points,$worldId,true);
    }

    /** Administrative correction of this world's total; the level follows its thresholds. */
    public static function setPoints(int $playerId, int $points, ?int $worldId = null): void
    {
        if($points<0||$points>2147483647)throw new \InvalidArgumentException('Invalid VIP points.');
        self::updatePoints($playerId,$points,$worldId,false);
    }

    private static function updatePoints(int $playerId, int $points, ?int $worldId, bool $relative): void
    {

        $db = Connection::getInstance();

        $worldId ??= WorldContext::id();
        $credit=static function()use($db,$playerId,$points,$worldId,$relative):void{
            WorldContext::city($playerId,$worldId,true);
            self::ensure($playerId,$worldId);
            $row=$db->query('SELECT vip_points FROM player_world_vip WHERE player_id=? AND world_id=? FOR UPDATE',[$playerId,$worldId])->fetch();
            if(!$row)return;
            $oldPoints=max(0,(int)$row['vip_points']);
            // Keep earned totals within the signed INT storage limit.
            $newPoints=$relative?$oldPoints+min($points,2147483647-$oldPoints):$points;
            $newLevel=self::levelForPoints($newPoints);
            $levelChanged=$newLevel!==self::levelForPoints($oldPoints);
            if($levelChanged) {
                if($worldId===WorldContext::id()) \Conquer\Game\Player\ActionPoints::get($playerId);
                // Settle only this world's production with its old perks.
                foreach($db->query('SELECT * FROM cities WHERE player_id=? AND world_id=?',[$playerId,$worldId])->fetchAll() as $city) {
                    $buildings=[];
                    foreach($db->query('SELECT building_code,level FROM city_buildings WHERE city_id=?',[$city['id']])->fetchAll() as $building)$buildings[$building['building_code']]=['level'=>(int)$building['level']];
                    \Conquer\Game\City\ResourceTick::persist($city,$buildings);
                }
            }
            $db->execute('UPDATE player_world_vip SET vip_points=?,vip_level=? WHERE player_id=? AND world_id=?',[$newPoints,$newLevel,$playerId,$worldId]);
            if($levelChanged && $worldId===WorldContext::id()) \Conquer\Game\Player\ActionPoints::get($playerId);
        };
        if($db->getPdo()->inTransaction())$credit();else $db->transaction($credit);
    }
}
