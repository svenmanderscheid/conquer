<?php
declare(strict_types=1);
namespace Conquer\Game\Kingdom;

use Conquer\Db\Connection;
use Conquer\Game\Hospital\HospitalService;
use Conquer\Game\Inventory\InventoryService;
use Conquer\Game\Player\ActionPoints;
use Conquer\Game\Treasure\TreasureService;
use Conquer\Game\Vip\VipService;
use Conquer\Game\World\WorldContext;
use Conquer\Game\Defense\DefenseService;

/** Adapts the historical item catalogue to the current city and queue schema. Runs inside a TX. */
final class KingdomInventory
{
    /** These buffs have direct consumers in BuffEngine; three legacy boosts use ActiveBuffService. */
    public const DIRECT_BOOSTS = ['construction_speed','gathering_speed','food_production','lumber_production',
        'stone_production','gold_production','troops_atk','troops_def','troops_hp','march_size','march_speed','vs_monster_attack'];
    /** Catalogue visibility is separate from ownership; missing items remain unusable. */
    public static function catalog(int $playerId): array
    {
        $owned=array_column(InventoryService::getInventory($playerId),'quantity','item_code');
        return array_map(static fn(array $def):array=>$def+['item_code'=>(int)$def['code'],'quantity'=>(int)($owned[$def['code']]??0)],array_values(InventoryService::allDefs()));
    }

    /** Prices are server-owned game currency values from the catalog. */
    public static function shop(): array
    {
        return array_values(array_filter(InventoryService::allDefs(),static fn(array $item):bool=>(int)($item['price_gems']??0)>0&&\Conquer\Game\CrystalEconomy::allowsItem($item)));
    }

    /** Called under KingdomService's player lock and transaction. */
    public static function buy(int $playerId,array $body): array
    {
        WorldContext::assertActionAvailable();
        $code=KingdomService::integer($body,'item_code');
        $quantity=isset($body['quantity'])?KingdomService::integer($body,'quantity'):1;
        KingdomService::require($quantity>=1&&$quantity<=100,'Kaufe zwischen 1 und 100 Gegenstände.');
        $item=InventoryService::getItemDef($code);$price=(int)($item['price_gems']??0);
        \Conquer\Game\CrystalEconomy::requireItem($item);
        KingdomService::require($price>0,'Dieser Gegenstand wird nicht zum Kauf angeboten.');
        $request=$body['request_id']??'';
        KingdomService::require(is_string($request)&&preg_match('/^[A-Za-z0-9_-]{16,80}$/D',$request)===1,'Eine eindeutige Kaufkennung wird benötigt.');
        $world=WorldContext::id();$hash=hash('sha256',json_encode(['inventory.buy',$world,$code,$quantity],JSON_THROW_ON_ERROR));$db=Connection::getInstance();
        $receipt=$db->query('SELECT payload_hash,result_json FROM world_operations WHERE player_id=? AND request_id=? FOR UPDATE',[$playerId,$request])->fetch();
        if($receipt){KingdomService::require(hash_equals($receipt['payload_hash'],$hash),'Diese Kaufkennung wurde bereits anders verwendet.');return json_decode($receipt['result_json'],true,32,JSON_THROW_ON_ERROR)+['duplicate'=>true];}
        $cost=$price*$quantity;
        KingdomService::require($db->execute('UPDATE players SET gems=gems-? WHERE id=? AND gems>=?',[$cost,$playerId,$cost])===1,'Du hast nicht genügend Edelsteine.');
        InventoryService::addItems($playerId,$code,$quantity);
        $result=['message'=>$quantity.' × '.$item['name'].' wurde deinem Inventar hinzugefügt.','item_code'=>$code,'quantity'=>$quantity,'cost_gems'=>$cost];
        $db->execute("INSERT INTO world_operations(player_id,session_id,request_id,payload_hash,action,world_id,result_json)VALUES(?,0,?,?,'inventory.buy',?,?)",[$playerId,$request,$hash,$world,json_encode($result,JSON_THROW_ON_ERROR)]);
        return $result+['duplicate'=>false];
    }

    public static function use(int $playerId, array $state, array $body): array
    {
        $code = KingdomService::integer($body, 'item_code');
        $def = InventoryService::getItemDef($code);
        KingdomService::require($def !== null, 'Dieser Gegenstand wurde nicht gefunden.');
        KingdomService::require(($def['is_usable'] ?? true) !== false,
            (string)($def['usage_hint'] ?? $def['description_de'] ?? 'Dieser Gegenstand kann hier nicht verwendet werden.'));
        $useAll = $body['use_all'] ?? false;
        KingdomService::require(is_bool($useAll), 'Ungültige Sammelverwendung.');
        if ($useAll) {
            KingdomService::require(!array_key_exists('quantity', $body), 'Wähle eine Menge oder alle Gegenstände.');
            KingdomService::require(isset($body['operation_key']), 'Eine eindeutige Vorgangskennung ist erforderlich.');
        }
        $quantity = isset($body['quantity']) ? KingdomService::integer($body, 'quantity', 1, 10000) : 1;
        if (($def['category'] ?? '') !== 'speedup') {
            KingdomService::require($quantity===1, 'Bitte verwende jeweils einen Gegenstand.');
        }
        $db = Connection::getInstance();
        $owned = (int) $db->query('SELECT quantity FROM player_inventory WHERE player_id=? AND item_code=? FOR UPDATE', [$playerId,$code])->fetchColumn();
        KingdomService::require($owned>0 && $owned >= $quantity, 'Dieser Gegenstand liegt nicht in ausreichender Menge in deinem Inventar.');
        $cityId = (int) $state['city']['id'];
        if ($useAll) {
            $result = self::useAll($playerId, $cityId, $def, $body, $owned);
            $quantity = (int)($result['quantity'] ?? $owned);
            KingdomService::require(InventoryService::removeItems($playerId, $code, $quantity), 'Die Gegenstände wurden bereits verwendet.');
            return array_replace($result, ['item_code'=>$code,'quantity'=>$quantity,'use_all'=>true]);
        }
        $result = match ($def['category']) {
            'resource_pack'=>self::resource($playerId, $cityId, $def),
            'speedup'=>self::speedup($playerId, $cityId, $def, $body, $quantity),
            'chest'=>self::chest($playerId, $def),
            'ap_refill'=>self::ap($playerId, $def),
            'vip_point'=>self::vip($playerId, $def),
            'boost'=>self::boost($playerId, $cityId, $def),
            'resource_box'=>self::resourceBox($playerId, $cityId, $def),
            'fragment_pack'=>self::fragmentPack($playerId, $def),
            'teleport'=>self::teleport($playerId, $cityId, $def, $body),
            default=>throw new \DomainException('Dieser Gegenstand kann hier nicht verwendet werden.'),
        };
        KingdomService::require(InventoryService::removeItems($playerId, $code, $quantity), 'Der Gegenstand wurde bereits verwendet.');
        return $result + ['item_code'=>$code,'quantity'=>$quantity];
    }

    /** The locked stock is resolved once, before grants; retries replay the original receipt. */
    private static function useAll(int $playerId, int $cityId, array $def, array $body, int $quantity): array
    {
        $category = $def['category'];
        if ($category === 'resource_pack') {
            $def['amount'] = (int)$def['amount'] * $quantity;
            return self::resource($playerId, $cityId, $def);
        }
        if ($category === 'vip_point') {
            $def['vip_points'] = (int)$def['vip_points'] * $quantity;
            return self::vip($playerId, $def);
        }
        if ($category === 'boost') {
            if ($def['boost_type'] === 'city_shield') {
                return DefenseService::activateShield($playerId, $cityId, (int)$def['duration_seconds'], $quantity);
            }
            $def['duration_seconds'] = (int)$def['duration_seconds'] * $quantity;
            return self::boost($playerId, $cityId, $def);
        }
        if ($category === 'ap_refill') {
            $ap = ActionPoints::get($playerId);
            $amount = (int)$def['ap_amount'];
            KingdomService::require($amount > 0 && $ap['current'] < $ap['max'], 'Deine Aktionspunkte sind bereits vollständig gefüllt.');
            $quantity = min($quantity, (int)ceil(($ap['max'] - $ap['current']) / $amount));
            $def['ap_amount'] = $amount * $quantity;
            return self::ap($playerId, $def) + ['quantity'=>$quantity];
        }
        if ($category === 'speedup') return self::speedup($playerId, $cityId, $def, $body, $quantity);
        if (in_array($category, ['chest','resource_box','fragment_pack'], true)) {
            return self::bulkRewards($playerId, $cityId, $def, $quantity);
        }
        throw new \DomainException('Dieser Gegenstand kann nur einzeln verwendet werden.');
    }

    /** Roll each random item independently, then credit each distinct reward once. */
    private static function bulkRewards(int $playerId, int $cityId, array $def, int $quantity): array
    {
        $items = $fragments = $resources = $gradeCodes = [];
        $resourceNames = ['food'=>'Nahrung','lumber'=>'Holz','stone'=>'Stein','gold'=>'Gold'];
        $category = $def['category'];
        for ($n = 0; $n < $quantity; $n++) {
            if ($category === 'resource_box') {
                $resource = array_keys($resourceNames)[random_int(0, 3)];
                $resources[$resource] = ($resources[$resource] ?? 0) + random_int((int)$def['amount_min'], (int)$def['amount_max']);
                continue;
            }
            $drops = $category === 'chest'
                ? \Conquer\Game\Treasure\ChestService::rollDropTable((string)$def['chest_type'])
                : [array_replace($def, ['quantity'=>(int)$def['fragment_amount']])];
            foreach ($drops as $drop) {
                $amount = (int)$drop['quantity'];
                KingdomService::require($amount > 0, 'Ungültige Beutemenge.');
                if (isset($drop['fragment_grade']) || isset($drop['treasure_code'])) {
                    $code = (int)($drop['treasure_code'] ?? 0);
                    if (!$code) {
                        $grade = (string)$drop['fragment_grade'];
                        $codes = $gradeCodes[$grade] ??= \Conquer\Game\Treasure\TreasureData::getCodesByGrade($grade);
                        KingdomService::require((bool)$codes, 'Für diese Seltenheit gibt es keine Relikte.');
                        $code = $codes[array_rand($codes)];
                    }
                    $fragments[$code] = ($fragments[$code] ?? 0) + $amount;
                } else {
                    $code = (int)$drop['item_code'];
                    KingdomService::require(InventoryService::getItemDef($code) !== null, 'Unbekannter Gegenstand in der Schatztruhe.');
                    $items[$code] = ($items[$code] ?? 0) + $amount;
                }
            }
        }
        $rewards = [];
        foreach ($items as $code=>$amount) {
            InventoryService::addItems($playerId, $code, $amount);
            $rewards[] = ['type'=>'item','quantity'=>$amount] + \Conquer\Game\Rewards\RewardPresentation::item($code);
        }
        foreach ($fragments as $code=>$amount) {
            TreasureService::addFragments($playerId, $code, $amount);
            $rewards[] = ['type'=>'fragment','quantity'=>$amount] + \Conquer\Game\Rewards\RewardPresentation::fragment($code);
        }
        foreach ($resources as $resource=>$amount) {
            self::resource($playerId, $cityId, ['resource'=>$resource,'amount'=>$amount]);
            $rewards[] = ['type'=>'resource','resource'=>$resource,'quantity'=>$amount,'amount'=>$amount,'name'=>$resourceNames[$resource],'rarity'=>'normal'];
        }
        if ($category === 'chest') \Conquer\Game\Quest\DailyQuestService::trackProgress($playerId, 'open_chest', $quantity);
        return ['message'=>"$quantity Gegenstände wurden verwendet. Die Belohnungen wurden gutgeschrieben.",'drops'=>$rewards];
    }

    private static function resource(int $playerId, int $cityId, array $def): array
    {
        $resource = $def['resource'];
        $amount = (int) $def['amount'];
        KingdomService::require($amount>0 && in_array($resource, ['food','lumber','stone','gold','gems'], true), 'Ungültiges Ressourcenpaket.');
        $db = Connection::getInstance();
        // Earned, unopened packs retain their full value even when the passive storage is full.
        if ($resource==='gems') { $db->execute('UPDATE players SET gems=gems+? WHERE id=?', [$amount,$playerId]); }
        else { $db->execute("UPDATE cities SET $resource=$resource+? WHERE id=?", [$amount,$cityId]); }
        $name = ['food'=>'Nahrung','lumber'=>'Holz','stone'=>'Stein','gold'=>'Gold','gems'=>'Edelsteine'][$resource];
        return ['message'=>"$amount $name wurden gutgeschrieben.",'resource'=>$resource,'amount'=>$amount];
    }

    private static function resourceBox(int $playerId,int $cityId,array $def): array
    {
        $resources=['food','lumber','stone','gold'];
        $result=self::resource($playerId,$cityId,['resource'=>$resources[random_int(0,3)],'amount'=>random_int((int)$def['amount_min'],(int)$def['amount_max'])]);
        $names=['food'=>'Nahrung','lumber'=>'Holz','stone'=>'Stein','gold'=>'Gold'];
        return $result+['drops'=>[['type'=>'resource','resource'=>$result['resource'],'quantity'=>$result['amount'],
            'amount'=>$result['amount'],'name'=>$names[$result['resource']],'rarity'=>'normal']]];
    }

    private static function fragmentPack(int $playerId,array $def): array
    {
        $drop=isset($def['treasure_code'])
            ? TreasureService::addFragments($playerId,(int)$def['treasure_code'],(int)$def['fragment_amount'])+['treasure_code'=>(int)$def['treasure_code']]
            : TreasureService::addRandomFragment($playerId,(string)$def['fragment_grade'],(int)$def['fragment_amount']);
        return ['message'=>$def['fragment_amount'].' Reliktfragmente wurden deiner Sammlung hinzugefügt.','drops'=>[['type'=>'fragment','quantity'=>(int)$def['fragment_amount']]+$drop
            +\Conquer\Game\Rewards\RewardPresentation::fragment((int)$drop['treasure_code'])]];
    }

    /** Called while both the player and combat locks are held, inside the item transaction. */
    private static function teleport(int $playerId,int $cityId,array $def,array $body): array
    {
        $db=Connection::getInstance();$world=WorldContext::id();
        $size=\Conquer\Game\Map\WorldPlacement::lockWorld($db,$world);
        $city=WorldContext::city($playerId,$world,true);$x=(int)$city['coord_x'];$y=(int)$city['coord_y'];
        KingdomService::require((int)$city['id']===$cityId,'Diese Stadt gehört nicht zur aktiven Welt.');
        $march=$db->query("SELECT id FROM marches WHERE world_id=? AND (player_id=? OR (target_x=? AND target_y=?)) AND state IN ('marching','resolving','returning','arrived') LIMIT 1 FOR UPDATE",[$world,$playerId,$x,$y])->fetchColumn();
        $rally=$db->query("SELECT r.id FROM rallies r LEFT JOIN rally_participants p ON p.rally_id=r.id WHERE r.world_id=? AND (r.leader_player_id=? OR r.target_player_id=? OR p.player_id=?) AND r.status IN ('gathering','marching','returning') LIMIT 1 FOR UPDATE",[$world,$playerId,$playerId,$playerId])->fetchColumn();
        $support=$db->query("SELECT id FROM reinforcements WHERE (sender_city_id=? OR target_city_id=?) AND state='active' LIMIT 1 FOR UPDATE",[$cityId,$cityId])->fetchColumn();
        $expedition=$db->query("SELECT id FROM expedition_missions WHERE city_id=? AND status IN ('marching','returning') LIMIT 1 FOR UPDATE",[$cityId])->fetchColumn();
        KingdomService::require($march===false&&$rally===false&&$support===false&&$expedition===false,'Hole zuerst deine Armeen zurück. Bei ankommenden Märschen, Rallies oder Verstärkungen ist kein Teleport möglich.');
        $mode=(string)($def['teleport_mode']??'random');
        KingdomService::require(in_array($mode,['random','advanced','alliance'],true),'Dieser Teleporter hat keinen gültigen Zielmodus.');
        $left=1;$right=$size-3;$top=1;$bottom=$size-3;
        KingdomService::require($left<=$right&&$top<=$bottom,'Kein gültiges Zielgebiet.');
        $hasTarget=array_key_exists('target_x',$body)||array_key_exists('target_y',$body);
        if($hasTarget){
            KingdomService::require(in_array($mode,['advanced','alliance'],true),'Mit dem Zufallsteleporter kann kein Ziel gewählt werden.');
            $tx=KingdomService::integer($body,'target_x',1,$size-3);
            $ty=KingdomService::integer($body,'target_y',1,$size-3);
            KingdomService::require(max(abs($tx-$x),abs($ty-$y))>=4,'Wähle einen anderen Ort für deine Stadt.');
            if($mode==='alliance'){
                $allianceId=$db->query('SELECT alliance_id FROM alliance_members WHERE player_id=? AND world_id=? FOR UPDATE',[$playerId,$world])->fetchColumn();
                KingdomService::require($allianceId!==false,'Du gehörst keiner Allianz an. Dein Teleporter bleibt erhalten.');
                $centers=$db->query('SELECT c.coord_x,c.coord_y FROM alliance_members m JOIN cities c ON c.player_id=m.player_id AND c.world_id=m.world_id WHERE m.alliance_id=? AND m.world_id=? AND c.is_hidden=0 FOR UPDATE',[(int)$allianceId,$world])->fetchAll();
                $inAllianceArea=false;
                foreach($centers as $center)if(max(abs($tx-(int)$center['coord_x']),abs($ty-(int)$center['coord_y']))<=12){$inAllianceArea=true;break;}
                KingdomService::require($inAllianceArea,'Dieser Platz liegt außerhalb deines Allianzgebiets. Wähle einen Ort höchstens 12 Felder von einer verbündeten Stadt entfernt.');
            }
            KingdomService::require(\Conquer\Game\Map\WorldPlacement::canPlace($db,$world,'city',$tx,$ty,$cityId),'Dieser 4 × 4-Platz ist nicht vollständig frei, trocken und zugänglich. Dein Teleporter bleibt erhalten.');
            $db->execute('UPDATE cities SET coord_x=?,coord_y=? WHERE id=?',[$tx,$ty,$cityId]);
            return ['message'=>"Deine Stadt steht jetzt bei X $tx / Y $ty.",'coord_x'=>$tx,'coord_y'=>$ty,'world_id'=>$world,'teleport_mode'=>$mode,'selected_destination'=>true];
        }
        KingdomService::require($mode==='random','Wähle zuerst einen Zielplatz auf der Weltkarte.');
        for($n=0;$n<250;$n++){
            $tx=random_int($left,$right);$ty=random_int($top,$bottom);
            if(max(abs($tx-$x),abs($ty-$y))<4||!\Conquer\Game\Map\WorldPlacement::canPlace($db,$world,'city',$tx,$ty,$cityId))continue;
            $db->execute('UPDATE cities SET coord_x=?,coord_y=? WHERE id=?',[$tx,$ty,$cityId]);
            return ['message'=>"Deine Stadt steht jetzt bei X $tx / Y $ty.",'coord_x'=>$tx,'coord_y'=>$ty,'world_id'=>$world,'teleport_mode'=>$mode];
        }
        throw new \DomainException('Im Zielgebiet wurde kein freier, trockener Platz gefunden. Dein Teleporter bleibt erhalten.');
    }

    private static function speedup(int $playerId, int $cityId, array $def, array $body, int $quantity): array
    {
        $db = Connection::getInstance();
        $subcategory = $def['subcategory'] ?? 'generic';
        $type = $body['queue_type'] ?? ($subcategory==='healing' ? 'healing' : '');
        $seconds = (int) $def['duration_seconds'];
        KingdomService::require($seconds>0, 'Dieser Beschleuniger hat keine gültige Dauer.');
        $allowed = $subcategory==='generic' ? ['building','research','training','healing'] : [$subcategory];
        KingdomService::require(is_string($type) && in_array($type, $allowed, true), 'Wähle eine passende laufende Warteschlange.');
        if ($type==='healing') {
            if (($body['use_all'] ?? false) === true) {
                $active = HospitalService::getStatus($cityId)['active'];
                KingdomService::require($active && ($body['batch_id'] ?? null) === $active['batch_id'], 'Dieser Heilungsauftrag ist bereits beendet.');
                $remaining = max(0, strtotime($active['ends_at']) - time());
                KingdomService::require($remaining > 0, 'Dieser Heilungsauftrag ist bereits beendet.');
                $quantity = min($quantity, (int)ceil($remaining / $seconds));
                HospitalService::reduceTime($cityId, $seconds * $quantity, $active['batch_id']);
                return ['message'=>'Die Heilungszeit wurde verkürzt.','seconds'=>$seconds * $quantity,'quantity'=>$quantity];
            }
            KingdomService::require($quantity===1, 'Heilungsbeschleuniger werden über das Hospital verwendet.');
            HospitalService::reduceTime($cityId,$seconds,isset($body['batch_id'])?(string)$body['batch_id']:null);
            return ['message'=>'Die Heilungszeit wurde verkürzt.','seconds'=>$seconds];
        }
        $id = KingdomService::integer($body, 'queue_id');
        $config = [
            'building'=>['building_queue','city_id',$cityId],
            'research'=>['research_queue','player_id',$playerId],
            'training'=>['troop_queue','city_id',$cityId],
        ];
        KingdomService::require(isset($config[$type]), 'Ungültige Warteschlange.');
        [$table,$ownerColumn,$ownerId] = $config[$type];
        $scope=$type==='research'?' AND world_id=' . \Conquer\Game\World\WorldContext::id():'';
        $row = $db->query("SELECT id,GREATEST(1,TIMESTAMPDIFF(SECOND,UTC_TIMESTAMP(),finishes_at)) remaining_seconds FROM $table WHERE id=? AND $ownerColumn=? AND is_processed=0 AND finishes_at>UTC_TIMESTAMP()".$scope.' FOR UPDATE', [$id,$ownerId])->fetch();
        KingdomService::require((bool) $row, 'Diese Warteschlange ist beendet oder gehört nicht zu deinem Königreich.');
        $needed = (int) ceil((int)$row['remaining_seconds'] / $seconds);
        $maxQuantity = ($body['use_all'] ?? false) ? $needed : min(10000, $needed);
        if ($body['use_all'] ?? false) $quantity = min($quantity, $maxQuantity);
        KingdomService::require($quantity <= $maxQuantity, 'So viele Beschleuniger werden für diese Restzeit nicht benötigt.');
        $appliedSeconds = $seconds * $quantity;
        $db->execute("UPDATE $table SET finishes_at=GREATEST(UTC_TIMESTAMP(),DATE_SUB(finishes_at,INTERVAL ? SECOND)) WHERE id=?", [$appliedSeconds,$id]);
        return ['message'=>'Die verbleibende Zeit wurde verkürzt.','seconds'=>$appliedSeconds,'quantity'=>$quantity,'queue_id'=>$id,'queue_type'=>$type];
    }

    private static function chest(int $playerId, array $def): array
    {
        $drops=\Conquer\Game\Treasure\ChestService::grantRewards($playerId,(string)$def['chest_type']);
        return ['message'=>'Die Schatzkiste ist geöffnet. Ihre Beute liegt in deinem Inventar und deiner Schatzkammer.','drops'=>$drops];
    }

    private static function ap(int $playerId, array $def): array
    {
        $current = ActionPoints::get($playerId);
        KingdomService::require($current['current']<$current['max'], 'Deine Aktionspunkte sind bereits vollständig gefüllt.');
        $amount = min((int) $def['ap_amount'], $current['max']-$current['current']);
        Connection::getInstance()->execute('UPDATE players SET action_points=LEAST(action_points+?,?) WHERE id=?', [$amount,$current['max'],$playerId]);
        return ['message'=>"$amount Aktionspunkte wurden wiederhergestellt.",'amount'=>$amount];
    }

    private static function vip(int $playerId, array $def): array
    {
        VipService::addPoints($playerId, (int) $def['vip_points']);
        return ['message'=>'Deine Prestige-Punkte wurden gutgeschrieben.'];
    }

    private static function boost(int $playerId, int $cityId, array $def): array
    {
        $type = (string) $def['boost_type'];
        $seconds = (int) $def['duration_seconds'];
        $db = Connection::getInstance();
        if($type==='city_shield')return DefenseService::activateShield($playerId,$cityId,$seconds);
        if($type==='anti_spy'){
            $city=WorldContext::city($playerId,null,true);
            KingdomService::require((int)$city['id']===$cityId,'Diese Stadt gehört nicht zur aktiven Welt.');
            $db->execute('UPDATE cities SET anti_spy_until=DATE_ADD(GREATEST(COALESCE(anti_spy_until,UTC_TIMESTAMP()),UTC_TIMESTAMP()),INTERVAL ? SECOND) WHERE id=?',[$seconds,$cityId]);
            return ['message'=>'Der Spähschutz wurde für diese Stadt aktiviert.','anti_spy_until'=>$db->query('SELECT anti_spy_until FROM cities WHERE id=?',[$cityId])->fetchColumn()];
        }
        $bonus = (float) ($def['bonus_pct']??0);
        KingdomService::require(in_array($type,array_merge(self::DIRECT_BOOSTS,['resource_production','research_speed','training_speed']),true)&&$bonus>0&&$bonus<=100,'Dieser Bonus wird nicht unterstützt.');
        $old=$db->query('SELECT bonus_pct,expires_at FROM player_charms_active WHERE player_id=? AND stat_category=? AND expires_at>UTC_TIMESTAMP() FOR UPDATE',[$playerId,$type])->fetch();
        KingdomService::require(!$old||(float)$old['bonus_pct']<=$bonus+0.00001,'Ein stärkerer Bonus ist bereits aktiv. Der Gegenstand bleibt in deinem Inventar.');
        // Production boosts are account-wide: settle every city before changing their strength.
        if(str_ends_with($type,'_production')){
            foreach($db->query('SELECT * FROM cities WHERE player_id=? ORDER BY world_id FOR UPDATE',[$playerId])->fetchAll()as$city){
                $buildings=[];foreach($db->query('SELECT building_code,level FROM city_buildings WHERE city_id=?',[(int)$city['id']])->fetchAll()as$b)$buildings[$b['building_code']]=['level'=>(int)$b['level']];
                \Conquer\Game\City\ResourceTick::persist($city,$buildings);
            }
        }
        $extend=$old&&abs((float)$old['bonus_pct']-$bonus)<0.00001;
        $expiry=$extend?'DATE_ADD(GREATEST(expires_at,UTC_TIMESTAMP()),INTERVAL ? SECOND)':'DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? SECOND)';
        $db->execute("INSERT INTO player_charms_active (player_id,stat_category,grade,charm_code,bonus_pct,expires_at)
            VALUES (?,?,'normal',?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? SECOND))
            ON DUPLICATE KEY UPDATE charm_code=VALUES(charm_code),bonus_pct=VALUES(bonus_pct),expires_at=".$expiry,
            [$playerId,$type,(int)$def['code'],$bonus,$seconds,$seconds]);
        $buffType=['resource_production'=>'production_boost','research_speed'=>'research_boost','training_speed'=>'training_boost'][$type]??null;
        if($buffType!==null){
            $expires=$db->query('SELECT expires_at FROM player_charms_active WHERE player_id=? AND stat_category=?',[$playerId,$type])->fetchColumn();
            $active=$db->query('SELECT id FROM active_buffs WHERE player_id=? AND buff_type=? AND expires_at>UTC_TIMESTAMP() ORDER BY id LIMIT 1 FOR UPDATE',[$playerId,$buffType])->fetchColumn();
            if($active)$db->execute('UPDATE active_buffs SET multiplier=?,expires_at=? WHERE id=?',[1+$bonus/100,$expires,$active]);
            else $db->execute('INSERT INTO active_buffs(player_id,buff_type,multiplier,expires_at) VALUES(?,?,?,?)',[$playerId,$buffType,1+$bonus/100,$expires]);
        }
        return ['message'=>'Der zeitlich begrenzte Bonus wurde aktiviert.'];
    }
}
