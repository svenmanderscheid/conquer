<?php
declare(strict_types=1);
namespace Conquer\Game\Alliance;

use Conquer\Db\Connection;

/** Long-running, shared alliance progression. */
final class AllianceResearchService
{
    public const MAX_LEVEL=20;
    private const COST_FACTOR=750;
    private function __construct() {}

    public static function getData(): array
    {
        return [
            'ally_troops_hp'=>self::node('Truppenstärke','battle','Erhöht die Lebenspunkte aller Truppen.','+{n}% Truppen-LP',.5),
            'ally_troops_atk'=>self::node('Gemeinsamer Angriff','battle','Erhöht den Angriff aller Truppen.','+{n}% Truppenangriff',.5),
            'ally_troops_def'=>self::node('Gemeinsame Verteidigung','battle','Erhöht die Verteidigung aller Truppen.','+{n}% Truppenverteidigung',.5),
            'ally_march_speed'=>self::node('Heerstraßen','battle','Beschleunigt Märsche aller Mitglieder.','+{n}% Marschtempo',.5),
            'ally_food_prod'=>self::node('Gemeinschaftliche Felder','economy','Steigert die Nahrungsproduktion aller Mitglieder.','+{n}% Nahrungsproduktion',1),
            'ally_lumber_prod'=>self::node('Gemeinschaftliche Forste','economy','Steigert die Holzproduktion aller Mitglieder.','+{n}% Holzproduktion',1),
            'ally_stone_prod'=>self::node('Gemeinschaftliche Steinbrüche','economy','Steigert die Steinproduktion aller Mitglieder.','+{n}% Steinproduktion',1),
            'ally_gold_prod'=>self::node('Gemeinschaftliche Münzen','economy','Steigert die Goldproduktion aller Mitglieder.','+{n}% Goldproduktion',.75),
            'ally_gathering_speed'=>self::node('Erfahrene Sammler','economy','Beschleunigt das Sammeln auf der Weltkarte.','+{n}% Sammeltempo',1),
            'ally_construction_speed'=>self::node('Allianzbaumeister','development','Verkürzt die Bauzeit aller Mitglieder.','+{n}% Bautempo',.5),
            'ally_research_speed'=>self::node('Geteiltes Wissen','development','Verkürzt persönliche Forschungszeiten.','+{n}% Forschungstempo',.5),
            'ally_training_speed'=>self::node('Gemeinsame Ausbildung','development','Verkürzt die Ausbildungszeit von Truppen.','+{n}% Ausbildungstempo',.5),
            'ally_march_limit'=>self::node('Koordinierte Feldzüge','territory','Gewährt auf Stufe 10 und 20 je einen weiteren Marschplatz.','+{n} Marschplätze',.1),
            'ally_outpost_capacity'=>self::node('Grenzverwaltung','territory','Erlaubt alle fünf Stufen einen weiteren Außenposten.','+{n} Außenposten',.2),
        ];
    }

    private static function node(string $name,string $tree,string $description,string $label,float $perLevel): array
    { return ['name'=>$name,'tree'=>$tree,'description'=>$description,'bonus_label'=>$label,'bonus_per_level'=>$perLevel]; }

    public static function costForLevel(int $level): array
    { $cost=self::COST_FACTOR*$level*$level;return ['lumber'=>$cost,'stone'=>$cost,'gold'=>$cost]; }

    /** Level 1 takes 15 minutes, level 20 roughly 14 hours. */
    public static function durationForLevel(int $level): int
    { return (int)round(900*pow(max(1,$level),1.35)); }

    public static function getState(int $allianceId): array
    {
        $db=Connection::getInstance();$levels=[];
        foreach($db->query('SELECT research_code,level FROM alliance_research WHERE alliance_id=?',[$allianceId])->fetchAll() as$row)$levels[$row['research_code']]=(int)$row['level'];
        $queue=$db->query('SELECT research_code,level_to,finishes_at FROM alliance_research_queue WHERE alliance_id=? AND is_processed=0 ORDER BY started_at LIMIT 1',[$allianceId])->fetch();
        $active=$queue?$queue['research_code']:null;$nodes=[];
        foreach(self::getData() as$code=>$def){$level=min(self::MAX_LEVEL,$levels[$code]??0);$next=$level+1;$nodes[$code]=['code'=>$code,...$def,'level'=>$level,'max_level'=>self::MAX_LEVEL,'bonus_current'=>round($level*$def['bonus_per_level'],2),'bonus_next'=>$level<self::MAX_LEVEL?round($next*$def['bonus_per_level'],2):null,'cost_next'=>$level<self::MAX_LEVEL?self::costForLevel($next):null,'duration_seconds'=>$level<self::MAX_LEVEL?self::durationForLevel($next):null,'in_queue'=>$active===$code,'finishes_at'=>$active===$code?$queue['finishes_at']:null];}
        return ['nodes'=>$nodes,'active_code'=>$active,'finishes_at'=>$queue['finishes_at']??null,'max_level'=>self::MAX_LEVEL];
    }

    public static function canStart(int $allianceId,string $code): bool
    {
        $db=Connection::getInstance();if(!isset(self::getData()[$code]))return false;
        $level=(int)($db->query('SELECT level FROM alliance_research WHERE alliance_id=? AND research_code=?',[$allianceId,$code])->fetchColumn()?:0);
        if($level>=self::MAX_LEVEL||$db->query('SELECT id FROM alliance_research_queue WHERE alliance_id=? AND is_processed=0 LIMIT 1',[$allianceId])->fetchColumn()!==false)return false;
        $cost=self::costForLevel($level+1);$balance=TreasuryService::getBalance($allianceId);
        return ($balance['lumber']??0)>=$cost['lumber']&&($balance['stone']??0)>=$cost['stone']&&($balance['gold']??0)>=$cost['gold'];
    }

    public static function start(int $allianceId,int $playerId,string $code): int
    {
        $db=Connection::getInstance();$world=$db->query('SELECT a.world_id FROM alliances a JOIN alliance_members m ON m.alliance_id=a.id AND m.player_id=? WHERE a.id=?',[$playerId,$allianceId])->fetchColumn();
        if($world===false)throw new \DomainException('Du gehörst nicht zu dieser Allianz.');
        $result=\Conquer\Game\Community\CommunityService::action($playerId,['action'=>'research.start','code'=>$code,'request_id'=>bin2hex(random_bytes(16))],(int)$world);
        return (int)$result['result']['id'];
    }
    public static function processTick(?int $allianceId=null): void { \Conquer\Game\Community\CommunityService::tickResearch($allianceId); }

    public static function getProductionBonuses(int $allianceId): array
    {
        $result=['food_pct'=>0.0,'lumber_pct'=>0.0,'stone_pct'=>0.0,'gold_pct'=>0.0];
        try{$rows=Connection::getInstance()->query("SELECT research_code,level FROM alliance_research WHERE alliance_id=? AND research_code IN ('ally_food_prod','ally_lumber_prod','ally_stone_prod','ally_gold_prod')",[$allianceId])->fetchAll();}catch(\PDOException){return $result;}
        $map=['ally_food_prod'=>['food_pct',.01],'ally_lumber_prod'=>['lumber_pct',.01],'ally_stone_prod'=>['stone_pct',.01],'ally_gold_prod'=>['gold_pct',.0075]];
        foreach($rows as$row){[$key,$amount]=$map[$row['research_code']];$result[$key]+=(int)$row['level']*$amount;}
        return $result;
    }
}
