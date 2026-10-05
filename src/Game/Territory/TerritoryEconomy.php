<?php
declare(strict_types=1);
namespace Conquer\Game\Territory;
use Conquer\Db\Connection;
use Conquer\Game\WorldRules;
use Conquer\Game\Map\WorldPlacement;

final class TerritoryEconomy
{
    public static function tick(int $world,int $now,int $limit): void
    {
        $rules=TerritoryService::profile($world);
        // Oldest unsettled owner first avoids starvation when batches contain >100 targets.
        foreach(Connection::getInstance()->query("SELECT * FROM territory_targets WHERE world_id=? AND owner_alliance_id IS NOT NULL AND benefit_type IN ('food','lumber','stone','gold') AND last_income_at<? ORDER BY last_income_at,id LIMIT ".max(1,min(1000,$limit)).' FOR UPDATE',[$world,TerritoryService::date($now)])->fetchAll() as $t)self::settle($t,$now,$rules);
    }
    public static function settle(array $target,int $at,array $rules): void
    {
        if(!$target['owner_alliance_id']||!in_array($target['benefit_type'],['food','lumber','stone','gold'],true))return;
        $start=TerritoryService::timestamp($target['last_income_at']??$target['owned_since']);if($at<=$start)return;
        $v=TerritoryRules::income($at-$start,(int)$rules['income_per_hour'],(int)$target['income_remainder']);$db=Connection::getInstance();
        $inserted=$db->execute('INSERT IGNORE INTO territory_income_ledger(world_id,continent_id,target_id,ownership_seq,alliance_id,period_start,period_end,resource,amount)VALUES(?,?,?,?,?,?,?,?,?)',[$target['world_id'],$target['continent_id'],$target['id'],$target['ownership_seq'],$target['owner_alliance_id'],TerritoryService::date($start),TerritoryService::date($at),$target['benefit_type'],$v['amount']]);
        if(!$inserted)return;
        self::treasury((int)$target['owner_alliance_id'],[$target['benefit_type']=>$v['amount']]);
        $db->execute('UPDATE territory_targets SET last_income_at=?,income_remainder=? WHERE world_id=? AND id=?',[TerritoryService::date($at),$v['remainder'],$target['world_id'],$target['id']]);
    }
    public static function transfer(array $t,int $alliance,int $at,int $campaign,array $rules,string $event): void
    {
        // Profile edits settle every owner before changing the continuous income
        // rate. An incoming campaign's snapshot binds its battle and rewards,
        // not the unrelated old owner's income after that profile edit.
        $db=Connection::getInstance();self::settle($t,$at,TerritoryService::profile((int)$t['world_id']));TerritoryGarrison::displace((int)$t['world_id'],$t['id'],$at);
        $db->execute('INSERT INTO territory_history(world_id,continent_id,target_id,event_key,old_alliance_id,new_alliance_id,campaign_id,occurred_at,detail_json)VALUES(?,?,?,?,?,?,?,?,?)',[$t['world_id'],$t['continent_id'],$t['id'],$event,$t['owner_alliance_id'],$alliance,$campaign?:null,TerritoryService::date($at),json_encode(['kind'=>$t['kind'],'rules_version'=>$rules['version']])]);
        $db->execute('UPDATE territory_targets SET owner_alliance_id=?,owned_since=?,last_income_at=?,income_remainder=0,ownership_seq=ownership_seq+1,fortification=0 WHERE world_id=? AND id=?',[$alliance,TerritoryService::date($at),TerritoryService::date($at),$t['world_id'],$t['id']]);
    }
    public static function reward(array $t,int $player,int $alliance,string $event,array $reward,int $at): void
    {
        Connection::getInstance()->execute('INSERT IGNORE INTO territory_rewards(world_id,continent_id,player_id,alliance_id,target_id,event_key,reward_json,created_at)VALUES(?,?,?,?,?,?,?,?)',[$t['world_id'],$t['continent_id'],$player,$alliance,$t['id'],$event,json_encode($reward),TerritoryService::date($at)]);
    }
    public static function claim(int $player,int $world,int $id): array
    {
        $db=Connection::getInstance();$r=$db->query('SELECT * FROM territory_rewards WHERE id=? AND world_id=? AND player_id=? FOR UPDATE',[$id,$world,$player])->fetch();TerritoryService::require((bool)$r,'Belohnung nicht gefunden.',404);
        if($r['claimed_at'])return ['message'=>'Diese Belohnung wurde bereits abgeholt.','reward_id'=>$id];
        $reward=json_decode($r['reward_json'],true);$city=$db->query('SELECT id FROM cities WHERE world_id=? AND player_id=? ORDER BY id LIMIT 1 FOR UPDATE',[$world,$player])->fetchColumn();TerritoryService::require((bool)$city,'Stadt nicht gefunden.');
        $db->execute('UPDATE cities SET food=food+?,lumber=lumber+?,stone=stone+?,gold=gold+? WHERE id=?',[$reward['food']??0,$reward['lumber']??0,$reward['stone']??0,$reward['gold']??0,$city]);
        foreach($reward['items']??[] as $code=>$count)\Conquer\Game\Inventory\InventoryService::addItems($player,(int)$code,(int)$count,$world);
        $db->execute('UPDATE territory_rewards SET claimed_at=UTC_TIMESTAMP() WHERE id=?',[$id]);return ['message'=>'Die verdiente Belohnung wurde gutgeschrieben.','reward_id'=>$id,'reward'=>$reward];
    }
    public static function support(int $player,int $world,array $body): array
    {
        $m=TerritoryService::mustMember($player,$world);$t=TerritoryService::target($world,(string)($body['target_id']??''),true);$rules=TerritoryService::profile($world);$kind=(string)($body['kind']??'');$aid=(int)$m['alliance_id'];
        TerritoryService::require(in_array($kind,['supply','scout','fortify'],true),'Ungültiger Unterstützungsauftrag.');
        $campaign=Connection::getInstance()->query("SELECT id FROM territory_campaigns WHERE world_id=? AND target_id=? AND alliance_id=? AND status IN ('gathering','marching') LIMIT 1",[$world,$t['id'],$aid])->fetchColumn();
        TerritoryService::require((int)$t['owner_alliance_id']===$aid||(bool)$campaign,'Unterstütze ein eigenes Gebiet oder einen laufenden Feldzug.');
        if($kind==='fortify')TerritoryService::require((int)$t['owner_alliance_id']===$aid,'Nur eigene Gebiete können befestigt werden.');
        $city=WorldRules::origin($player,TerritoryService::integer($body,'city_id'),$world);$db=Connection::getInstance();$day=gmdate('Y-m-d');
        TerritoryService::require(!$db->query('SELECT id FROM territory_support WHERE world_id=? AND player_id=? AND kind=? AND day_key=?',[$world,$player,$kind,$day])->fetchColumn(),'Diesen Auftrag hast du heute bereits erfüllt.');
        TerritoryService::require($db->execute('UPDATE cities SET food=food-? WHERE id=? AND food>=?',[$rules['support_cost'],$city['id'],$rules['support_cost']])===1,'Nicht genügend Nahrung für die Versorgung.');
        $db->execute('INSERT INTO territory_support(world_id,alliance_id,player_id,target_id,kind,day_key,created_at)VALUES(?,?,?,?,?,?,UTC_TIMESTAMP())',[$world,$aid,$player,$t['id'],$kind,$day]);
        if($kind==='fortify')$db->execute('UPDATE territory_targets SET fortification=LEAST(10,fortification+1) WHERE world_id=? AND id=?',[$world,$t['id']]);
        $mission=null;
        if($t['kind']==='canton'&&$kind==='supply'&&(int)$t['owner_alliance_id']===$aid){
            $count=(int)$db->query("SELECT COUNT(*) FROM territory_support WHERE world_id=? AND alliance_id=? AND target_id=? AND kind='supply' AND day_key=?",[$world,$aid,$t['id'],$day])->fetchColumn();
            $db->execute("INSERT IGNORE INTO territory_benefit_usage(world_id,alliance_id,benefit,day_key,used)VALUES(?,?,'canton_mission',?,0)",[$world,$aid,$day]);
            if($count>=(int)$rules['canton_mission_contributors']&&$db->execute("UPDATE territory_benefit_usage SET used=1 WHERE world_id=? AND alliance_id=? AND benefit='canton_mission' AND day_key=? AND used=0",[$world,$aid,$day])===1){$mission=array_fill_keys(['food','lumber','stone','gold'],(int)$rules['canton_mission_reward']);self::treasury($aid,$mission);}
        }
        $reward=['gold'=>100];
        if($t['benefit_type']==='abbey'&&(int)$t['owner_alliance_id']===$aid){
            self::consume($world,$aid,'abbey',$rules['special_daily_limit']);
            foreach(\Conquer\Game\Inventory\InventoryService::allDefs() as $code=>$item){if(($item['category']??'')==='speedup'&&($item['subcategory']??'')==='research'&&(int)($item['duration_seconds']??0)===300){$reward['items'][(int)$code]=1;break;}}
            TerritoryService::require(!empty($reward['items']),'Der Forschungsbeschleuniger ist derzeit nicht verfügbar.');
        }
        self::reward($t,$player,$aid,'support:'.$kind.':'.$day,$reward,time());
        return ['message'=>'Dein Beitrag wurde verbucht. Die persönliche Belohnung ist abholbar.','canton_mission_reward'=>$mission,'scouting'=>$kind==='scout'?['npc_troops'=>$rules['npc_troops'][$t['kind']],'fortification'=>(int)$t['fortification']]:null];
    }
    public static function consume(int $world,int $alliance,string $benefit,int $limit): void
    {
        $db=Connection::getInstance();$db->execute('INSERT IGNORE INTO territory_benefit_usage(world_id,alliance_id,benefit,day_key,used)VALUES(?,?,?,UTC_DATE(),0)',[$world,$alliance,$benefit]);
        TerritoryService::require($db->execute('UPDATE territory_benefit_usage SET used=used+1 WHERE world_id=? AND alliance_id=? AND benefit=? AND day_key=UTC_DATE() AND used<?',[$world,$alliance,$benefit,$limit])===1,'Das gemeinsame Tageskontingent ist ausgeschöpft.');
    }
    public static function teleport(int $player,int $world,array $body): array
    {
        $m=TerritoryService::mustMember($player,$world);$t=TerritoryService::target($world,(string)($body['target_id']??''),true);$rules=TerritoryService::profile($world);$city=WorldRules::origin($player,TerritoryService::integer($body,'city_id'),$world);
        TerritoryService::require($t['benefit_type']==='rune'&&(int)$t['owner_alliance_id']===(int)$m['alliance_id'],'Eine eigene Runenwacht wird benötigt.');
        $x=TerritoryService::integer($body,'x',0);$y=TerritoryService::integer($body,'y',0);TerritoryService::require(hypot($x-$t['x'],$y-$t['y'])<=$rules['rune_radius'],'Der Ankunftsort liegt zu weit von der Runenwacht entfernt.');
        TerritoryService::require(max(abs($x-(int)$city['coord_x']),abs($y-(int)$city['coord_y']))>=4,'Wähle einen anderen Platz für deine Stadt.');
        $db=Connection::getInstance();WorldPlacement::lockWorld($db,$world);
        $active=(int)$db->query("SELECT COUNT(*) FROM marches WHERE world_id=? AND (player_id=? OR (target_x=? AND target_y=?)) AND state IN ('marching','returning','resolving','arrived')",[$world,$player,$city['coord_x'],$city['coord_y']])->fetchColumn();
        $active+=(int)$db->query("SELECT COUNT(*) FROM rallies r WHERE r.world_id=? AND r.status IN ('gathering','marching','returning') AND (r.leader_player_id=? OR r.target_player_id=? OR EXISTS(SELECT 1 FROM rally_participants p WHERE p.rally_id=r.id AND p.player_id=? AND p.status IN ('joining','pending','marching')))",[$world,$player,$player,$player])->fetchColumn();
        $active+=(int)$db->query("SELECT COUNT(*) FROM territory_garrisons WHERE world_id=? AND city_id=? AND status<>'returned'",[$world,$city['id']])->fetchColumn();
        $active+=(int)$db->query("SELECT COUNT(*) FROM territory_army_returns WHERE world_id=? AND city_id=? AND status='returning'",[$world,$city['id']])->fetchColumn();
        $active+=(int)$db->query("SELECT COUNT(*) FROM reinforcements WHERE (sender_city_id=? OR target_city_id=?) AND state IN ('marching','active','returning')",[$city['id'],$city['id']])->fetchColumn();
        $active+=(int)$db->query("SELECT COUNT(*) FROM expedition_missions WHERE city_id=? AND status IN ('marching','returning')",[$city['id']])->fetchColumn();
        TerritoryService::require($active===0,'Hole alle Armeen vor dem Teleport zurück.');TerritoryService::require(WorldPlacement::canPlace($db,$world,'city',$x,$y,(int)$city['id']),'Der vollständige Stadtplatz muss frei und trocken sein.');
        self::consume($world,(int)$m['alliance_id'],'rune',$rules['rune_daily_charges']);$db->execute('UPDATE cities SET coord_x=?,coord_y=? WHERE id=?',[$x,$y,$city['id']]);
        return ['message'=>'Deine Stadt ist bei der Runenwacht angekommen.','x'=>$x,'y'=>$y];
    }
    public static function treasury(int $aid,array $resources): void
    {
        Connection::getInstance()->execute('INSERT INTO alliance_treasury(alliance_id,food,lumber,stone,gold)VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE food=food+VALUES(food),lumber=lumber+VALUES(lumber),stone=stone+VALUES(stone),gold=gold+VALUES(gold)',[$aid,$resources['food']??0,$resources['lumber']??0,$resources['stone']??0,$resources['gold']??0]);
    }
    /** Face value of the guaranteed resource loot in the bound encounter definition. */
    public static function regionalBaseResources(array $definition): array
    {
        $base=[];foreach(['food','lumber','stone','gold'] as $resource)$base[$resource]=max(0,(int)($definition['resource_reward'][$resource]??0));
        foreach($definition['drops']??[] as $drop){
            if((float)($drop['probability']??0)<1)continue;
            $item=\Conquer\Game\Inventory\InventoryService::getItemDef((int)($drop['item_code']??0));$resource=$item['resource']??'';
            if(($item['category']??'')!=='resource_pack'||!array_key_exists($resource,$base))continue;
            $base[$resource]+=max(0,(int)($item['amount']??0))*max(0,(int)($drop['count']??0));
        }
        return $base;
    }
    /** One small shared reward per actual regional NPC kill; rally size never multiplies it. */
    public static function regionalKill(int $world,int $player,int $x,int $y,string $event,array $base,int $at): array
    {
        if(!TerritoryService::enabled($world))return [];$geo=\Conquer\Game\World\LuxembourgGeography::at($x,$y);if(!$geo)return [];
        $db=Connection::getInstance();$aid=WorldRules::alliance($player,$world);if(!$aid)return [];$t=TerritoryService::target($world,'canton:'.$geo['canton_id'],true);
        $owner=self::ownerAt($t,$at);if($owner!==$aid)return [];
        $old=$db->query('SELECT reward_json FROM territory_pve_ledger WHERE world_id=? AND event_key=?',[$world,$event])->fetchColumn();if($old!==false)return json_decode($old,true);
        $rules=TerritoryService::profile($world);$day=gmdate('Y-m-d',$at);$db->execute("INSERT IGNORE INTO territory_benefit_usage(world_id,alliance_id,benefit,day_key,used)VALUES(?,?,'regional_supply',?,0)",[$world,$aid,$day]);
        $used=(int)$db->query("SELECT used FROM territory_benefit_usage WHERE world_id=? AND alliance_id=? AND benefit='regional_supply' AND day_key=? FOR UPDATE",[$world,$aid,$day])->fetchColumn();$remaining=max(0,(int)$rules['regional_daily_cap']-$used);$reward=[];
        foreach(['food','lumber','stone','gold'] as $resource){$amount=min($remaining,(int)floor(max(0,(int)($base[$resource]??0))*(int)$rules['regional_supply_percent']/100));$reward[$resource]=$amount;$remaining-=$amount;}
        $amount=array_sum($reward);$db->execute('INSERT INTO territory_pve_ledger(world_id,event_key,continent_id,canton_id,alliance_id,player_id,reward_json,occurred_at)VALUES(?,?,?,?,?,?,?,?)',[$world,$event,$t['continent_id'],$t['canton_id'],$aid,$player,json_encode($reward),TerritoryService::date($at)]);
        $db->execute("UPDATE territory_benefit_usage SET used=used+? WHERE world_id=? AND alliance_id=? AND benefit='regional_supply' AND day_key=?",[$amount,$world,$aid,$day]);self::treasury($aid,$reward);return $reward;
    }
    public static function ownerAt(array $t,int $at): ?int
    {
        $db=Connection::getInstance();$prior=$db->query('SELECT new_alliance_id FROM territory_history WHERE world_id=? AND target_id=? AND occurred_at<=? ORDER BY occurred_at DESC,id DESC LIMIT 1',[$t['world_id'],$t['id'],TerritoryService::date($at)])->fetch();if($prior)return $prior['new_alliance_id']===null?null:(int)$prior['new_alliance_id'];
        $next=$db->query('SELECT old_alliance_id FROM territory_history WHERE world_id=? AND target_id=? AND occurred_at>? ORDER BY occurred_at,id LIMIT 1',[$t['world_id'],$t['id'],TerritoryService::date($at)])->fetch();if($next)return $next['old_alliance_id']===null?null:(int)$next['old_alliance_id'];
        return $t['owner_alliance_id']!==null&&TerritoryService::timestamp($t['owned_since'])<=$at?(int)$t['owner_alliance_id']:null;
    }
}
