<?php
declare(strict_types=1);
namespace Conquer\Game\Shrine;

use Conquer\Db\Connection;

/** Existing shrine catalog and the shared one-hour conquest rules. */
final class ShrineService
{
    public const CONTEST_DURATION_SECONDS = 3600;
    public const NPC_GARRISON = [
        'C'=>['50100101'=>50_000,'50200101'=>50_000],
        'B'=>['50100301'=>100_000,'50200301'=>100_000],
        'A'=>['50100401'=>200_000,'50200401'=>200_000],
        'S'=>['50100401'=>500_000,'50200401'=>500_000,'50300401'=>500_000],
    ];
    public const SHRINE_BONUSES = [
        'C'=>['resource_production'=>5.0],
        'B'=>['all_attack'=>10.0,'research_speed'=>10.0],
        'A'=>['all_hp'=>20.0,'construction_speed'=>20.0],
        'S'=>['all_attack'=>50.0,'all_defense'=>50.0,'all_hp'=>50.0],
    ];

    public static function getAllShrines(int $worldId): array
    {
        $rows=Connection::getInstance()->query(self::selectSql().' WHERE s.world_id=? ORDER BY s.id',[$worldId])->fetchAll();
        return array_map(self::decorate(...),$rows);
    }

    public static function getShrine(int $shrineId): ?array
    {
        $row=Connection::getInstance()->query(self::selectSql().' WHERE s.id=?',[$shrineId])->fetch();
        return $row?self::decorate($row):null;
    }

    private static function selectSql(): string
    {
        return 'SELECT s.id,s.world_id,s.shrine_code,s.tier AS shrine_tier,s.coord_x,s.coord_y,sc.alliance_id,a.name AS alliance_name,a.tag AS alliance_tag,sc.captured_at,sc.contested_until,sc.secured_at,sc.garrison_troops_json FROM shrines s LEFT JOIN shrine_captures sc ON sc.shrine_id=s.id LEFT JOIN alliances a ON a.id=sc.alliance_id';
    }

    private static function decorate(array $row): array
    {
        foreach(['id','world_id','coord_x','coord_y'] as $key)$row[$key]=(int)$row[$key];
        $row['alliance_id']=$row['alliance_id']===null?null:(int)$row['alliance_id'];
        $congress=$row['shrine_code']==='CONGRESS';
        $element=ShrineEvent::shrine($row['shrine_code']);
        $row['name']=$congress?'Kongress':($element['name']??'Schrein '.$row['shrine_code']);
        $row['kind']=$congress?'congress':'shrine';
        $row['element']=$element['element']??null;
        $row['art_key']=$row['shrine_code'];
        $row['state']=$row['alliance_id']===null?'neutral':($row['secured_at']!==null?'secured':'contested');
        $row['bonuses']=$congress||$element?[]:(self::SHRINE_BONUSES[$row['shrine_tier']]??[]);
        $row['npc_garrison']=self::NPC_GARRISON[$row['shrine_tier']]??[];
        $row['garrison_troops']=$row['garrison_troops_json']===null&&$row['alliance_id']===null?$row['npc_garrison']:(json_decode((string)$row['garrison_troops_json'],true)?:[]);
        unset($row['garrison_troops_json']);
        return $row;
    }

    public static function checkSecured(): void
    {
        $db=Connection::getInstance();
        $db->execute('UPDATE shrine_captures SET secured_at=contested_until WHERE contested_until<=UTC_TIMESTAMP() AND secured_at IS NULL AND alliance_id IS NOT NULL');
        $db->execute('UPDATE shrines s JOIN shrine_captures c ON c.shrine_id=s.id SET s.owner_alliance_id=IF(c.secured_at IS NULL,NULL,c.alliance_id),s.contesting_alliance_id=IF(c.secured_at IS NULL,c.alliance_id,NULL),s.captured_at=c.captured_at,s.secured_at=c.secured_at,s.contest_started_at=IF(c.secured_at IS NULL,c.captured_at,NULL)');
    }

    public static function getAllianceBonuses(int $allianceId): array
    {
        $rows=Connection::getInstance()->query("SELECT s.tier FROM shrine_captures c JOIN shrines s ON s.id=c.shrine_id WHERE c.alliance_id=? AND c.secured_at IS NOT NULL AND s.shrine_code NOT IN ('CONGRESS','SHRINE_FOREST','SHRINE_ICE','SHRINE_SAND','SHRINE_LAVA')",[$allianceId])->fetchAll();
        $totals=[];foreach($rows as $row)foreach(self::SHRINE_BONUSES[$row['tier']]??[] as $key=>$value)$totals[$key]=($totals[$key]??0)+$value;
        return $totals;
    }
}
