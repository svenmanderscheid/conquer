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
    private const UNLOCK_COST = 10;
    private const EFFECT_UPGRADE_BASE_COST = 10;
    private const EFFECT_CURVE = [0.0,0.10,0.25,0.45,0.70,1.0];
    /** Gewöhnliche Relikte: zwei frei aufwertbare Effekte mit je einem Meisterbonus. */
    private const EFFECT_OVERRIDES = [
        60100001=>[['food_production',5,'food_production',5],['food_storage_capacity',5,'gathering_speed',3]],
        60100107=>[['lumber_production',5,'lumber_production',5],['lumber_storage_capacity',5,'gathering_speed',3]],
        60100101=>[['stone_production',5,'stone_production',5],['stone_storage_capacity',5,'gathering_speed',3]],
        60100105=>[['gold_production',5,'gold_production',5],['gold_storage_capacity',5,'gathering_speed',3]],
        60100103=>[['food_gathering_speed',5,'food_gathering_speed',5],['food_protection_capacity',5,'resource_production',3]],
        60100002=>[['lumber_gathering_speed',5,'lumber_gathering_speed',5],['lumber_protection_capacity',5,'resource_production',3]],
        60100003=>[['stone_gathering_speed',5,'stone_gathering_speed',5],['stone_protection_capacity',5,'resource_production',3]],
        60100102=>[['gold_gathering_speed',5,'gold_gathering_speed',5],['gold_protection_capacity',5,'resource_production',3]],
        60100104=>[['infantry_attack',3,'ranged_attack',3],['infantry_speed',3,'infantry_load',3]],
        60100108=>[['ranged_attack',3,'cavalry_attack',3],['ranged_speed',3,'ranged_load',3]],
        60100106=>[['cavalry_attack',3,'cavalry_speed',3],['infantry_speed',3,'cavalry_load',3]],
        60100109=>[['infantry_load',3,'infantry_defense',3],['infantry_defense',3,'infantry_hp',3]],
        60100006=>[['ranged_load',3,'ranged_defense',3],['ranged_defense',3,'ranged_hp',3]],
        60200003=>[['cavalry_load',3,'cavalry_defense',3],['cavalry_defense',3,'cavalry_hp',3]],
    ];
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
        $progress=[];
        foreach($db->query('SELECT treasure_code,effect_index,parts FROM player_treasure_effects WHERE player_id=?',[$playerId])->fetchAll() as $row)$progress[(int)$row['treasure_code']][(int)$row['effect_index']]=(int)$row['parts'];
        $items = [];
        foreach (TreasureData::all() as $code=>$definition) {
            if(!empty($definition['legacy_only'])&&!isset($owned[$code]))continue;
            $row = $owned[$code] ?? [];
            $fragments = (int)($row['fragments'] ?? 0);
            $parts=$progress[$code]??[];
            $unlocked=isset($parts[0])&&$parts[0]>=1;
            $effects=self::effectState($definition,$parts,$code);
            $active=self::effectStats($definition,$parts,true,$code);
            $boosts=self::effectStats($definition,$parts,false,$code);
            $preview=self::effectStats($definition,[0=>1],false,$code);
            $level=$unlocked?max(1,count(array_filter($effects,static fn(array $effect):bool=>$effect['master_unlocked']))):0;
            $max=max(1,count($effects));
            $unsupported = array_diff_key($preview,array_flip(self::ACTIVE_STATS));
            $items[] = [
                'treasure_code'=>$code,'name'=>(string)$definition['name'],'grade'=>(string)$definition['grade'],
                'name_de'=>(string)($definition['name_de'] ?? $definition['name']),
                'icon'=>(string)($definition['icon'] ?? ''),'icon_framed'=>(bool)($definition['icon_framed'] ?? false),
                'source_reference'=>$definition['source_reference'] ?? null,
                'description'=>(string)($definition['description'] ?? ''),'fragments'=>$fragments,
                'fragments_next'=>0,
                'fragments_per_level'=>(int)$definition['fragments_per_level'],'level'=>$level,'max_level'=>$max,
                'equipped_slot'=>isset($row['equipped_slot'])?(int)$row['equipped_slot']:null,
                'stats_at_level'=>$unlocked?$active:[],
                'preview_stats'=>$preview,
                'next_level_stats'=>[],
                'boost_stats_at_level'=>$unlocked?$boosts:[],
                'next_boost_stats'=>[],
                'effects'=>$effects,
                'master_bonus'=>self::effectMasterSummary($effects),
                'is_unlocked'=>$unlocked,'is_usable'=>(bool)$effects,
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
            'bonuses'=>self::getEquippedStats($playerId,$worldId),'presets'=>self::getPresets($playerId,$worldId),
            'universal_fragments'=>self::universalFragments($playerId)];
    }

    public static function universalFragments(int $playerId): array
    {
        $result=['normal'=>0,'rare'=>0,'epic'=>0,'legendary'=>0,'mythic'=>0];
        foreach(Connection::getInstance()->query('SELECT grade,quantity FROM player_universal_treasure_fragments WHERE player_id=?',[$playerId])->fetchAll() as $row)$result[(string)$row['grade']]=(int)$row['quantity'];
        return $result;
    }

    public static function exchangeUniversalFragments(int $playerId,int $treasureCode,int $amount): array
    {
        $definition=TreasureData::get($treasureCode);
        if(!$definition||$amount<1||$amount>10000)throw new \DomainException('Ungültiger Fragmenttausch.');
        $grade=(string)($definition['grade']??'normal');
        return self::atomic($playerId,static function(Connection $db)use($playerId,$treasureCode,$amount,$grade):array{
            $db->execute('INSERT IGNORE INTO player_universal_treasure_fragments(player_id,grade,quantity) VALUES(?,?,0)',[$playerId,$grade]);
            $available=(int)$db->query('SELECT quantity FROM player_universal_treasure_fragments WHERE player_id=? AND grade=? FOR UPDATE',[$playerId,$grade])->fetchColumn();
            if($available<$amount)throw new \DomainException('Du hast nicht genug Allround-Fragmente dieser Seltenheit.');
            $db->execute('UPDATE player_universal_treasure_fragments SET quantity=quantity-? WHERE player_id=? AND grade=?',[$amount,$playerId,$grade]);
            $db->execute('INSERT INTO player_treasures(player_id,treasure_code,fragments) VALUES(?,?,?) ON DUPLICATE KEY UPDATE fragments=fragments+VALUES(fragments)',[$playerId,$treasureCode,$amount]);
            $newlyUnlocked=self::unlockIfReady($db,$playerId,$treasureCode);
            $total=(int)$db->query('SELECT fragments FROM player_treasures WHERE player_id=? AND treasure_code=?',[$playerId,$treasureCode])->fetchColumn();
            return ['message'=>$newlyUnlocked?'Relikt freigeschaltet: Der erste Teilstern ist aktiv.':$amount.' Allround-Fragmente umgetauscht.','grade'=>$grade,'remaining'=>$available-$amount,'fragments'=>$total,'newly_unlocked'=>$newlyUnlocked];
        });
    }

    public static function upgradeEffect(int $playerId,int $treasureCode,int $effectIndex): array
    {
        $definition=TreasureData::get($treasureCode);
        $effects=$definition?self::effectDefinitions($definition,$treasureCode):[];
        if(!$definition||!isset($effects[$effectIndex]))throw new \DomainException('Dieser Relikt-Effekt ist nicht verfügbar.');
        return self::atomic($playerId,static function(Connection $db)use($playerId,$treasureCode,$effectIndex,$definition):array{
            $owned=$db->query('SELECT fragments FROM player_treasures WHERE player_id=? AND treasure_code=? FOR UPDATE',[$playerId,$treasureCode])->fetchColumn();
            if($owned===false)throw new \DomainException('Dieses Relikt ist noch nicht freigeschaltet.');
            $parts=$db->query('SELECT parts FROM player_treasure_effects WHERE player_id=? AND treasure_code=? AND effect_index=? FOR UPDATE',[$playerId,$treasureCode,$effectIndex])->fetchColumn();
            if($parts===false){
                if(!self::isUnlocked($db,$playerId,$treasureCode))throw new \DomainException('Dieses Relikt ist noch nicht freigeschaltet.');
                $parts=0;
            }
            $parts=(int)$parts;
            if($parts>=5)throw new \DomainException('Dieser Effekt ist bereits vollständig aufgewertet.');
            $cost=self::effectUpgradeCost($definition,$parts);
            if((int)$owned<$cost)throw new \DomainException('Du hast nicht genug Reliktfragmente.');
            $cities=$db->query('SELECT c.* FROM cities c JOIN player_treasure_loadouts l ON l.player_id=c.player_id AND l.world_id=c.world_id WHERE c.player_id=? AND l.treasure_code=? ORDER BY c.world_id FOR UPDATE',[$playerId,$treasureCode])->fetchAll();
            foreach($cities as $city)self::settle($city);
            $db->execute('UPDATE player_treasures SET fragments=fragments-? WHERE player_id=? AND treasure_code=?',[$cost,$playerId,$treasureCode]);
            $db->execute('INSERT INTO player_treasure_effects(player_id,treasure_code,effect_index,parts) VALUES(?,?,?,1) ON DUPLICATE KEY UPDATE parts=parts+1',[$playerId,$treasureCode,$effectIndex]);
            return ['message'=>$parts+1===5?'Effekt gemeistert – Meisterbonus freigeschaltet.':'Relikt-Effekt aufgewertet.','effect_index'=>$effectIndex,'parts'=>$parts+1,'spent'=>$cost,'fragments'=>(int)$owned-$cost];
        });
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
            $db->execute('UPDATE player_treasures SET fragments=fragments+? WHERE player_id=? AND treasure_code=?',[$amount,$playerId,$treasureCode]);
            $newlyUnlocked=self::unlockIfReady($db,$playerId,$treasureCode);
            $remaining=(int)$db->query('SELECT fragments FROM player_treasures WHERE player_id=? AND treasure_code=?',[$playerId,$treasureCode])->fetchColumn();
            return ['fragments'=>$remaining,'level'=>$newlyUnlocked?2:(self::isUnlocked($db,$playerId,$treasureCode)?2:0),'newly_unlocked'=>$newlyUnlocked];
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
            if($fragments===false || !self::isUnlocked($db,$playerId,$treasureCode))return false;
            if(!self::effectDefinitions(TreasureData::get($treasureCode),$treasureCode))return false;
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
        return self::equippedStats($playerId,$worldId,false);
    }

    /** Runtime bonuses preserve percent/flat units before effects are aggregated. */
    public static function getEquippedBuffs(int $playerId,?int $worldId=null): array
    {
        return self::equippedStats($playerId,$worldId,true);
    }

    private static function equippedStats(int $playerId,?int $worldId,bool $forBuffs): array
    {
        $worldId??=WorldContext::id();
        $slots=TreasureData::getUnlockSlots(self::houseLevel($playerId,$worldId));
        $db=Connection::getInstance();
        $rows=$db->query('SELECT t.treasure_code,t.fragments FROM player_treasure_loadouts l
            JOIN player_treasures t ON t.player_id=l.player_id AND t.treasure_code=l.treasure_code
            WHERE l.player_id=? AND l.world_id=? AND l.slot<=?',[$playerId,$worldId,$slots])->fetchAll();
        $result=[];
        foreach($rows as $row){
            $code=(int)$row['treasure_code'];$definition=TreasureData::get($code);$parts=self::effectProgress($db,$playerId,$code);
            if(!$definition||!isset($parts[0]))continue;
            if(!$forBuffs){foreach(self::effectStats($definition,$parts,true,$code)as$key=>$value)$result[$key]=($result[$key]??0)+$value;continue;}
            foreach(self::effectState($definition,$parts,$code) as $effect){
                $values=[[$effect['type'],$effect['current_value'],$effect['unit']??null]];
                if($effect['master_unlocked'])$values[]=[$effect['master_type'],$effect['master_value'],$effect['master_unit']??$effect['unit']??null];
                foreach($values as [$key,$value,$unit]){
                    // Older catalogs used absolute capacity stats without unit metadata.
                    $capacity=in_array($key,['march_capacity','hospital_capacity'],true);
                    $flat=$unit==='flat'||($unit===null&&$capacity);
                    if($capacity)$key=$flat?$key.'_flat':($key==='march_capacity'?'march_size':$key);
                    $result[$key]=($result[$key]??0)+($flat?(float)$value:(float)$value/100);
                }
            }
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
            if($fragments===false||!self::isUnlocked($db,$playerId,$code))throw new \DomainException('Ein Relikt dieses Presets ist noch nicht freigeschaltet.');
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
    private static function effectDefinitions(array $definition,?int $treasureCode=null): array
    {
        if(isset($definition['effects'])&&is_array($definition['effects'])&&$definition['effects']!==[])return array_values(array_map(
            static fn(array $effect):array=>[
                'type'=>(string)($effect['type']??''),
                'label_de'=>(string)($effect['label_de']??''),
                'boost_max'=>(float)($effect['boost_max']??0),
                'master_type'=>(string)($effect['master_type']??$effect['type']??''),
                'master_label_de'=>(string)($effect['master_label_de']??''),
                'master_value'=>(float)($effect['master_value']??0),
                'unit'=>(string)($effect['unit']??'percent'),
                'master_unit'=>(string)($effect['master_unit']??$effect['unit']??'percent'),
            ],
            $definition['effects']
        ));
        if($treasureCode!==null&&isset(self::EFFECT_OVERRIDES[$treasureCode]))return array_map(
            static fn(array $effect):array=>['type'=>$effect[0],'boost_max'=>(float)$effect[1],'master_type'=>$effect[2],'master_value'=>(float)$effect[3]],
            self::EFFECT_OVERRIDES[$treasureCode]
        );
        $effects=[];
        foreach($definition['stats']??[] as $stat){
            $type=(string)($stat['type']??'');
            if(!in_array($type,self::ACTIVE_STATS,true))continue;
            $boostMax=(float)($stat['base_value']??0)+(float)($stat['per_level']??0)*4;
            $master=(float)($stat['per_level']??0)*5;
            $effects[]=['type'=>$type,'boost_max'=>$boostMax,'master_type'=>$type,'master_value'=>$master];
        }
        return $effects;
    }
    private static function effectState(array $definition,array $parts,?int $treasureCode=null): array
    {
        $result=[];
        foreach(self::effectDefinitions($definition,$treasureCode) as $index=>$effect){
            $part=max(0,min(5,(int)($parts[$index]??0)));
            $current=$effect['boost_max']*self::EFFECT_CURVE[$part];
            $next=$part<5?$effect['boost_max']*self::EFFECT_CURVE[$part+1]:$current;
            $result[]=$effect+['index'=>$index,'parts'=>$part,'current_value'=>$current,'next_value'=>$next,
                'upgrade_cost'=>$part<5?self::EFFECT_UPGRADE_BASE_COST*($part+1):0,'master_unlocked'=>$part>=5];
        }
        return $result;
    }
    private static function effectStats(array $definition,array $parts,bool $includeMaster,?int $treasureCode=null): array
    {
        $stats=[];
        foreach(self::effectState($definition,$parts,$treasureCode) as $effect){
            $stats[$effect['type']]=($stats[$effect['type']]??0)+(float)$effect['current_value'];
            if($includeMaster&&$effect['master_unlocked'])$stats[$effect['master_type']]=($stats[$effect['master_type']]??0)+(float)$effect['master_value'];
        }
        return $stats;
    }
    private static function effectMasterSummary(array $effects): array
    {
        $current=[];$maximum=[];
        foreach($effects as $effect){
            $type=(string)$effect['master_type'];$maximum[$type]=($maximum[$type]??0)+(float)$effect['master_value'];
            if($effect['master_unlocked'])$current[$type]=($current[$type]??0)+(float)$effect['master_value'];
        }
        return ['unlock_level'=>5,'is_unlocked'=>$effects!==[]&&count(array_filter($effects,static fn(array $effect):bool=>$effect['master_unlocked']))===count($effects),'stats'=>$current,'max_stats'=>$maximum];
    }
    private static function effectUpgradeCost(array $definition,int $parts): int
    {
        return self::EFFECT_UPGRADE_BASE_COST*(max(0,min(4,$parts))+1);
    }
    private static function effectProgress(Connection $db,int $playerId,int $treasureCode): array
    {
        $result=[];
        foreach($db->query('SELECT effect_index,parts FROM player_treasure_effects WHERE player_id=? AND treasure_code=?',[$playerId,$treasureCode])->fetchAll() as $row)$result[(int)$row['effect_index']]=(int)$row['parts'];
        return $result;
    }
    private static function isUnlocked(Connection $db,int $playerId,int $treasureCode): bool
    {
        return $db->query('SELECT 1 FROM player_treasure_effects WHERE player_id=? AND treasure_code=? AND effect_index=0 AND parts>=1',[$playerId,$treasureCode])->fetchColumn()!==false;
    }
    private static function unlockIfReady(Connection $db,int $playerId,int $treasureCode): bool
    {
        if(self::isUnlocked($db,$playerId,$treasureCode))return false;
        $available=$db->query('SELECT fragments FROM player_treasures WHERE player_id=? AND treasure_code=? FOR UPDATE',[$playerId,$treasureCode])->fetchColumn();
        if($available===false||(int)$available<self::UNLOCK_COST)return false;
        $definition=TreasureData::get($treasureCode);
        if(!$definition||!self::effectDefinitions($definition,$treasureCode))return false;
        $db->execute('UPDATE player_treasures SET fragments=fragments-? WHERE player_id=? AND treasure_code=?',[self::UNLOCK_COST,$playerId,$treasureCode]);
        $db->execute('INSERT INTO player_treasure_effects(player_id,treasure_code,effect_index,parts) VALUES(?,?,0,1)',[$playerId,$treasureCode]);
        return true;
    }
    private static function stats(array $definition,int $level): array
    {
        $stats=[];foreach($definition['stats']as$stat)$stats[$stat['type']]=TreasureData::getStatValue($definition,$level,$stat['type']);return $stats;
    }
    private static function activeStats(array $definition,int $level): array {return array_intersect_key(self::stats($definition,$level),array_flip(self::ACTIVE_STATS));}
    /**
     * Levels 1-5 build the normal treasure boost. Levels 6-10 add the
     * master portion. Both parts still sum to the established stat formula.
     */
    private static function masterBonus(array $definition,int $level): array
    {
        $current=[];$maximum=[];
        foreach($definition['stats'] as $stat){
            $type=(string)($stat['type']??'');
            if(!in_array($type,self::ACTIVE_STATS,true))continue;
            $step=(float)($stat['per_level']??0);
            $current[$type]=$step*max(0,min(5,$level-5));
            $maximum[$type]=$step*5;
        }
        return ['unlock_level'=>6,'is_unlocked'=>$level>=6,'stats'=>$current,'max_stats'=>$maximum];
    }
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
