<?php
declare(strict_types=1);

namespace Conquer\Game\March;

use Conquer\Game\World\WorldContext;

use Conquer\Db\Connection;
use Conquer\Game\City\TroopData;
use Conquer\Game\Player\ActionPoints;

/**
 * Validates and dispatches a troop march (SPEC §8).
 *
 * March types:
 *   5 = MARCH_MONSTER        — attack a field monster
 *   6 = MARCH_CHARM          — collect a map charm
 *   7 = MARCH_ATTACK_PLAYER  — attack another player's city
 *   8 = MARCH_SCOUT          — scout another player's city (no troops needed)
 *
 * March slots: max 3 (base, no research unlock yet).
 * March cap: 50 000 troops per march.
 */
final class MarchDispatcher
{
    private const MAX_SLOTS           = 3;
    private const MAX_CAP             = MarchArmy::MAX_CAP;
    private const MARCH_MONSTER       = 5;
    private const MARCH_CHARM         = 6;
    private const MARCH_ATTACK_PLAYER = 7;
    private const MARCH_SCOUT         = 8;
    public const  MARCH_GATHER        = 9;
    public const  MARCH_SUPPORT       = 10;

    private function __construct() {}

    /**
     * Throws RuntimeException('MARCH_SLOT_FULL') when the player has no free march slots.
     * Called internally and from GatherService which shares the same slot cap.
     *
     * @throws \RuntimeException
     */
    public static function assertSlotAvailable(int $playerId,?int $worldId=null,bool $gather=false): void
    {
        $db = Connection::getInstance();

        $worldId??=WorldContext::id();
        $active = (int) $db->query(
            "SELECT COUNT(*) FROM marches
             WHERE player_id = ? AND world_id=? AND state IN ('marching','resolving','returning','arrived')",
            [$playerId,$worldId],
        )->fetchColumn();

        $active += (int)$db->query("SELECT COUNT(*) FROM rallies r WHERE r.world_id=? AND r.status IN ('gathering','marching','returning') AND (r.leader_player_id=? OR EXISTS(SELECT 1 FROM rally_participants rp WHERE rp.rally_id=r.id AND rp.player_id=? AND rp.status IN ('pending','marching')))",[$worldId,$playerId,$playerId])->fetchColumn();

        $limits = \Conquer\Game\Research\ResearchEffects::limits(\Conquer\Game\Research\BuffEngine::getBuffs($playerId,$worldId));
        $gathering=(int)$db->query("SELECT COUNT(*) FROM marches WHERE player_id=? AND world_id=? AND march_type=9 AND state IN ('marching','resolving','returning','arrived')",[$playerId,$worldId])->fetchColumn();
        if ($active >= $limits['march_slots']+$limits['gather_march_slots'] || (!$gather && $active-$gathering >= $limits['march_slots'])) {
            throw new \RuntimeException('MARCH_SLOT_FULL');
        }
    }

    /**
     * Dispatch a monster attack march.
     *
     * @param array<int,int> $selectedTroops  troop_code → count to send
     *
     * @throws \RuntimeException on validation failure
     */
    public static function dispatchMonster(
        int   $playerId,
        int   $cityId,
        int   $originX,
        int   $originY,
        int   $targetX,
        int   $targetY,
        array $selectedTroops,
        ?string $requestId = null,
    ): int {
        if (empty($selectedTroops)) {
            throw new \RuntimeException('Keine Truppen ausgewählt.');
        }

        $db = Connection::getInstance();

        $origin = \Conquer\Game\WorldRules::origin($playerId,$cityId);
        WorldContext::assertActionAvailable((int)$origin['world_id']);
        if (!$origin) { throw new \RuntimeException('Ungültige Stadt.'); }
        $worldId = (int)$origin['world_id'];
        $originX = (int) $origin['coord_x'];
        $originY = (int) $origin['coord_y'];
        $buffs = \Conquer\Game\Research\BuffEngine::getBuffs($playerId,$worldId);
        $cleanTroops = MarchArmy::clean($selectedTroops, \Conquer\Game\Research\ResearchEffects::limits($buffs)['march_capacity']);
        $requestId=self::requestId($requestId);
        $payloadHash=self::payloadHash(['kind'=>'monster','city_id'=>$cityId,'target_x'=>$targetX,'target_y'=>$targetY,'troops'=>$cleanTroops]);
        if($requestId!==null&&($existing=self::existingRequest($db,$playerId,$worldId,$requestId,$payloadHash,self::MARCH_MONSTER))!==null)return $existing;
        if(class_exists(\Conquer\Game\World\LandAccessPolicy::class))\Conquer\Game\World\LandAccessPolicy::assertTargetOpen($worldId,$targetX,$targetY);

        // ── Verify troops available in city ──────────────────────────────────
        $availableRows = $db->query(
            'SELECT troop_code, count FROM city_troops WHERE city_id = ?',
            [$cityId],
        )->fetchAll();

        $available = [];
        foreach ($availableRows as $r) {
            $available[(int) $r['troop_code']] = (int) $r['count'];
        }

        foreach ($cleanTroops as $code => $count) {
            if (($available[$code] ?? 0) < $count) {
                $def  = TroopData::get($code);
                $name = $def['name'] ?? ('Code ' . $code);
                throw new \RuntimeException(
                    'Nicht genug ' . $name . '. Vorhanden: ' . ($available[$code] ?? 0) . ', benötigt: ' . $count . '.'
                );
            }
        }

        // ── Validate target monster ──────────────────────────────────────────
        $monster = $db->query(
            'SELECT * FROM field_monsters WHERE world_id = ? AND coord_x = ? AND coord_y = ?',
            [$worldId,$targetX, $targetY],
        )->fetch();

        if ($monster === false) {
            throw new \RuntimeException('Kein Monster auf diesem Tile (' . $targetX . ',' . $targetY . ').');
        }

        $definition=\Conquer\Game\Map\MonsterData::get((int)$monster['monster_code']);
        if(!\Conquer\Game\Map\MonsterData::isActive((int)$monster['monster_code']))throw new \RuntimeException('Dieses Monster ist derzeit nicht aktiv.');
        if(($definition['type']??'solo')==='rally'||($monster['monster_type']??'solo')==='rally')throw new \RuntimeException('Dieses Monster erfordert eine Allianz-Rally.');
        if(!empty($monster['expires_at'])&&strtotime($monster['expires_at'].' UTC')<=time())throw new \RuntimeException('Dieses Monster ist bereits verschwunden.');
        if ((int) $monster['hp_current'] <= 0) {
            throw new \RuntimeException('Das Monster ist bereits besiegt.');
        }
        $actionPointCost=max(0,(int)($definition['action_point_cost']??ActionPoints::costForMonster((string)($definition['name']??''))));

        // ── Calculate march duration (SPEC §8.3) ─────────────────────────────
        $distance  = sqrt(($targetX - $originX) ** 2 + ($targetY - $originY) ** 2);
        $minSpeed  = PHP_INT_MAX;

        foreach ($cleanTroops as $code => $_) {
            $speed = MarchSpeed::monster((int)$code,$buffs);
            if ($speed < $minSpeed) $minSpeed = $speed;
        }

        $marchSecs   = max(5, (int) floor($distance * 100 / $minSpeed));
        $monsterId   = (int) $monster['id'];
        $troopsJson  = json_encode($cleanTroops);
        $snapshotJson=json_encode(['world_id'=>$worldId,'field_monster_id'=>$monsterId,'coord_x'=>$targetX,'coord_y'=>$targetY,'monster_code'=>(int)$monster['monster_code'],'effective_monster_level'=>isset($monster['effective_monster_level'])?(int)$monster['effective_monster_level']:null,'regional_level_at_spawn'=>isset($monster['regional_level_at_spawn'])?(int)$monster['regional_level_at_spawn']:null,'spawn_rule_revision'=>isset($monster['spawn_rule_revision'])?(int)$monster['spawn_rule_revision']:null,'definition'=>$definition,'captured_at'=>gmdate('Y-m-d H:i:s')],JSON_THROW_ON_ERROR);

        // ── Transaction: deduct troops + insert march ─────────────────────────
        $dispatchLock=self::acquirePlayerLock($db,$playerId);
        try{$skinSnapshot=MarchSkinService::dispatchSnapshot($playerId);
        $marchSecs=max(5,(int)floor($distance*100/($minSpeed*MarchSkinService::speedMultiplier($skinSnapshot))));
        $marchId=$db->transaction(function () use (
            $db, $cityId, $playerId, $targetX, $targetY,
            $monsterId, $troopsJson, $marchSecs, $cleanTroops,$worldId,$snapshotJson,$requestId,$payloadHash,$skinSnapshot,$actionPointCost,
        ): int {
            if($requestId!==null&&($existing=self::existingRequest($db,$playerId,$worldId,$requestId,$payloadHash,self::MARCH_MONSTER))!==null)return $existing;
            self::assertSlotAvailable($playerId,$worldId);
            $current=$db->query('SELECT coord_x,coord_y,hp_current FROM field_monsters WHERE id=? FOR UPDATE',[$monsterId])->fetch();
            if(!$current||(int)$current['coord_x']!==$targetX||(int)$current['coord_y']!==$targetY||(int)$current['hp_current']<=0)throw new \RuntimeException('Das Monster ist nicht mehr auf diesem Feld. Wähle das Ziel auf der Karte erneut.');
            ActionPoints::deduct($playerId,$actionPointCost);
            MarchArmy::reserve($db, $cityId, $cleanTroops);

            // Insert march.
            $db->execute(
                'INSERT INTO marches
                    (player_id, world_id, march_type, march_skin, march_speed_bonus_pct, origin_city_id,
                     target_x, target_y, target_type, target_id,
                     encounter_snapshot_json, request_id, request_payload_hash,
                     troops_json, departure_time, arrival_time, state)
                 VALUES
                    (:pid, :world, :type, :march_skin, :march_bonus, :city,
                     :tx, :ty, 3, :mid, :snapshot, :request_id, :request_hash,
                     :troops,
                     UTC_TIMESTAMP(),
                     DATE_ADD(UTC_TIMESTAMP(), INTERVAL :dur SECOND),
                     "marching")',
                [
                    ':pid'    => $playerId,
                    ':world'  => $worldId,
                    ':type'   => self::MARCH_MONSTER,
                    ':march_skin'=>$skinSnapshot['march_skin'],
                    ':march_bonus'=>$skinSnapshot['bonus_pct'],
                    ':city'   => $cityId,
                    ':tx'     => $targetX,
                    ':ty'     => $targetY,
                    ':mid'    => $monsterId,
                    ':snapshot'=>$snapshotJson,
                    ':request_id'=>$requestId,
                    ':request_hash'=>$requestId===null?null:$payloadHash,
                    ':troops' => $troopsJson,
                    ':dur'    => $marchSecs,
                ],
            );

            return (int) $db->lastInsertId();
        });
        }finally{self::releaseDispatchLock($db,$dispatchLock);}

        return $marchId;
    }

    /**
     * Dispatch a charm-collection march (march_type = 6).
     * Requires at least 1 troop. Speed = slowest troop.
     *
     * @throws \RuntimeException on validation failure
     */
    public static function dispatchCharm(
        int   $playerId,
        int   $cityId,
        int   $originX,
        int   $originY,
        int   $targetX,
        int   $targetY,
        int   $charmId,
        array $selectedTroops,
        ?string $requestId = null,
    ): int {
        $origin=\Conquer\Game\WorldRules::origin($playerId,$cityId);WorldContext::assertActionAvailable((int)$origin['world_id']);
        $worldId=(int)$origin['world_id'];
        $originX=(int)$origin['coord_x'];$originY=(int)$origin['coord_y'];
        if (empty($selectedTroops)) {
            throw new \RuntimeException('Mindestens 1 Truppe muss zum Einsammeln mitgeschickt werden.');
        }

        $db = Connection::getInstance();

        $buffs = \Conquer\Game\Research\BuffEngine::getBuffs($playerId, $worldId);
        $cleanTroops = MarchArmy::clean($selectedTroops, \Conquer\Game\Research\ResearchEffects::limits($buffs)['march_capacity']);
        $requestId=self::requestId($requestId);
        $payloadHash=self::payloadHash(['kind'=>'charm','city_id'=>$cityId,'charm_id'=>$charmId,'target_x'=>$targetX,'target_y'=>$targetY,'troops'=>$cleanTroops]);
        if($requestId!==null&&($existing=self::existingRequest($db,$playerId,$worldId,$requestId,$payloadHash,self::MARCH_CHARM))!==null)return $existing;
        if(class_exists(\Conquer\Game\World\LandAccessPolicy::class))\Conquer\Game\World\LandAccessPolicy::assertTargetOpen($worldId,$targetX,$targetY);

        // Verify available
        $availableRows = $db->query(
            'SELECT troop_code, count FROM city_troops WHERE city_id = ?',
            [$cityId],
        )->fetchAll();
        $available = [];
        foreach ($availableRows as $r) $available[(int)$r['troop_code']] = (int)$r['count'];

        foreach ($cleanTroops as $code => $count) {
            if (($available[$code] ?? 0) < $count) {
                $name = TroopData::get($code)['name'] ?? ('Code ' . $code);
                throw new \RuntimeException('Nicht genug ' . $name . '.');
            }
        }

        // Validate charm exists and is collectible
        $charm = $db->query(
            'SELECT id FROM map_charms WHERE id = ? AND world_id = ? AND coord_x=? AND coord_y=? AND collected_by IS NULL AND expires_at > UTC_TIMESTAMP()',
            [$charmId,$worldId,$targetX,$targetY],
        )->fetch();
        if ($charm === false) {
            throw new \RuntimeException('Charm nicht mehr verfügbar (abgelaufen oder bereits eingesammelt).');
        }

        // March speed = slowest troop
        $minSpeed = PHP_INT_MAX;
        foreach ($cleanTroops as $code => $_) {
            $speed = MarchSpeed::charm((int)$code);
            if ($speed < $minSpeed) $minSpeed = $speed;
        }
        $distance  = sqrt(($targetX - $originX) ** 2 + ($targetY - $originY) ** 2);
        $marchSecs = max(5, (int) floor($distance * 100 / $minSpeed));

        $marchId    = 0;
        $troopsJson = json_encode($cleanTroops);

        $dispatchLock=self::acquirePlayerLock($db,$playerId);
        try{$skinSnapshot=MarchSkinService::dispatchSnapshot($playerId);
        $marchSecs=max(5,(int)floor($distance*100/($minSpeed*MarchSkinService::speedMultiplier($skinSnapshot))));
        $marchId=$db->transaction(function () use (
            $db, $cityId, $playerId, $targetX, $targetY,
            $charmId, $troopsJson, $marchSecs, $cleanTroops,$worldId,$requestId,$payloadHash,$skinSnapshot,
        ): int {
            if($requestId!==null&&($existing=self::existingRequest($db,$playerId,$worldId,$requestId,$payloadHash,self::MARCH_CHARM))!==null)return $existing;
            self::assertSlotAvailable($playerId,$worldId);
            $current=$db->query('SELECT id FROM map_charms WHERE id=? AND world_id=? AND coord_x=? AND coord_y=? AND collected_by IS NULL AND expires_at>DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? SECOND) FOR UPDATE',[$charmId,$worldId,$targetX,$targetY,$marchSecs])->fetch();
            if(!$current)throw new \RuntimeException('Charm kann vor Ablauf nicht mehr erreicht werden.');
            MarchArmy::reserve($db, $cityId, $cleanTroops);

            $db->execute(
                'INSERT INTO marches
                    (player_id, world_id, march_type, march_skin, march_speed_bonus_pct, origin_city_id,
                     target_x, target_y, target_type, target_id,
                     request_id, request_payload_hash, troops_json, departure_time, arrival_time, state)
                 VALUES
                    (:pid, :world, :type, :march_skin, :march_bonus, :city,
                     :tx, :ty, 4, :cid, :request_id, :request_hash,
                     :troops,
                     UTC_TIMESTAMP(),
                     DATE_ADD(UTC_TIMESTAMP(), INTERVAL :dur SECOND),
                     "marching")',
                [
                    ':pid'    => $playerId,
                    ':world'  => $worldId,
                    ':type'   => self::MARCH_CHARM,
                    ':march_skin'=>$skinSnapshot['march_skin'],
                    ':march_bonus'=>$skinSnapshot['bonus_pct'],
                    ':city'   => $cityId,
                    ':tx'     => $targetX,
                    ':ty'     => $targetY,
                    ':cid'    => $charmId,
                    ':request_id'=>$requestId,
                    ':request_hash'=>$requestId===null?null:$payloadHash,
                    ':troops' => $troopsJson,
                    ':dur'    => $marchSecs,
                ],
            );

            return (int) $db->lastInsertId();
        });
        }finally{self::releaseDispatchLock($db,$dispatchLock);}

        return $marchId;
    }

    /**
     * Dispatch a player-vs-player attack march (march_type = 7).
     *
     * @param array<int,int> $selectedTroops  troop_code → count to send
     *
     * @throws \RuntimeException on validation failure
     */
    public static function dispatchPlayerAttack(int $playerId,int $cityId,int $originX,int $originY,int $targetX,int $targetY,array $selectedTroops): int
    {
        return self::dispatchCityMarch($playerId,$cityId,$targetX,$targetY,self::MARCH_ATTACK_PLAYER,$selectedTroops);
    }

    /**
     * Dispatch a scout march (march_type = 8).
     * No troops required — scouts move at fixed high speed.
     *
     * @throws \RuntimeException on validation failure
     */
    public static function dispatchScout(int $playerId,int $cityId,int $originX,int $originY,int $targetX,int $targetY): int
    {
        return self::dispatchCityMarch($playerId,$cityId,$targetX,$targetY,self::MARCH_SCOUT,[]);
    }

    /**
     * Dispatch a gather march to a field object (march_type = 9).
     * Delegates to GatherService for full validation and insertion.
     *
     * @throws \RuntimeException on validation failure
     */
    public static function dispatchGather(
        int $playerId,
        int $cityId,
        int $targetX,
        int $targetY,
        int $troopCount = 0,
        ?array $selectedTroops = null,
        bool $attack = false,
    ): int {
        // Delegate to GatherService
        return \Conquer\Game\March\GatherService::dispatch($playerId, $cityId, $targetX, $targetY, $troopCount, $selectedTroops, $attack)['march_id'];
    }

    /**
     * Dispatch a reinforcement march (march_type = 10 = MARCH_SUPPORT).
     * Troops travel to a friendly city. On arrival the march_tick inserts
     * them into the reinforcements table and deducts from origin city.
     *
     * @param array<int,int> $selectedTroops  troop_code → count
     * @throws \RuntimeException on slot full or validation failure
     */
    public static function dispatchReinforce(int $playerId,int $cityId,int $originX,int $originY,int $targetX,int $targetY,int $targetCityId,int $targetPlayerId,array $selectedTroops): int
    {
        return self::dispatchCityMarch($playerId,$cityId,$targetX,$targetY,self::MARCH_SUPPORT,$selectedTroops,$targetCityId,$targetPlayerId);
    }

    private static function requestId(?string $requestId): ?string
    {
        if($requestId===null)return null;
        if(!preg_match('/^[A-Za-z0-9_-]{16,80}$/D',$requestId))throw new \DomainException('REQUEST_ID_INVALID',400);
        return $requestId;
    }

    private static function payloadHash(array $payload): string
    {
        if(isset($payload['troops'])&&is_array($payload['troops']))ksort($payload['troops'],SORT_NUMERIC);
        return hash('sha256',json_encode($payload,JSON_THROW_ON_ERROR));
    }

    private static function existingRequest(Connection $db,int $playerId,int $worldId,string $requestId,string $payloadHash,int $marchType): ?int
    {
        $row=$db->query('SELECT id,march_type,request_payload_hash FROM marches WHERE player_id=? AND world_id=? AND request_id=?',[$playerId,$worldId,$requestId])->fetch();
        if(!$row)return null;
        if((int)$row['march_type']!==$marchType||!hash_equals((string)$row['request_payload_hash'],$payloadHash))throw new \DomainException('REQUEST_ID_CONFLICT',409);
        return (int)$row['id'];
    }

    private static function acquirePlayerLock(Connection $db,int $playerId): string
    {
        $lock='conquer-player-'.$playerId;
        if((int)$db->query('SELECT GET_LOCK(?,5)',[$lock])->fetchColumn()!==1)throw new \RuntimeException('REQUEST_BUSY');
        return $lock;
    }

    private static function releaseDispatchLock(Connection $db,string $lock): void
    {
        $db->query('SELECT RELEASE_LOCK(?)',[$lock]);
    }

    private static function dispatchCityMarch(int $playerId,int $cityId,int $targetX,int $targetY,int $type,array $troops,?int $targetId=null,?int $targetPlayerId=null): int
    {
        $db=Connection::getInstance();$origin=\Conquer\Game\WorldRules::origin($playerId,$cityId);$world=(int)$origin['world_id'];WorldContext::assertActionAvailable($world);
        $lock='conquer-player-'.$playerId;
        if((int)$db->query('SELECT GET_LOCK(?,5)',[$lock])->fetchColumn()!==1)throw new \RuntimeException('Deine Armee wird gerade aktualisiert.');
        try{return \Conquer\Game\WorldRules::combatLock(fn()=>$db->transaction(function()use($db,$playerId,$cityId,$targetX,$targetY,$type,$troops,$targetId,$targetPlayerId,$world):int{
            \Conquer\Game\Map\WorldPlacement::lockWorld($db,$world);
            $origin=$db->query('SELECT * FROM cities WHERE id=? AND player_id=? AND world_id=? FOR UPDATE',[$cityId,$playerId,$world])->fetch();
            if(!$origin)throw new \RuntimeException('Diese Stadt gehört dir nicht.');
            self::assertSlotAvailable($playerId);
            $buffs=\Conquer\Game\Research\BuffEngine::getBuffs($playerId,$world);
            $skinSnapshot=MarchSkinService::dispatchSnapshot($playerId);
            $clean=$type===self::MARCH_SCOUT?[]:MarchArmy::clean($troops,\Conquer\Game\Research\ResearchEffects::limits($buffs)['march_capacity']);
            if($type===self::MARCH_SUPPORT){
                $target=\Conquer\Game\WorldRules::assertReinforcementAllowed($playerId,(int)$targetId,$world,$targetX,$targetY);
                if((int)$target['player_id']!==$targetPlayerId)throw new \RuntimeException('Die Zielstadt hat einen anderen Besitzer.');
                if((int)$db->query("SELECT COUNT(*) FROM marches WHERE target_id=? AND march_type=10 AND state IN ('marching','resolving','arrived')",[$targetId])->fetchColumn()>=5)throw new \RuntimeException('Diese Stadt hat bereits fünf Verstärkungen.');
            }else{$target=\Conquer\Game\WorldRules::assertCityAttackAllowed($playerId,$targetX,$targetY,$targetPlayerId,$world);}
            $speed=200.0;
            $skinMultiplier=MarchSkinService::speedMultiplier($skinSnapshot);
            if($clean){$speed=PHP_FLOAT_MAX;foreach($clean as $code=>$count){$speed=min($speed,$type===self::MARCH_ATTACK_PLAYER?MarchSpeed::pvp((int)$code,$buffs,$skinMultiplier):MarchSpeed::generic((int)$code,$buffs,$skinMultiplier));}}
            else $speed*=$skinMultiplier;
            $seconds=max($type===self::MARCH_SCOUT?2:5,(int)floor(hypot($targetX-(int)$origin['coord_x'],$targetY-(int)$origin['coord_y'])*100/max(1,$speed)));
            if($clean)MarchArmy::reserve($db,$cityId,$clean);
            if($type!==self::MARCH_SUPPORT)\Conquer\Game\WorldRules::relinquishShield($playerId,$cityId);
            $db->execute("INSERT INTO marches(player_id,world_id,march_type,march_skin,march_speed_bonus_pct,origin_city_id,target_x,target_y,target_type,target_id,troops_json,departure_time,arrival_time,state) VALUES(?,?,?,?,?,?,?, ?,2,?,?,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? SECOND),'marching')",[$playerId,$world,$type,$skinSnapshot['march_skin'],$skinSnapshot['bonus_pct'],$cityId,$targetX,$targetY,(int)$target['id'],json_encode($clean),$seconds]);
            return $db->lastInsertId();
        }));}finally{$db->query('SELECT RELEASE_LOCK(?)',[$lock]);}
    }

    /**
     * Returns all active marches for a player (for the map overlay + city UI).
     *
     * @return list<array<string,mixed>>
     */
    public static function listActive(int $playerId): array
    {
        $db = Connection::getInstance();

        try {
            return $db->query(
                "SELECT m.id, m.march_type, m.march_skin, m.march_speed_bonus_pct, m.origin_city_id,
                        c.coord_x AS origin_x, c.coord_y AS origin_y,
                        m.target_x, m.target_y, m.target_type, m.target_id,
                        CASE fo.object_type
                            WHEN 1 THEN 'food'
                            WHEN 2 THEN 'lumber'
                            WHEN 3 THEN 'stone'
                            WHEN 4 THEN 'gold'
                            WHEN 5 THEN 'gems'
                            ELSE NULL
                        END AS target_resource,
                        m.troops_json, m.departure_time, m.arrival_time, m.return_time, m.gathering_finishes_at, m.state
                 FROM   marches m
                 JOIN   cities c ON c.id = m.origin_city_id
                 LEFT JOIN field_objects fo ON fo.id = m.target_id AND fo.world_id = m.world_id AND m.target_type = 5
                 WHERE  m.player_id = ? AND m.world_id=? AND m.state IN ('marching','resolving','returning','arrived')
                 ORDER  BY m.arrival_time ASC",
                [$playerId,WorldContext::id()],
            )->fetchAll();
        } catch (\PDOException) {
            return [];
        }
    }

    /**
     * Returns all active marches from all players (for public map overlay).
     * Only includes PvP attacks (type 7) and monster marches (type 5).
     *
     * @return list<array<string,mixed>>
     */
    public static function listAllActive(): array
    {
        $db = Connection::getInstance();

        try {
            return $db->query(
                "SELECT m.id, m.march_type, m.march_skin, m.player_id,
                        c.coord_x AS origin_x, c.coord_y AS origin_y,
                        m.target_x, m.target_y,
                        m.departure_time, m.arrival_time, m.return_time, m.state
                 FROM   marches m
                 JOIN   cities c ON c.id = m.origin_city_id
                 WHERE  m.world_id=? AND m.state IN ('marching','resolving','returning','arrived')
                   AND  m.march_type IN (5, 7, 15)
                 ORDER  BY m.arrival_time ASC",
                [WorldContext::id()],
            )->fetchAll();
        } catch (\PDOException) {
            return [];
        }
    }
}
