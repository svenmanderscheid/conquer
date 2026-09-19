<?php
declare(strict_types=1);
namespace Conquer\Game;

use Conquer\Game\World\WorldContext;

use Conquer\Db\Connection;

/** Shared city-attack rules, checked both before reservation and on arrival. */
final class WorldRules
{
    public static function origin(int $playerId, int $cityId, ?int $worldId=null): array
    {
        $city=Connection::getInstance()->query('SELECT * FROM cities WHERE id=? AND player_id=? AND world_id=?',[$cityId,$playerId,$worldId??WorldContext::id()])->fetch();
        if(!$city)throw new \RuntimeException('Diese Stadt gehört dir nicht.');
        return $city;
    }

    public static function alliance(int $playerId,?int $worldId=null): ?int
    {
        $db=Connection::getInstance();$value=$db->query('SELECT alliance_id FROM alliance_members WHERE player_id=? AND world_id=?'.($db->getPdo()->inTransaction()?' FOR UPDATE':''),[$playerId,$worldId??WorldContext::id()])->fetchColumn();
        return $value===false?null:(int)$value;
    }

    public static function assertCityAttackAllowed(int $playerId, int $targetX, int $targetY, ?int $targetPlayerId=null, ?int $worldId=null): array
    {
        $db=Connection::getInstance();
        $worldId??=WorldContext::id();
        $size=(int)$db->query('SELECT map_size FROM worlds WHERE id=?',[$worldId])->fetchColumn();
        if($targetX<0||$targetX>=$size||$targetY<0||$targetY>=$size)throw new \RuntimeException('Ungültige Zielkoordinaten.');
        \Conquer\Game\World\LandAccessPolicy::assertTargetOpen($worldId,$targetX,$targetY);
        $city=$db->query("SELECT c.*,p.beginner_shield_until,p.is_hidden AS player_hidden,COALESCE(k.display_name,p.username) AS display_name,COALESCE(k.name_frame,'default') AS name_frame FROM cities c JOIN players p ON p.id=c.player_id LEFT JOIN kingdom_profiles k ON k.player_id=p.id WHERE c.world_id=? AND c.coord_x=? AND c.coord_y=?".($db->getPdo()->inTransaction()?' FOR UPDATE':''),[$worldId,$targetX,$targetY])->fetch();
        if(!$city||($targetPlayerId!==null&&(int)$city['player_id']!==$targetPlayerId)||(int)$city['is_hidden']||(int)$city['player_hidden'])throw new \RuntimeException('Diese Zielstadt ist nicht mehr verfügbar.');
        if((int)$city['player_id']===$playerId)throw new \RuntimeException('Du kannst deine eigene Stadt nicht angreifen.');
        $alliance=self::alliance($playerId,$worldId);
        if($alliance!==null&&$alliance===self::alliance((int)$city['player_id'],$worldId))throw new \RuntimeException('Mitglieder deiner eigenen Allianz können nicht angegriffen werden.');
        if(\Conquer\Game\Community\CommunityService::protectedRelation($playerId,(int)$city['player_id'],$worldId)!==null)throw new \RuntimeException('Ein Bündnis oder Nichtangriffspakt schützt diese Stadt.');
        if(self::shieldActive($city))throw new \RuntimeException('Diese Stadt ist durch einen Schutzschild geschützt.');
        return $city;
    }

    /** Legacy administrative shields without an expiry remain permanent. */
    public static function shieldActive(array $city, ?int $now=null): bool
    {
        $now??=time();
        return (!empty($city['is_shielded']) && (empty($city['shield_expires_at']) || strtotime($city['shield_expires_at'].' UTC')>$now))
            || (!empty($city['beginner_shield_until']) && strtotime($city['beginner_shield_until'].' UTC')>$now);
    }

    public static function assertReinforcementAllowed(int $playerId,int $targetCityId,int $worldId,?int $x=null,?int $y=null): array
    {
        $db=Connection::getInstance();
        $city=$db->query('SELECT c.*,p.is_hidden AS player_hidden FROM cities c JOIN players p ON p.id=c.player_id WHERE c.id=? AND c.world_id=?'.($db->getPdo()->inTransaction()?' FOR UPDATE':''),[$targetCityId,$worldId])->fetch();
        if(!$city || (int)$city['is_hidden'] || (int)$city['player_hidden'] || ($x!==null && ((int)$city['coord_x']!==$x || (int)$city['coord_y']!==$y)))throw new \RuntimeException('Diese Zielstadt ist nicht mehr verfügbar.');
        \Conquer\Game\World\LandAccessPolicy::assertTargetOpen($worldId,(int)$city['coord_x'],(int)$city['coord_y']);
        $alliance=self::alliance($playerId,$worldId);
        if((int)$city['player_id']===$playerId || $alliance===null || $alliance!==self::alliance((int)$city['player_id'],$worldId))throw new \RuntimeException('Verstärkung ist nur für andere Mitglieder deiner Allianz möglich.');
        return $city;
    }

    /** Called only within the transaction which successfully reserves an attacking army. */
    public static function relinquishShield(int $playerId,int $cityId): void
    {
        $db=Connection::getInstance();
        $db->execute('UPDATE cities SET is_shielded=0,shield_expires_at=NULL WHERE id=? AND player_id=?',[$cityId,$playerId]);
        $db->execute('UPDATE players SET beginner_shield_until=NULL WHERE id=?',[$playerId]);
    }

    /** Serializes city battles without acquiring another player's advisory lock. */
    public static function combatLock(callable $fn): mixed
    {
        $db=Connection::getInstance();$key='conquer-city-combat';
        if((int)$db->query('SELECT GET_LOCK(?,5)',[$key])->fetchColumn()!==1)throw new \RuntimeException('Ein Kampf wird gerade abgerechnet. Versuche es erneut.');
        try{return $fn();}finally{$db->query('SELECT RELEASE_LOCK(?)',[$key]);}
    }
}
