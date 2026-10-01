<?php
declare(strict_types=1);
namespace Conquer\Game\World;

/** Server-side target-zone gate. Transit remains abstract in the MVP. */
final class LandAccessPolicy
{
    /** Discovery endpoints must not initialize land rows merely by viewing a source. */
    public static function isOpenReadOnly(int $worldId,int $x,int $y): bool
    {
        if(!WorldMapProfile::contains($worldId,$x,$y))return false;
        if(WorldMapProfile::isLuxembourg($worldId)||!LandProgressService::available())return true;
        $profile=WorldMapProfile::forWorld($worldId);
        $geometry=LandGeometry::at((int)$profile['width'],$x,$y,(int)$profile['height']);
        $status=\Conquer\Db\Connection::getInstance()->query('SELECT status FROM world_land_zones WHERE world_id=? AND zone_key=?',[$worldId,$geometry['zone']])->fetchColumn();
        // Land initialization opens all zones by default; explicit restrictions win.
        return $status===false||$status==='open';
    }

    public static function isOpen(int $worldId,int $x,int $y): bool
    {
        if(!WorldMapProfile::contains($worldId,$x,$y))return false;
        if(WorldMapProfile::isLuxembourg($worldId))return true;
        if(!LandProgressService::available())return true;
        try{$land=LandProgressService::at($worldId,$x,$y);return $land===null||$land['open']===true;}
        catch(\DomainException){return false;}
    }

    public static function assertTargetOpen(int $worldId,int $x,int $y): void
    {
        if(!WorldMapProfile::contains($worldId,$x,$y))throw new \DomainException('Dieses Ziel liegt außerhalb der bebaubaren Welt.',409);
        if(WorldMapProfile::isLuxembourg($worldId))return;
        if(!LandProgressService::available())return;
        $land=LandProgressService::at($worldId,$x,$y);
        if($land===null)return;
        if(!$land['open'])throw new \DomainException('Dieses Ziel liegt in einer noch geschlossenen Weltzone.',409);
    }
}
