<?php
declare(strict_types=1);
namespace Conquer\Game\Rewards;

use Conquer\Db\Connection;
use Conquer\Game\Inventory\InventoryService;
use Conquer\Game\Treasure\TreasureData;
use Conquer\Game\World\WorldSettings;

/** Shared rules: world override, global override, then shipped defaults. */
final class RewardCatalog
{
    private static ?Connection $connection = null;
    private static ?array $overrides = null;
    private static array $worldOverrides = [];

    public static function resetCache(): void { self::$overrides = null; self::$worldOverrides=[]; }

    public static function records(int $worldId=0): array
    {
        if (!Connection::isInitialized()) return [];
        $db = Connection::getInstance();
        if (self::$connection !== $db) { self::$connection = $db; self::resetCache(); }
        if($worldId>0){
            $global=self::records(0);
            foreach(self::worldRecords($worldId) as $key=>$row)if($row['config']!==null)$global[$key]=$row;
            return $global;
        }
        if (self::$overrides !== null) return self::$overrides;
        try { $rows = $db->query('SELECT * FROM reward_overrides')->fetchAll(); }
        catch (\PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) !== 1146) throw $e;
            return self::$overrides = []; // Older installations use the shipped catalog.
        }
        $out = [];
        foreach ($rows as $row) {
            $row['config'] = $row['config_json'] === null ? null : json_decode($row['config_json'], true, 64, JSON_THROW_ON_ERROR);
            $out[$row['source_type'].':'.$row['source_key']] = $row;
        }
        return self::$overrides = $out;
    }

    public static function worldRecords(int $worldId): array
    {
        if(!Connection::isInitialized())return [];
        self::records(0);
        if(isset(self::$worldOverrides[$worldId]))return self::$worldOverrides[$worldId];
        try{$rows=Connection::getInstance()->query('SELECT * FROM reward_world_overrides WHERE world_id=?',[$worldId])->fetchAll();}
        catch(\PDOException $e){if((int)($e->errorInfo[1]??0)!==1146)throw $e;return self::$worldOverrides[$worldId]=[];}
        $out=[];
        foreach($rows as $row){$row['config']=$row['config_json']===null?null:json_decode($row['config_json'],true,64,JSON_THROW_ON_ERROR);$out[$row['source_type'].':'.$row['source_key']]=$row;}
        return self::$worldOverrides[$worldId]=$out;
    }

    public static function override(string $type, string $key,?int $worldId=null): ?array
    {
        return self::records($worldId??\Conquer\Game\World\WorldContext::id())[$type.':'.$key]['config'] ?? null;
    }

    public static function json(string $file): array
    {
        static $files = [];
        return $files[$file] ??= json_decode((string)file_get_contents(dirname(__DIR__, 3).'/data/'.$file.'.json'), true, 128, JSON_THROW_ON_ERROR);
    }

    public static function sources(string $type): array
    {
        $out = [];
        if ($type === 'monster') {
            foreach (self::json('monsters')['monsters'] as $d) {
                $raw=$d;
                $d=\Conquer\Game\Map\MonsterData::definition((int)$raw['code']);
                $name = ['Orc'=>'Ork','Skeleton'=>'Skelett','Golem'=>'Golem','Treasure Goblin'=>'Schatzgoblin'][$d['name']] ?? $d['name'];
                $key = (string)$raw['code'];
                $active=\Conquer\Game\Map\MonsterData::isActive((int)$key);
                $out[$key] = ['key'=>$key,'name'=>$name,'subtitle'=>'Stufe '.$d['level'].' · '.($d['type']==='rally'?'Rally-Boss':'Solo-Monster').($active?'':' · Noch nicht aktiv'),'active'=>$active,'catalog_level'=>(int)$raw['level'],'image'=>self::monsterImage($d),'definition'=>$d];
            }
        } elseif ($type === 'dungeon') {
            foreach (self::json('dungeons')['dungeons'] as $d) {
                $key = $d['dungeon_code'];
                $out[$key] = ['key'=>$key,'name'=>$d['name'],'subtitle'=>$d['theme'],'image'=>'dungeons/'.$key.'.webp','definition'=>$d];
            }
        } elseif ($type === 'chest') {
            foreach (self::json('chest_drops')['chests'] as $key=>$d) $out[$key] = ['key'=>$key,'name'=>['silver'=>'Silbertruhe','gold'=>'Goldtruhe','platinum'=>'Platintruhe'][$key] ?? $key,'subtitle'=>'Schatzkammer · gewichtete Ziehungen','image'=>'items/chest-'.$key.'.svg','definition'=>$d];
        } elseif ($type === 'expedition') {
            foreach (\Conquer\Game\Expedition\EncounterCatalog::BOSSES as $boss=>$b) foreach (\Conquer\Game\Expedition\EncounterCatalog::DIFFICULTIES as $difficulty=>$d) {
                $key = $boss.'.'.$difficulty;
                $out[$key] = ['key'=>$key,'name'=>$b['name'],'subtitle'=>$d['name'],'image'=>$b['image'],'definition'=>['resources'=>array_map(static fn($n)=>$n*$b['chapter']*$d['factor'], \Conquer\Game\Expedition\ExpeditionRules::REWARD),'drops'=>[],'gems'=>0]];
            }
        } else throw new \InvalidArgumentException('Unbekannte Beutequelle.');
        return $out;
    }

    private static function monsterImage(array $d): string
    {
        $name = strtolower($d['name']);
        foreach (['grumwald','frostgrimm','glutramm','sandmaul'] as $boss) if (str_contains($name,$boss)) return 'monsters/'.$boss.'.png';
        if (str_contains($name,'skeleton')) return 'skeleton.png';
        if (str_contains($name,'golem')) return 'golem.png';
        if (str_contains($name,'goblin')) return 'map/life-goblin.png';
        if (str_contains($name,'orc') || str_contains($name,'ork')) return 'orc.png';
        return 'hud/expeditions.svg';
    }

    public static function defaults(string $type, string $key): array
    {
        $source = self::sources($type)[$key] ?? null;
        if (!$source) throw new \InvalidArgumentException('Diese Beutequelle wurde nicht gefunden.');
        $d = $source['definition'];
        if ($type === 'monster') {
            $drops = $d['drops'] ?? [];
            if ($d['type'] === 'rally' && !isset($d['source_code'])) {
                $name = strtolower($d['name']);
                $family = $d['reward_family'] ?? (str_contains($name,'magdar')?'magdar':(str_contains($name,'deathkar')?'deathkar':'dragon'));
                $drops = self::json('rally_rewards')[$family];
            }
            $level = (int)$d['level']; $family = (int)floor((int)$key/100)%100;
            $weights = $level<=3?[82,18,0]:($level<=6?[70,30,0]:($level<=8?[0,80,20]:[0,50,50]));
            return ['drops'=>self::availableDrops($drops),
                'resource_reward'=>$d['resource_reward']??['food'=>100,'lumber'=>100,'stone'=>50,'gold'=>50],
                'gems_drop'=>$d['gems_drop']??['chance'=>0,'amount'=>0],
                'charms'=>['chance'=>1,'normal'=>$weights[0],'epic'=>$weights[1],'legendary'=>$weights[2]]];
        }
        if ($type === 'dungeon') return ['treasure_code'=>(int)$d['treasure_code'],'fragments'=>3,'item_chance'=>.18,'item_quantity'=>1,'items'=>array_map(static fn($c)=>['item_code'=>(int)$c,'weight'=>1],$d['item_codes'])];
        return $d;
    }

    public static function effective(string $type, string $key,?int $worldId=null): array
    {
        $config=self::override($type,$key,$worldId) ?? self::defaults($type,$key);
        if($type==='monster')$config['charms']['chance']=1;
        return $config;
    }

    public static function monster(array $d): array
    {
        $key=(string)($d['spawn_code']??$d['code']??'');
        static $catalogCodes=null;
        $catalogCodes??=array_fill_keys(array_map('strval',array_column(self::json('monsters')['monsters'],'code')),true);
        // A few retired world aliases (for example Orc 20200100) have no editor row.
        if(!isset($catalogCodes[$key]))$key=(string)($d['code']??$key);
        $cfg = self::override('monster',$key);
        if ($cfg === null) { $d['drops']=self::availableDrops($d['drops']??[]);return $d; }
        $cfg['charms']['chance']=1;
        return array_replace($d, ['drops'=>$cfg['drops'],'resource_reward'=>$cfg['resource_reward'],'gems_drop'=>$cfg['gems_drop'],'admin_charms'=>$cfg['charms'],'admin_reward_override'=>true]);
    }

    /** Legacy monster tables contain retired IDs. Never award unknown inventory codes. */
    public static function availableDrops(array $drops): array
    {
        $out=[];
        foreach($drops as $v)if(InventoryService::getItemDef((int)$v['item_code'])!==null)$out[]=['item_code'=>(int)$v['item_code'],'count'=>(int)$v['count'],'probability'=>(float)$v['probability']];
        return $out;
    }

    public static function validate(string $type, string $key, mixed $input): array
    {
        self::defaults($type,$key); // Allowlisted source, never a user-provided file path.
        if (!is_array($input)) throw new \InvalidArgumentException('Die Beuteeinstellungen fehlen.');
        $integer = static fn($v,$min,$max,$label)=>WorldSettings::integer($v,$min,$max,$label);
        $chance = static function($v,string $label): float {
            if (!is_scalar($v)||!is_numeric($v)||!is_finite((float)$v)||(float)$v<0||(float)$v>100) throw new \InvalidArgumentException($label.': bitte 0 bis 100 Prozent eingeben.');
            return round((float)$v/100,6);
        };
        $resources = static function($values)use($integer):array {
            if (!is_array($values)) throw new \InvalidArgumentException('Rohstoffmengen fehlen.');
            $out=[]; foreach (['food','lumber','stone','gold'] as $r) $out[$r]=$integer($values[$r]??null,0,1000000000,'Rohstoffmenge');
            return $out;
        };
        $rows = $input['rows'] ?? [];
        if (!is_array($rows)||count($rows)>200) throw new \InvalidArgumentException('Maximal 80 Beuteeinträge pro Quelle.');
        $entries=[]; $seen=[];
        foreach ($rows as $row) {
            if (!is_array($row)) throw new \InvalidArgumentException('Ungültiger Beuteeintrag.');
            $target=$row['target']??null;
            if (!is_string($target)&&!is_int($target)) throw new \InvalidArgumentException('Bitte einen Gegenstand auswählen.');
            $entry=[];
            if ($type==='chest' && in_array($target,['fragment:normal','fragment:rare','fragment:epic','fragment:legendary','fragment:mythic'],true)) $entry['fragment_grade']=substr($target,9);
            else {
                $code=$integer($target,1,2147483647,'Gegenstand');
                if (InventoryService::getItemDef($code)===null) throw new \InvalidArgumentException('Dieser Gegenstand existiert nicht im Katalog.');
                $entry['item_code']=$code;
            }
            if (isset($seen[(string)$target])) throw new \InvalidArgumentException('Jeder Gegenstand darf nur einmal in der Beuteliste stehen.');
            $seen[(string)$target]=true;
            if ($type==='chest'||$type==='dungeon') $entry['weight']=$integer($row['weight']??null,0,1000000,'Gewichtung');
            else $entry['probability']=$chance($row['chance']??null,'Dropchance');
            if ($type!=='dungeon') $entry[$type==='chest'?'quantity':'count']=$integer($row['quantity']??null,1,100000,'Anzahl');
            $entries[]=$entry;
        }
        if ($type==='chest') {
            if (array_sum(array_column($entries,'weight'))<1) throw new \InvalidArgumentException('Die Truhe braucht mindestens einen Eintrag mit positiver Gewichtung.');
            return ['rolls'=>$integer($input['rolls']??null,1,20,'Ziehungen'),'drop_table'=>$entries];
        }
        if ($type==='dungeon') {
            $code=$integer($input['treasure_code']??null,1,2147483647,'Relikt');
            if (!TreasureData::get($code)) throw new \InvalidArgumentException('Unbekanntes Relikt.');
            $probability=$chance($input['item_chance']??null,'Chance auf einen Gegenstand');
            if ($probability>0 && array_sum(array_column($entries,'weight'))<1) throw new \InvalidArgumentException('Wähle mindestens einen Gegenstand mit positiver Gewichtung oder setze die Itemchance auf 0 %.');
            return ['treasure_code'=>$code,'fragments'=>$integer($input['fragments']??null,0,10000,'Fragmente'),'item_chance'=>$probability,'item_quantity'=>$integer($input['item_quantity']??null,1,100000,'Itemmenge'),'items'=>$entries];
        }
        if ($type==='expedition') return ['resources'=>$resources($input['resources']??null),'gems'=>$integer($input['gems']??null,0,1000000,'Edelsteine'),'drops'=>$entries];
        $charms=$input['charms']??[];
        if (!is_array($charms)) throw new \InvalidArgumentException('Ungültige Talisman-Einstellung.');
        $c=['chance'=>1]; // Every defeated monster leaves exactly one map charm.
        foreach (['normal','epic','legendary'] as $grade) $c[$grade]=$integer($charms[$grade]??null,0,100,'Talismanverteilung');
        if (array_sum([$c['normal'],$c['epic'],$c['legendary']])!==100) throw new \InvalidArgumentException('Die drei Talisman-Anteile müssen zusammen 100 % ergeben.');
        return ['drops'=>$entries,'resource_reward'=>$resources($input['resources']??null),'gems_drop'=>['chance'=>$chance($input['gems_chance']??null,'Edelsteinchance'),'amount'=>$integer($input['gems_amount']??null,0,1000000,'Edelsteinmenge')],'charms'=>$c];
    }

    public static function roll(float $probability): bool
    {
        return $probability >= 1 || ($probability > 0 && random_int(1,1000000) <= (int)round($probability*1000000));
    }

    public static function rollItems(array $drops): array
    {
        $items=[];
        foreach ($drops as $drop) if (self::roll((float)$drop['probability'])) {
            $code=(int)$drop['item_code'];$items[$code]=($items[$code]??0)+(int)$drop['count'];
        }
        return $items;
    }
}
