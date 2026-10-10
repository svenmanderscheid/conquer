<?php
declare(strict_types=1);
namespace Conquer\Game\Rewards;

use Conquer\Db\Connection;
use Conquer\Game\Locale;
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
        $config = self::records($worldId??\Conquer\Game\World\WorldContext::id())[$type.':'.$key]['config'] ?? null;
        if ($config === null) return null;
        foreach (['drops','items','drop_table','fragment_drops','relic_drops'] as $field) {
            if (!isset($config[$field])) continue;
            $config[$field] = array_values(array_filter($config[$field], match($field){'fragment_drops'=>self::isFragmentDropEligible(...),'relic_drops'=>self::isRelicDropEligible(...),default=>self::isDropEligible(...)}));
        }
        // An old chest override containing only retired rewards falls back to its current default pool.
        if ($type === 'chest' && empty($config['drop_table'])) $config['drop_table'] = self::defaults($type, $key)['drop_table'];
        if ($type === 'dungeon' && !TreasureData::get((int)($config['treasure_code']??0))) {
            $config['treasure_code']=self::defaults($type,$key)['treasure_code'];
        }
        return $config;
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
        } elseif ($type === 'farm') {
            $images = [1=>'ui-resources/food.png',2=>'ui-resources/lumber.png',3=>'ui-resources/stone.png',4=>'ui-resources/gold.png',5=>'items/gems.svg'];
            foreach (self::json('field_objects')['objects'] as $d) {
                if (!isset($images[$d['code']-20100100])) continue; // Only the five gatherable resource families.
                $key = $d['code'].'.'.$d['level'];
                $out[$key] = ['key'=>$key,'name'=>\Conquer\Game\Locale::t('admin.drops.field.'.($d['code']-20100100)), 'subtitle'=>\Conquer\Game\Locale::t('admin.drops.level',['level'=>$d['level']]),'image'=>$images[$d['code']-20100100], 'definition'=>$d];
            }
        } elseif ($type === 'dungeon') {
            foreach (self::json('dungeons')['dungeons'] as $d) {
                $key = $d['dungeon_code'];
                $out[$key] = ['key'=>$key,'name'=>$d['name'],'subtitle'=>$d['theme'],'image'=>'dungeons/'.($d['art_code']??$key).'.webp','definition'=>$d];
            }
        } elseif ($type === 'chest') {
            foreach (self::json('chest_drops')['chests'] as $key=>$d) $out[$key] = ['key'=>$key,'name'=>['silver'=>'Blaue Schatztruhe','gold'=>'Goldtruhe','platinum'=>'Platintruhe'][$key] ?? $key,'subtitle'=>'Schatzkammer · gewichtete Ziehungen','image'=>$key==='silver'?'items/daily-chest-blue-v1.png':'items/chest-'.$key.'.svg','definition'=>$d];
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
        foreach (['grumwald','frostgrimm','glutramm','sandmaul'] as $boss) if (str_contains($name,$boss)) return 'monsters/storybook-v2/'.$boss.'.png';
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
        if ($type === 'farm') return ['drops'=>self::availableDrops($d['drops']??[]),'fragment_drops'=>MonsterRewardRules::farmFragments((int)$d['level']),'relic_drops'=>[]];
        if ($type === 'monster') {
            $drops = $d['drops'] ?? [];
            if ($d['type'] === 'rally' && !isset($d['source_code'])) {
                $name = strtolower($d['name']);
                $family = $d['reward_family'] ?? (str_contains($name,'magdar')?'magdar':(str_contains($name,'deathkar')?'deathkar':'dragon'));
                $drops = self::json('rally_rewards')[$family];
            }
            $drops=MonsterRewardRules::rallyDrops($d,$drops);
            $level = (int)$d['level']; $family = (int)floor((int)$key/100)%100;
            $weights = $level<=3?[82,18,0]:($level<=6?[70,30,0]:($level<=8?[0,80,20]:[0,50,50]));
            return ['drops'=>self::availableDrops($drops),'fragment_drops'=>$d['fragment_drops']??MonsterRewardRules::fragments($level),'relic_drops'=>[],
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
        foreach (['drops','items','drop_table','fragment_drops','relic_drops'] as $field) if (isset($config[$field]))
            $config[$field]=array_values(array_filter($config[$field],match($field){'fragment_drops'=>self::isFragmentDropEligible(...),'relic_drops'=>self::isRelicDropEligible(...),default=>self::isDropEligible(...)}));
        if(in_array($type,['monster','farm'],true)){$config['fragment_drops']??=[];$config['relic_drops']??=[];}
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
        if ($cfg === null) { $d['drops']=self::availableDrops($d['drops']??[]);$d['fragment_drops']??=MonsterRewardRules::fragments((int)$d['level']);$d['relic_drops']??=[];return $d; }
        $cfg['charms']['chance']=1;
        return array_replace($d, ['drops'=>$cfg['drops'],'fragment_drops'=>$cfg['fragment_drops']??[],'relic_drops'=>$cfg['relic_drops']??[],'resource_reward'=>$cfg['resource_reward'],'gems_drop'=>$cfg['gems_drop'],'admin_charms'=>$cfg['charms'],'admin_reward_override'=>true]);
    }

    /** Legacy monster tables contain retired IDs. Never award unknown inventory codes. */
    public static function availableDrops(array $drops): array
    {
        $out=[];
        foreach($drops as $v)if(InventoryService::isDropEligible((int)$v['item_code']))$out[]=['item_code'=>(int)$v['item_code'],'count'=>(int)$v['count'],'probability'=>(float)$v['probability']]+array_intersect_key($v,['count_min'=>true]);
        return $out;
    }

    /** Apply the same eligibility rules to previews, saved overrides and actual rolls. */
    public static function isDropEligible(array $row): bool
    {
        if(isset($row['item_code'])&&!InventoryService::isDropEligible((int)$row['item_code']))return false;
        if(isset($row['treasure_code'])&&!TreasureData::get((int)$row['treasure_code']))return false;
        if(isset($row['relic_code'])&&!self::isRelicDropEligible(['treasure_code'=>$row['relic_code']]))return false;
        if(isset($row['fragment_grade'])&&!TreasureData::getCodesByGrade((string)$row['fragment_grade']))return false;
        return true;
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
            if ($type==='chest' && in_array($target,['fragment:normal','fragment:rare','fragment:epic','fragment:legendary'],true)) $entry['fragment_grade']=substr($target,9);
            elseif($type==='chest'&&is_string($target)&&preg_match('/^(treasure|relic):([1-9][0-9]*)$/D',$target,$match)){
                $code=$integer($match[2],1,2147483647,Locale::t('admin.drops.fragment_relic'));
                if(!self::isRelicDropEligible(['treasure_code'=>$code]))throw new \InvalidArgumentException(Locale::t('admin.drops.relic_invalid'));
                $entry[$match[1]==='relic'?'relic_code':'treasure_code']=$code;
            }
            else {
                $code=$integer($target,1,2147483647,'Gegenstand');
                if (InventoryService::getItemDef($code)===null) {
                    \Conquer\Admin\RewardLedger::rejected('unknown_item',0,0,$code,is_scalar($row['quantity']??null)?(int)$row['quantity']:0,['source_type'=>$type,'source_key'=>$key,'reference'=>'drop-rule-validation']);
                    throw new \InvalidArgumentException('Dieser Gegenstand existiert nicht im Katalog.');
                }
                $entry['item_code']=$code;
            }
            if (isset($seen[(string)$target])) throw new \InvalidArgumentException('Jeder Gegenstand darf nur einmal in der Beuteliste stehen.');
            $seen[(string)$target]=true;
            if ($type==='chest'||$type==='dungeon') $entry['weight']=$integer($row['weight']??null,0,1000000,'Gewichtung');
            else $entry['probability']=$chance($row['chance']??null,'Dropchance');
            if ($type!=='dungeon') {
                $entry[$type==='chest'?'quantity':'count']=$integer($row['quantity']??null,1,100000,'Anzahl');
                if(isset($row['quantity_min'])&&$row['quantity_min']!=='')$entry[$type==='chest'?'quantity_min':'count_min']=$integer($row['quantity_min'],1,(int)$row['quantity'],Locale::t('admin.drops.quantity_min'));
            }
            $entries[]=$entry;
        }
        if ($type==='chest') {
            if (array_sum(array_column($entries,'weight'))<1) throw new \InvalidArgumentException('Die Truhe braucht mindestens einen Eintrag mit positiver Gewichtung.');
            $bonuses=[];$bonusSeen=[];$bonusRows=$input['bonus_rows']??[];
            if(!is_array($bonusRows)||count($bonusRows)>50)throw new \InvalidArgumentException(Locale::t('admin.drops.bonus_invalid'));
            foreach($bonusRows as $row){
                if(!is_array($row))throw new \InvalidArgumentException(Locale::t('admin.drops.bonus_invalid'));
                $code=$integer($row['target']??null,1,2147483647,'Gegenstand');
                if(!InventoryService::isDropEligible($code)||isset($bonusSeen[$code]))throw new \InvalidArgumentException(Locale::t('admin.drops.bonus_invalid'));
                $bonusSeen[$code]=true;
                $entry=['item_code'=>$code,'count'=>$integer($row['quantity']??null,1,100000,'Anzahl'),'probability'=>$chance($row['chance']??null,'Dropchance')];
                if(isset($row['quantity_min'])&&$row['quantity_min']!=='')$entry['count_min']=$integer($row['quantity_min'],1,$entry['count'],Locale::t('admin.drops.quantity_min'));
                $bonuses[]=$entry;
            }
            $config=['rolls'=>$integer($input['rolls']??null,1,20,'Ziehungen'),'drop_table'=>$entries];
            if($bonuses)$config['bonus_drops']=$bonuses;
            return $config;
        }
        if ($type==='dungeon') {
            $code=$integer($input['treasure_code']??null,1,2147483647,'Relikt');
            if (!TreasureData::get($code)) throw new \InvalidArgumentException('Unbekanntes Relikt.');
            $probability=$chance($input['item_chance']??null,'Chance auf einen Gegenstand');
            if ($probability>0 && array_sum(array_column($entries,'weight'))<1) throw new \InvalidArgumentException('Wähle mindestens einen Gegenstand mit positiver Gewichtung oder setze die Itemchance auf 0 %.');
            return ['treasure_code'=>$code,'fragments'=>$integer($input['fragments']??null,0,10000,'Fragmente'),'item_chance'=>$probability,'item_quantity'=>$integer($input['item_quantity']??null,1,100000,'Itemmenge'),'items'=>$entries];
        }
        if ($type==='expedition') return ['resources'=>$resources($input['resources']??null),'gems'=>$integer($input['gems']??null,0,1000000,'Edelsteine'),'drops'=>$entries];
        $fragments=[];$fragmentSeen=[];
        if(in_array($type,['monster','farm'],true)){
            $fragmentRows=$input['fragment_rows']??[];
            if(!is_array($fragmentRows)||count($fragmentRows)>100)throw new \InvalidArgumentException(Locale::t('admin.drops.fragment_limit'));
            foreach($fragmentRows as $row){
                if(!is_array($row)||!is_string($row['target']??null))throw new \InvalidArgumentException(Locale::t('admin.drops.fragment_invalid'));
                $target=$row['target'];$entry=[];
                if(in_array($target,['fragment:normal','fragment:rare','fragment:epic','fragment:legendary'],true)){
                    $grade=substr($target,9);
                    if(!TreasureData::getCodesByGrade($grade))throw new \InvalidArgumentException(Locale::t('admin.drops.fragment_invalid'));
                    $entry['fragment_grade']=$grade;
                }elseif(preg_match('/^treasure:([1-9][0-9]*)$/D',$target,$match)){
                    $code=$integer($match[1],1,2147483647,Locale::t('admin.drops.fragment_relic'));
                    if(!self::isFragmentDropEligible(['treasure_code'=>$code]))throw new \InvalidArgumentException(Locale::t('admin.drops.fragment_invalid'));
                    $entry['treasure_code']=$code;
                }else throw new \InvalidArgumentException(Locale::t('admin.drops.fragment_invalid'));
                if(isset($fragmentSeen[$target]))throw new \InvalidArgumentException(Locale::t('admin.drops.fragment_duplicate'));
                $fragmentSeen[$target]=true;
                $entry['count']=$integer($row['quantity']??null,1,100000,Locale::t('admin.drops.fragment_quantity'));
                if(isset($row['quantity_min'])&&$row['quantity_min']!=='')$entry['count_min']=$integer($row['quantity_min'],1,$entry['count'],Locale::t('admin.drops.quantity_min'));
                if(isset($row['exclusive_group'])&&$row['exclusive_group']!==''){
                    if(!is_string($row['exclusive_group'])||!preg_match('/^[a-z][a-z0-9_]{0,39}$/D',$row['exclusive_group']))throw new \InvalidArgumentException(Locale::t('admin.drops.fragment_invalid'));
                    $entry['exclusive_group']=$row['exclusive_group'];
                }
                $entry['probability']=$chance($row['chance']??null,Locale::t('admin.drops.fragment_chance'));
                $fragments[]=$entry;
            }
        }
        $groupChances=[];
        foreach($fragments as $entry)if(isset($entry['exclusive_group']))$groupChances[$entry['exclusive_group']]=($groupChances[$entry['exclusive_group']]??0)+$entry['probability'];
        foreach($groupChances as $total)if($total>1.0000001)throw new \InvalidArgumentException(Locale::t('admin.drops.group_chance_invalid'));
        $relics=[];$relicSeen=[];
        $relicRows=$input['relic_rows']??[];
        if(!is_array($relicRows)||count($relicRows)>100)throw new \InvalidArgumentException(Locale::t('admin.drops.relic_limit'));
        foreach($relicRows as $row){
            if(!is_array($row)||!is_string($row['target']??null)||!preg_match('/^relic:([1-9][0-9]*)$/D',$row['target'],$match))throw new \InvalidArgumentException(Locale::t('admin.drops.relic_invalid'));
            $code=$integer($match[1],1,2147483647,Locale::t('admin.drops.fragment_relic'));
            if(!self::isRelicDropEligible(['treasure_code'=>$code]))throw new \InvalidArgumentException(Locale::t('admin.drops.relic_invalid'));
            if(isset($relicSeen[$code]))throw new \InvalidArgumentException(Locale::t('admin.drops.relic_duplicate'));
            $relicSeen[$code]=true;
            $relics[]=['treasure_code'=>$code,'count'=>$integer($row['quantity']??null,1,100000,Locale::t('admin.drops.relic_quantity')),'probability'=>$chance($row['chance']??null,Locale::t('admin.drops.relic_chance'))];
        }
        if ($type==='farm') return ['drops'=>$entries,'fragment_drops'=>$fragments,'relic_drops'=>$relics];
        $charms=$input['charms']??[];
        if (!is_array($charms)) throw new \InvalidArgumentException('Ungültige Talisman-Einstellung.');
        $c=['chance'=>1]; // Every defeated monster leaves exactly one map charm.
        foreach (['normal','epic','legendary'] as $grade) $c[$grade]=$integer($charms[$grade]??null,0,100,'Talismanverteilung');
        if (array_sum([$c['normal'],$c['epic'],$c['legendary']])!==100) throw new \InvalidArgumentException('Die drei Talisman-Anteile müssen zusammen 100 % ergeben.');
        return ['drops'=>$entries,'fragment_drops'=>$fragments,'relic_drops'=>$relics,'resource_reward'=>$resources($input['resources']??null),'gems_drop'=>['chance'=>$chance($input['gems_chance']??null,'Edelsteinchance'),'amount'=>$integer($input['gems_amount']??null,0,1000000,'Edelsteinmenge')],'charms'=>$c];
    }

    public static function roll(float $probability): bool
    {
        return $probability >= 1 || ($probability > 0 && random_int(1,1000000) <= (int)round($probability*1000000));
    }

    public static function rollItems(array $drops): array
    {
        $items=[];
        foreach ($drops as $drop) if (self::roll((float)$drop['probability'])) {
            $code=(int)$drop['item_code'];$items[$code]=($items[$code]??0)+self::quantity($drop);
        }
        return $items;
    }

    /** Resolve a random relic now; the stored return haul is never rolled again. */
    public static function rollFragments(array $drops): array
    {
        $fragments=[];$groups=[];
        // A shared draw makes rarity alternatives mutually exclusive. Their listed
        // probabilities remain unconditional and are used unchanged by previews.
        foreach($drops as $drop)if(isset($drop['exclusive_group'])&&self::isFragmentDropEligible($drop))$groups[$drop['exclusive_group']][]=$drop;
        $selected=[];
        foreach($groups as $name=>$rows){
            $draw=random_int(1,1000000);$total=0;
            foreach($rows as $index=>$row){$total+=(int)round((float)$row['probability']*1000000);if($draw<=$total){$selected[$name]=$index;break;}}
        }
        $indexes=[];
        foreach($drops as $drop){
            if(!self::isFragmentDropEligible($drop))continue;
            if(isset($drop['exclusive_group'])){
                $name=$drop['exclusive_group'];$index=$indexes[$name]??0;$indexes[$name]=$index+1;
                if(($selected[$name]??-1)!==$index)continue;
            }elseif(!self::roll((float)($drop['probability']??0)))continue;
            $code=(int)($drop['treasure_code']??0);
            if(!$code){
                $pool=TreasureData::getCodesByGrade((string)($drop['fragment_grade']??''));
                if(!$pool)continue;
                $code=$pool[random_int(0,count($pool)-1)];
            }
            $count=self::quantity($drop);
            if($count>0)$fragments[$code]=($fragments[$code]??0)+$count;
        }
        return $fragments;
    }

    /** Legacy rows keep their fixed amount; ranges are inclusive and uniform. */
    public static function quantity(array $drop,string $field='count'): int
    {
        $max=max(0,(int)($drop[$field]??0));$min=(int)($drop[$field.'_min']??$max);
        if($min<0||$min>$max)throw new \InvalidArgumentException('Invalid reward quantity range.');
        return $min===$max?$max:random_int($min,$max);
    }

    public static function grantFragments(int $playerId,array $fragments,array $rewardContext=[]): void
    {
        foreach($fragments as $code=>$count)if(!TreasureData::isRetired((int)$code))
            \Conquer\Game\Treasure\TreasureService::addFragments($playerId,(int)$code,(int)$count,$rewardContext);
    }

    /** Freeze whole-relic rewards separately from fragments for returns and reports. */
    public static function rollRelics(array $drops): array
    {
        $relics=[];
        foreach($drops as $drop){
            if(!self::isRelicDropEligible($drop)||!self::roll((float)($drop['probability']??0)))continue;
            $code=(int)$drop['treasure_code'];$count=max(0,(int)($drop['count']??0));
            if($count>0)$relics[$code]=($relics[$code]??0)+$count;
        }
        return $relics;
    }

    public static function grantRelics(int $playerId,array $relics,array $rewardContext=[]): void
    {
        foreach($relics as $code=>$count)if(!TreasureData::isRetired((int)$code))
            \Conquer\Game\Treasure\TreasureService::addRelics($playerId,(int)$code,(int)$count,$rewardContext);
    }

    private static function isRelicDropEligible(array $row): bool
    {
        $definition=TreasureData::get((int)($row['treasure_code']??0));
        return $definition!==null&&empty($definition['legacy_only']);
    }

    private static function isFragmentDropEligible(array $row): bool
    {
        if(!self::isDropEligible($row))return false;
        if(isset($row['treasure_code']))return empty(TreasureData::get((int)$row['treasure_code'])['legacy_only']);
        return isset($row['fragment_grade']);
    }
}
