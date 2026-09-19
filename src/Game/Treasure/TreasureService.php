<?php
declare(strict_types=1);
namespace Conquer\Game\Treasure;

use Conquer\Db\Connection;
use Conquer\Game\City\ResourceTick;
use Conquer\Game\World\WorldContext;

/** Account-wide collection and world-specific, atomically replaced equipment. */
final class TreasureService
{
    public const SLOT_UNLOCK_LEVELS = [1,1,5,10,20,25];
    // Each catalog key has a real consumer through BuffEngine. Values below are
    // percentages, except the two capacities, which are flat troop counts.
    public const ACTIVE_STATS = ['all_attack','all_defense','all_hp','cavalry_attack','construction_speed',
        'food_production','gathering_speed','gold_production','infantry_defense','infantry_hp','lumber_production',
        'march_speed','ranged_attack','research_speed','stone_production','training_speed','vs_monster_attack',
        'march_capacity','hospital_capacity','resource_protection'];

    public static function getPlayerTreasures(int $playerId, ?int $worldId = null): array
    {
        $worldId ??= WorldContext::id();
        $db = Connection::getInstance();
        $rows = $db->query('SELECT t.treasure_code,t.fragments,l.slot AS equipped_slot FROM player_treasures t
            LEFT JOIN player_treasure_loadouts l ON l.player_id=t.player_id AND l.treasure_code=t.treasure_code AND l.world_id=?
            WHERE t.player_id=?', [$worldId,$playerId])->fetchAll();
        $owned = []; foreach ($rows as $row) $owned[(int)$row['treasure_code']] = $row;
        $items = [];
        foreach (TreasureData::all() as $code=>$definition) {
            $row = $owned[$code] ?? [];
            $fragments = (int)($row['fragments'] ?? 0);
            $level = self::levelFromFragments($fragments,$code);
            $max = (int)($definition['max_level'] ?? 10);
            $preview = self::stats($definition,1);
            $unsupported = array_diff_key($preview,array_flip(self::ACTIVE_STATS));
            $items[] = [
                'treasure_code'=>$code,'name'=>(string)$definition['name'],'grade'=>(string)$definition['grade'],
                'name_de'=>(string)($definition['name_de'] ?? $definition['name']),
                'icon'=>(string)($definition['icon'] ?? ''),'icon_framed'=>(bool)($definition['icon_framed'] ?? false),
                'source_reference'=>$definition['source_reference'] ?? null,
                'description'=>(string)($definition['description'] ?? ''),'fragments'=>$fragments,
                'fragments_next'=>self::fragmentsForNextLevel($definition,$level),
                'fragments_per_level'=>(int)$definition['fragments_per_level'],'level'=>$level,'max_level'=>$max,
                'equipped_slot'=>isset($row['equipped_slot'])?(int)$row['equipped_slot']:null,
                'stats_at_level'=>$level>0?self::activeStats($definition,$level):[],
                'preview_stats'=>self::activeStats($definition,1),
                'next_level_stats'=>$level<$max?self::activeStats($definition,$level+1):[],
                'is_unlocked'=>$level>0,'is_usable'=>(bool)self::activeStats($definition,1),
                'unsupported_stats'=>$unsupported,
                'effect_note'=>$unsupported?'Zusätzliche Sammlereffekte sind noch nicht aktiv.':'',
            ];
        }
        return $items;
    }

    public static function state(int $playerId, ?int $worldId = null): array
    {
        $worldId ??= WorldContext::id();
        $house = self::houseLevel($playerId,$worldId);
        return ['items'=>self::getPlayerTreasures($playerId,$worldId),'slots'=>TreasureData::getUnlockSlots($house),
            'house_level'=>$house,'slot_unlock_levels'=>self::SLOT_UNLOCK_LEVELS,'world_id'=>$worldId,
            'bonuses'=>self::getEquippedStats($playerId,$worldId),'presets'=>self::getPresets($playerId,$worldId)];
    }

    public static function houseLevel(int $playerId, ?int $worldId = null): int
    {
        $level = Connection::getInstance()->query('SELECT b.level FROM city_buildings b JOIN cities c ON c.id=b.city_id
            WHERE c.player_id=? AND c.world_id=? AND b.building_code=?',[$playerId,$worldId??WorldContext::id(),'treasure_house'])->fetchColumn();
        return $level===false?0:(int)$level;
    }

    public static function addFragments(int $playerId, int $treasureCode, int $amount): array
    {
        if ($amount<=0 || TreasureData::get($treasureCode)===null) throw new \InvalidArgumentException('Ungültige Schatzfragmente.');
        return self::atomic($playerId,static function(Connection $db)use($playerId,$treasureCode,$amount):array{
            $db->execute('INSERT IGNORE INTO player_treasures(player_id,treasure_code,fragments) VALUES(?,?,0)',[$playerId,$treasureCode]);
            $before=(int)$db->query('SELECT fragments FROM player_treasures WHERE player_id=? AND treasure_code=? FOR UPDATE',[$playerId,$treasureCode])->fetchColumn();
            if($before>4294967295-$amount)throw new \DomainException('Zu viele Schatzfragmente.');
            $oldLevel=self::levelFromFragments($before,$treasureCode);
            $level=self::levelFromFragments($before+$amount,$treasureCode);
            if($level!==$oldLevel){
                // An equipped treasure also changes production when fragments level it up.
                $cities=$db->query('SELECT c.* FROM cities c JOIN player_treasure_loadouts l ON l.player_id=c.player_id AND l.world_id=c.world_id
                    WHERE c.player_id=? AND l.treasure_code=? ORDER BY c.world_id FOR UPDATE',[$playerId,$treasureCode])->fetchAll();
                foreach($cities as $city)self::settle($city);
            }
            $db->execute('UPDATE player_treasures SET fragments=fragments+? WHERE player_id=? AND treasure_code=?',[$amount,$playerId,$treasureCode]);
            return ['fragments'=>$before+$amount,'level'=>$level,'newly_unlocked'=>$oldLevel<1&&$level>=1];
        });
    }

    /** $treasureHouseLevel is retained for old callers; the authoritative city level is read here. */
    public static function equipTreasure(int $playerId,int $treasureCode,int $slot,int $treasureHouseLevel,?int $worldId=null): bool
    {
        $worldId??=WorldContext::id();
        WorldContext::assertActionAvailable($worldId);
        if($slot<1 || $slot>6 || TreasureData::get($treasureCode)===null)return false;
        return self::atomic($playerId,static function(Connection $db)use($playerId,$treasureCode,$slot,$worldId):bool{
            $city=WorldContext::city($playerId,$worldId,true);
            if($slot>TreasureData::getUnlockSlots(self::houseLevel($playerId,$worldId)))return false;
            $fragments=$db->query('SELECT fragments FROM player_treasures WHERE player_id=? AND treasure_code=? FOR UPDATE',[$playerId,$treasureCode])->fetchColumn();
            if($fragments===false || self::levelFromFragments((int)$fragments,$treasureCode)<1)return false;
            if(!self::activeStats(TreasureData::get($treasureCode),1))return false;
            self::settle($city); // Persist at old bonuses before touching either slot.
            self::ensureSlots($playerId,$worldId);
            $db->execute('UPDATE player_treasure_loadouts SET treasure_code=NULL WHERE player_id=? AND world_id=? AND (slot=? OR treasure_code=?)',[$playerId,$worldId,$slot,$treasureCode]);
            $db->execute('UPDATE player_treasure_loadouts SET treasure_code=? WHERE player_id=? AND world_id=? AND slot=?',[$treasureCode,$playerId,$worldId,$slot]);
            return true;
        });
    }

    public static function unequipTreasure(int $playerId,int $treasureCode,?int $worldId=null): bool
    {
        $worldId??=WorldContext::id();
        WorldContext::assertActionAvailable($worldId);
        return self::atomic($playerId,static function(Connection $db)use($playerId,$treasureCode,$worldId):bool{
            $city=WorldContext::city($playerId,$worldId,true);
            if(!$db->query('SELECT slot FROM player_treasure_loadouts WHERE player_id=? AND world_id=? AND treasure_code=? FOR UPDATE',[$playerId,$worldId,$treasureCode])->fetchColumn())return false;
            self::settle($city);
            return $db->execute('UPDATE player_treasure_loadouts SET treasure_code=NULL WHERE player_id=? AND world_id=? AND treasure_code=?',[$playerId,$worldId,$treasureCode])>0;
        });
    }

    public static function getEquippedStats(int $playerId,?int $worldId=null): array
    {
        $worldId??=WorldContext::id();
        $slots=TreasureData::getUnlockSlots(self::houseLevel($playerId,$worldId));
        $rows=Connection::getInstance()->query('SELECT t.treasure_code,t.fragments FROM player_treasure_loadouts l
            JOIN player_treasures t ON t.player_id=l.player_id AND t.treasure_code=l.treasure_code
            WHERE l.player_id=? AND l.world_id=? AND l.slot<=?',[$playerId,$worldId,$slots])->fetchAll();
        $result=[];
        foreach($rows as $row){
            $code=(int)$row['treasure_code'];$definition=TreasureData::get($code);$level=self::levelFromFragments((int)$row['fragments'],$code);
            if(!$definition||$level<1)continue;
            foreach(self::activeStats($definition,$level)as$key=>$value)$result[$key]=($result[$key]??0)+$value;
        }
        return $result;
    }

    /** Five world-specific snapshots; unsaved and deliberately empty are distinct. */
    public static function getPresets(int $playerId,?int $worldId=null): array
    {
        $rows=Connection::getInstance()->query('SELECT preset,items_json FROM player_treasure_presets WHERE player_id=? AND world_id=?',[$playerId,$worldId??WorldContext::id()])->fetchAll();
        $saved=[];foreach($rows as$row)$saved[(int)$row['preset']]=self::decodePreset((string)$row['items_json']);
        $result=[];for($slot=1;$slot<=5;$slot++)$result[]=['slot'=>$slot,'saved'=>isset($saved[$slot]),'items'=>$saved[$slot]??array_fill(0,6,null)];
        return $result;
    }

    public static function savePreset(int $playerId,int $preset,?int $worldId=null): void
    {
        if($preset<1||$preset>5)throw new \DomainException('Wähle einen Speicherplatz von 1 bis 5.');
        $worldId??=WorldContext::id();WorldContext::assertActionAvailable($worldId);
        self::atomic($playerId,static function(Connection $db)use($playerId,$preset,$worldId):void{
            WorldContext::city($playerId,$worldId,true);
            $items=array_fill(0,6,null);
            foreach($db->query('SELECT slot,treasure_code FROM player_treasure_loadouts WHERE player_id=? AND world_id=? ORDER BY slot FOR UPDATE',[$playerId,$worldId])->fetchAll()as$row)$items[(int)$row['slot']-1]=$row['treasure_code']===null?null:(int)$row['treasure_code'];
            self::validatePreset($db,$playerId,$worldId,$items);
            $db->execute('INSERT INTO player_treasure_presets(player_id,world_id,preset,items_json) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE items_json=VALUES(items_json),updated_at=UTC_TIMESTAMP()',[$playerId,$worldId,$preset,json_encode($items,JSON_THROW_ON_ERROR)]);
        });
    }

    public static function applyPreset(int $playerId,int $preset,?int $worldId=null): void
    {
        if($preset<1||$preset>5)throw new \DomainException('Wähle einen Speicherplatz von 1 bis 5.');
        $worldId??=WorldContext::id();WorldContext::assertActionAvailable($worldId);
        self::atomic($playerId,static function(Connection $db)use($playerId,$preset,$worldId):void{
            $city=WorldContext::city($playerId,$worldId,true);
            $json=$db->query('SELECT items_json FROM player_treasure_presets WHERE player_id=? AND world_id=? AND preset=? FOR UPDATE',[$playerId,$worldId,$preset])->fetchColumn();
            if($json===false)throw new \DomainException('Dieser Speicherplatz ist noch leer. Speichere zuerst deine Ausrüstung.');
            $items=self::decodePreset((string)$json);
            self::validatePreset($db,$playerId,$worldId,$items);
            self::settle($city); // Earned resources use the old equipment, once for the whole swap.
            self::ensureSlots($playerId,$worldId);
            $db->execute('UPDATE player_treasure_loadouts SET treasure_code=NULL WHERE player_id=? AND world_id=?',[$playerId,$worldId]);
            foreach($items as$index=>$code)if($code!==null)$db->execute('UPDATE player_treasure_loadouts SET treasure_code=? WHERE player_id=? AND world_id=? AND slot=?',[$code,$playerId,$worldId,$index+1]);
        });
    }

    private static function decodePreset(string $json): array
    {
        $items=json_decode($json,true);
        if(!is_array($items)||!array_is_list($items)||count($items)!==6)throw new \DomainException('Dieses Preset ist ungültig. Speichere es erneut.');
        foreach($items as$code)if($code!==null&&(!is_int($code)||$code<=0))throw new \DomainException('Dieses Preset enthält ein ungültiges Relikt.');
        return $items;
    }

    private static function validatePreset(Connection $db,int $playerId,int $worldId,array $items): void
    {
        $unlocked=TreasureData::getUnlockSlots(self::houseLevel($playerId,$worldId));$seen=[];
        foreach($items as$index=>$code){
            if($code===null)continue;
            if($index+1>$unlocked)throw new \DomainException('Für dieses Preset muss deine Schatzkammer weiter ausgebaut werden.');
            $definition=TreasureData::get($code);
            if(!$definition||!self::activeStats($definition,1)||isset($seen[$code]))throw new \DomainException('Dieses Preset enthält ein ungültiges oder doppeltes Relikt.');
            $fragments=$db->query('SELECT fragments FROM player_treasures WHERE player_id=? AND treasure_code=? FOR UPDATE',[$playerId,$code])->fetchColumn();
            if($fragments===false||self::levelFromFragments((int)$fragments,$code)<1)throw new \DomainException('Ein Relikt dieses Presets ist noch nicht freigeschaltet.');
            $seen[$code]=true;
        }
    }

    public static function addRandomFragment(int $playerId,string $grade,int $amount=1): array
    {
        $codes=TreasureData::getCodesByGrade($grade);
        if(!$codes)throw new \RuntimeException('No treasures found for grade: '.$grade);
        $code=$codes[array_rand($codes)];$definition=TreasureData::get($code);
        $state=self::addFragments($playerId,$code,$amount);
        return ['treasure_code'=>$code,'name'=>(string)($definition['name_de'] ?? $definition['name']),'fragments_added'=>$amount,'new_total'=>$state['fragments']];
    }

    private static function ensureSlots(int $playerId,int $worldId): void
    {
        for($slot=1;$slot<=6;$slot++)Connection::getInstance()->execute('INSERT IGNORE INTO player_treasure_loadouts(player_id,world_id,slot) VALUES(?,?,?)',[$playerId,$worldId,$slot]);
    }
    private static function settle(array $city): void
    {
        $buildings=[];
        foreach(Connection::getInstance()->query('SELECT building_code,level FROM city_buildings WHERE city_id=?',[(int)$city['id']])->fetchAll()as$row)$buildings[$row['building_code']]=['level'=>(int)$row['level']];
        ResourceTick::persist($city,$buildings);
    }
    private static function atomic(int $playerId,callable $operation): mixed
    {
        $db=Connection::getInstance();$key='conquer-player-'.$playerId;
        if((int)$db->query('SELECT GET_LOCK(?,5)',[$key])->fetchColumn()!==1)throw new \RuntimeException('Deine Schatzkammer wird gerade aktualisiert.');
        try{return $db->getPdo()->inTransaction()?$operation($db):$db->transaction($operation);}
        finally{$db->query('SELECT RELEASE_LOCK(?)',[$key]);}
    }
    private static function stats(array $definition,int $level): array
    {
        $stats=[];foreach($definition['stats']as$stat)$stats[$stat['type']]=TreasureData::getStatValue($definition,$level,$stat['type']);return $stats;
    }
    private static function activeStats(array $definition,int $level): array {return array_intersect_key(self::stats($definition,$level),array_flip(self::ACTIVE_STATS));}
    private static function levelFromFragments(int $fragments,int $code): int
    {
        $definition=TreasureData::get($code);if(!$definition)return 0;
        return min((int)($definition['max_level']??10),max(0,intdiv($fragments,max(1,(int)($definition['fragments_per_level']??10)))));
    }
    private static function fragmentsForNextLevel(array $definition,int $level): int
    {
        return $level>=(int)($definition['max_level']??10)?0:($level+1)*(int)$definition['fragments_per_level'];
    }
}
