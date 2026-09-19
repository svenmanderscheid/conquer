<?php
declare(strict_types=1);
namespace Conquer\Game\World;
use Conquer\Db\Connection;

/** Authenticated request context; background workers temporarily use persisted world IDs. */
final class WorldContext
{
    private static int $worldId=1;
    private static ?int $playerId=null;
    public static function id(): int {return self::$worldId;}
    public static function bind(int $worldId,?int $playerId=null): void
    {
        if($worldId<1)throw new \DomainException('Ungültige Welt.');
        self::$worldId=$worldId;self::$playerId=$playerId;
    }
    public static function run(int $worldId,callable $callback): mixed
    {
        $previous=self::$worldId;$player=self::$playerId;self::bind($worldId);
        try{return $callback();}finally{self::bind($previous,$player);}
    }
    public static function current(mixed $requested=null): int
    {
        if($requested!==null&&self::integer($requested)!==self::id())throw new \DomainException('Diese Anfrage gehört zu einer anderen Welt. Bitte lade die Ansicht neu.',409);
        return self::id();
    }
    public static function assertExpected(mixed $expected): void {self::current($expected);}
    public static function assertActionAvailable(?int $worldId=null): void
    {
        $status=Connection::getInstance()->query('SELECT status FROM worlds WHERE id=?',[$worldId??self::id()])->fetchColumn();
        if(!in_array($status,['open','running'],true))throw new \DomainException('Diese Welt ist pausiert oder geschlossen. Änderungen sind momentan nicht möglich.',409);
    }
    public static function city(int $playerId,?int $worldId=null,bool $lock=false): array
    {
        $city=Connection::getInstance()->query('SELECT c.* FROM cities c JOIN worlds w ON w.id=c.world_id WHERE c.player_id=? AND c.world_id=?'.($lock?' FOR UPDATE':''),[$playerId,$worldId??self::id()])->fetch();
        if(!$city)throw new \DomainException('Du hast in dieser Welt noch keine Stadt.',403);return $city;
    }
    public static function integer(mixed $value): int
    {
        if((!is_int($value)&&!is_string($value))||filter_var($value,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>2147483647]])===false)throw new \DomainException('Ungültige Welt-ID.');return (int)$value;
    }
}
