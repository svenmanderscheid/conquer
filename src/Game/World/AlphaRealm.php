<?php
declare(strict_types=1);
namespace Conquer\Game\World;

use Conquer\Db\Connection;
use Conquer\Game\Map\{WorldPlacement,FieldObjectData,MonsterData};

/** Idempotent explicit setup; never migrates or resets an existing player world. */
final class AlphaRealm
{
    public const SLUG='alpha-luxembourg-2x';
    public static function setup(): array
    {
        $db=Connection::getInstance();
        if ((int)$db->query("SELECT GET_LOCK('uok-alpha-entry',10)")->fetchColumn()!==1) throw new \RuntimeException('Alpha setup is already running.');
        try { return $db->transaction(static function(Connection $db): array {
            $id=(int)($db->query('SELECT id FROM worlds WHERE slug=? FOR UPDATE',[self::SLUG])->fetchColumn()?:0);
            if ($id) {
                $entry=WorldEntry::settings($id);
                if (!$entry || !WorldMapProfile::isLuxembourg($id)) throw new \RuntimeException('The Alpha slug is already used by another world.');
                return self::receipt($id,false);
            }
            $db->execute("INSERT INTO worlds(name,slug,status,map_size,map_seed,speed_factor,gather_factor,haul_factor) VALUES(?,?,'open',768,?,2,2,1)",['Luxembourg Alpha · 2×',self::SLUG,random_int(1,2147483647)]);
            $id=$db->lastInsertId();
            WorldMapProfile::configureEmptyWorld($id);
            WorldService::initializeWorld($id);
            // Wiltz is a compact forest start with easy solo opponents.
            $db->execute('UPDATE world_entry_settings SET default_slot=NULL WHERE default_slot=1');
            $db->execute("INSERT INTO world_entry_settings(world_id,default_slot,starting_resources,spawn_canton,spawn_x,spawn_y,spawn_radius) VALUES(?,1,500000,'08',170,370,64)",[$id]);
            $cfg=WorldSettings::defaults(); $cfg['interval_minutes']=15;
            // Keep the concentrated Wiltz start from filling with overlapping targets.
            $cfg['resource_limit']=250; $cfg['monster_limit']=150;
            $cfg['resource_density_pct']=0.03; $cfg['monster_density_pct']=0.018;
            $cfg['batch_limit']=30;
            $cfg['resource_weights']=['food'=>25,'lumber'=>25,'stone'=>20,'gold'=>20,'gems'=>10];
            $db->execute('INSERT INTO world_spawn_settings(world_id,settings_json,next_run_at) VALUES(?,?,UTC_TIMESTAMP())',[$id,json_encode($cfg,JSON_THROW_ON_ERROR)]);
            WorldPlacement::lockWorld($db,$id);
            // Keep the innermost village cluster free; offer useful nearby starter targets.
            foreach ([1,2,3,4,5,1,2,3,4,5] as $n=>$type) {
                $angle=$n*2*M_PI/10; $x=170+(int)round(26*cos($angle)); $y=370+(int)round(26*sin($angle));
                $spot=WorldPlacement::findNear($db,$id,'resource',$x,$y,null,8);
                if (!$spot) throw new \RuntimeException('No safe starter resource position.');
                $amount=FieldObjectData::capacity($type,1);
                $db->execute('INSERT INTO field_objects(world_id,coord_x,coord_y,object_type,level,resource_amount,resource_max,expires_at) VALUES(?,?,?,?,1,?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 24 HOUR))',[$id,$spot[0],$spot[1],$type,$amount,$amount]);
                RegionalSpawns::stamp('field_objects',$db->lastInsertId(),$id,$spot[0],$spot[1]);
            }
            foreach (['Orc','Skeleton','Golem'] as $n=>$family) {
                $x=150+$n*20; $y=402;
                $candidates=RegionalSpawns::candidates($id,$family,$x,$y,0,1,true);
                usort($candidates,static fn($a,$b)=>$a['level']<=>$b['level']);
                if (!$candidates) continue;
                $code=(int)$candidates[0]['code']; $def=WorldContext::run($id,static fn()=>MonsterData::get($code));
                $spot=WorldPlacement::findNear($db,$id,'monster',$x,$y,null,8);
                if (!$spot) continue;
                $db->execute("INSERT INTO field_monsters(world_id,monster_code,coord_x,coord_y,hp_current,monster_type,expires_at) VALUES(?,?,?,?,?,'solo',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 12 HOUR))",[$id,$code,$spot[0],$spot[1],max(1,(int)round($def['stats']['hp']*$def['amount']))]);
                RegionalSpawns::stamp('field_monsters',$db->lastInsertId(),$id,$spot[0],$spot[1]);
            }
            return self::receipt($id,true);
        }); } finally { $db->query("SELECT RELEASE_LOCK('uok-alpha-entry')"); }
    }
    public static function receipt(int $id,bool $created): array
    {
        return ['created'=>$created,'world'=>Connection::getInstance()->query('SELECT id,name,slug,status,speed_factor,gather_factor,haul_factor FROM worlds WHERE id=?',[$id])->fetch(),'entry'=>WorldEntry::settings($id),'map'=>WorldMapProfile::forWorld($id)];
    }
}
