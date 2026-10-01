<?php
declare(strict_types=1);
namespace Conquer\Game\Rewards;

use Conquer\Db\Connection;
use Conquer\Game\Dungeon\DungeonRules;
use Conquer\Game\Expedition\EncounterCatalog;
use Conquer\Game\Inventory\InventoryService;
use Conquer\Game\Locale;
use Conquer\Game\Map\MonsterData;
use Conquer\Game\Player\ActionPoints;
use Conquer\Game\Trading\TradingShopService;
use Conquer\Game\Treasure\{ChestService,TreasureData};
use Conquer\Game\World\{LandAccessPolicy,WorldContext};

/** Read-only acquisition guide. The reward rules, world and viewer determine every source. */
final class ItemSourceService
{
    public static function query(array $input): array
    {
        $fields=array_values(array_filter(['item_code','treasure_code'],static fn($key)=>array_key_exists($key,$input)));
        if(count($fields)!==1)throw new \DomainException(Locale::t('sources.invalid'));
        $key=$fields[0];$value=$input[$key];
        if((!is_int($value)&&!is_string($value))||filter_var($value,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>2147483647]])===false)throw new \DomainException(Locale::t('sources.invalid'));
        $code=(int)$value;$definition=$key==='item_code'?InventoryService::getItemDef($code):TreasureData::get($code);
        if(!$definition)throw new \DomainException(Locale::t('sources.invalid'));
        return [$key=>$code];
    }

    /** One reward entry, including the extra random relic selection inside a fragment pack. */
    public static function matchReward(array $query,array $entry,float $chance=1.0,int $quantity=1): ?array
    {
        if($chance<=0||$quantity<1)return null;
        $chance=max(0.0,min(1.0,$chance));$code=(int)($entry['item_code']??0);
        if(isset($query['item_code']))return $code===$query['item_code']?['quantity'=>$quantity,'chance'=>$chance]:null;
        $treasureCode=(int)$query['treasure_code'];$definition=TreasureData::get($treasureCode);
        if(!$definition)return null;
        if(isset($entry['treasure_code']))return (int)$entry['treasure_code']===$treasureCode?['quantity'=>$quantity,'chance'=>$chance]:null;
        $pack=$code>0?InventoryService::getItemDef($code):null;
        if($code>0&&($pack['category']??'')!=='fragment_pack')return null;
        if($pack&&isset($pack['treasure_code'])){
            if((int)$pack['treasure_code']!==$treasureCode)return null;
            return ['quantity'=>(int)$pack['fragment_amount'],'chance'=>$chance,'via_item_code'=>$code,'pack_quantity'=>$quantity,'selection_chance'=>1.0];
        }
        $grade=$pack['fragment_grade']??$entry['fragment_grade']??null;
        if($grade!==$definition['grade'])return null;
        $pool=TreasureData::getCodesByGrade((string)$grade);
        if(!in_array($treasureCode,$pool,true)||!$pool)return null;
        $selection=1/count($pool);
        if($pack)return ['quantity'=>(int)$pack['fragment_amount'],'chance'=>$chance,'via_item_code'=>$code,'pack_quantity'=>$quantity,'selection_chance'=>$selection];
        return ['quantity'=>$quantity,'chance'=>$chance*$selection,'random_relic'=>true];
    }

    public static function search(int $playerId,array $input): array
    {
        $query=self::query($input);WorldContext::current($input['expected_world_id']??null);
        $db=Connection::getInstance();$city=WorldContext::city($playerId);$world=(int)$city['world_id'];
        $buildings=array_column($db->query('SELECT building_code,level FROM city_buildings WHERE city_id=?',[$city['id']])->fetchAll(),'level','building_code');
        $alliance=(int)$db->query('SELECT alliance_id FROM alliance_members WHERE player_id=? AND world_id=?',[$playerId,$world])->fetchColumn();
        $troops=(int)$db->query('SELECT COALESCE(SUM(count),0) FROM city_troops WHERE city_id=?',[$city['id']])->fetchColumn();
        $inventory=array_column($db->query('SELECT item_code,quantity FROM player_inventory WHERE player_id=?',[$playerId])->fetchAll(),'quantity','item_code');
        $sources=[];$monsterSources=[];$codes=[];
        foreach(array_unique(array_merge(array_column(RewardCatalog::json('monsters')['monsters'],'code'),array_column(RewardCatalog::json('world_spawn')['monsters'],'code')))as$code){
            $code=(int)$code;if(!MonsterData::isActive($code))continue;$d=MonsterData::get($code);$level=max(1,(int)$d['level']);
            $rewards=self::drops($query,$d['drops']??[]);if(!$rewards)continue;
            $group=($d['name']??'').':'.$level.':'.hash('sha256',json_encode($rewards,JSON_THROW_ON_ERROR));
            $codes[$code]=$group;
            if(isset($monsterSources[$group]))continue;
            $rally=$d['type']==='rally';$needs=[self::note('sources.victory')];
            $needs[]=self::note('sources.monster_cost',['ap'=>max(0,(int)($d['action_point_cost']??ActionPoints::costForMonster($d['name']))),'level'=>$level]);
            if($rally)$needs[]=self::note('sources.rally');
            if($troops<1)$needs[]=self::note('sources.train');
            if($rally&&$alliance<1)$needs[]=self::note('sources.join_alliance');
            $monsterSources[$group]=['id'=>'monster:'.$code,'type'=>'monster','name'=>Locale::text($d['name']),'level'=>$level,'rewards'=>$rewards,'notes'=>$needs,'status'=>'unavailable','reason'=>self::note('sources.no_target'),'destination'=>null];
        }
        if($codes){
            $sql='SELECT id,monster_code,coord_x,coord_y,hp_current FROM field_monsters WHERE world_id=? AND hp_current>0 AND (expires_at IS NULL OR expires_at>UTC_TIMESTAMP()) AND monster_code IN ('.implode(',',array_fill(0,count($codes),'?')).') ORDER BY POW(coord_x-?,2)+POW(coord_y-?,2),id';
            $params=[$world,...array_keys($codes),(int)$city['coord_x'],(int)$city['coord_y']];
            for($offset=0;;$offset+=200){
                $rows=$db->query($sql.' LIMIT 200 OFFSET '.$offset,$params)->fetchAll();
                foreach($rows as$row){$group=$codes[(int)$row['monster_code']];if($monsterSources[$group]['destination']!==null||!LandAccessPolicy::isOpenReadOnly($world,(int)$row['coord_x'],(int)$row['coord_y']))continue;
                    $monsterSources[$group]['destination']=['tab'=>'world','target'=>['kind'=>'monsters','data'=>MonsterData::mapData($row)]];
                    $monsterSources[$group]['status']='available';$monsterSources[$group]['reason']=self::note('sources.map_available');
                }
                if(count($rows)<200)break;
            }
        }
        $sources=array_values($monsterSources);
        $chests=$db->query('SELECT * FROM player_chests WHERE player_id=?',[$playerId])->fetch()?:[];
        foreach(RewardCatalog::sources('chest')as$type=>$source){
            $cfg=RewardCatalog::effective('chest',(string)$type,$world);$total=array_sum(array_column($cfg['drop_table'],'weight'));$rewards=[];
            foreach($cfg['drop_table']as$entry){$match=self::matchReward($query,$entry,$total>0?(int)$entry['weight']/$total:0,(int)$entry['quantity']);if($match)$rewards[]=$match+['rolls'=>(int)$cfg['rolls'],'per_draw'=>true];}
            if(!$rewards)continue;
            $inventoryChest=null;$chestItem=null;
            foreach(InventoryService::allDefs()as$item)if(($item['chest_type']??'')===$type){$chestItem=(int)$item['code'];break;}
            foreach($inventory as$code=>$quantity){$item=InventoryService::getItemDef((int)$code);if(($item['chest_type']??'')===$type&&(int)$quantity>0){$inventoryChest=(int)$code;break;}}
            $free=false;$next=null;$house=(int)($buildings['treasure_house']??0)>0;
            if($type==='silver'){
                $used=substr((string)($chests['last_free_silver_reset']??''),0,10)===gmdate('Y-m-d')?(int)($chests['free_silver_used_today']??0):0;
                $next=empty($chests['last_free_silver_at'])?0:strtotime($chests['last_free_silver_at'].' UTC')+ChestService::SILVER_COOLDOWN_SECONDS;
                if($used>=ChestService::FREE_SILVER_PER_DAY)$next=max($next,strtotime('tomorrow UTC'));
                $free=$house&&$next<=time();
            }elseif($type==='gold'){$next=empty($chests['last_free_gold_at'])?0:strtotime($chests['last_free_gold_at'].' UTC')+ChestService::GOLD_COOLDOWN_SECONDS;$free=$house&&$next<=time();}
            $available=$inventoryChest!==null||$free;
            $reason=$available?'sources.chest_available':(!$house&&$type!=='platinum'?'sources.house':($type==='platinum'?'sources.need_chest':'sources.chest_wait'));
            $notes=[self::note('sources.chest_rolls',['count'=>(int)$cfg['rolls']])];if($next&&$next>time())$notes[]=self::note('sources.next_at',['time'=>gmdate('c',$next)]);
            $sources[]=['id'=>'chest:'.$type,'type'=>'chest','name'=>Locale::t('sources.chest.'.$type),'rewards'=>$rewards,'notes'=>$notes,'status'=>$available?'available':'unavailable','reason'=>self::note($reason),'destination'=>['tab'=>$inventoryChest!==null&&!$free||$type==='platinum'?'inventory':'treasures','item_code'=>$inventoryChest??$chestItem,'section'=>'chests']];
        }
        $rotation=array_column(DungeonRules::weeklyRotation()['available'],'dungeon_code');
        $specialized=(bool)$db->query("SELECT 1 FROM player_lord_talents WHERE player_id=? AND world_id=? AND rank>0 AND (talent_code LIKE 'attack\\_%' OR talent_code LIKE 'defense\\_%' OR talent_code LIKE 'gather\\_%' OR talent_code LIKE 'hunter\\_%') LIMIT 1",[$playerId,$world])->fetchColumn();
        $activeDungeon=(bool)$db->query("SELECT 1 FROM dungeon_members m JOIN dungeon_runs r ON r.id=m.run_id WHERE m.player_id=? AND r.world_id=? AND r.status IN ('recruiting','running','decision') LIMIT 1",[$playerId,$world])->fetchColumn();
        foreach(RewardCatalog::sources('dungeon')as$key=>$source){
            $cfg=RewardCatalog::effective('dungeon',(string)$key,$world);$rewards=[];
            $fragment=self::matchReward($query,['treasure_code'=>$cfg['treasure_code']],1,(int)$cfg['fragments']);if($fragment)$rewards[]=$fragment;
            $custom=RewardCatalog::override('dungeon',(string)$key,$world)!==null;
            $entries=$cfg['items'];$total=array_sum(array_column($entries,'weight'));
            foreach($entries as$i=>$entry){
                $chance=$custom?($total>0?(float)$cfg['item_chance']*(int)$entry['weight']/$total:0):($i===$playerId%max(1,count($entries))?(float)$cfg['item_chance']:0);
                $match=self::matchReward($query,$entry,$chance,(int)$cfg['item_quantity']);if($match)$rewards[]=$match;
            }
            if(!$rewards)continue;$inRotation=in_array($key,$rotation,true);$available=$inRotation&&$specialized&&$troops>=10&&!$activeDungeon;
            $reason=!$inRotation?'sources.not_rotation':($activeDungeon?'sources.dungeon_busy':(!$specialized?'sources.talent':($troops<10?'sources.ten_troops':'sources.dungeon_available')));
            $sources[]=['id'=>'dungeon:'.$key,'type'=>'dungeon','name'=>Locale::text($source['name']),'rewards'=>$rewards,'notes'=>[self::note('sources.dungeon_rules'),self::note('sources.dungeon_base'),self::note('sources.duration',['minutes'=>(int)ceil($source['definition']['base_duration']/60)])],'status'=>$available?'available':'unavailable','reason'=>self::note($reason),'destination'=>['tab'=>'dungeons','dungeon_code'=>$key]];
        }
        $chapter=max(1,(int)$db->query('SELECT chapter FROM world_chapters WHERE world_id=?',[$world])->fetchColumn());
        foreach(RewardCatalog::sources('expedition')as$key=>$source){
            $cfg=RewardCatalog::effective('expedition',(string)$key,$world);$rewards=self::drops($query,$cfg['drops']??[]);if(!$rewards)continue;
            [$boss,$difficulty]=explode('.',$key);$b=EncounterCatalog::BOSSES[$boss];$d=EncounterCatalog::DIFFICULTIES[$difficulty];$castle=max($b['castle'],$d['castle']);
            $available=$alliance>0&&(int)$city['castle_level']>=$castle&&$chapter>=$b['chapter'];
            $sources[]=['id'=>'expedition:'.$key,'type'=>'expedition','name'=>Locale::text($b['name']).' · '.Locale::text($d['name']),'rewards'=>$rewards,'notes'=>[self::note('sources.expedition_rules'),self::note('sources.expedition_levels',['castle'=>$castle,'chapter'=>$b['chapter']])],'status'=>$available?'available':'unavailable','reason'=>self::note($available?'sources.expedition_available':'sources.expedition_locked'),'destination'=>['tab'=>'expeditions','boss_code'=>$boss,'difficulty'=>$difficulty]];
        }
        $shop=TradingShopService::state($playerId);
        foreach(['caravan'=>$shop['offers'],'vip'=>$shop['vip']['offers']]as$mode=>$offers)foreach($offers as$offer){
            $match=self::matchReward($query,$offer,1,(int)$offer['quantity']);if(!$match)continue;
            $available=!$offer['locked']&&(int)$offer['remaining']>0;
            $reason=$offer['locked']?'sources.shop_locked':((int)$offer['remaining']>0?'sources.shop_available':'sources.sold_out');
            $sources[]=['id'=>'shop:'.$mode.':'.$offer['id'],'type'=>'shop','name'=>Locale::t('sources.shop.'.$mode),'rewards'=>[$match],'notes'=>[self::note('sources.price',['amount'=>$offer['price']['amount'],'resource'=>Locale::t('common.'.$offer['price']['resource'])]),self::note('sources.stock',['count'=>$offer['remaining']]),self::note('sources.shop_levels',['level'=>(int)($offer['vip_level']??0)])],'status'=>$available?'available':'unavailable','reason'=>self::note($reason),'destination'=>['tab'=>'market','mode'=>$mode,'offer_id'=>$offer['id']]];
        }
        if(isset($query['treasure_code']))foreach($inventory as$code=>$quantity){
            if((int)$quantity<1)continue;$match=self::matchReward($query,['item_code'=>(int)$code]);if(!$match)continue;
            $sources[]=['id'=>'inventory:'.$code,'type'=>'inventory','name'=>Locale::text(RewardPresentation::item((int)$code)['name']),'rewards'=>[$match],'notes'=>[self::note('sources.stock',['count'=>(int)$quantity])],'status'=>'available','reason'=>self::note('sources.owned_pack'),'destination'=>['tab'=>'inventory','item_code'=>(int)$code]];
        }
        $worldStatus=$db->query('SELECT status FROM worlds WHERE id=?',[$world])->fetchColumn();
        if(!in_array($worldStatus,['open','running'],true))foreach($sources as&$source){$source['status']='unavailable';$source['reason']=self::note('sources.world_closed');}unset($source);
        usort($sources,static fn($a,$b)=>($a['status']==='available'?0:1)<=>($b['status']==='available'?0:1)?:($a['level']??0)<=>($b['level']??0)?:strcmp($a['id'],$b['id']));
        $presentation=isset($query['item_code'])?RewardPresentation::item($query['item_code']):RewardPresentation::fragment($query['treasure_code']);
        $presentation['name']=Locale::text($presentation['name']);
        return ['query'=>$query,'world_id'=>$world,'item'=>$presentation,'sources'=>$sources,'generated_at'=>gmdate('c')];
    }

    private static function drops(array $query,array $drops): array
    {
        $out=[];foreach($drops as$drop){$match=self::matchReward($query,$drop,(float)($drop['probability']??0),(int)($drop['count']??0));if($match)$out[]=$match;}return $out;
    }
    private static function note(string $key,array $params=[]): array {return ['key'=>$key,'params'=>$params];}
}
