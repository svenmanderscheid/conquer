<?php
declare(strict_types=1);
namespace Conquer\Game\World;

/** Server-side target-zone gate. Transit remains abstract in the MVP. */
final class LandAccessPolicy
{
    public static function isOpen(int $worldId,int $x,int $y): bool
    {
        if(!LandProgressService::available())return true;
        try{$land=LandProgressService::at($worldId,$x,$y);return $land===null||$land['open']===true;}
        catch(\DomainException){return false;}
    }

    public static function assertTargetOpen(int $worldId,int $x,int $y): void
    {
        if(!LandProgressService::available())return;
        $land=LandProgressService::at($worldId,$x,$y);
        if($land===null)return;
        if(!$land['open'])throw new \DomainException('Dieses Ziel liegt in einer noch geschlossenen Weltzone.',409);
    }
}
