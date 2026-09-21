<?php
declare(strict_types=1);
namespace Conquer\Admin;

use Conquer\Db\Connection;
use Conquer\Game\City\{CityState,BuildingData,TroopData,ResourceTick};
use Conquer\Game\Inventory\InventoryService;
use Conquer\Game\Research\ResearchData;
use Conquer\Game\World\WorldSettings;

/** Admin mutations, receipt and audit commit atomically. No swallowed database errors. */
final class AdminService
{
    public static function execute(int $adminId,string $action,array $input): array
    {
        $db=Connection::getInstance();
        $op=$input['operation_id']??'';
        if(!is_string($op)||!preg_match('/^[a-f0-9]{32}$/D',$op))throw new \InvalidArgumentException('Ungültige Vorgangs-ID. Bitte lade die Seite neu.');
        $reason=self::text($input['reason']??'',3,500,'Begründung');
        unset($input['csrf_token'],$input['operation_id']);ksort($input);
        $hash=hash('sha256',json_encode($input,JSON_THROW_ON_ERROR));
        $locks=['conquer-admin-op-'.$op];
        if(in_array($action,['reward-save','reward-reset'],true))$locks[]='conquer-admin-rewards';
        $playerId=WorldSettings::integer($input['player_id']??0,0,2147483647,'Spieler-ID');
        if($playerId>0)$locks[]='conquer-player-'.$playerId;
        $acquired=[];
        try {
            foreach($locks as $lock){if((int)$db->query('SELECT GET_LOCK(?,5)',[$lock])->fetchColumn()!==1)throw new \RuntimeException('Vorgang ist beschäftigt. Bitte erneut versuchen.');$acquired[]=$lock;}
            return $db->transaction(static function(Connection $db)use($adminId,$action,$input,$op,$hash,$reason):array {
                $admin=$db->query('SELECT role FROM admin_users WHERE id=? FOR UPDATE',[$adminId])->fetchColumn();
                if($admin!=='superadmin')throw new \InvalidArgumentException('Diese Aktion benötigt die Superadmin-Rolle.');
                $receipt=$db->query('SELECT * FROM admin_operations WHERE operation_id=? FOR UPDATE',[$op])->fetch();
                if($receipt){if((int)$receipt['admin_id']!==$adminId||$receipt['action']!==$action||!hash_equals($receipt['payload_hash'],$hash))throw new \InvalidArgumentException('Diese Vorgangs-ID gehört zu einer anderen Änderung.');$result=json_decode($receipt['result_json'],true,512,JSON_THROW_ON_ERROR);$result['duplicate']=true;return $result;}
                $db->execute('INSERT INTO admin_operations(operation_id,admin_id,action,payload_hash) VALUES(?,?,?,?)',[$op,$adminId,$action,$hash]);
                $result=match($action) {
                    'alpha-waitlist-update'=>AlphaWaitlistAdmin::update($db,$input),
                    'alpha-key-create'=>AlphaKeyAdmin::create($db,$input),
                    'alpha-key-revoke'=>AlphaKeyAdmin::revoke($db,$input),
                    'world-save','world-create'=>self::world($db,$adminId,$action,$input),
                    'world-events'=>self::events($input),
                    'gift'=>self::gift($db,$op,$input),
                    'reward-save','reward-reset'=>RewardEditor::save($db,$adminId,$action,$input),
                    'land-rules-save'=>self::landRules($adminId,$input),
                    'bug-report-update'=>self::bugReport($db,$adminId,$input),
                    default=>self::player($db,$action,$input),
                };
                $result['duplicate']=false;$result['operation_id']=$op;
                $db->execute('INSERT INTO admin_audit_log(admin_id,action,target_type,target_id,details,ip) VALUES(?,?,?,?,?,?)',[$adminId,'admin.'.$action,$result['target_type'],$result['target_id'],json_encode(['operation_id'=>$op,'reason'=>$reason,'before'=>$result['before']??null,'after'=>$result['after']??null,'result'=>$result['message']],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$_SERVER['REMOTE_ADDR']??null]);
                // Invitation secrets may travel to the one-time response, never to durable receipts.
                $receiptResult=$result;unset($receiptResult['issued_keys']);
                $db->execute('UPDATE admin_operations SET result_json=? WHERE operation_id=?',[json_encode($receiptResult,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$op]);
                return $result;
            });
        } finally {foreach(array_reverse($acquired) as $lock)$db->query('SELECT RELEASE_LOCK(?)',[$lock]);}
    }

    private static function bugReport(Connection $db,int $adminId,array $input): array
    {
        $id=WorldSettings::integer($input['report_id']??0,1,PHP_INT_MAX,'Meldungs-ID');
        $status=$input['status']??'';$priority=$input['priority']??'';
        if(!is_string($status)||!in_array($status,['new','in_progress','resolved','closed'],true))throw new \InvalidArgumentException('Ungültiger Bearbeitungsstatus.');
        if(!is_string($priority)||!in_array($priority,['low','normal','high','urgent'],true))throw new \InvalidArgumentException('Ungültige Priorität.');
        $note=self::text($input['admin_note']??'',0,4000,'Interne Notiz');
        $before=$db->query('SELECT id,world_id,status,priority,admin_note,handled_by,handled_at FROM bug_reports WHERE id=? FOR UPDATE',[$id])->fetch();
        if(!$before)throw new \InvalidArgumentException('Bugmeldung nicht gefunden.');
        $handled=in_array($status,['resolved','closed'],true)?gmdate('Y-m-d H:i:s'):null;
        $db->execute('UPDATE bug_reports SET status=?,priority=?,admin_note=?,handled_by=?,handled_at=? WHERE id=?',[$status,$priority,$note,$adminId,$handled,$id]);
        $after=['status'=>$status,'priority'=>$priority,'admin_note'=>$note,'handled_by'=>$adminId,'handled_at'=>$handled];
        return ['target_type'=>'bug_report','target_id'=>$id,'world_id'=>(int)$before['world_id'],'before'=>$before,'after'=>$after,'message'=>'Bugmeldung #'.$id.' wurde aktualisiert.'];
    }

    private static function world(Connection $db,int $adminId,string $action,array $input): array
    {
        $name=self::text($input['name']??'',2,50,'Weltname');$status=$input['status']??'open';
        if(!in_array($status,['open','running','paused','closed'],true))throw new \InvalidArgumentException('Ungültiger Weltstatus.');
        $cfg=WorldSettings::validate($input['settings']??[]);
        $factors=[];foreach(['speed_factor','gather_factor','haul_factor'] as $key){$v=$input[$key]??1;if(!is_scalar($v)||!is_numeric($v)||(float)$v<0.1||(float)$v>20)throw new \InvalidArgumentException('Weltfaktoren müssen zwischen 0,1 und 20 liegen.');$factors[$key]=(float)$v;}
        $before=null;
        if($action==='world-create') {
            $slug=$input['slug']??'';if(!is_string($slug)||!preg_match('/^[a-z0-9][a-z0-9-]{1,19}$/D',$slug))throw new \InvalidArgumentException('Weltkürzel: 2–20 Kleinbuchstaben, Ziffern oder Bindestriche.');
            $size=WorldSettings::integer($input['map_size']??256,256,256,'Kartengröße');
            if($db->query('SELECT id FROM worlds WHERE slug=?',[$slug])->fetchColumn())throw new \InvalidArgumentException('Dieses Weltkürzel wird bereits verwendet.');
            $db->execute('INSERT INTO worlds(name,slug,status,map_size,map_seed,speed_factor,gather_factor,haul_factor) VALUES(?,?,?,?,?,?,?,?)',[$name,$slug,$status,$size,random_int(1,2147483647),...array_values($factors)]);$id=$db->lastInsertId();
            \Conquer\Game\World\WorldService::initializeWorld($id);
        } else {
            $id=WorldSettings::integer($input['world_id']??0,1,2147483647,'Welt-ID');
            $before=$db->query('SELECT * FROM worlds WHERE id=? FOR UPDATE',[$id])->fetch();if(!$before)throw new \InvalidArgumentException('Welt nicht gefunden.');
            $before['spawn']=WorldSettings::get($id)['settings'];
            $db->execute('UPDATE worlds SET name=?,status=?,speed_factor=?,gather_factor=?,haul_factor=? WHERE id=?',[$name,$status,...array_values($factors),$id]);
        }
        $next=gmdate('Y-m-d H:i:s',WorldSettings::nextWindow($cfg,time()));
        $db->execute('INSERT INTO world_spawn_settings(world_id,settings_json,next_run_at,updated_by) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE settings_json=VALUES(settings_json),next_run_at=VALUES(next_run_at),updated_by=VALUES(updated_by)',[$id,json_encode($cfg,JSON_THROW_ON_ERROR),$next,$adminId]);
        return ['target_type'=>'world','target_id'=>$id,'world_id'=>$id,'before'=>$before,'after'=>['name'=>$name,'status'=>$status,'factors'=>$factors,'spawn'=>$cfg],'message'=>$action==='world-create'?'Welt wurde angelegt.':'Welteinstellungen wurden gespeichert.'];
    }

    private static function events(array $input): array
    {
        $id=WorldSettings::integer($input['world_id']??0,1,2147483647,'Welt-ID');
        $class='Conquer\\Game\\Conquest\\EventService';
        if(!class_exists($class))throw new \InvalidArgumentException('Eventverwaltung ist noch nicht verfügbar.');
        $before=$class::settings($id);$after=$class::saveSettings($id,$input);
        return ['target_type'=>'world','target_id'=>$id,'before'=>$before,'after'=>$after,'message'=>'Eventtermine wurden gespeichert.'];
    }

    private static function landRules(int $adminId,array $input): array
    {
        $world=WorldSettings::integer($input['world_id']??null,1,2147483647,'Welt-ID');
        $revision=WorldSettings::integer($input['revision']??null,0,2147483647,'Version');
        $rules=$input['rules']??null;if(!is_array($rules))throw new \InvalidArgumentException('Landregeln fehlen.');
        $before=\Conquer\Game\World\LandRules::get($world);
        $after=\Conquer\Game\World\LandRules::save($world,array_replace_recursive($before,$rules),$adminId,$revision);
        \Conquer\Game\World\LandUnlockService::evaluate($world);
        return ['target_type'=>'world','target_id'=>$world,'world_id'=>$world,'before'=>$before,'after'=>$after,'message'=>'Landregeln gespeichert. Bereits geöffnete Gebiete bleiben offen.'];
    }

    private static function player(Connection $db,string $action,array $input): array
    {
        $id=WorldSettings::integer($input['player_id']??0,1,2147483647,'Spieler-ID');
        $world=WorldSettings::integer($input['world_id']??0,1,2147483647,'Welt-ID');
        $player=$db->query('SELECT id,username,is_banned,gems,vip_level,vip_points FROM players WHERE id=? FOR UPDATE',[$id])->fetch();
        if(!$player)throw new \InvalidArgumentException('Spieler nicht gefunden.');
        $city=$db->query('SELECT * FROM cities WHERE player_id=? AND world_id=? FOR UPDATE',[$id,$world])->fetch();
        if(!$city)throw new \InvalidArgumentException('Der Spieler hat in dieser Welt keine Stadt.');
        $cityId=(int)$city['id'];$before=[];$after=[];
        switch($action) {
            case 'ban':case 'unban':
                $before=['is_banned'=>(int)$player['is_banned']];$after=['is_banned'=>$action==='ban'?1:0];
                $db->execute('UPDATE players SET is_banned=? WHERE id=?',[$after['is_banned'],$id]);
                if($action==='ban')$db->execute('DELETE FROM sessions WHERE player_id=?',[$id]);
                break;
            case 'grant-gems':
                $amount=WorldSettings::integer($input['amount']??0,1,1000000,'Edelsteine');
                $before=['gems'=>(int)$player['gems']];$after=['gems'=>(int)$player['gems']+$amount];
                if($after['gems']>2000000000)throw new \InvalidArgumentException('Edelsteinlimit überschritten.');
                $db->execute('UPDATE players SET gems=? WHERE id=?',[$after['gems'],$id]);break;
            case 'set-account-values':
                $gems=WorldSettings::integer($input['gems']??null,0,2000000000,'Edelsteine');
                $vip=WorldSettings::integer($input['vip_level']??null,1,15,'VIP-Stufe');
                $points=WorldSettings::integer($input['vip_points']??null,0,2000000000,'VIP-Punkte');
                $before=['gems'=>(int)$player['gems'],'vip_level'=>(int)$player['vip_level'],'vip_points'=>(int)$player['vip_points']];
                $after=['gems'=>$gems,'vip_level'=>$vip,'vip_points'=>$points];
                $db->execute('UPDATE players SET gems=?,vip_level=?,vip_points=? WHERE id=?',[$gems,$vip,$points,$id]);break;
            case 'grant-shield':
                $hours=WorldSettings::integer($input['hours']??0,1,720,'Schutzdauer');
                $before=['is_shielded'=>$city['is_shielded'],'shield_expires_at'=>$city['shield_expires_at']];
                $until=gmdate('Y-m-d H:i:s',max(time(),$city['shield_expires_at']?strtotime($city['shield_expires_at'].' UTC'):0)+$hours*3600);
                $after=['is_shielded'=>1,'shield_expires_at'=>$until];
                $db->execute('UPDATE cities SET is_shielded=1,shield_expires_at=? WHERE id=?',[$until,$cityId]);break;
            case 'set-resources':
                foreach(['food','lumber','stone','gold'] as $r){$before[$r]=(int)$city[$r];$after[$r]=WorldSettings::integer($input[$r]??null,0,1000000000000,$r);}
                $db->execute('UPDATE cities SET food=?,lumber=?,stone=?,gold=?,last_resource_update=UTC_TIMESTAMP() WHERE id=?',[...array_values($after),$cityId]);break;
            case 'set-building':
                $code=$input['building_code']??'';
                if(!is_string($code)||!in_array($code,CityState::BUILDING_CODES,true))throw new \InvalidArgumentException('Unbekanntes Gebäude.');
                $level=WorldSettings::integer($input['level']??null,1,30,'Gebäudestufe');
                if($db->query('SELECT id FROM building_queue WHERE city_id=? AND building_code=? AND is_processed=0 LIMIT 1 FOR UPDATE',[$cityId,$code])->fetchColumn())throw new \InvalidArgumentException('Für dieses Gebäude läuft ein Ausbau. Erst abschließen lassen, damit kein Ausbau überschrieben wird.');
                $buildings=[];foreach($db->query('SELECT building_code,level FROM city_buildings WHERE city_id=?',[$cityId])->fetchAll() as $row)$buildings[$row['building_code']]=['level'=>(int)$row['level']];
                $before=['building_code'=>$code,'level'=>(int)($buildings[$code]['level']??1)];$after=['building_code'=>$code,'level'=>$level];
                // Settle elapsed production using the old building rates first.
                $factor=(float)$db->query('SELECT speed_factor FROM worlds WHERE id=?',[$world])->fetchColumn();
                ResourceTick::persist($city,$buildings,$factor);
                $db->execute('INSERT INTO city_buildings(city_id,building_code,level) VALUES(?,?,?) ON DUPLICATE KEY UPDATE level=VALUES(level)',[$cityId,$code,$level]);
                $buildings[$code]=['level'=>$level];$power=BuildingData::calculateCityPower($buildings);
                $db->execute('UPDATE cities SET power=?,castle_level=? WHERE id=?',[$power,$buildings['castle']['level']??1,$cityId]);
                if($code==='wall')\Conquer\Game\Defense\DefenseService::syncWall($cityId);
                break;
            case 'set-research':
                $code=$input['research_code']??'';$def=is_string($code)?ResearchData::get($code):null;
                if(!$def)throw new \InvalidArgumentException('Unbekannte Forschung.');
                $level=WorldSettings::integer($input['level']??null,0,(int)$def['max_level'],'Forschungsstufe');
                if($db->query('SELECT id FROM research_queue WHERE player_id=? AND world_id=? AND research_code=? AND is_processed=0 LIMIT 1 FOR UPDATE',[$id,$world,$code])->fetchColumn())throw new \InvalidArgumentException('Diese Forschung läuft bereits. Erst abschließen lassen.');
                $old=$db->query('SELECT level FROM player_research WHERE player_id=? AND world_id=? AND research_code=? FOR UPDATE',[$id,$world,$code])->fetchColumn();
                $before=['research_code'=>$code,'level'=>(int)$old];$after=['research_code'=>$code,'level'=>$level];
                $db->execute('INSERT INTO player_research(player_id,world_id,research_code,level) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE level=VALUES(level)',[$id,$world,$code,$level]);break;
            case 'add-troops':case 'set-troops':
                $code=WorldSettings::integer($input['troop_code']??0,1,2147483647,'Truppencode');
                if(!TroopData::get($code))throw new \InvalidArgumentException('Unbekannter Truppentyp.');
                $old=$db->query('SELECT count FROM city_troops WHERE city_id=? AND troop_code=? FOR UPDATE',[$cityId,$code])->fetchColumn();
                $amount=WorldSettings::integer($input['count']??null,$action==='add-troops'?1:0,1000000,'Truppenanzahl');
                $count=$action==='add-troops'?(int)$old+$amount:$amount;
                if($count>2000000000)throw new \InvalidArgumentException('Truppenlimit überschritten.');
                $before=['troop_code'=>$code,'count'=>(int)$old];$after=['troop_code'=>$code,'count'=>$count,'added'=>$action==='add-troops'?$amount:null];
                $db->execute('INSERT INTO city_troops(city_id,troop_code,count) VALUES(?,?,?) ON DUPLICATE KEY UPDATE count=VALUES(count)',[$cityId,$code,$count]);break;
            default:throw new \InvalidArgumentException('Unbekannte Verwaltungsaktion.');
        }
        return ['target_type'=>'player','target_id'=>$id,'before'=>$before,'after'=>$after,'message'=>'Änderung für '.$player['username'].' wurde gespeichert.'];
    }

    private static function gift(Connection $db,string $operation,array $input): array
    {
        $world=WorldSettings::integer($input['world_id']??0,1,2147483647,'Welt-ID');
        $id=WorldSettings::integer($input['player_id']??0,0,2147483647,'Spieler-ID');
        $title=self::text($input['title']??'',3,100,'Geschenktitel');$message=self::text($input['message']??'',0,1000,'Nachricht');
        $rewards=[];foreach(['food','lumber','stone','gold','gems'] as $resource)$rewards[$resource]=WorldSettings::integer($input[$resource]??0,0,$resource==='gems'?1000000:1000000000,$resource);
        $item=WorldSettings::integer($input['item_code']??0,0,2147483647,'Gegenstand');$quantity=WorldSettings::integer($input['quantity']??0,0,100000,'Anzahl');
        if(($item>0)!==($quantity>0)||($item>0&&!InventoryService::getItemDef($item)))throw new \InvalidArgumentException('Gegenstand und Menge müssen gemeinsam gültig sein.');
        if(array_sum($rewards)===0&&$quantity===0)throw new \InvalidArgumentException('Mindestens eine Belohnung ist erforderlich.');
        $rewards['item_code']=$item;$rewards['quantity']=$quantity;
        $recipients=$db->query('SELECT c.id,c.player_id FROM cities c JOIN players p ON p.id=c.player_id WHERE c.world_id=?'.($id?' AND p.id=?':' AND p.is_banned=0').' ORDER BY p.id LIMIT 1001',$id?[$world,$id]:[$world])->fetchAll();
        if(!$recipients)throw new \InvalidArgumentException('Keine Empfänger in dieser Welt gefunden.');
        if(count($recipients)>1000)throw new \InvalidArgumentException('Maximal 1000 Empfänger pro Vorgang. Bitte die Empfänger einzeln auswählen.');
        if(!$id&&WorldSettings::integer($input['recipient_count']??0,1,1000,'Empfängerzahl')!==count($recipients))throw new \InvalidArgumentException('Die Empfängerzahl hat sich geändert. Bitte lade die Weltseite neu.');
        foreach($recipients as $recipient) {
            $pid=(int)$recipient['player_id'];$cid=(int)$recipient['id'];
            $player=$db->query('SELECT gems FROM players WHERE id=? FOR UPDATE',[$pid])->fetch();
            $city=$db->query('SELECT food,lumber,stone,gold FROM cities WHERE id=? FOR UPDATE',[$cid])->fetch();
            $oldItem=$item?(int)$db->query('SELECT quantity FROM player_inventory WHERE player_id=? AND item_code=? FOR UPDATE',[$pid,$item])->fetchColumn():0;
            $before=array_map('intval',$city);$before['gems']=(int)$player['gems'];$before['item_quantity']=$oldItem;$after=$before;
            foreach(['food','lumber','stone','gold','gems'] as $r)$after[$r]+=$rewards[$r];$after['item_quantity']+=$quantity;
            if($after['gems']>2000000000||$after['item_quantity']>4000000000)throw new \InvalidArgumentException('Ein Empfänger würde das Bestandslimit überschreiten.');
            $db->execute('UPDATE cities SET food=food+?,lumber=lumber+?,stone=stone+?,gold=gold+? WHERE id=?',[$rewards['food'],$rewards['lumber'],$rewards['stone'],$rewards['gold'],$cid]);
            $db->execute('UPDATE players SET gems=gems+? WHERE id=?',[$rewards['gems'],$pid]);
            if($item)InventoryService::addItems($pid,$item,$quantity);
            $db->execute('INSERT INTO admin_gifts(operation_id,player_id,world_id,title,message,rewards_json,before_json,after_json) VALUES(?,?,?,?,?,?,?,?)',[$operation,$pid,$world,$title,$message,json_encode($rewards,JSON_THROW_ON_ERROR),json_encode($before,JSON_THROW_ON_ERROR),json_encode($after,JSON_THROW_ON_ERROR)]);
            $giftId=$db->lastInsertId();
            $db->execute("INSERT INTO notifications(player_id,type,data_json) VALUES(?,'admin_gift',?)",[$pid,json_encode(['gift_id'=>$giftId,'title'=>$title,'message'=>$message,'rewards'=>$rewards,'world_id'=>$world],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
        }
        return ['target_type'=>$id?'player':'world','target_id'=>$id?:$world,'before'=>['recipients'=>count($recipients)],'after'=>['title'=>$title,'rewards'=>$rewards,'recipients'=>count($recipients)],'message'=>'Geschenk an '.count($recipients).' Spieler zugestellt. Jeder Empfänger hat eine dauerhafte Empfangsbestätigung.'];
    }

    private static function text(mixed $input,int $min,int $max,string $name): string
    {
        if(!is_string($input))throw new \InvalidArgumentException('Ungültiges Feld: '.$name);
        $input=trim($input);$length=mb_strlen($input);
        if($length<$min||$length>$max)throw new \InvalidArgumentException($name.' muss '.$min.' bis '.$max.' Zeichen enthalten.');return $input;
    }
}
