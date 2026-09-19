<?php
declare(strict_types=1);
namespace Conquer\Game\World;

use Conquer\Db\Connection;

/** Evaluates the two acyclic outer -> middle -> center development gates. */
final class LandUnlockService
{
    private static array $cache=[];

    public static function evaluate(int $worldId): array
    {
        if(!LandProgressService::available())return [];
        LandProgressService::ensureWorld($worldId);$db=Connection::getInstance();
        $work=static function()use($db,$worldId):void{
            $rules=LandRules::get($worldId);$world=$db->query('SELECT created_at,started_at FROM worlds WHERE id=? FOR UPDATE',[$worldId])->fetch();
            if(!$world)throw new \DomainException('Welt nicht gefunden.',404);
            foreach(['middle','center'] as $zone){
                $target=$db->query('SELECT * FROM world_land_zones WHERE world_id=? AND zone_key=? FOR UPDATE',[$worldId,$zone])->fetch();
                if(!$target||$target['status']==='open')continue;$gate=$rules['gates'][$zone];$source=$gate['source_zone'];
                $sourceZone=$db->query('SELECT * FROM world_land_zones WHERE world_id=? AND zone_key=? FOR UPDATE',[$worldId,$source])->fetch();
                if(!$sourceZone||$sourceZone['status']!=='open')continue;
                $eligible=(int)$db->query('SELECT COUNT(*) FROM world_land_parts WHERE world_id=? AND geometry_version=? AND zone_key=? AND has_spawn_anchor=1',[$worldId,LandGeometry::VERSION,$source])->fetchColumn();
                if($eligible===0)throw new \DomainException("Die Gate-Zone $source besitzt keine erreichbaren Landteile.");
                $required=min($eligible,max((int)$gate['minimum_count'],(int)ceil($eligible*(float)$gate['ratio'])));
                $reached=(int)$db->query('SELECT COUNT(*) FROM world_land_parts WHERE world_id=? AND geometry_version=? AND zone_key=? AND has_spawn_anchor=1 AND current_level>=?',[$worldId,LandGeometry::VERSION,$source,(int)$gate['target_level']])->fetchColumn();
                $base=$zone==='middle'?($world['started_at']?:$world['created_at']):$sourceZone['opened_at'];
                if(!$base)continue;$baseTime=strtotime($base.' UTC');$notBefore=$baseTime+(int)$gate['not_before_days']*86400;$timeReady=time()>=$notBefore;
                $developmentReady=$reached>=$required;$fallback=$gate['fallback_after_days'];$fallbackReady=$fallback!==null&&time()>=$baseTime+(int)$fallback*86400;
                if(!$timeReady||(!$developmentReady&&!$fallbackReady))continue;
                $reason=$developmentReady?'development':'time';
                if($db->execute("UPDATE world_land_zones SET status='open',opened_at=UTC_TIMESTAMP(),opened_reason=?,rule_revision=? WHERE world_id=? AND zone_key=? AND status='locked'",[$reason,$rules['revision'],$worldId,$zone])!==1)continue;
                $payload=['world_id'=>$worldId,'zone'=>$zone,'reason'=>$reason,'eligible_count'=>$eligible,'required_count'=>$required,'reached_count'=>$reached,'target_level'=>(int)$gate['target_level']];
                $result=['zone'=>$zone,'open'=>true,'opened_reason'=>$reason]+$payload;
                $db->execute("INSERT IGNORE INTO land_progress_events(world_id,land_part_id,event_key,source_type,source_id,actor_player_id,payload_hash,raw_points,credited_points,result_json,metadata_json)VALUES(?,NULL,?,'zone_open',?,NULL,?,0,0,?,?)",[$worldId,'zone_open:'.$zone,$zone,hash('sha256',json_encode($payload,JSON_THROW_ON_ERROR)),json_encode($result,JSON_THROW_ON_ERROR),json_encode($payload,JSON_THROW_ON_ERROR)]);
            }
        };
        $db->getPdo()->inTransaction()?$work():$db->transaction($work);self::invalidate($worldId);LandProgressService::invalidate($worldId);return self::status($worldId);
    }

    public static function status(int $worldId): array
    {
        if(!LandProgressService::available())return [];$db=Connection::getInstance();$key=spl_object_id($db->getPdo()).':'.$worldId;
        if(isset(self::$cache[$key]))return self::$cache[$key];$rules=LandRules::get($worldId);$world=$db->query('SELECT created_at,started_at FROM worlds WHERE id=?',[$worldId])->fetch();
        if(!$world)throw new \DomainException('Welt nicht gefunden.',404);$result=[];
        foreach($db->query("SELECT * FROM world_land_zones WHERE world_id=? ORDER BY FIELD(zone_key,'outer','middle','center')",[$worldId])->fetchAll() as $zone){
            $keyName=$zone['zone_key'];
            if($keyName==='outer'){$result[]=['key'=>'outer','open'=>$zone['status']==='open','opened_at'=>$zone['opened_at'],'eligible_count'=>null,'required_count'=>null,'target_level'=>null,'reached_count'=>null,'not_before'=>null,'progress_pct'=>100];continue;}
            $gate=$rules['gates'][$keyName];$source=$gate['source_zone'];
            $eligible=(int)$db->query('SELECT COUNT(*) FROM world_land_parts WHERE world_id=? AND geometry_version=? AND zone_key=? AND has_spawn_anchor=1',[$worldId,LandGeometry::VERSION,$source])->fetchColumn();
            if($eligible===0)throw new \DomainException("Die Gate-Zone $source besitzt keine erreichbaren Landteile.");
            $required=min($eligible,max((int)$gate['minimum_count'],(int)ceil($eligible*(float)$gate['ratio'])));
            $reached=(int)$db->query('SELECT COUNT(*) FROM world_land_parts WHERE world_id=? AND geometry_version=? AND zone_key=? AND has_spawn_anchor=1 AND current_level>=?',[$worldId,LandGeometry::VERSION,$source,(int)$gate['target_level']])->fetchColumn();
            $sourceOpened=$keyName==='middle'?($world['started_at']?:$world['created_at']):$db->query("SELECT opened_at FROM world_land_zones WHERE world_id=? AND zone_key='middle'",[$worldId])->fetchColumn();
            $notBefore=$sourceOpened?gmdate('Y-m-d H:i:s',strtotime($sourceOpened.' UTC')+(int)$gate['not_before_days']*86400):null;
            $result[]=['key'=>$keyName,'open'=>$zone['status']==='open','opened_at'=>$zone['opened_at'],'opened_reason'=>$zone['opened_reason'],
                'eligible_count'=>$eligible,'required_count'=>$required,'target_level'=>(int)$gate['target_level'],'reached_count'=>$reached,'not_before'=>$notBefore,
                'progress_pct'=>round(min(100,$reached*100/max(1,$required)),1)];
        }
        return self::$cache[$key]=$result;
    }

    public static function invalidate(int $worldId): void
    {
        $suffix=':'.$worldId;foreach(array_keys(self::$cache) as $key)if(str_ends_with($key,$suffix))unset(self::$cache[$key]);
    }
}
