<?php
declare(strict_types=1);
namespace Conquer\Game\Premium;

use Conquer\Db\Connection;

final class NameFrameService
{
    /** @return array<string,array{name:string,rarity:string}> */
    public static function catalog(): array
    {
        $catalog = [
            'default'=>['name'=>'Grenzlandrahmen','rarity'=>'common'],
            'forest'=>['name'=>'Waldhain-Rahmen','rarity'=>'legendary'],
            'fire'=>['name'=>'Glutwacht-Rahmen','rarity'=>'legendary'],
            'water'=>['name'=>'Gezeiten-Rahmen','rarity'=>'legendary'],
            'wind'=>['name'=>'Himmels-Rahmen','rarity'=>'legendary'],
        ];
        foreach (ThemeBundleCatalog::all()['entries'] as $bundle) {
            if ($bundle['step'] !== 1) continue;
            $id=$bundle['theme_id'];
            if(isset($catalog[$id]))continue;
            $march=\Conquer\Game\March\MarchSkinService::catalog()[$id];
            $catalog[$id]=['name'=>$bundle['contents']['cosmetic']['name'],'rarity'=>$march['rarity'],'legacy'=>true];
        }
        return $catalog;
    }

    public static function state(int $playerId):array
    {
        LocalCosmeticEntitlements::sync($playerId);
        $db=Connection::getInstance();$owned=array_fill_keys($db->query('SELECT frame_code FROM player_name_frames WHERE player_id=?',[$playerId])->fetchAll(\PDO::FETCH_COLUMN),true);
        foreach($db->query('SELECT skin_code FROM player_castle_skins WHERE player_id=?',[$playerId])->fetchAll(\PDO::FETCH_COLUMN) as $skinId)$owned[(string)$skinId]=true;
        $equipped=$db->query('SELECT name_frame FROM kingdom_profiles WHERE player_id=?',[$playerId])->fetchColumn();
        $catalog=self::catalog();
        if(!is_string($equipped)||!isset($catalog[$equipped])||($equipped!=='default'&&!isset($owned[$equipped])))$equipped='default';
        $entries=[];
        foreach($catalog as $id=>$definition){if(($definition['legacy']??false)&&!isset($owned[$id]))continue;$entries[]=['id'=>$id,'theme_id'=>$id,'name'=>$definition['name'],'rarity'=>$definition['rarity'],'owned'=>$id==='default'||isset($owned[$id]),'equipped'=>$equipped===$id];}
        return ['entries'=>$entries,'equipped'=>$equipped];
    }

    public static function equip(int $playerId,mixed $frameId):array
    {
        LocalCosmeticEntitlements::sync($playerId);
        if(!is_string($frameId)||!isset(self::catalog()[$frameId]))throw new \DomainException('Wähle einen verfügbaren Namensrahmen.');
        $db=Connection::getInstance();
        if($frameId!=='default'&&$db->query('SELECT 1 FROM player_name_frames WHERE player_id=? AND frame_code=? UNION SELECT 1 FROM player_castle_skins WHERE player_id=? AND skin_code=? LIMIT 1',[$playerId,$frameId,$playerId,$frameId])->fetchColumn()===false)throw new \DomainException('Dieser Namensrahmen gehört dir noch nicht.');
        $db->execute('UPDATE kingdom_profiles SET name_frame=? WHERE player_id=?',[$frameId==='default'?null:$frameId,$playerId]);
        return ['message'=>'Dein Namensrahmen wurde angelegt.','name_frame'=>$frameId];
    }
}
