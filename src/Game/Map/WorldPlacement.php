<?php
declare(strict_types=1);
namespace Conquer\Game\Map;
use Conquer\Db\Connection;

/** All placement writers lock the world row until their transaction commits. */
final class WorldPlacement
{
    private static array $sizes=[];
    public static function lockWorld(Connection $db,int $worldId): int
    {
        if(!$db->getPdo()->inTransaction())throw new \LogicException('World placement needs a transaction.');
        $size=$db->query('SELECT map_size FROM worlds WHERE id=? FOR UPDATE',[$worldId])->fetchColumn();
        if($size===false)throw new \RuntimeException('Welt nicht gefunden.');
        return self::$sizes[spl_object_id($db).':'.$worldId]=(int)$size;
    }
    /** Only large regional bosses reserve four tiles. */
    public static function monsterKind(int $code): string
    {
        return (int)(MonsterData::definition($code)['footprint']??1)===2?'boss':'monster';
    }
    public static function footprint(string $kind,int $x,int $y): array
    {
        return match($kind){'city'=>[$x-1,$y-1,$x+2,$y+2],'alliance_center'=>[$x-2,$y-2,$x+2,$y+2],'outpost'=>[$x-1,$y-1,$x+1,$y+1],'boss'=>[$x,$y,$x+1,$y+1],'resource','monster'=>[$x,$y,$x,$y],'shrine'=>[$x-2,$y-2,$x+3,$y+3],'congress'=>[$x-3,$y-3,$x+3,$y+3],default=>throw new \InvalidArgumentException('Unknown map object kind')};
    }
    public static function conflicts(string $kind,int $x,int $y,string $otherKind,int $ox,int $oy): bool
    {
        [$l,$t,$r,$b]=self::footprint($kind,$x,$y);[$ol,$ot,$or,$ob]=self::footprint($otherKind,$ox,$oy);
        $gap=$kind==='resource'&&$otherKind==='resource'?2:0;
        return !($r+$gap<$ol||$l-$gap>$or||$b+$gap<$ot||$t-$gap>$ob);
    }
    public static function canPlace(Connection $db,int $worldId,string $kind,int $x,int $y,?int $ignoreId=null): bool
    {
        $size=self::$sizes[spl_object_id($db).':'.$worldId]??(int)$db->query('SELECT map_size FROM worlds WHERE id=?',[$worldId])->fetchColumn();
        [$left,$top,$right,$bottom]=self::footprint($kind,$x,$y);
        if($left<0||$top<0||$right>=$size||$bottom>=$size||!WorldTerrain::isDryRectangle($left,$top,$right,$bottom))return false;
        // Landmarks are preplaced in locked zones; interactions still require access.
        if(!in_array($kind,['shrine','congress'],true)&&class_exists(\Conquer\Game\World\LandAccessPolicy::class)){
            foreach([[$left,$top],[$right,$top],[$left,$bottom],[$right,$bottom]] as [$cx,$cy])
                if(!\Conquer\Game\World\LandAccessPolicy::isOpen($worldId,$cx,$cy))return false;
        }
        // A defeated monster's charm reserves its original tile until collected/expired.
        $charm=$db->query('SELECT id FROM map_charms WHERE world_id=? AND collected_by IS NULL AND expires_at>UTC_TIMESTAMP() AND coord_x BETWEEN ? AND ? AND coord_y BETWEEN ? AND ? LIMIT 1 FOR UPDATE',[$worldId,$left,$right,$top,$bottom])->fetchColumn();
        if($charm!==false)return false;
        // Current reads remain accurate even if a caller had already opened a repeatable-read snapshot.
        foreach(['cities'=>'city','field_objects'=>'resource','field_monsters'=>'monster','shrines'=>'shrine'] as $table=>$otherKind){
            // Cities extend two tiles; landmarks extend at most three tiles from their anchor.
            $pad=$kind==='resource'&&$otherKind==='resource'?3:match($otherKind){'city'=>2,'resource','monster'=>1,'shrine'=>3,default=>0};
            $rows=$db->query('SELECT id,coord_x,coord_y'.($table==='shrines'?',shrine_code':($table==='field_monsters'?',monster_code':'')).' FROM '.$table.' WHERE world_id=? AND coord_x BETWEEN ? AND ? AND coord_y BETWEEN ? AND ? FOR UPDATE',[$worldId,$left-$pad,$right+$pad,$top-$pad,$bottom+$pad])->fetchAll();
            foreach($rows as $row){$rowKind=($row['shrine_code']??'')==='CONGRESS'?'congress':$otherKind;if($otherKind==='monster')$rowKind=self::monsterKind((int)$row['monster_code']);if(($kind===$rowKind||(in_array($kind,['monster','boss'],true)&&$otherKind==='monster'))&&$ignoreId!==null&&(int)$row['id']===$ignoreId)continue;if(self::conflicts($kind,$x,$y,$rowKind,(int)$row['coord_x'],(int)$row['coord_y']))return false;}
        }
        try{$structures=$db->query('SELECT id,structure_type,coord_x,coord_y FROM alliance_structures WHERE world_id=? AND coord_x BETWEEN ? AND ? AND coord_y BETWEEN ? AND ? FOR UPDATE',[$worldId,$left-3,$right+3,$top-3,$bottom+3])->fetchAll();}catch(\PDOException){$structures=[];}
        foreach($structures as$row){$rowKind=$row['structure_type']==='center'?'alliance_center':'outpost';if($ignoreId!==null&&(int)$row['id']===$ignoreId)continue;if(self::conflicts($kind,$x,$y,$rowKind,(int)$row['coord_x'],(int)$row['coord_y']))return false;}
        return true;
    }
    public static function findNear(Connection $db,int $worldId,string $kind,int $x,int $y,?int $ignoreId=null,int $radius=24,?string $biome=null): ?array
    {
        for($r=0;$r<=$radius;$r++)for($dy=-$r;$dy<=$r;$dy++)for($dx=-$r;$dx<=$r;$dx++){
            if(max(abs($dx),abs($dy))!==$r)continue;
            if(($biome===null||WorldTerrain::biomeAt($x+$dx,$y+$dy)===$biome)&&self::canPlace($db,$worldId,$kind,$x+$dx,$y+$dy,$ignoreId))return [$x+$dx,$y+$dy];
        }
        return null;
    }
    /** Move legacy water/edge/overlapping cities only while no army depends on their old position. */
    public static function repairCities(Connection $db,int $worldId,?int $playerId=null): int
    {
        $size=self::lockWorld($db,$worldId);$moved=0;
        $rows=$db->query('SELECT id,player_id,coord_x,coord_y FROM cities WHERE world_id=?'.($playerId===null?'':' AND player_id=?').' ORDER BY id FOR UPDATE',$playerId===null?[$worldId]:[$worldId,$playerId])->fetchAll();
        foreach($rows as $city){
            $x=(int)$city['coord_x'];$y=(int)$city['coord_y'];$id=(int)$city['id'];$pid=(int)$city['player_id'];
            // Keep the older city fixed when expanding neighbouring legacy footprints.
            // Resources and monsters defer to cities and have their own guarded repair.
            $overlap=$db->query('SELECT id FROM cities WHERE world_id=? AND id<? AND coord_x BETWEEN ? AND ? AND coord_y BETWEEN ? AND ? LIMIT 1 FOR UPDATE',[$worldId,$id,$x-3,$x+3,$y-3,$y+3])->fetchColumn();
            $landmarkOverlap=false;
            // City [-1,+2] plus the largest landmark [-3,+3] needs anchor candidates [-4,+5].
            foreach($db->query('SELECT shrine_code,coord_x,coord_y FROM shrines WHERE world_id=? AND coord_x BETWEEN ? AND ? AND coord_y BETWEEN ? AND ? FOR UPDATE',[$worldId,$x-4,$x+5,$y-4,$y+5])->fetchAll() as $landmark){
                if(self::conflicts('city',$x,$y,$landmark['shrine_code']==='CONGRESS'?'congress':'shrine',(int)$landmark['coord_x'],(int)$landmark['coord_y'])){$landmarkOverlap=true;break;}
            }
            if($x>=1&&$y>=1&&$x<$size-2&&$y<$size-2&&WorldTerrain::isDryRectangle(...self::footprint('city',$x,$y))&&$overlap===false&&!$landmarkOverlap)continue;
            $active=$db->query("SELECT id FROM marches WHERE world_id=? AND (player_id=? OR (target_x=? AND target_y=?)) AND state IN ('marching','resolving','returning') LIMIT 1 FOR UPDATE",[$worldId,$pid,$x,$y])->fetchColumn();
            if($active!==false)continue;
            $rally=$db->query("SELECT r.id FROM rallies r LEFT JOIN rally_participants p ON p.rally_id=r.id WHERE r.world_id=? AND (r.leader_player_id=? OR r.target_player_id=? OR p.player_id=?) AND r.status IN ('gathering','marching','returning') LIMIT 1 FOR UPDATE",[$worldId,$pid,$pid,$pid])->fetchColumn();
            if($rally!==false)continue;
            $expedition=$db->query("SELECT id FROM expedition_missions WHERE player_id=? AND status IN ('marching','returning') LIMIT 1 FOR UPDATE",[$pid])->fetchColumn();
            if($expedition!==false)continue;
            $destination=self::findNear($db,$worldId,'city',max(1,min($size-3,$x)),max(1,min($size-3,$y)),$id,32);
            if($destination===null)continue;
            $db->execute('UPDATE cities SET coord_x=?,coord_y=? WHERE id=?',[$destination[0],$destination[1],$id]);$moved++;
        }
        return $moved;
    }
}
