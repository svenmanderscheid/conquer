<?php
declare(strict_types=1);
namespace Conquer\Game\World;

use Conquer\Db\Connection;
use Conquer\Game\Map\WorldTerrain;

/** Persistent E16 state, contribution receipts and read models. */
final class LandProgressService
{
    private static array $available=[];
    private static array $initialized=[];
    private static array $worlds=[];
    private static array $parts=[];

    public static function available(): bool
    {
        $db=Connection::getInstance();$key=spl_object_id($db->getPdo());
        if(array_key_exists($key,self::$available))return self::$available[$key];
        try{return self::$available[$key]=(bool)$db->query("SHOW TABLES LIKE 'world_land_parts'")->fetchColumn();}
        catch(\PDOException $e){
            if((int)($e->errorInfo[1]??0)!==1146)throw $e;
            return self::$available[$key]=false;
        }
    }

    /** Initialize a world once. Pass legacy=true only for an existing world that must open every zone. */
    public static function ensureWorld(int $worldId,bool $legacy=false): bool
    {
        if(!self::available())return false;$db=Connection::getInstance();$cacheKey=spl_object_id($db->getPdo()).':'.$worldId;
        if(isset(self::$initialized[$cacheKey])&&!$legacy)return true;
        $callerTransaction=$db->getPdo()->inTransaction();
        $work=static function()use($db,$worldId,$legacy):void{
            $world=$db->query('SELECT id,map_size,created_at,started_at FROM worlds WHERE id=? FOR UPDATE',[$worldId])->fetch();
            if(!$world)throw new \DomainException('Welt nicht gefunden.',404);
            $defaults=LandRules::defaults();
            $db->execute('INSERT IGNORE INTO world_land_rules(world_id,settings_json,revision)VALUES(?,?,1)',[$worldId,json_encode($defaults,JSON_THROW_ON_ERROR)]);
            foreach(['outer','middle','center'] as $zone){
                $open=$legacy||$zone==='outer';
                $db->execute('INSERT IGNORE INTO world_land_zones(world_id,zone_key,status,opened_at,opened_reason,rule_revision)VALUES(?,?,?,'.($open?'UTC_TIMESTAMP()':'NULL').',?,1)',[$worldId,$zone,$open?'open':'locked',$open?($legacy?'legacy':'initial'):null]);
            }
            if($legacy)$db->execute("UPDATE world_land_zones SET status='open',opened_at=COALESCE(opened_at,UTC_TIMESTAMP()),opened_reason='legacy' WHERE world_id=?",[$worldId]);
            $mapSize=(int)$world['map_size'];$expected=LandGeometry::dimensions($mapSize)['columns']**2;
            $existing=(int)$db->query('SELECT COUNT(*) FROM world_land_parts WHERE world_id=? AND geometry_version=?',[$worldId,LandGeometry::VERSION])->fetchColumn();
            if($existing<$expected){
                $batch=[];
                foreach(LandGeometry::all($mapSize) as $parcel){
                    [$dry,$anchor]=self::terrainStats($parcel);
                    $batch[]=[$worldId,LandGeometry::VERSION,$parcel['parcel_x'],$parcel['parcel_y'],$parcel['zone'],$parcel['initial_level'],$parcel['initial_level'],$dry,$anchor?1:0];
                    if(count($batch)>=128){self::insertParts($db,$batch);$batch=[];}
                }
                if($batch)self::insertParts($db,$batch);
            }
            foreach(['outer','middle','center'] as $zone){
                $eligible=(int)$db->query('SELECT COUNT(*) FROM world_land_parts WHERE world_id=? AND geometry_version=? AND zone_key=? AND has_spawn_anchor=1',[$worldId,LandGeometry::VERSION,$zone])->fetchColumn();
                if($eligible===0)throw new \DomainException("Die Zone $zone besitzt keinen trockenen, erreichbaren Landteil.");
            }
            $core=(int)$db->query("SELECT COUNT(*) FROM world_land_parts WHERE world_id=? AND geometry_version=? AND zone_key='center' AND initial_level=9 AND has_spawn_anchor=1",[$worldId,LandGeometry::VERSION])->fetchColumn();
            if($core===0)throw new \DomainException('Die Welt besitzt keinen trockenen, erreichbaren Stufe-9-Kern.');
        };
        $callerTransaction?$work():$db->transaction($work);self::invalidate($worldId);
        // A caller-owned transaction may still roll back; cache only committed initialization.
        if(!$callerTransaction)self::$initialized[$cacheKey]=true;
        return true;
    }

    /** Cheap cached coordinate lookup used by placement and spawn loops. */
    public static function at(int $worldId,int $x,int $y): ?array
    {
        if(!self::available())return null;self::ensureWorld($worldId);$world=self::world($worldId);
        $geometry=LandGeometry::at($world['map_size'],$x,$y);$parts=self::partCache($worldId);
        $row=$parts[$geometry['parcel_x'].':'.$geometry['parcel_y']]??null;
        if(!$row)return null;
        return ['id'=>(int)$row['id'],'level'=>(int)$row['current_level'],'zone'=>$row['zone_key'],'open'=>$row['zone_status']==='open',
            'initial_level'=>(int)$row['initial_level'],'rule_revision'=>(int)LandRules::get($worldId)['revision']];
    }

    public static function overview(int $worldId,int $playerId): array
    {
        if(!self::available())return ['available'=>false,'geometry'=>null,'zones'=>[],'lands'=>[]];
        self::ensureWorld($worldId);LandUnlockService::evaluate($worldId);$world=self::world($worldId);$rules=LandRules::get($worldId);
        $city=Connection::getInstance()->query('SELECT coord_x,coord_y FROM cities WHERE world_id=? AND player_id=?',[$worldId,$playerId])->fetch();
        $own=$city?LandGeometry::at($world['map_size'],(int)$city['coord_x'],(int)$city['coord_y']):null;$lands=[];
        foreach(self::partCache($worldId) as $row){
            $g=LandGeometry::parcel($world['map_size'],(int)$row['parcel_x'],(int)$row['parcel_y']);$level=(int)$row['current_level'];$threshold=LandRules::threshold($rules,$level);$points=$level>=9?0:(int)$row['progress_points'];
            $lands[]=['id'=>(int)$row['id'],'parcel_x'=>(int)$row['parcel_x'],'parcel_y'=>(int)$row['parcel_y'],'bounds'=>$g['bounds'],'center'=>$g['center'],
                'zone'=>$row['zone_key'],'initial_level'=>(int)$row['initial_level'],'level'=>$level,'points'=>$points,'next_threshold'=>$threshold,
                'progress_pct'=>$threshold===null?100:round(min(100,$points*100/max(1,$threshold)),1),'open'=>$row['zone_status']==='open',
                'developable'=>(bool)$row['has_spawn_anchor'],'own_land'=>$own!==null&&(int)$row['parcel_x']===$own['parcel_x']&&(int)$row['parcel_y']===$own['parcel_y']];
        }
        return ['available'=>true,'geometry'=>LandGeometry::dimensions($world['map_size']),'zones'=>LandUnlockService::status($worldId),'lands'=>$lands,'rules_revision'=>(int)$rules['revision']];
    }

    public static function detail(int $worldId,int $playerId,int $landId): array
    {
        if(!self::available())throw new \DomainException('Regionalentwicklung ist noch nicht verfügbar.',404);
        self::ensureWorld($worldId);$db=Connection::getInstance();$rules=LandRules::get($worldId);
        $row=$db->query('SELECT p.*,z.status AS zone_status FROM world_land_parts p JOIN world_land_zones z ON z.world_id=p.world_id AND z.zone_key=p.zone_key WHERE p.id=? AND p.world_id=?',[$landId,$worldId])->fetch();
        if(!$row)throw new \DomainException('Landteil nicht gefunden.',404);
        $world=self::world($worldId);$g=LandGeometry::parcel($world['map_size'],(int)$row['parcel_x'],(int)$row['parcel_y']);$level=(int)$row['current_level'];$threshold=LandRules::threshold($rules,$level);
        $sources=$db->query('SELECT source_type AS source,COALESCE(SUM(credited_points),0) AS points,COUNT(*) AS event_count FROM land_progress_events WHERE world_id=? AND land_part_id=? GROUP BY source_type ORDER BY source_type',[$worldId,$landId])->fetchAll();
        foreach($sources as &$source){$source['points']=(int)$source['points'];$source['event_count']=(int)$source['event_count'];}unset($source);
        $recent=$db->query('SELECT id,source_type AS source,credited_points AS points,level_before,level_after,created_at FROM land_progress_events WHERE world_id=? AND land_part_id=? ORDER BY id DESC LIMIT 30',[$worldId,$landId])->fetchAll();
        foreach($recent as &$event)foreach(['id','points','level_before','level_after'] as $key)$event[$key]=$event[$key]===null?null:(int)$event[$key];unset($event);
        $daily=$db->query('SELECT source_type AS source,raw_points,credited_points,contribution_on AS date FROM land_daily_contributions WHERE world_id=? AND land_part_id=? AND player_id=? AND contribution_on=UTC_DATE() ORDER BY source_type',[$worldId,$landId,$playerId])->fetchAll();
        foreach($daily as &$day){$day['raw_points']=(int)$day['raw_points'];$day['credited_points']=(int)$day['credited_points'];}unset($day);
        $hasCity=(bool)$db->query('SELECT id FROM cities WHERE world_id=? AND player_id=?',[$worldId,$playerId])->fetchColumn();
        $donationLimit=$level>=9?0:max(1,(int)floor(($threshold??0)*(float)$rules['donation_daily_ratio']));
        $donationUsed=0;foreach($daily as $day)if($day['source']==='donation')$donationUsed=(int)$day['raw_points'];
        $donation=['resource_values'=>$rules['resource_values'],'resource_units_per_point'=>(int)$rules['resource_units_per_point'],
            'daily_limit_points'=>$donationLimit,'remaining_points'=>max(0,$donationLimit-$donationUsed)];
        $monsters=[];$regional='Conquer\\Game\\World\\RegionalSpawns';
        if($row['zone_status']==='open'&&class_exists($regional)&&method_exists($regional,'availableMonsters'))$monsters=$regional::availableMonsters($worldId,(int)floor($g['center']['x']),(int)floor($g['center']['y']));
        return ['id'=>(int)$row['id'],'parcel_x'=>(int)$row['parcel_x'],'parcel_y'=>(int)$row['parcel_y'],'bounds'=>$g['bounds'],'center'=>$g['center'],'zone'=>$row['zone_key'],
            'initial_level'=>(int)$row['initial_level'],'level'=>$level,'points'=>$level>=9?0:(int)$row['progress_points'],'next_threshold'=>$threshold,
            'progress_pct'=>$threshold===null?100:round(min(100,(int)$row['progress_points']*100/max(1,$threshold)),1),'open'=>$row['zone_status']==='open',
            'developable'=>(bool)$row['has_spawn_anchor'],'revision'=>(int)$row['revision'],'can_donate'=>$hasCity&&$row['zone_status']==='open'&&(bool)$row['has_spawn_anchor']&&$level<9,
            'sources'=>$sources,'own_daily'=>$daily,'recent_events'=>$recent,'donation'=>$donation,'monsters'=>array_values(is_array($monsters)?$monsters:[])];
    }

    public static function recordMonsterKill(int $worldId,int $monsterId,int $x,int $y,int $monsterLevel,int $playerId): array
    {
        if($monsterId<1||$playerId<1||$monsterLevel<0)throw new \DomainException('Ungültiger Monsterabschluss.');
        if(!self::available())return ['available'=>false,'credited_points'=>0,'duplicate'=>false];self::ensureWorld($worldId);
        $rules=LandRules::get($worldId);$db=Connection::getInstance();$saved=$db->query('SELECT regional_point_value FROM field_monsters WHERE id=? AND world_id=?',[$monsterId,$worldId])->fetchColumn();
        $raw=$saved!==false&&$saved!==null?(int)$saved:max(1,$monsterLevel)*(int)$rules['monster_points_per_level'];
        $payload=['world_id'=>$worldId,'monster_id'=>$monsterId,'x'=>$x,'y'=>$y,'monster_level'=>$monsterLevel,'player_id'=>$playerId];
        return self::credit($worldId,$x,$y,'monster_kill',(string)$monsterId,'monster_kill:'.$monsterId,$playerId,$raw,0,null,$payload,false);
    }

    public static function recordGather(int $worldId,int $marchId,int $x,int $y,string $resource,int $amount,int $playerId): array
    {
        if($marchId<1||$playerId<1||$amount<0||!in_array($resource,['food','lumber','stone','gold','gems'],true))throw new \DomainException('Ungültiger Sammelabschluss.');
        if(!self::available())return ['available'=>false,'credited_points'=>0,'duplicate'=>false];self::ensureWorld($worldId);
        $rules=LandRules::get($worldId);$value=(int)($rules['resource_values'][$resource]??0);$raw=$value===0?0:intdiv($amount*$value,(int)$rules['resource_units_per_point']);
        $payload=['world_id'=>$worldId,'march_id'=>$marchId,'x'=>$x,'y'=>$y,'resource'=>$resource,'amount'=>$amount,'player_id'=>$playerId];
        return self::credit($worldId,$x,$y,'gather',(string)$marchId,'gather:'.$marchId,$playerId,$raw,$amount,$resource,$payload,true);
    }

    public static function donate(int $worldId,int $playerId,int $landId,string $requestId,int $expectedRevision,array $resources): array
    {
        if(!self::available())throw new \DomainException('Regionalentwicklung ist noch nicht verfügbar.',404);
        if(!preg_match('/^[A-Za-z0-9_-]{16,80}$/D',$requestId))throw new \DomainException('Eine gültige Vorgangskennung ist erforderlich.');
        $clean=['food'=>0,'lumber'=>0,'stone'=>0,'gold'=>0];
        foreach($resources as $key=>$value)if(!array_key_exists($key,$clean))throw new \DomainException('Diese Ressource kann nicht gespendet werden.');
        foreach($clean as $key=>$unused){$value=$resources[$key]??0;if((!is_int($value)&&!is_string($value))||filter_var($value,FILTER_VALIDATE_INT)===false||(int)$value<0||(int)$value>1000000000000)throw new \DomainException('Ungültige Spendenmenge.');$clean[$key]=(int)$value;}
        if(array_sum($clean)<1)throw new \DomainException('Wähle mindestens eine Ressource für die Spende.');
        $eventKey='donate:'.$playerId.':'.$requestId;$payload=['world_id'=>$worldId,'player_id'=>$playerId,'land_id'=>$landId,'request_id'=>$requestId,'revision'=>$expectedRevision,'resources'=>$clean];$hash=self::hash($payload);
        $existing=self::existingEvent($worldId,$eventKey,$hash);if($existing){$existing['duplicate']=true;return $existing;}
        $rules=LandRules::get($worldId);$weighted=0;foreach($clean as $key=>$amount)$weighted+=$amount*(int)$rules['resource_values'][$key];
        $raw=intdiv($weighted,(int)$rules['resource_units_per_point']);if($raw<1)throw new \DomainException('Die Spende ist zu klein für einen Entwicklungspunkt.');
        $db=Connection::getInstance();$work=function()use($db,$worldId,$playerId,$landId,$expectedRevision,$clean,$raw,$eventKey,$requestId,$payload,$hash):array{
            self::ensureWorld($worldId);$old=self::existingEvent($worldId,$eventKey,$hash);if($old){$old['duplicate']=true;return $old;}
            $land=$db->query('SELECT p.*,z.status AS zone_status FROM world_land_parts p JOIN world_land_zones z ON z.world_id=p.world_id AND z.zone_key=p.zone_key WHERE p.id=? AND p.world_id=? FOR UPDATE',[$landId,$worldId])->fetch();
            if(!$land)throw new \DomainException('Landteil nicht gefunden.',404);
            if((int)$land['revision']!==$expectedRevision)throw new \DomainException('Der Landteil wurde zwischenzeitlich verändert.',409);
            if($land['zone_status']!=='open'||!(bool)$land['has_spawn_anchor'])throw new \DomainException('Dieser Landteil ist geschlossen.',409);
            if((int)$land['current_level']>=9)throw new \DomainException('Dieser Landteil hat bereits Stufe 9 erreicht.',409);
            $rules=LandRules::get($worldId);$threshold=LandRules::threshold($rules,(int)$land['current_level'])??0;$cap=max(1,(int)floor($threshold*(float)$rules['donation_daily_ratio']));
            $used=(int)$db->query("SELECT raw_points FROM land_daily_contributions WHERE world_id=? AND land_part_id=? AND player_id=? AND contribution_on=UTC_DATE() AND source_type='donation' FOR UPDATE",[$worldId,$landId,$playerId])->fetchColumn();
            if($raw>$cap-$used)throw new \DomainException('Dein tägliches Spendenlimit für diesen Landteil ist erreicht.',409);
            $city=$db->query('SELECT id,food,lumber,stone,gold FROM cities WHERE world_id=? AND player_id=? FOR UPDATE',[$worldId,$playerId])->fetch();
            if(!$city)throw new \DomainException('Du hast in dieser Welt keine Stadt.',403);
            foreach($clean as $key=>$amount)if((int)$city[$key]<$amount)throw new \DomainException('Für diese Spende fehlen Ressourcen.');
            $db->execute('UPDATE cities SET food=food-?,lumber=lumber-?,stone=stone-?,gold=gold-? WHERE id=?',[$clean['food'],$clean['lumber'],$clean['stone'],$clean['gold'],$city['id']]);
            return self::creditLocked($worldId,$land,'donation',$requestId,$eventKey,$playerId,$raw,array_sum($clean),'resources',$payload,$hash,true);
        };
        return $db->getPdo()->inTransaction()?$work():$db->transaction($work);
    }

    public static function invalidate(int $worldId): void
    {
        $suffix=':'.$worldId;
        foreach(array_keys(self::$worlds) as $key)if(str_ends_with($key,$suffix))unset(self::$worlds[$key]);
        foreach(array_keys(self::$parts) as $key)if(str_ends_with($key,$suffix))unset(self::$parts[$key]);
    }

    private static function credit(int $worldId,int $x,int $y,string $source,string $sourceId,string $eventKey,int $playerId,int $rawPoints,int $rawAmount,?string $resource,array $payload,bool $dailyDamping): array
    {
        if(!self::available())return ['available'=>false,'credited_points'=>0,'duplicate'=>false];$db=Connection::getInstance();$hash=self::hash($payload);
        $work=function()use($db,$worldId,$x,$y,$source,$sourceId,$eventKey,$playerId,$rawPoints,$rawAmount,$resource,$payload,$dailyDamping,$hash):array{
            self::ensureWorld($worldId);$old=self::existingEvent($worldId,$eventKey,$hash);if($old){$old['duplicate']=true;return $old;}
            $world=self::world($worldId);$g=LandGeometry::at($world['map_size'],$x,$y);
            $land=$db->query('SELECT p.*,z.status AS zone_status FROM world_land_parts p JOIN world_land_zones z ON z.world_id=p.world_id AND z.zone_key=p.zone_key WHERE p.world_id=? AND p.geometry_version=? AND p.parcel_x=? AND p.parcel_y=? FOR UPDATE',[$worldId,LandGeometry::VERSION,$g['parcel_x'],$g['parcel_y']])->fetch();
            if(!$land)throw new \DomainException('Landteil nicht gefunden.',404);
            return self::creditLocked($worldId,$land,$source,$sourceId,$eventKey,$playerId,$rawPoints,$rawAmount,$resource,$payload,$hash,$dailyDamping);
        };
        return $db->getPdo()->inTransaction()?$work():$db->transaction($work);
    }

    private static function creditLocked(int $worldId,array $land,string $source,string $sourceId,string $eventKey,int $playerId,int $rawPoints,int $rawAmount,?string $resource,array $payload,string $hash,bool $dailyDamping): array
    {
        $db=Connection::getInstance();if($land['zone_status']!=='open'||!(bool)$land['has_spawn_anchor'])throw new \DomainException('Dieser Landteil ist geschlossen.',409);
        $rules=LandRules::get($worldId);$before=(int)$land['current_level'];$credited=max(0,$rawPoints);
        if($dailyDamping&&$source==='gather'&&$rawPoints>0){
            $threshold=LandRules::threshold($rules,$before);$fullLimit=$threshold===null?0:max(0,(int)floor($threshold*(float)$rules['gather_full_daily_ratio']));
            $daily=$db->query('SELECT raw_points,credited_points FROM land_daily_contributions WHERE world_id=? AND land_part_id=? AND player_id=? AND contribution_on=UTC_DATE() AND source_type=? FOR UPDATE',[$worldId,$land['id'],$playerId,$source])->fetch();
            $used=$daily?(int)$daily['raw_points']:0;$full=max(0,min($rawPoints,$fullLimit-$used));$reduced=max(0,$rawPoints-$full);
            $credited=$full+(int)floor($reduced*(float)$rules['gather_reduced_factor']);
        }
        if($before>=9)$credited=0;[$after,$points]=self::advance($before,(int)$land['progress_points'],$credited,$rules);
        if($after!==$before||$points!==(int)$land['progress_points'])$db->execute('UPDATE world_land_parts SET current_level=?,progress_points=?,revision=revision+1 WHERE id=?',[$after,$points,$land['id']]);
        if($dailyDamping){
            $db->execute('INSERT INTO land_daily_contributions(world_id,land_part_id,player_id,contribution_on,source_type,raw_points,credited_points)VALUES(?,?,?,UTC_DATE(),?,?,?) ON DUPLICATE KEY UPDATE raw_points=raw_points+VALUES(raw_points),credited_points=credited_points+VALUES(credited_points)',[$worldId,$land['id'],$playerId,$source,$rawPoints,$credited]);
        }
        $result=['available'=>true,'land_id'=>(int)$land['id'],'source'=>$source,'raw_points'=>$rawPoints,'credited_points'=>$credited,'level_before'=>$before,'level_after'=>$after,'points'=>$after>=9?0:$points,'next_threshold'=>LandRules::threshold($rules,$after),'duplicate'=>false];
        $db->execute('INSERT INTO land_progress_events(world_id,land_part_id,event_key,source_type,source_id,actor_player_id,payload_hash,resource_code,raw_amount,raw_points,credited_points,level_before,level_after,result_json,metadata_json)VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[$worldId,$land['id'],$eventKey,$source,$sourceId,$playerId,$hash,$resource,$rawAmount,$rawPoints,$credited,$before,$after,json_encode($result,JSON_THROW_ON_ERROR),json_encode($payload,JSON_THROW_ON_ERROR)]);
        $result['event_id']=$db->lastInsertId();$db->execute('UPDATE land_progress_events SET result_json=? WHERE id=?',[json_encode($result,JSON_THROW_ON_ERROR),$result['event_id']]);
        self::invalidate($worldId);if($after>$before)LandUnlockService::evaluate($worldId);return $result;
    }

    private static function advance(int $level,int $points,int $credit,array $rules): array
    {
        $remaining=$credit;
        while($level<9){$needed=(LandRules::threshold($rules,$level)??0)-$points;if($remaining<$needed){$points+=$remaining;break;}$remaining-=$needed;$level++;$points=0;}
        return [$level,$level>=9?0:$points];
    }

    private static function existingEvent(int $worldId,string $eventKey,string $hash): ?array
    {
        $row=Connection::getInstance()->query('SELECT payload_hash,result_json FROM land_progress_events WHERE world_id=? AND event_key=?',[$worldId,$eventKey])->fetch();
        if(!$row)return null;if(!hash_equals((string)$row['payload_hash'],$hash))throw new \DomainException('Diese Vorgangskennung wurde bereits mit anderen Angaben verwendet.',409);
        return json_decode((string)$row['result_json'],true,32,JSON_THROW_ON_ERROR);
    }

    private static function world(int $worldId): array
    {
        $db=Connection::getInstance();$key=spl_object_id($db->getPdo()).':'.$worldId;if(isset(self::$worlds[$key]))return self::$worlds[$key];
        $row=$db->query('SELECT id,map_size,created_at,started_at FROM worlds WHERE id=?',[$worldId])->fetch();if(!$row)throw new \DomainException('Welt nicht gefunden.',404);
        $row['id']=(int)$row['id'];$row['map_size']=(int)$row['map_size'];return self::$worlds[$key]=$row;
    }

    private static function partCache(int $worldId): array
    {
        $db=Connection::getInstance();$key=spl_object_id($db->getPdo()).':'.$worldId;if(isset(self::$parts[$key]))return self::$parts[$key];$parts=[];
        foreach($db->query('SELECT p.*,z.status AS zone_status,z.rule_revision FROM world_land_parts p JOIN world_land_zones z ON z.world_id=p.world_id AND z.zone_key=p.zone_key WHERE p.world_id=? AND p.geometry_version=? ORDER BY p.parcel_y,p.parcel_x',[$worldId,LandGeometry::VERSION])->fetchAll() as $row)$parts[$row['parcel_x'].':'.$row['parcel_y']]=$row;
        return self::$parts[$key]=$parts;
    }

    private static function terrainStats(array $parcel): array
    {
        $dry=0;$anchor=false;$b=$parcel['bounds'];
        for($y=$b['y_min'];$y<=$b['y_max'];$y++)for($x=$b['x_min'];$x<=$b['x_max'];$x++){
            if(!WorldTerrain::isWater($x,$y))$dry++;
            if(!$anchor&&WorldTerrain::isDryRectangle($x,$y,$x,$y))$anchor=true;
        }
        return [$dry,$anchor];
    }

    private static function insertParts(Connection $db,array $rows): void
    {
        $values=[];$params=[];foreach($rows as $row){$values[]='(?,?,?,?,?,?,?,?,?)';array_push($params,...$row);}
        $db->execute('INSERT IGNORE INTO world_land_parts(world_id,geometry_version,parcel_x,parcel_y,zone_key,initial_level,current_level,developable_tile_count,has_spawn_anchor)VALUES'.implode(',',$values),$params);
    }

    private static function hash(array $payload): string{return hash('sha256',json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));}
}
