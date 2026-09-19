<?php
declare(strict_types=1);
namespace Conquer\Game\World;
use Conquer\Db\Connection;
use Conquer\Game\Map\WorldPlacement;
use Conquer\Game\Map\MonsterData;

/** A bounded pass, serialized with map placement and all other spawn workers. */
final class WorldSpawnService
{
    public static function tick(?int $worldId=null,bool $force=false,string $source='cron'): array
    {
        $db=Connection::getInstance();$results=[];
        $worlds=$db->query('SELECT id FROM worlds'.($worldId===null?'':' WHERE id=?'),$worldId===null?[]:[$worldId])->fetchAll();
        foreach($worlds as $world) {
            $id=(int)$world['id'];$lock='conquer-spawn-'.$id;
            if((int)$db->query('SELECT GET_LOCK(?,0)',[$lock])->fetchColumn()!==1)continue;
            try {
                // Commit initialization before the placement loop so coordinate
                // lookups can reuse the cache without caching a pending transaction.
                LandProgressService::ensureWorld($id);
                $results[$id]=$db->transaction(static fn(Connection $db)=>self::run($db,$id,$force,$source));
            }
            catch(\Throwable $e) {
                $db->execute("INSERT INTO world_spawn_runs(world_id,trigger_source,status,details_json) VALUES(?,?,'failed',?)",[$id,$source,json_encode(['message'=>substr($e->getMessage(),0,500)],JSON_THROW_ON_ERROR)]);
                if($force)throw $e;
                $results[$id]=['status'=>'failed'];
            } finally {$db->query('SELECT RELEASE_LOCK(?)',[$lock]);}
        }
        return $results;
    }
    private static function run(Connection $db,int $worldId,bool $force,string $source): array
    {
        $size=WorldPlacement::lockWorld($db,$worldId);
        $world=$db->query('SELECT status FROM worlds WHERE id=?',[$worldId])->fetch();
        $state=WorldSettings::get($worldId);$cfg=$state['settings'];$now=time();
        if(!$cfg['enabled']||!in_array($world['status'],['open','running'],true))return ['status'=>'paused'];
        LandUnlockService::evaluate($worldId);
        if(!WorldSettings::inWindow($cfg,$now))return ['status'=>'outside_window'];
        if(!$force&&$state['next_run_at']&&strtotime($state['next_run_at'].' UTC')>$now)return ['status'=>'not_due'];
        if(!$state['configured'])$db->execute('INSERT INTO world_spawn_settings(world_id,settings_json) VALUES(?,?)',[$worldId,json_encode($cfg,JSON_THROW_ON_ERROR)]);
        $removed=0;
        foreach(['field_objects'=>5,'field_monsters'=>3] as $table=>$targetType) {
            $expired=$table==='field_objects'?'(f.expires_at<=UTC_TIMESTAMP() OR f.resource_amount=0) AND f.gatherer_march_id IS NULL':'(f.hp_current<=0 OR f.expires_at<=UTC_TIMESTAMP() OR (f.expires_at IS NULL AND f.spawned_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL '.(int)$cfg['monster_lifetime_hours'].' HOUR)))';
            $removed+=$db->execute("DELETE f FROM $table f WHERE f.world_id=? AND $expired
                AND NOT EXISTS(SELECT 1 FROM marches m WHERE m.world_id=f.world_id AND m.state IN ('marching','resolving','returning') AND ((m.target_type=? AND m.target_id=f.id) OR (m.target_x=f.coord_x AND m.target_y=f.coord_y)))
                AND NOT EXISTS(SELECT 1 FROM rallies r WHERE r.world_id=f.world_id AND r.status IN ('gathering','marching','returning') AND r.target_x=f.coord_x AND r.target_y=f.coord_y)",[$worldId,$targetType]);
        }
        $result=['status'=>'completed','resources_spawned'=>0,'monsters_spawned'=>0,'expired_removed'=>$removed,'placement_misses'=>0,'chance_skipped'=>0];
        $budget=(int)$cfg['batch_limit'];
        $deficits=[];
        foreach(['resource'=>'field_objects','monster'=>'field_monsters']as$kind=>$table){
            $target=min((int)$cfg[$kind.'_limit'],(int)floor($size*$size*$cfg[$kind.'_density_pct']/100));
            $existing=(int)$db->query('SELECT COUNT(*) FROM '.$table.' WHERE world_id=?',[$worldId])->fetchColumn();
            $deficits[$kind]=max(0,$target-$existing);
        }
        foreach(['resource','monster'] as $kind) {
            $table=$kind==='resource'?'field_objects':'field_monsters';
            $existing=(int)$db->query('SELECT COUNT(*) FROM '.$table.' WHERE world_id=?',[$worldId])->fetchColumn();
            $target=min((int)$cfg[$kind.'_limit'],(int)floor($size*$size*$cfg[$kind.'_density_pct']/100));
            $count=min($budget,max(0,$target-$existing));$result[$kind.'_target']=$target;
            // Both populations receive attempts in the same pass; a large mine
            // deficit must not starve monsters for several scheduled intervals.
            if($kind==='resource'&&$deficits['monster']>0){
                $count=min($count,(int)floor($budget*$deficits['resource']/max(1,array_sum($deficits))));
            }
            for($i=0;$i<$count;$i++) {
                $budget--;
                if(random_int(1,100000)>$cfg[$kind.'_chance_pct']*1000){$result['chance_skipped']++;continue;}
                $weights=$cfg[$kind.'_weights'];
                if($kind==='monster'){$weights['dragon']=0;$weights['Magdar']=0;}
                if(array_sum($weights)<1){$result['placement_misses']++;continue;}
                $type=self::weighted($weights);
                $placed=false;
                for($attempt=0;$attempt<30;$attempt++) {
                    $x=random_int(1,$size-2);$y=random_int(1,$size-2);
                    if($kind==='resource'&&!WorldPlacement::canPlace($db,$worldId,$kind,$x,$y))continue;
                    if($kind==='monster'){
                        $candidates=RegionalSpawns::candidates($worldId,$type,$x,$y,$cfg['monster_level_min'],$cfg['monster_level_max'],random_int(1,10)===1);
                        if(!$candidates)continue;
                        $monster=$candidates[array_rand($candidates)];
                        if(!WorldPlacement::canPlace($db,$worldId,WorldPlacement::monsterKind((int)$monster['code']),$x,$y))continue;
                    }
                    if($kind==='resource') {
                        $resourceType=array_search($type,['food','lumber','stone','gold','gems'],true)+1;
                        $level=random_int($cfg['resource_level_min'],$cfg['resource_level_max']);
                        $amount=\Conquer\Game\Map\FieldObjectData::capacity($resourceType,$level);
                        $db->execute('INSERT INTO field_objects(world_id,coord_x,coord_y,object_type,level,resource_amount,resource_max,expires_at) VALUES(?,?,?,?,?,?,?,?)',[$worldId,$x,$y,$resourceType,$level,$amount,$amount,gmdate('Y-m-d H:i:s',$now+$cfg['resource_lifetime_hours']*3600)]);
                        RegionalSpawns::stamp('field_objects',$db->lastInsertId(),$worldId,$x,$y);
                        $result['resources_spawned']++;
                    } else {
                        $code=(int)$monster['code'];$def=WorldContext::run($worldId,static fn()=>MonsterData::get($code));$hp=max(1,(int)round($def['stats']['hp']*$def['amount']));
                        $monsterType=$def['type']??'solo';
                        $db->execute('INSERT INTO field_monsters(world_id,monster_code,coord_x,coord_y,hp_current,monster_type,expires_at) VALUES(?,?,?,?,?,?,?)',[$worldId,$code,$x,$y,$hp,$monsterType,gmdate('Y-m-d H:i:s',$now+$cfg['monster_lifetime_hours']*3600)]);
                        RegionalSpawns::stamp('field_monsters',$db->lastInsertId(),$worldId,$x,$y);
                        $result['monsters_spawned']++;
                    }
                    $placed=true;break;
                }
                if(!$placed)$result['placement_misses']++;
            }
        }
        $next=WorldSettings::nextWindow($cfg,$now+$cfg['interval_minutes']*60);
        $db->execute('UPDATE world_spawn_settings SET next_run_at=?,last_run_at=UTC_TIMESTAMP() WHERE world_id=?',[gmdate('Y-m-d H:i:s',$next),$worldId]);
        $db->execute("INSERT INTO world_spawn_runs(world_id,trigger_source,status,resources_spawned,monsters_spawned,expired_removed,details_json) VALUES(?,?,'completed',?,?,?,?)",[$worldId,$source,$result['resources_spawned'],$result['monsters_spawned'],$removed,json_encode($result,JSON_THROW_ON_ERROR)]);
        return $result;
    }
    /** Mid-tier rally spawn slots receive their zone's native boss. */
    public static function regionalMonsterCode(int $code,int $x,int $y): int
    {
        if($code<20200501||$code>20200510)return $code;
        $base=match(\Conquer\Game\Map\WorldTerrain::biomeAt($x,$y)){'forest'=>20202400,'ice'=>20202100,'sand'=>20202200,'lava'=>20202300,default=>20200500};
        return $base+($code-20200500);
    }
    private static function weighted(array $weights): string
    {
        $roll=random_int(1,array_sum($weights));foreach($weights as $key=>$weight){$roll-=$weight;if($roll<=0)return (string)$key;}
        throw new \LogicException('Invalid spawn weights.');
    }
}
