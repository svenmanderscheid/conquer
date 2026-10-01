<?php
declare(strict_types=1);
namespace Conquer\Game\World;

use Conquer\Db\Connection;

/** Immutable per-world geometry. Absence of a profile explicitly means the legacy map. */
final class WorldMapProfile
{
    private static ?\WeakMap $cache = null;

    public static function forWorld(int $worldId): array
    {
        $db=Connection::getInstance(); self::$cache??=new \WeakMap();
        $cached=self::$cache[$db]??[];
        if(isset($cached[$worldId]))return $cached[$worldId];
        try {$row=$db->query('SELECT * FROM world_map_profiles WHERE world_id=?',[$worldId])->fetch();}
        catch(\PDOException $e){if((int)($e->errorInfo[1]??0)!==1146)throw $e;$row=false;}
        if($row){
            if($row['profile_key']!=='luxembourg'||(int)$row['geometry_version']!==1||(int)$row['map_width']!==768||(int)$row['map_height']!==1100)throw new \DomainException('Diese Kartenversion wird nicht unterstützt.',409);
            if(!hash_equals($row['geometry_hash'],self::geometryHash()))throw new \DomainException('Die gespeicherte Kartengeometrie stimmt nicht mit der freigegebenen Version überein.',409);
            $profile=['key'=>'luxembourg','version'=>1,'width'=>768,'height'=>1100,'continent_id'=>$row['continent_id'],
                'geometry_hash'=>$row['geometry_hash'],'geography_url'=>'assets/world-lux-preview/game-geography.json','hydrology_url'=>'assets/world-lux-preview/game-hydrology.json','travel_scale'=>0.3];
        }else{
            $size=$db->query('SELECT map_size FROM worlds WHERE id=?',[$worldId])->fetchColumn();
            if($size===false)throw new \DomainException('Welt nicht gefunden.',404);
            $profile=['key'=>'legacy','version'=>1,'width'=>(int)$size,'height'=>(int)$size,'continent_id'=>'legacy','geometry_hash'=>null,'geography_url'=>null,'hydrology_url'=>null,'travel_scale'=>1.0];
        }
        $cached[$worldId]=$profile;self::$cache[$db]=$cached;return $profile;
    }

    public static function invalidate(int $worldId): void
    {
        if(self::$cache===null)return;$db=Connection::getInstance();$cached=self::$cache[$db]??[];unset($cached[$worldId]);self::$cache[$db]=$cached;
    }
    /** Pin terrain and target positions together for the lifetime of this world. */
    public static function geometryHash(): string
    {
        static $hash;return $hash??=hash('sha256',LuxembourgGeography::hash().hash_file('sha256',dirname(__DIR__,3).'/data/luxembourg_landmarks.json'));
    }
    public static function isLuxembourg(int $worldId): bool {return self::forWorld($worldId)['key']==='luxembourg';}
    public static function contains(int $worldId,int $x,int $y): bool
    {
        $p=self::forWorld($worldId);return $x>=0&&$y>=0&&$x<$p['width']&&$y<$p['height']&&($p['key']!=='luxembourg'||LuxembourgGeography::at($x,$y)!==null);
    }

    /** Creation only. Existing saved worlds require a reviewed migration, never automatic moves. */
    public static function configureEmptyWorld(int $worldId,string $key='luxembourg'): array
    {
        if($key!=='luxembourg')throw new \DomainException('Unbekannte Kartenvorlage.');
        $db=Connection::getInstance();
        $work=static function()use($db,$worldId):array{
            if(!$db->query('SELECT id FROM worlds WHERE id=? FOR UPDATE',[$worldId])->fetchColumn())throw new \DomainException('Welt nicht gefunden.',404);
            if($db->query('SELECT world_id FROM world_map_profiles WHERE world_id=?',[$worldId])->fetchColumn()){self::invalidate($worldId);return self::forWorld($worldId);}
            foreach(['cities','field_objects','field_monsters','shrines','marches','rallies','neutral_villages','world_land_parts']as$table){
                if($db->query('SELECT 1 FROM '.$table.' WHERE world_id=? LIMIT 1',[$worldId])->fetchColumn())throw new \DomainException('Nur eine neue, leere Welt kann diese Kartenvorlage erhalten. Bestehende Spielstände benötigen eine geprüfte Migration.',409);
            }
            $db->execute("INSERT INTO world_map_profiles(world_id,profile_key,geometry_version,continent_id,map_width,map_height,geometry_hash) VALUES(?,'luxembourg',1,'luxembourg',768,1100,?)",[$worldId,self::geometryHash()]);
            $db->execute('UPDATE worlds SET map_size=768 WHERE id=?',[$worldId]);
            self::invalidate($worldId);return self::forWorld($worldId);
        };
        try{return $db->getPdo()->inTransaction()?$work():$db->transaction($work);}finally{self::invalidate($worldId);}
    }
}
