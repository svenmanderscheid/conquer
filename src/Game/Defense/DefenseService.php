<?php
declare(strict_types=1);
namespace Conquer\Game\Defense;

use Conquer\Game\World\WorldContext;

use Conquer\Db\Connection;
use Conquer\Game\WorldRules;
use Conquer\Game\City\{CityState,TroopData,TroopTrainer,ResourceTick};
use Conquer\Game\March\{MarchArmy,MarchDispatcher,MarchTick};
use Conquer\Game\Research\{BuffEngine,ResearchEffects};

/** City defense and army tools. Every mutation validates ownership on the server. */
final class DefenseService
{
    public static function state(int $playerId,?int $cityId=null): array
    {
        return self::locked($playerId,function()use($playerId,$cityId):array{
            $db=Connection::getInstance();$city=self::city($playerId,$cityId);$cityId=(int)$city['id'];$world=(int)$city['world_id'];
            MarchTick::runForPlayer($playerId);TroopTrainer::processQueue($db,$cityId);self::processPromotions($cityId);
            $city=self::syncWall($cityId);ResourceTick::persist($city,self::buildings($cityId));$city=self::city($playerId,$cityId);
            $shield=$db->query('SELECT beginner_shield_until FROM players WHERE id=?',[$playerId])->fetch();
            $buffs=BuffEngine::getBuffs($playerId,$world);$buildings=self::buildings($cityId);
            $available=array_map('intval',$db->query('SELECT troop_code,count FROM city_troops WHERE city_id=?',[$cityId])->fetchAll(\PDO::FETCH_KEY_PAIR));
            $troops=[];
            foreach(TroopData::all() as $code=>$def){
                $next=TroopData::get($code+100);$promotion=null;
                if($next && (int)$next['type']===(int)$def['type']){
                    $quote=self::promotionQuote($code,1,$buffs,\Conquer\Game\Buff\ActiveBuffService::getMultiplier($playerId,'training_boost'));
                    $promotion=$quote+['unlocked'=>self::unlocked($next,$buildings),'building_level'=>max(TroopData::PROMOTION_BUILDING_LEVEL,(int)$next['unlock_building']),'castle_level'=>max(TroopData::PROMOTION_BUILDING_LEVEL,(int)$next['unlock_castle'])];
                }
                $troops[]=['code'=>$code,'name'=>$def['name_de']??$def['name'],'type'=>(int)$def['type'],'training_building'=>TroopData::buildingFor($code),'tier'=>(int)$def['tier'],'available'=>$available[$code]??0,'promotion'=>$promotion];
            }
            $formations=$db->query('SELECT slot,name,troops_json FROM troop_formations WHERE player_id=? ORDER BY slot',[$playerId])->fetchAll();
            foreach($formations as &$f){$f['slot']=(int)$f['slot'];$f['troops']=json_decode($f['troops_json'],true)?:[];unset($f['troops_json']);}unset($f);
            $reinforcements=$db->query("SELECT r.*,p.username AS sender_name,q.username AS target_name FROM reinforcements r JOIN players p ON p.id=r.sender_id JOIN players q ON q.id=r.target_player_id WHERE (r.sender_city_id=? OR r.target_city_id=?) AND r.state='active' ORDER BY r.id DESC",[$cityId,$cityId])->fetchAll();
            foreach($reinforcements as &$r){$r['troops']=json_decode($r['troops_json'],true)?:[];$r['can_recall']=(int)$r['sender_id']===$playerId;unset($r['troops_json']);}unset($r);
            return ['city_id'=>$cityId,'world_id'=>$world,'wall'=>self::wallStats($city,$buildings),'shield'=>['active'=>WorldRules::shieldActive($city+$shield),'expires_at'=>$city['shield_expires_at'],'beginner_until'=>$shield['beginner_shield_until']],
                'anti_spy'=>['active'=>!empty($city['anti_spy_until']) && strtotime($city['anti_spy_until'].' UTC')>time(),'expires_at'=>$city['anti_spy_until']??null],
                'protected_resources'=>self::protectedResources($city,$buffs),'protection_fraction'=>self::protectionFraction($buffs),'troops'=>$troops,'formations'=>$formations,'limits'=>ResearchEffects::limits($buffs),
                'reinforcements'=>$reinforcements,'promotions'=>$db->query("SELECT id,source_code,target_code,count,finishes_at FROM defense_promotions WHERE city_id=? AND state='training' ORDER BY id",[$cityId])->fetchAll(),
                'training_busy'=>(bool)$db->query('SELECT id FROM troop_queue WHERE city_id=? AND is_processed=0 LIMIT 1',[$cityId])->fetchColumn(),
                'training_slots'=>array_map('intval',$db->query('SELECT DISTINCT barrack_slot FROM troop_queue WHERE city_id=? AND is_processed=0',[$cityId])->fetchAll(\PDO::FETCH_COLUMN)),
                'targets'=>$db->query('SELECT c.id AS city_id,c.player_id,c.coord_x,c.coord_y,COALESCE(k.display_name,p.username) AS name,am.alliance_id FROM cities c JOIN players p ON p.id=c.player_id LEFT JOIN kingdom_profiles k ON k.player_id=p.id LEFT JOIN alliance_members am ON am.player_id=p.id AND am.world_id=c.world_id WHERE c.world_id=? AND c.player_id<>? AND c.is_hidden=0 AND p.is_hidden=0 ORDER BY name LIMIT 200',[$world,$playerId])->fetchAll(),
                'alliance_id'=>WorldRules::alliance($playerId),'server_time'=>time()];
        });
    }

    public static function action(int $playerId,array $body): array
    {
        return self::locked($playerId,function()use($playerId,$body):array{
            $city=self::city($playerId,isset($body['city_id'])?self::integer($body,'city_id'):null);$id=(int)$city['id'];
            if(in_array($body['action']??'',['wall.repair','promotion.start','scout','reinforce'],true))WorldContext::assertActionAvailable((int)$city['world_id']);
            return match($body['action']??''){
                'wall.repair'=>self::repairWall($playerId,$id,self::integer($body,'hp_amount',1,100000000)),
                'formation.save'=>self::saveFormation($playerId,$city,$body),
                'formation.delete'=>self::deleteFormation($playerId,self::integer($body,'slot',1,4)),
                'promotion.start'=>self::promote($playerId,$id,self::integer($body,'troop_code'),self::integer($body,'count',1,50000)),
                'promotion.cancel'=>self::cancelPromotion($playerId,self::integer($body,'promotion_id')),
                'reinforcement.recall'=>self::recallReinforcement($playerId,self::integer($body,'reinforcement_id')),
                'march.recall'=>self::recallMarch($playerId,self::integer($body,'march_id')),
                'scout','reinforce'=>self::dispatch($playerId,$city,$body),
                default=>throw new \DomainException('Unbekannte Verteidigungsaktion.'),
            };
        });
    }

    /** May be called inside the inventory transaction; this method does not consume an item. */
    public static function activateShield(int $playerId,int $cityId,int $seconds,int $quantity=1): array
    {
        $owned=WorldRules::origin($playerId,$cityId);WorldContext::assertActionAvailable((int)$owned['world_id']);
        if($seconds<60 || $seconds>604800 || $quantity<1)throw new \DomainException('Ungültige Schutzdauer.');
        $seconds *= $quantity;
        return self::atomic(function()use($playerId,$cityId,$seconds):array{
            $db=Connection::getInstance();$city=$db->query('SELECT * FROM cities WHERE id=? AND player_id=? FOR UPDATE',[$cityId,$playerId])->fetch();
            if(!$city)throw new \DomainException('Diese Stadt gehört dir nicht.',403);
            $hostile=$db->query("SELECT id FROM marches WHERE player_id=? AND world_id=? AND march_type IN (7,8,15) AND state IN ('marching','resolving') LIMIT 1",[$playerId,(int)$city['world_id']])->fetchColumn();
            $rally=$db->query("SELECT r.id FROM rallies r WHERE r.world_id=? AND r.target_player_id IS NOT NULL AND r.status IN ('gathering','marching') AND (r.leader_player_id=? OR EXISTS(SELECT 1 FROM rally_participants rp WHERE rp.rally_id=r.id AND rp.player_id=? AND rp.status IN ('pending','marching'))) LIMIT 1",[(int)$city['world_id'],$playerId,$playerId])->fetchColumn();
            if($hostile!==false||$rally!==false)throw new \DomainException('Rufe zuerst deine ausgehenden Stadtangriffe und Späher zurück.');
            $expires=gmdate('Y-m-d H:i:s',max(time(),empty($city['shield_expires_at'])?0:(int)strtotime($city['shield_expires_at'].' UTC'))+$seconds);
            $db->execute('UPDATE cities SET is_shielded=1,shield_expires_at=? WHERE id=?',[$expires,$cityId]);
            return ['message'=>'Deine Stadt ist geschützt. Ein Stadtangriff oder Spähauftrag hebt den Schutz auf.','shield_expires_at'=>$expires];
        });
    }

    public static function protectionFraction(array $buffs): float {return min(1,max(0,(float)($buffs['resource_protect']??0)+(float)($buffs['resource_protection']??0)));}
    public static function protectedResources(array $city,array $buffs): array
    {
        $result=[];$fraction=self::protectionFraction($buffs);foreach(['food','lumber','stone','gold'] as $key)$result[$key]=(int)min(max(0,(float)($city[$key]??0)),floor(max(0,(float)($city[$key]??0))*$fraction+max(0,(float)($buffs['storage_protection_flat']??0))+1e-8));return $result;
    }

    /** Synchronize wall upgrades and regeneration under a row lock without overwriting combat damage. */
    public static function syncWall(int $cityId): array
    {
        return self::atomic(function()use($cityId):array{
            $db=Connection::getInstance();$city=$db->query('SELECT * FROM cities WHERE id=? FOR UPDATE',[$cityId])->fetch();
            if(!$city)throw new \DomainException('Stadt nicht gefunden.');
            $level=max(1,(int)$db->query("SELECT level FROM city_buildings WHERE city_id=? AND building_code='wall'",[$cityId])->fetchColumn());
            $max=5000*$level;$oldMax=max(1,(int)$city['wall_hp_max']);$hp=min($max,max(0,(int)$city['wall_hp_current'])+max(0,$max-$oldMax));
            $elapsed=max(0,time()-(int)strtotime($city['wall_last_update'].' UTC'));
            // Zero HP stays breached until a repair or a successful relocation.
            if($hp>0)$hp=min($max,$hp+(int)floor($elapsed*$max/36000*(1+max(0,\Conquer\Game\Player\MasteryService::bonuses((int)$city['player_id'],(int)$city['world_id'])['talent_wall_recovery']??0))));
            if($hp!==(int)$city['wall_hp_current']||$max!==$oldMax){
                $db->execute('UPDATE cities SET wall_hp_current=?,wall_hp_max=?,wall_last_update=UTC_TIMESTAMP() WHERE id=?',[$hp,$max,$cityId]);$city['wall_last_update']=gmdate('Y-m-d H:i:s');
            }
            $city['wall_hp_current']=$hp;$city['wall_hp_max']=$max;
            if((int)$city['is_shielded'] && !empty($city['shield_expires_at']) && strtotime($city['shield_expires_at'].' UTC')<=time()){$db->execute('UPDATE cities SET is_shielded=0,shield_expires_at=NULL WHERE id=?',[$cityId]);$city['is_shielded']=0;$city['shield_expires_at']=null;}
            return $city;
        });
    }

    public static function wallStats(array $city,?array $buildings=null): array
    {
        $buildings??=self::buildings((int)$city['id']);$level=max(1,(int)($buildings['wall']['level']??1));
        $fraction=max(0,(float)$city['wall_hp_current'])/max(1,(float)$city['wall_hp_max']);
        return ['level'=>$level,'durability'=>(int)$city['wall_hp_current'],'durability_max'=>(int)$city['wall_hp_max'],'attack_buff'=>0,'defense_buff'=>round(min(60,$level*2)*$fraction,2),'repair_cost_per_1000'=>['stone'=>100,'lumber'=>50]];
    }

    public static function repairWall(int $playerId,int $cityId,int $amount): array
    {
        $owned=WorldRules::origin($playerId,$cityId);WorldContext::assertActionAvailable((int)$owned['world_id']);
        if($amount<1||$amount>100000000)throw new \DomainException('Ungültige Reparaturmenge.');
        return self::atomic(function()use($playerId,$cityId,$amount):array{
            $db=Connection::getInstance();WorldRules::origin($playerId,$cityId);$city=self::syncWall($cityId);ResourceTick::persist($city,self::buildings($cityId));$city=self::city($playerId,$cityId);$missing=(int)$city['wall_hp_max']-(int)$city['wall_hp_current'];
            if($missing<=0)throw new \DomainException('Die Mauer ist vollständig repariert.');
            $hp=min($missing,$amount);$chunks=(int)ceil($hp/1000);$stone=100*$chunks;$lumber=50*$chunks;
            if($db->execute('UPDATE cities SET stone=stone-?,lumber=lumber-?,wall_hp_current=wall_hp_current+?,wall_last_update=UTC_TIMESTAMP() WHERE id=? AND stone>=? AND lumber>=?',[$stone,$lumber,$hp,$cityId,$stone,$lumber])!==1)throw new \DomainException('Für die Reparatur fehlen Stein oder Holz.');
            return ['message'=>'Die Stadtmauer wurde repariert.','hp_repaired'=>$hp,'stone_spent'=>$stone,'lumber_spent'=>$lumber,'wall_hp_current'=>(int)$city['wall_hp_current']+$hp,'wall_hp_max'=>(int)$city['wall_hp_max']];
        });
    }

    public static function promotionQuote(int $sourceCode,int $count,array $buffs,float $boost=1): array
    {
        $source=TroopData::get($sourceCode);$target=TroopData::get($sourceCode+100);
        if(!$source||!$target||(int)$source['type']!==(int)$target['type']||$count<1||$count>50000)throw new \DomainException('Diese Truppen können nicht weiter befördert werden.');
        $training=ResearchEffects::training($sourceCode+100,$buffs,$boost);
        return ['source_code'=>$sourceCode,'target_code'=>$sourceCode+100,'name'=>$target['name_de']??$target['name'],'count'=>$count,'cost'=>array_map(fn($n)=>(int)ceil($n*$count*.7),$training['cost']),'duration_seconds'=>max(1,(int)ceil(TroopData::trainingSeconds($sourceCode+100,$count)*.5/$training['speed_multiplier'])),'max_count'=>$training['max_count']];
    }

    public static function promote(int $playerId,int $cityId,int $sourceCode,int $count): array
    {
        $owned=WorldRules::origin($playerId,$cityId);WorldContext::assertActionAvailable((int)$owned['world_id']);
        self::processPromotions($cityId);TroopTrainer::processQueue(Connection::getInstance(),$cityId);
        return self::atomic(function()use($playerId,$cityId,$sourceCode,$count):array{
            $db=Connection::getInstance();$city=$db->query('SELECT * FROM cities WHERE id=? AND player_id=? FOR UPDATE',[$cityId,$playerId])->fetch();if(!$city)throw new \DomainException('Diese Stadt gehört dir nicht.',403);
            $world=(int)$city['world_id'];$buildings=self::buildings($cityId);$quote=self::promotionQuote($sourceCode,$count,BuffEngine::getBuffs($playerId,$world),\Conquer\Game\Buff\ActiveBuffService::getMultiplier($playerId,'training_boost'));
            ResourceTick::persist($city,$buildings);
            if(!self::unlocked(TroopData::get($quote['target_code']),$buildings))throw new \DomainException('Beförderungen benötigen Ausbildungsgebäude und Stadtzentrum ab Stufe 13 sowie die Gebäudestufen der Zieltruppe.');
            if($count>$quote['max_count'])throw new \DomainException('Zu viele Truppen für einen Ausbildungsauftrag.');
            if($db->query('SELECT id FROM troop_queue WHERE city_id=? AND barrack_slot=? AND is_processed=0 LIMIT 1 FOR UPDATE',[$cityId,TroopData::slotFor($sourceCode)])->fetchColumn()!==false||self::hasPromotion($cityId,TroopData::slotFor($sourceCode)))throw new \DomainException('Dieses Ausbildungsgebäude ist bereits beschäftigt.');
            $cost=$quote['cost'];
            if($db->execute('UPDATE cities SET food=food-?,lumber=lumber-?,stone=stone-?,gold=gold-? WHERE id=? AND food>=? AND lumber>=? AND stone>=? AND gold>=?',[$cost['food'],$cost['lumber'],$cost['stone'],$cost['gold'],$cityId,$cost['food'],$cost['lumber'],$cost['stone'],$cost['gold']])!==1)throw new \DomainException('Für die Beförderung fehlen Ressourcen.');
            MarchArmy::reserve($db,$cityId,[$sourceCode=>$count]);
            $db->execute("INSERT INTO defense_promotions(player_id,city_id,source_code,target_code,count,cost_json,started_at,finishes_at) VALUES(?,?,?,?,?,?,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? SECOND))",[$playerId,$cityId,$sourceCode,$quote['target_code'],$count,json_encode($cost),$quote['duration_seconds']]);
            return $quote+['promotion_id'=>$db->lastInsertId(),'message'=>'Die Beförderung wurde begonnen.'];
        });
    }

    public static function processPromotions(int $cityId): void
    {
        try { self::atomic(function()use($cityId):void{
            $db=Connection::getInstance();$rows=$db->query("SELECT * FROM defense_promotions WHERE city_id=? AND state='training' AND finishes_at<=UTC_TIMESTAMP() ORDER BY id FOR UPDATE",[$cityId])->fetchAll();
            foreach($rows as $row){if($db->execute("UPDATE defense_promotions SET state='complete' WHERE id=? AND state='training'",[$row['id']])!==1)continue;self::creditTroops($cityId,[(int)$row['target_code']=>(int)$row['count']]);}
        }); } catch(\PDOException $e) { if(($e->errorInfo[1]??0)!==1146)throw $e; }
    }

    public static function hasPromotion(int $cityId, ?int $type=null): bool
    {
        try{return Connection::getInstance()->query("SELECT id FROM defense_promotions WHERE city_id=? AND state='training'".($type!==null?' AND FLOOR(source_code/100000)%10=?':'').' LIMIT 1',$type!==null?[$cityId,$type]:[$cityId])->fetchColumn()!==false;}
        catch(\PDOException $e){if(($e->errorInfo[1]??0)!==1146)throw $e;return false;}
    }

    public static function cancelPromotion(int $playerId,int $promotionId): array
    {
        return self::atomic(function()use($playerId,$promotionId):array{
            $db=Connection::getInstance();$row=$db->query("SELECT p.* FROM defense_promotions p JOIN cities c ON c.id=p.city_id WHERE p.id=? AND p.player_id=? AND c.world_id=? AND p.state='training' AND finishes_at>UTC_TIMESTAMP() FOR UPDATE",[$promotionId,$playerId,WorldContext::id()])->fetch();
            if(!$row)throw new \DomainException('Diese Beförderung ist bereits abgeschlossen oder gehört nicht dir.');
            $db->execute("UPDATE defense_promotions SET state='cancelled' WHERE id=?",[$promotionId]);self::creditTroops((int)$row['city_id'],[(int)$row['source_code']=>(int)$row['count']]);
            $cost=json_decode($row['cost_json'],true);$db->execute('UPDATE cities SET food=food+?,lumber=lumber+?,stone=stone+?,gold=gold+? WHERE id=?',[$cost['food'],$cost['lumber'],$cost['stone'],$cost['gold'],$row['city_id']]);
            return ['message'=>'Beförderung abgebrochen. Truppen und gezahlte Ressourcen sind zurück.'];
        });
    }

    public static function recallReinforcement(int $playerId,int $reinforcementId): array
    {
        return WorldRules::combatLock(fn()=>self::atomic(function()use($playerId,$reinforcementId):array{
            $db=Connection::getInstance();$r=$db->query("SELECT r.* FROM reinforcements r JOIN cities c ON c.id=r.sender_city_id WHERE r.id=? AND r.sender_id=? AND c.world_id=? AND r.state='active' FOR UPDATE",[$reinforcementId,$playerId,WorldContext::id()])->fetch();
            if(!$r)throw new \DomainException('Diese Verstärkung ist nicht aktiv oder gehört nicht dir.',403);
            $db->execute("UPDATE reinforcements SET state='recalled',recalled_at=UTC_TIMESTAMP() WHERE id=?",[$reinforcementId]);
            if($db->execute("UPDATE marches SET state='returning',haul_json=?,return_time=DATE_ADD(UTC_TIMESTAMP(),INTERVAL GREATEST(5,TIMESTAMPDIFF(SECOND,departure_time,arrival_time)) SECOND) WHERE id=? AND player_id=? AND state='arrived'",[json_encode(['survivors'=>json_decode($r['troops_json'],true)?:[],'loot'=>[]]),$r['march_id'],$playerId])!==1)throw new \DomainException('Dieser Rückmarsch wurde bereits abgerechnet.');
            return ['march_id'=>(int)$r['march_id'],'message'=>'Deine Verstärkung ist auf dem Heimweg.'];
        }));
    }

    public static function recallMarch(int $playerId,int $marchId): array
    {
        return self::atomic(function()use($playerId,$marchId):array{
            $db=Connection::getInstance();$m=$db->query("SELECT * FROM marches WHERE id=? AND player_id=? AND world_id=? AND ((state='marching' AND arrival_time>UTC_TIMESTAMP()) OR (march_type=9 AND state IN ('marching','arrived'))) FOR UPDATE",[$marchId,$playerId,WorldContext::id()])->fetch();
            if(!$m)throw new \DomainException('Dieser Marsch kann nicht mehr zurückgerufen werden.');
            if((int)$m['march_type']===9&&strtotime($m['arrival_time'].' UTC')<=time()){
                \Conquer\Game\March\GatherService::resolveGather($db,\Conquer\Logger::getInstance(),$marchId,$playerId,(int)$m['origin_city_id'],(int)$m['target_x'],(int)$m['target_y'],(int)$m['target_id']);
                \Conquer\Game\March\GatherService::finish($marchId,true);
                return ['march_id'=>$marchId,'message'=>'Die Sammler kehren mit den bisher gesammelten Ressourcen zurück.'];
            }
            if(in_array((int)$m['march_type'],[13,14],true))throw new \DomainException('Die Garnison kann nach der Ankunft im Kongress zurückgerufen werden.');
            $db->execute("UPDATE marches SET state='returning',haul_json=?,return_time=DATE_ADD(UTC_TIMESTAMP(),INTERVAL GREATEST(2,TIMESTAMPDIFF(SECOND,departure_time,UTC_TIMESTAMP())) SECOND) WHERE id=?",[json_encode(['survivors'=>json_decode($m['troops_json'],true)?:[],'loot'=>[]]),$marchId]);
            if((int)$m['march_type']===9)$db->execute('UPDATE field_objects SET gatherer_march_id=NULL WHERE id=? AND gatherer_march_id=?',[$m['target_id'],$marchId]);
            return ['march_id'=>$marchId,'message'=>'Die Armee ist auf dem Heimweg.'];
        });
    }

    private static function dispatch(int $playerId,array $city,array $body): array
    {
        $db=Connection::getInstance();$targetId=self::integer($body,'target_player_id');$target=$db->query('SELECT * FROM cities WHERE player_id=? AND world_id=? ORDER BY id LIMIT 1',[$targetId,$city['world_id']])->fetch();
        if(!$target)throw new \DomainException('In deiner Welt wurde keine Zielstadt gefunden.');
        $args=[$playerId,(int)$city['id'],(int)$city['coord_x'],(int)$city['coord_y'],(int)$target['coord_x'],(int)$target['coord_y']];
        $id=$body['action']==='scout'?MarchDispatcher::dispatchScout(...$args):MarchDispatcher::dispatchReinforce(...[...$args,(int)$target['id'],$targetId,MarchArmy::clean($body['troops']??null,ResearchEffects::limits(BuffEngine::getBuffs($playerId,(int)$city['world_id']))['march_capacity'])]);
        return ['march_id'=>$id,'message'=>$body['action']==='scout'?'Deine Späher sind unterwegs.':'Deine Verstärkung ist unterwegs.'];
    }

    private static function saveFormation(int $playerId,array $city,array $body): array
    {
        $slot=self::integer($body,'slot',1,4);$name=$body['name']??'';
        if(!is_string($name)||mb_strlen(trim($name))<1||mb_strlen(trim($name))>48)throw new \DomainException('Ein Formationsname mit 1 bis 48 Zeichen ist erforderlich.');
        $troops=MarchArmy::clean($body['troops']??null,ResearchEffects::limits(BuffEngine::getBuffs($playerId,(int)$city['world_id']))['march_capacity']);
        Connection::getInstance()->execute('INSERT INTO troop_formations(player_id,slot,name,troops_json) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name),troops_json=VALUES(troops_json)',[$playerId,$slot,trim($name),json_encode($troops)]);
        return ['slot'=>$slot,'message'=>'Formation gespeichert. Die Truppen werden erst beim Marsch reserviert.'];
    }
    private static function deleteFormation(int $playerId,int $slot): array {Connection::getInstance()->execute('DELETE FROM troop_formations WHERE player_id=? AND slot=?',[$playerId,$slot]);return ['message'=>'Formation gelöscht.'];}
    private static function unlocked(array $troop,array $buildings): bool
    {
        $level=(int)($buildings[TroopData::buildingFor((int)$troop['code'])]['level']??0);
        $castle=(int)($buildings['castle']['level']??0);
        return $level>=TroopData::PROMOTION_BUILDING_LEVEL && $castle>=TroopData::PROMOTION_BUILDING_LEVEL
            && TroopData::isUnlocked((int)$troop['code'],$level,$castle);
    }
    private static function buildings(int $cityId): array {$result=[];foreach(Connection::getInstance()->query('SELECT building_code,level FROM city_buildings WHERE city_id=?',[$cityId])->fetchAll() as $row)$result[$row['building_code']]=['level'=>(int)$row['level']];return $result;}
    private static function city(int $playerId,?int $cityId=null): array {$city=WorldContext::city($playerId);if($cityId!==null&&(int)$city['id']!==$cityId)throw new \DomainException('Diese Stadt gehört nicht zur aktiven Welt.',403);return $city;}
    private static function creditTroops(int $cityId,array $troops): void {foreach($troops as $code=>$count)Connection::getInstance()->execute('INSERT INTO city_troops(city_id,troop_code,count) VALUES(?,?,?) ON DUPLICATE KEY UPDATE count=count+VALUES(count)',[$cityId,$code,$count]);}
    private static function integer(array $body,string $key,int $min=1,int $max=2147483647): int {if(!isset($body[$key])||!is_int($body[$key])||$body[$key]<$min||$body[$key]>$max)throw new \DomainException('Ungültiger Wert für '.$key.'.');return $body[$key];}
    private static function atomic(callable $fn): mixed {$db=Connection::getInstance();return $db->getPdo()->inTransaction()?$fn():$db->transaction($fn);}
    private static function locked(int $playerId,callable $fn): mixed {$db=Connection::getInstance();$lock='conquer-player-'.$playerId;if((int)$db->query('SELECT GET_LOCK(?,5)',[$lock])->fetchColumn()!==1)throw new \DomainException('Deine Armee wird gerade aktualisiert.');try{return $fn();}finally{$db->query('SELECT RELEASE_LOCK(?)',[$lock]);}}
}
