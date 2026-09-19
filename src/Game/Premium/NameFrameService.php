<?php
declare(strict_types=1);
namespace Conquer\Game\Premium;

use Conquer\Db\Connection;

final class NameFrameService
{
    public static function state(int $playerId):array
    {
        LocalCosmeticEntitlements::sync($playerId);
        $db=Connection::getInstance();$owned=array_fill_keys($db->query('SELECT frame_code FROM player_name_frames WHERE player_id=?',[$playerId])->fetchAll(\PDO::FETCH_COLUMN),true);
        $equipped=$db->query('SELECT name_frame FROM kingdom_profiles WHERE player_id=?',[$playerId])->fetchColumn();
        if(!is_string($equipped)||!isset($owned[$equipped]))$equipped='default';
        $entries=[['id'=>'default','theme_id'=>'default','name'=>'Standardrahmen','owned'=>true,'equipped'=>$equipped==='default']];
        foreach(ThemeBundleCatalog::all()['entries']as$bundle){
            if($bundle['step']!==1)continue;$cosmetic=$bundle['contents']['cosmetic'];$id=$cosmetic['id'];
            $entries[]=['id'=>$id,'theme_id'=>$bundle['theme_id'],'name'=>$cosmetic['name'],'owned'=>isset($owned[$id]),'equipped'=>$equipped===$id];
        }
        return ['entries'=>$entries,'equipped'=>$equipped];
    }

    public static function equip(int $playerId,mixed $frameId):array
    {
        LocalCosmeticEntitlements::sync($playerId);
        if(!is_string($frameId)||!preg_match('/^[a-z0-9_]{1,32}$/D',$frameId))throw new \DomainException('Wähle einen verfügbaren Namensrahmen.');
        $db=Connection::getInstance();
        if($frameId!=='default'&&$db->query('SELECT 1 FROM player_name_frames WHERE player_id=? AND frame_code=?',[$playerId,$frameId])->fetchColumn()===false)throw new \DomainException('Dieser Namensrahmen gehört dir noch nicht.');
        $db->execute('UPDATE kingdom_profiles SET name_frame=? WHERE player_id=?',[$frameId==='default'?null:$frameId,$playerId]);
        return ['message'=>'Dein Namensrahmen wurde angelegt.','name_frame'=>$frameId];
    }
}
