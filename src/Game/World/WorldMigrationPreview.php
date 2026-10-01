<?php
declare(strict_types=1);
namespace Conquer\Game\World;

use Conquer\Db\Connection;

/** Read-only inventory and proposed relocation. Deliberately has no apply operation. */
final class WorldMigrationPreview
{
    public static function build(int $worldId): array
    {
        $db=Connection::getInstance();$source=WorldMapProfile::forWorld($worldId);
        if($source['key']==='luxembourg')throw new \DomainException('Diese Welt verwendet bereits die Luxemburg-Karte.');
        $inventory=[];$schemas=[];
        foreach($db->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN)as$table){
            if(!preg_match('/^[a-zA-Z0-9_]+$/D',$table))continue;
            $columns=$db->query('SHOW COLUMNS FROM `'.$table.'`')->fetchAll(\PDO::FETCH_COLUMN);
            $schemas[$table]=$columns;
        }
        // Every world-bound table, including future reward/claim tables, is included.
        // Older garrisons and research tables derive their world through a saved parent.
        $parents=['city_id'=>'cities','origin_city_id'=>'cities','sender_city_id'=>'cities','target_city_id'=>'cities','rally_id'=>'rallies','march_id'=>'marches','shrine_id'=>'shrines','structure_id'=>'alliance_structures','land_part_id'=>'world_land_parts'];
        foreach($schemas as$table=>$columns){
            $where=[];$params=[];
            if(in_array('world_id',$columns,true)){$where[]='world_id=?';$params[]=$worldId;}
            else foreach($parents as$column=>$parent)if(in_array($column,$columns,true)&&isset($schemas[$parent])&&in_array('world_id',$schemas[$parent],true)){$where[]='`'.$column.'` IN (SELECT id FROM `'.$parent.'` WHERE world_id=?)';$params[]=$worldId;}
            if(!$where)continue;
            $rows=$db->query('SELECT * FROM `'.$table.'` WHERE '.implode(' OR ',$where).(in_array('id',$columns,true)?' ORDER BY id':''),$params)->fetchAll();
            $inventory[$table]=['count'=>count($rows),'sha256'=>hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR)),'rows'=>$rows];
        }
        $reserved=LuxembourgGeography::landmarks();$mapping=[];$byPosition=[];$unresolved=[];
        foreach(['cities'=>'city','alliance_structures'=>'structure','neutral_villages'=>'neutral_village','field_objects'=>'resource','field_monsters'=>'monster']as$table=>$kind)foreach($inventory[$table]['rows']??[]as$row){
            $id=(int)$row['id'];$size=match($kind){'city'=>4,'structure'=>($row['structure_type']??'')==='center'?5:3,'neutral_village'=>2,'monster'=>(\Conquer\Game\Map\WorldPlacement::monsterKind((int)$row['monster_code'])==='boss'?2:1),default=>1};
            $preferred=[(int)round((int)$row['coord_x']/$source['width']*768),(int)round((int)$row['coord_y']/$source['height']*1100)];
            $destination=self::find($preferred,$size,$reserved);$key=$table.':'.$id;
            $entry=['source_id'=>$id,'table'=>$table,'from'=>['x'=>(int)$row['coord_x'],'y'=>(int)$row['coord_y']],'to'=>$destination,'preserve'=>'Complete source row; change only reviewed coordinates.'];
            if($destination){$reserved[]=['x'=>$destination['x'],'y'=>$destination['y'],'footprint'=>$size];$byPosition[$row['coord_x'].':'.$row['coord_y']]=$destination;}else$unresolved[]=$key;
            $mapping[$key]=$entry;
        }
        $armies=[];
        foreach(['marches','rallies']as$table)foreach($inventory[$table]['rows']??[]as$row){
            $state=$row['state']??$row['status']??'';$active=in_array($state,['marching','resolving','returning','gathering'],true);
            if(!$active)continue;$endpoints=[];foreach([['origin_x','origin_y'],['from_x','from_y'],['target_x','target_y']]as[$xKey,$yKey])if(isset($row[$xKey],$row[$yKey]))$endpoints[$xKey]=['from'=>[$row[$xKey],$row[$yKey]],'proposed'=>$byPosition[$row[$xKey].':'.$row[$yKey]]??null];
            $armies[]=['table'=>$table,'id'=>(int)$row['id'],'state'=>$state,'endpoints'=>$endpoints,'resolution'=>'Must finish normally before migration; troop, reward and timing snapshots remain unchanged.'];
        }
        $land=[];foreach($inventory['world_land_parts']['rows']??[]as$row){
            $old=LandGeometry::parcel($source['width'],(int)$row['parcel_x'],(int)$row['parcel_y'],$source['height']);
            $x=min(767,(int)round($old['center']['x']/$source['width']*768));$y=min(1099,(int)round($old['center']['y']/$source['height']*1100));
            $land[]=['source_id'=>(int)$row['id'],'source_parcel'=>[$row['parcel_x'],$row['parcel_y']],'level'=>(int)$row['current_level'],'progress_points'=>(int)$row['progress_points'],'proposed_anchor'=>['x'=>$x,'y'=>$y],
                'destination_geography'=>LuxembourgGeography::at($x,$y),'resolution'=>'Retain original development record and contribution history; explicit reviewed progression transfer required. Never convert to commune ownership.'];
        }
        $blockers=[];
        if($armies)$blockers[]='Active marches or rallies must finish before a reviewed migration.';
        if(($inventory['shrines']['count']??0)>0)$blockers[]='Legacy shrine/congress ownership, garrisons and outstanding rewards need an explicit settlement receipt.';
        if($land)$blockers[]='Land progress transfer requires a reviewed policy; all original parcels and receipts are included.';
        if($unresolved)$blockers[]='Some positions could not be mapped without collision.';
        foreach($inventory as$table=>$entry)if(preg_match('/garrison|reinforcement/',$table)&&$entry['count'])$blockers[]=$table.': stationing and returning armies need explicit resolution.';
        return ['mode'=>'preview_only','world_id'=>$worldId,'source_profile'=>$source,'target_profile'=>['key'=>'luxembourg','version'=>1,'width'=>768,'height'=>1100,'geometry_hash'=>WorldMapProfile::geometryHash()],
            'generated_at'=>gmdate('c'),'writes_performed'=>false,'applicable'=>false,'blockers'=>$blockers,'unresolved_positions'=>$unresolved,'position_mapping'=>array_values($mapping),'active_armies'=>$armies,'land_progress_mapping'=>$land,'preserved_inventory'=>$inventory,
            'review_requirements'=>['Take a consistent backup and pause new world actions.','Complete or explicitly map every active army, garrison, legacy objective and outstanding claim.','Verify source row hashes immediately before any separately implemented migration.','Rehearse in a disposable database and reconcile troops, resources, cities, research and receipt totals.']];
    }

    private static function find(array $preferred,int $size,array $reserved): ?array
    {
        for($radius=0;$radius<1100;$radius++)for($dy=-$radius;$dy<=$radius;$dy++){
            $y=$preferred[1]+$dy;if($y<0||$y>=1100)continue;$columns=abs($dy)===$radius?range(-$radius,$radius):[-$radius,$radius];
            foreach($columns as$dx){$x=$preferred[0]+$dx;if($x<0||$x>=768)continue;$at=LuxembourgGeography::at($x,$y);if(!$at)continue;[$l,$t,$r,$b]=LuxembourgGeography::boundsForSize($x,$y,$size);
                if(!LuxembourgGeography::isDryRectangle($l-.5,$t-.5,$r+.5,$b+.5,$size===4?$at['canton_id']:null))continue;
                foreach($reserved as$o){[$ol,$ot,$or,$ob]=LuxembourgGeography::boundsForSize($o['x'],$o['y'],$o['footprint']);if(!($r<$ol||$l>$or||$b<$ot||$t>$ob))continue 2;}
                return ['x'=>$x,'y'=>$y]+$at;
            }
        }return null;
    }
}
