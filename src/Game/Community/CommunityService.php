<?php
declare(strict_types=1);
namespace Conquer\Game\Community;

use Conquer\Db\Connection;
use Conquer\Game\Alliance\AllianceResearchService;
use Conquer\Game\Alliance\AllianceTerritoryService;
use Conquer\Game\City\ResourceTick;
use Conquer\Game\March\BattleReportService;

/** Community commands are scoped to a real city and committed with replay receipts. */
final class CommunityService
{
    private const ROLES=['member'=>1,'veteran'=>2,'officer'=>3,'vice_leader'=>4,'leader'=>5];
    private const RESOURCES=['food','lumber','stone','gold'];

    /** Small, read-only snapshot for the permanently visible map chat. */
    public static function chatState(int $playerId,?int $worldId=null,?int $privatePlayerId=null): array
    {
        $worldId??=\Conquer\Game\World\WorldContext::id();
        \Conquer\Game\World\WorldContext::current($worldId);
        self::city($playerId,$worldId);
        $db=Connection::getInstance();$member=self::member($playerId,$worldId);
        $alliance=$member?$db->query('SELECT id,name,tag FROM alliances WHERE id=?',[$member['alliance_id']])->fetch():null;
        $privatePlayer=null;$privateChat=[];
        if($privatePlayerId!==null){
            self::require($privatePlayerId!==$playerId,'Du kannst keinen privaten Chat mit dir selbst öffnen.');
            self::city($privatePlayerId,$worldId);
            $privatePlayer=$db->query("SELECT p.id,COALESCE(k.display_name,p.username) AS username,COALESCE(k.avatar,'knight') AS avatar FROM players p LEFT JOIN kingdom_profiles k ON k.player_id=p.id WHERE p.id=?",[$privatePlayerId])->fetch();
            self::require((bool)$privatePlayer,'Dieser Spieler wurde nicht gefunden.');
            $privateChat=array_reverse($db->query("SELECT m.id,m.sender_id AS player_id,COALESCE(k.display_name,p.username) AS username,COALESCE(k.avatar,'knight') AS avatar,m.message,m.created_at,s.id AS shared_report_id FROM private_chat_messages m JOIN players p ON p.id=m.sender_id LEFT JOIN kingdom_profiles k ON k.player_id=p.id LEFT JOIN battle_report_shares s ON s.channel='private' AND s.message_id=m.id WHERE m.world_id=? AND ((m.sender_id=? AND m.recipient_id=?) OR (m.sender_id=? AND m.recipient_id=?)) ORDER BY m.id DESC LIMIT 50",[$worldId,$playerId,$privatePlayerId,$privatePlayerId,$playerId])->fetchAll());
        }
        return [
            'player_id'=>$playerId,'world_id'=>$worldId,'alliance'=>$alliance,
            'world_chat'=>array_reverse($db->query("SELECT w.id,w.player_id,COALESCE(k.display_name,w.username) AS username,COALESCE(k.avatar,'knight') AS avatar,COALESCE(a.tag,w.alliance_tag) AS alliance_tag,w.message,w.created_at,s.id AS shared_report_id FROM world_chat w LEFT JOIN kingdom_profiles k ON k.player_id=w.player_id LEFT JOIN alliance_members am ON am.player_id=w.player_id AND am.world_id=w.world_id LEFT JOIN alliances a ON a.id=am.alliance_id AND a.world_id=w.world_id LEFT JOIN battle_report_shares s ON s.channel='world' AND s.message_id=w.id WHERE w.world_id=? ORDER BY w.id DESC LIMIT 50",[$worldId])->fetchAll()),
            'alliance_chat'=>$alliance?array_reverse($db->query("SELECT m.id,m.player_id,COALESCE(k.display_name,m.username) AS username,COALESCE(k.avatar,'knight') AS avatar,m.message,m.sent_at AS created_at,s.id AS shared_report_id FROM alliance_messages m LEFT JOIN kingdom_profiles k ON k.player_id=m.player_id LEFT JOIN battle_report_shares s ON s.channel='alliance' AND s.message_id=m.id WHERE m.alliance_id=? ORDER BY m.id DESC LIMIT 50",[$alliance['id']])->fetchAll()):[],
            'private_player'=>$privatePlayer,'private_chat'=>$privateChat,
        ];
    }

    public static function state(int $playerId,?int $worldId=null): array
    {
        $worldId??=\Conquer\Game\World\WorldContext::id();
        \Conquer\Game\World\WorldContext::current($worldId);
        $db=Connection::getInstance();
        self::city($playerId,$worldId);
        self::tick($worldId);
        $city=self::city($playerId,$worldId);
        $member=self::member($playerId,$worldId);
        $alliance=$member ? $db->query('SELECT id,name,tag FROM alliances WHERE id=?',[$member['alliance_id']])->fetch() : null;
        $mail=$db->query('SELECT m.*,p.username AS sender_name,q.username AS recipient_name FROM community_mail m JOIN players p ON p.id=m.sender_id JOIN players q ON q.id=m.recipient_id WHERE m.world_id=? AND (m.sender_id=? OR m.recipient_id=?) ORDER BY m.id DESC LIMIT 100',[$worldId,$playerId,$playerId])->fetchAll();
        $gifts=$db->query('SELECT id,title,message,rewards_json,delivered_at FROM admin_gifts WHERE player_id=? AND world_id=? ORDER BY id DESC LIMIT 50',[$playerId,$worldId])->fetchAll();
        foreach($gifts as&$gift){$gift['rewards']=json_decode($gift['rewards_json'],true,16,JSON_THROW_ON_ERROR);unset($gift['rewards_json']);$item=(int)($gift['rewards']['item_code']??0);$gift['item_name']=$item?(\Conquer\Game\Inventory\InventoryService::getItemDef($item)['name']??('Gegenstand #'.$item)):null;}unset($gift);
        $queues=array_merge(
            $db->query("SELECT id,'building' AS type,building_code AS label,finishes_at FROM building_queue WHERE city_id=? AND is_processed=0 AND finishes_at>UTC_TIMESTAMP()",[$city['id']])->fetchAll(),
            $db->query("SELECT id,'research' AS type,research_code AS label,finishes_at FROM research_queue WHERE player_id=? AND world_id=? AND is_processed=0 AND finishes_at>UTC_TIMESTAMP()",[$playerId,$worldId])->fetchAll()
        );
        $aid=(int)($member['alliance_id']??0);
        $help=$aid?$db->query("SELECT h.*,p.username,q.finishes_at,EXISTS(SELECT 1 FROM community_help_log l WHERE l.request_id=h.id AND l.helper_id=?) AS already_helped FROM community_help_requests h JOIN players p ON p.id=h.player_id JOIN alliance_members m ON m.player_id=h.player_id AND m.alliance_id=h.alliance_id JOIN (SELECT id,'building' AS queue_type,finishes_at,is_processed FROM building_queue UNION ALL SELECT id,'research',finishes_at,is_processed FROM research_queue) q ON q.id=h.queue_id AND q.queue_type=h.queue_type WHERE h.alliance_id=? AND h.world_id=? AND q.is_processed=0 AND q.finishes_at>UTC_TIMESTAMP() AND h.help_count<h.max_helps AND h.reduced_seconds<FLOOR(h.initial_seconds*0.3) ORDER BY h.id DESC LIMIT 100",[$playerId,$aid,$worldId])->fetchAll():[];
        return [
            'player_id'=>$playerId,'world_id'=>$worldId,'server_time'=>time(),'alliance'=>$alliance,'role'=>$member['role']??null,
            'world_chat'=>array_reverse($db->query("SELECT w.id,w.player_id,COALESCE(k.display_name,w.username) AS username,COALESCE(k.avatar,'knight') AS avatar,COALESCE(a.tag,w.alliance_tag) AS alliance_tag,w.message,w.created_at,s.id AS shared_report_id FROM world_chat w LEFT JOIN kingdom_profiles k ON k.player_id=w.player_id LEFT JOIN alliance_members am ON am.player_id=w.player_id AND am.world_id=w.world_id LEFT JOIN alliances a ON a.id=am.alliance_id AND a.world_id=w.world_id LEFT JOIN battle_report_shares s ON s.channel='world' AND s.message_id=w.id WHERE w.world_id=? ORDER BY w.id DESC LIMIT 50",[$worldId])->fetchAll()),
            'alliance_chat'=>$aid?array_reverse($db->query("SELECT m.id,m.player_id,COALESCE(k.display_name,m.username) AS username,COALESCE(k.avatar,'knight') AS avatar,m.message,m.sent_at AS created_at,s.id AS shared_report_id FROM alliance_messages m LEFT JOIN kingdom_profiles k ON k.player_id=m.player_id LEFT JOIN battle_report_shares s ON s.channel='alliance' AND s.message_id=m.id WHERE m.alliance_id=? ORDER BY m.id DESC LIMIT 50",[$aid])->fetchAll()):[],
            'mail'=>$mail,'gifts'=>$gifts,'unread'=>(int)$db->query('SELECT COUNT(*) FROM community_mail WHERE recipient_id=? AND world_id=? AND read_at IS NULL',[$playerId,$worldId])->fetchColumn(),
            'players'=>$db->query('SELECT p.id,p.username FROM players p JOIN cities c ON c.player_id=p.id AND c.world_id=? WHERE p.id<>? ORDER BY p.username LIMIT 200',[$worldId,$playerId])->fetchAll(),
            'members'=>$aid?$db->query('SELECT m.player_id,m.role,p.username,c.coord_x,c.coord_y FROM alliance_members m JOIN players p ON p.id=m.player_id JOIN cities c ON c.player_id=m.player_id AND c.world_id=? WHERE m.alliance_id=? ORDER BY p.username',[$worldId,$aid])->fetchAll():[],
            'queues'=>$queues,'help_requests'=>$help,'research'=>$aid?AllianceResearchService::getState($aid):null,
            'territory'=>$aid?AllianceTerritoryService::allianceState($aid,$worldId):null,
            'treasury'=>$aid?($db->query('SELECT food,lumber,stone,gold FROM alliance_treasury WHERE alliance_id=?',[$aid])->fetch()?:array_fill_keys(self::RESOURCES,0)):null,
            'alliances'=>$db->query('SELECT id,name,tag FROM alliances WHERE world_id=? AND id<>? ORDER BY name LIMIT 200',[$worldId,$aid])->fetchAll(),
            'treaties'=>$aid?$db->query('SELECT d.*,a.name AS target_name,a.tag AS target_tag FROM alliance_diplomacy d JOIN alliances a ON a.id=d.target_id AND a.world_id=? WHERE d.alliance_id=?',[$worldId,$aid])->fetchAll():[],
            'proposals'=>$aid?$db->query("SELECT t.*,a.name AS alliance_name,b.name AS target_name FROM community_treaty_proposals t JOIN alliances a ON a.id=t.alliance_id JOIN alliances b ON b.id=t.target_id WHERE t.world_id=? AND (t.alliance_id=? OR t.target_id=?) AND t.status='pending' ORDER BY t.id DESC LIMIT 100",[$worldId,$aid,$aid])->fetchAll():[],
            'shipments'=>$db->query('SELECT s.*,p.username AS sender_name,q.username AS recipient_name FROM community_shipments s JOIN players p ON p.id=s.sender_id JOIN players q ON q.id=s.recipient_id WHERE s.world_id=? AND (s.sender_id=? OR s.recipient_id=?) ORDER BY s.id DESC LIMIT 100',[$worldId,$playerId,$playerId])->fetchAll(),
        ];
    }

    public static function action(int $playerId,array $body,?int $worldId=null): array
    {
        $worldId??=\Conquer\Game\World\WorldContext::id();
        \Conquer\Game\World\WorldContext::current($body['expected_world_id']??$worldId);
        \Conquer\Game\World\WorldContext::assertActionAvailable($worldId);
        self::city($playerId,$worldId);
        $request=self::string($body,'request_id',16,80);
        self::require((bool)preg_match('/^[a-zA-Z0-9_-]+$/D',$request),'Ungültige Vorgangskennung.');
        $hashBody=$body;unset($hashBody['request_id']);ksort($hashBody);
        $hash=hash('sha256',json_encode([$worldId,$hashBody],JSON_THROW_ON_ERROR));
        $db=Connection::getInstance();
        $result=$db->transaction(function(Connection $db)use($playerId,$worldId,$body,$request,$hash):array{
            // Shared player row serializes chat cooldowns and replay receipts across worlds.
            self::require((bool)$db->query('SELECT id FROM players WHERE id=? FOR UPDATE',[$playerId])->fetchColumn(),'Spieler nicht gefunden.');
            $receipt=$db->query('SELECT payload_hash,result_json FROM community_operations WHERE player_id=? AND request_id=?',[$playerId,$request])->fetch();
            if($receipt){self::require(hash_equals($receipt['payload_hash'],$hash),'Diese Vorgangskennung gehört zu einer anderen Aktion.');return json_decode($receipt['result_json'],true,32,JSON_THROW_ON_ERROR);}
            $city=self::city($playerId,$worldId);
            $action=$body['action']??'';
            $result=match($action){
                'chat.send'=>self::chat($playerId,$worldId,$body),
                'mail.send'=>self::sendMail($playerId,$worldId,$body),
                'mail.read'=>self::readMail($playerId,$worldId,$body),
                'mailbox.read','mailbox.star','mailbox.claim','mailbox.read_all','mailbox.claim_all','mailbox.delete_read'=>MailboxService::action($playerId,$worldId,$body),
                'help.request'=>self::requestHelp($playerId,$worldId,$city,$body),
                'help.give'=>self::giveHelp($playerId,$worldId,$body),
                'alliance.role'=>self::role($playerId,$worldId,$body),
                'research.start'=>self::startResearch($playerId,$worldId,$body),
                'structure.place'=>AllianceTerritoryService::place($playerId,$worldId,self::string($body,'structure_type',3,20),self::integer($body,'coord_x',0,10000),self::integer($body,'coord_y',0,10000)),
                'treasury.donate'=>self::donate($playerId,$worldId,$city,$body),
                'treaty.propose','treaty.accept','treaty.decline','treaty.cancel','treaty.end'=>self::treaty($playerId,$worldId,$body),
                'shipment.send'=>self::shipment($playerId,$worldId,$city,$body),
                default=>throw new \DomainException('Diese Gemeinschaftsaktion ist nicht verfügbar.'),
            };
            $db->execute('INSERT INTO community_operations(player_id,request_id,payload_hash,result_json)VALUES(?,?,?,?)',[$playerId,$request,$hash,json_encode($result,JSON_THROW_ON_ERROR)]);
            return $result;
        });
        return ['message'=>$result['message']??'Gespeichert.','result'=>$result];
    }

    private static function chat(int $player,int $world,array $body):array
    {
        $db=Connection::getInstance();$channel=self::string($body,'channel',1,12);$text=self::string($body,'message',1,200);
        self::require(in_array($channel,['world','alliance','private'],true),'Unbekannter Chat.');
        $member=self::member($player,$world,$channel==='alliance');
        if($channel==='alliance')self::require((bool)$member,'Tritt zuerst einer Allianz bei.');
        $target=null;
        if($channel==='private'){$target=self::integer($body,'player_id');self::require($target!==$player,'Du kannst dir nicht selbst schreiben.');self::city($target,$world);}
        $recent=(int)$db->query('SELECT (SELECT COUNT(*) FROM world_chat WHERE player_id=? AND created_at>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 3 SECOND))+(SELECT COUNT(*) FROM alliance_messages WHERE player_id=? AND sent_at>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 3 SECOND))+(SELECT COUNT(*) FROM private_chat_messages WHERE sender_id=? AND created_at>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 3 SECOND))',[$player,$player,$player])->fetchColumn();
        self::require($recent===0,'Bitte warte drei Sekunden zwischen Chatnachrichten.');
        $name=$db->query('SELECT username FROM players WHERE id=?',[$player])->fetchColumn();
        $reportId=array_key_exists('report_id',$body)?self::integer($body,'report_id'):null;
        if($reportId!==null)self::require((bool)$db->query("SELECT id FROM battle_reports WHERE id=? AND world_id=? AND (attacker_id=? OR defender_id=?) AND outcome<>'scouted'",[$reportId,$world,$player,$player])->fetchColumn(),'Dieser Kampfbericht kann nicht geteilt werden.',403);
        if($channel==='world')$db->execute('INSERT INTO world_chat(world_id,player_id,username,alliance_tag,message)VALUES(?,?,?,?,?)',[$world,$player,$name,$member['tag']??null,$text]);
        elseif($channel==='alliance')$db->execute('INSERT INTO alliance_messages(alliance_id,player_id,username,message)VALUES(?,?,?,?)',[$member['alliance_id'],$player,$name,$text]);
        else $db->execute('INSERT INTO private_chat_messages(world_id,sender_id,recipient_id,message)VALUES(?,?,?,?)',[$world,$player,$target,$text]);
        $messageId=$db->lastInsertId();$shareId=null;
        if($reportId!==null){$db->execute('INSERT INTO battle_report_shares(world_id,battle_report_id,shared_by,channel,message_id,alliance_id,recipient_id)VALUES(?,?,?,?,?,?,?)',[$world,$reportId,$player,$channel,$messageId,$channel==='alliance'?$member['alliance_id']:null,$target]);$shareId=$db->lastInsertId();}
        return ['message'=>'Nachricht gesendet.','id'=>$messageId,'shared_report_id'=>$shareId];
    }

    public static function sharedReport(int $playerId,int $shareId,int $worldId):array
    {
        self::city($playerId,$worldId);$db=Connection::getInstance();
        $share=$db->query('SELECT * FROM battle_report_shares WHERE id=? AND world_id=?',[$shareId,$worldId])->fetch();
        self::require((bool)$share,'Dieser geteilte Bericht ist nicht verfügbar.',404);
        $allowed=$share['channel']==='world';
        if($share['channel']==='alliance'){$member=self::member($playerId,$worldId);$allowed=$member&&(int)$member['alliance_id']===(int)$share['alliance_id'];}
        if($share['channel']==='private')$allowed=$playerId===(int)$share['shared_by']||$playerId===(int)$share['recipient_id'];
        self::require($allowed,'Du darfst diesen geteilten Bericht nicht öffnen.',403);
        $report=BattleReportService::shared((int)$share['battle_report_id'],$worldId);
        self::require((bool)$report,'Dieser Kampfbericht ist nicht mehr verfügbar.',404);
        return $report;
    }

    private static function sendMail(int $player,int $world,array $body):array
    {
        $db=Connection::getInstance();$target=self::integer($body,'player_id');self::require($target!==$player,'Wähle einen anderen Empfänger.');self::city($target,$world);
        $subject=self::string($body,'subject',1,100);$text=self::string($body,'body',1,4000);
        self::require(!(bool)$db->query('SELECT id FROM community_mail WHERE sender_id=? AND created_at>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 10 SECOND) LIMIT 1',[$player])->fetchColumn(),'Bitte warte zehn Sekunden zwischen Briefen.');
        self::require((int)$db->query('SELECT COUNT(*) FROM community_mail WHERE sender_id=? AND created_at>=UTC_DATE()',[$player])->fetchColumn()<100,'Du kannst täglich höchstens 100 Briefe senden.');
        $db->execute('INSERT INTO community_mail(world_id,sender_id,recipient_id,subject,body)VALUES(?,?,?,?,?)',[$world,$player,$target,$subject,$text]);
        return ['message'=>'Dein Brief wurde zugestellt.','id'=>$db->lastInsertId()];
    }

    private static function readMail(int $player,int $world,array $body):array
    {
        $db=Connection::getInstance();$id=self::integer($body,'mail_id');
        $mail=$db->query('SELECT id FROM community_mail WHERE id=? AND recipient_id=? AND world_id=? FOR UPDATE',[$id,$player,$world])->fetch();
        self::require((bool)$mail,'Dieser Brief gehört nicht zu deinem Posteingang.',403);
        $db->execute('UPDATE community_mail SET read_at=COALESCE(read_at,UTC_TIMESTAMP()) WHERE id=?',[$id]);return ['message'=>'Brief als gelesen markiert.'];
    }

    private static function requestHelp(int $player,int $world,array $city,array $body):array
    {
        $db=Connection::getInstance();$member=self::mustMember($player,$world);$type=self::queueType($body);$id=self::integer($body,'queue_id');
        $job=self::queue($type,$id,$player,(int)$city['id'],$world);
        $remaining=max(0,strtotime($job['finishes_at'])-time());self::require($remaining>=4,'Dieser Auftrag ist bereits fast fertig.');
        $existing=$db->query('SELECT id,alliance_id FROM community_help_requests WHERE queue_type=? AND queue_id=? FOR UPDATE',[$type,$id])->fetch();
        if($existing){self::require((int)$existing['alliance_id']===(int)$member['alliance_id'],'Für diesen Auftrag wurde bereits Hilfe angefordert.');return ['message'=>'Die Hilfe ist bereits angefordert.','id'=>(int)$existing['id']];}
        $db->execute('INSERT INTO community_help_requests(world_id,alliance_id,player_id,city_id,queue_type,queue_id,initial_seconds)VALUES(?,?,?,?,?,?,?)',[$world,$member['alliance_id'],$player,$city['id'],$type,$id,$remaining]);
        return ['message'=>'Deine Allianz kann jetzt helfen.','id'=>$db->lastInsertId()];
    }

    private static function giveHelp(int $player,int $world,array $body):array
    {
        $db=Connection::getInstance();$member=self::mustMember($player,$world);$id=self::integer($body,'help_id');
        $h=$db->query('SELECT * FROM community_help_requests WHERE id=? AND alliance_id=? AND world_id=? FOR UPDATE',[$id,$member['alliance_id'],$world])->fetch();
        self::require((bool)$h,'Diese Hilfeanfrage ist nicht verfügbar.');self::require((int)$h['player_id']!==$player,'Du kannst dir nicht selbst helfen.');
        $owner=$db->query('SELECT player_id FROM alliance_members WHERE player_id=? AND alliance_id=? FOR UPDATE',[$h['player_id'],$member['alliance_id']])->fetchColumn();self::require((bool)$owner,'Der Auftraggeber hat die Allianz verlassen.');
        self::require(!(bool)$db->query('SELECT helper_id FROM community_help_log WHERE request_id=? AND helper_id=?',[$id,$player])->fetchColumn(),'Du hast diesem Auftrag bereits geholfen.');
        self::require((int)$db->query('SELECT COUNT(*) FROM community_help_log WHERE helper_id=? AND created_at>=UTC_DATE()',[$player])->fetchColumn()<30,'Du hast heute bereits 30 Mal geholfen.');
        self::require((int)$h['help_count']<(int)$h['max_helps'],'Dieser Auftrag hat bereits genügend Hilfe erhalten.');
        $job=self::queue($h['queue_type'],(int)$h['queue_id'],(int)$h['player_id'],(int)$h['city_id'],$world);
        $seconds=min(30,(int)floor((int)$h['initial_seconds']*.3)-(int)$h['reduced_seconds'],max(0,strtotime($job['finishes_at'])-time()-1));
        self::require($seconds>0,'Dieser Auftrag hat bereits die maximale Zeitersparnis erreicht.');
        $table=$h['queue_type']==='building'?'building_queue':'research_queue';
        $db->execute("UPDATE $table SET finishes_at=DATE_SUB(finishes_at,INTERVAL ? SECOND) WHERE id=?",[$seconds,$h['queue_id']]);
        $db->execute('UPDATE community_help_requests SET reduced_seconds=reduced_seconds+?,help_count=help_count+1 WHERE id=?',[$seconds,$id]);
        $db->execute('INSERT INTO community_help_log(request_id,helper_id,seconds_removed)VALUES(?,?,?)',[$id,$player,$seconds]);
        return ['message'=>'Du hast den Auftrag um '.$seconds.' Sekunden verkürzt.','seconds'=>$seconds];
    }

    private static function role(int $player,int $world,array $body):array
    {
        $db=Connection::getInstance();$actor=self::mustMember($player,$world);$target=self::integer($body,'player_id');$role=self::string($body,'role',1,20);
        self::require($target!==$player,'Den eigenen Rang kannst du nicht ändern.');
        self::require(isset(self::ROLES[$role])&&$role!=='leader','Die Führung wird über die Allianzverwaltung übertragen.');
        $member=$db->query('SELECT role FROM alliance_members WHERE player_id=? AND alliance_id=? FOR UPDATE',[$target,$actor['alliance_id']])->fetch();
        self::require((bool)$member,'Dieses Mitglied gehört nicht zu deiner Allianz.');
        $rank=self::ROLES[$actor['role']];self::require($rank>=4&&$rank>self::ROLES[$member['role']]&&$rank>self::ROLES[$role],'Du kannst nur Ränge unter deinem eigenen Rang verwalten.',403);
        $db->execute('UPDATE alliance_members SET role=? WHERE player_id=? AND alliance_id=?',[$role,$target,$actor['alliance_id']]);return ['message'=>'Der Mitgliederrang wurde geändert.'];
    }

    private static function startResearch(int $player,int $world,array $body):array
    {
        $db=Connection::getInstance();$member=self::mustMember($player,$world);self::require(self::ROLES[$member['role']]>=4,'Nur die Allianzführung darf Forschung beginnen.',403);
        $aid=(int)$member['alliance_id'];$db->query('SELECT id FROM alliances WHERE id=? FOR UPDATE',[$aid])->fetch();self::settleResearchLocked($aid);
        $code=self::string($body,'code',1,60);self::require(isset(AllianceResearchService::getData()[$code]),'Unbekannte Allianzforschung.');
        self::require(!(bool)$db->query('SELECT id FROM alliance_research_queue WHERE alliance_id=? AND is_processed=0 LIMIT 1',[$aid])->fetchColumn(),'Es läuft bereits eine Allianzforschung.');
        $current=(int)$db->query('SELECT level FROM alliance_research WHERE alliance_id=? AND research_code=?',[$aid,$code])->fetchColumn();self::require($current<AllianceResearchService::MAX_LEVEL,'Die höchste Forschungsstufe ist bereits erreicht.');
        $next=$current+1;$cost=AllianceResearchService::costForLevel($next);
        self::require($db->execute('UPDATE alliance_treasury SET lumber=lumber-?,stone=stone-?,gold=gold-? WHERE alliance_id=? AND lumber>=? AND stone>=? AND gold>=?',[$cost['lumber'],$cost['stone'],$cost['gold'],$aid,$cost['lumber'],$cost['stone'],$cost['gold']])===1,'Die Bündniskasse enthält nicht genügend Vorräte.');
        try{$db->execute('INSERT INTO alliance_research_queue(alliance_id,research_code,level_to,started_by,finishes_at)VALUES(?,?,?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? SECOND))',[$aid,$code,$next,$player,AllianceResearchService::durationForLevel($next)]);}catch(\PDOException $error){if((string)$error->getCode()==='23000')throw new \DomainException('Es läuft bereits eine Allianzforschung.');throw $error;}
        return ['message'=>'Allianzforschung gestartet.','id'=>$db->lastInsertId()];
    }

    private static function donate(int $player,int $world,array $city,array $body):array
    {
        $db=Connection::getInstance();$resource=self::resource($body);$amount=self::integer($body,'amount',1,10000000);
        self::persistCity((int)$city['id']);$member=self::mustMember($player,$world);
        self::require($db->execute("UPDATE cities SET $resource=$resource-? WHERE id=? AND $resource>=?",[$amount,$city['id'],$amount])===1,'Dein Vorrat reicht dafür nicht aus.');
        $db->execute("INSERT INTO alliance_treasury(alliance_id,$resource)VALUES(?,?) ON DUPLICATE KEY UPDATE $resource=$resource+VALUES($resource)",[$member['alliance_id'],$amount]);
        $db->execute("INSERT INTO alliance_donations(alliance_id,player_id,$resource)VALUES(?,?,?)",[$member['alliance_id'],$player,$amount]);return ['message'=>'Deine Spende wurde der Bündniskasse gutgeschrieben.'];
    }

    private static function treaty(int $player,int $world,array $body):array
    {
        $db=Connection::getInstance();$actor=self::member($player,$world);self::require((bool)$actor,'Tritt zuerst einer Allianz bei.');$aid=(int)$actor['alliance_id'];
        $action=$body['action'];$proposal=null;
        if(in_array($action,['treaty.accept','treaty.decline','treaty.cancel'],true)){
            $proposal=$db->query('SELECT * FROM community_treaty_proposals WHERE id=? AND world_id=?', [self::integer($body,'proposal_id'),$world])->fetch();
            self::require($proposal&&in_array($aid,[(int)$proposal['alliance_id'],(int)$proposal['target_id']],true),'Dieses Angebot gehört nicht zu deiner Allianz.',403);
            $target=$aid===(int)$proposal['alliance_id']?(int)$proposal['target_id']:(int)$proposal['alliance_id'];
        }else $target=self::integer($body,'alliance_id');
        self::require($target!==$aid,'Wähle eine andere Allianz.');
        $ids=[$aid,$target];sort($ids);foreach($ids as$id)self::require((bool)$db->query('SELECT id FROM alliances WHERE id=? AND world_id=? FOR UPDATE',[$id,$world])->fetchColumn(),'Die Zielallianz existiert nicht in dieser Welt.');
        $actor=self::mustMember($player,$world);self::require((int)$actor['alliance_id']===$aid&&self::ROLES[$actor['role']]>=4,'Nur die Allianzführung darf Verträge verwalten.',403);
        if($action==='treaty.propose'){
            $relation=self::string($body,'relation',1,10);self::require(in_array($relation,['ally','nap'],true),'Unbekannter Vertrag.');
            self::require(!(bool)$db->query('SELECT id FROM alliance_diplomacy WHERE (alliance_id=? AND target_id=?) OR (alliance_id=? AND target_id=?) LIMIT 1',[$aid,$target,$target,$aid])->fetchColumn(),'Beende zuerst den bestehenden Vertrag.');
            self::require(!(bool)$db->query("SELECT id FROM community_treaty_proposals WHERE status='pending' AND expires_at>UTC_TIMESTAMP() AND ((alliance_id=? AND target_id=?) OR (alliance_id=? AND target_id=?)) LIMIT 1",[$aid,$target,$target,$aid])->fetchColumn(),'Zwischen diesen Allianzen ist bereits ein Angebot offen.');
            $db->execute('INSERT INTO community_treaty_proposals(world_id,alliance_id,target_id,relation,proposed_by,expires_at)VALUES(?,?,?,?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 3 DAY))',[$world,$aid,$target,$relation,$player]);return ['message'=>'Das Vertragsangebot gilt drei Tage.','id'=>$db->lastInsertId()];
        }
        if($action==='treaty.end'){
            $db->execute('DELETE FROM alliance_diplomacy WHERE (alliance_id=? AND target_id=?) OR (alliance_id=? AND target_id=?)',[$aid,$target,$target,$aid]);return ['message'=>'Der Vertrag wurde für beide Allianzen beendet.'];
        }
        $proposal=$db->query('SELECT * FROM community_treaty_proposals WHERE id=? FOR UPDATE',[$proposal['id']])->fetch();
        self::require($proposal['status']==='pending'&&strtotime($proposal['expires_at'])>time(),'Dieses Angebot ist nicht mehr offen.');
        self::require($action==='treaty.cancel'?(int)$proposal['alliance_id']===$aid:(int)$proposal['target_id']===$aid,'Nur die empfangende Allianz darf das Angebot beantworten.',403);
        $status=['treaty.accept'=>'accepted','treaty.decline'=>'declined','treaty.cancel'=>'cancelled'][$action];
        if($status==='accepted'){
            foreach([[$aid,$target],[$target,$aid]]as[$source,$dest])$db->execute('INSERT INTO alliance_diplomacy(alliance_id,target_id,relation,initiated_by)VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE relation=VALUES(relation),initiated_by=VALUES(initiated_by)',[$source,$dest,$proposal['relation'],$player]);
        }
        $db->execute('UPDATE community_treaty_proposals SET status=?,resolved_at=UTC_TIMESTAMP() WHERE id=?',[$status,$proposal['id']]);return ['message'=>$status==='accepted'?'Der Vertrag gilt jetzt für beide Allianzen.':'Das Angebot wurde geschlossen.'];
    }

    private static function shipment(int $player,int $world,array $city,array $body):array
    {
        $db=Connection::getInstance();$target=self::integer($body,'player_id');self::require($target!==$player,'Wähle ein anderes Allianzmitglied.');
        $destination=self::city($target,$world);$resource=self::resource($body);$amount=self::integer($body,'amount',1,1000000);
        // Both cities always lock in ID order, including reciprocal shipments.
        $ids=[(int)$city['id'],(int)$destination['id']];sort($ids);foreach($ids as$id)$db->query('SELECT id FROM cities WHERE id=? FOR UPDATE',[$id])->fetch();
        $member=self::mustMember($player,$world);$other=$db->query('SELECT player_id FROM alliance_members WHERE player_id=? AND alliance_id=? FOR UPDATE',[$target,$member['alliance_id']])->fetchColumn();
        self::require((bool)$other,'Lieferungen sind nur innerhalb deiner Allianz möglich.');
        self::require((int)$db->query("SELECT COUNT(*) FROM community_shipments WHERE sender_id=? AND status='travelling'",[$player])->fetchColumn()<5,'Du kannst höchstens fünf Lieferungen gleichzeitig versenden.');
        self::persistCity((int)$city['id']);
        self::require($db->execute("UPDATE cities SET $resource=$resource-? WHERE id=? AND $resource>=?",[$amount,$city['id'],$amount])===1,'Dein Vorrat reicht für diese Lieferung nicht aus.');
        $distance=hypot((int)$city['coord_x']-(int)$destination['coord_x'],(int)$city['coord_y']-(int)$destination['coord_y']);
        $seconds=max(60,min(86400,(int)ceil($distance*30)));
        $db->execute('INSERT INTO community_shipments(world_id,alliance_id,sender_id,recipient_id,source_city_id,target_city_id,resource,amount,arrives_at)VALUES(?,?,?,?,?,?,?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? SECOND))',[$world,$member['alliance_id'],$player,$target,$city['id'],$destination['id'],$resource,$amount,$seconds]);
        return ['message'=>'Die Lieferung ist unterwegs.','id'=>$db->lastInsertId(),'duration_seconds'=>$seconds];
    }

    /** Called by the world worker and lazily on community reads. */
    public static function tick(?int $worldId=null):void
    {
        $db=Connection::getInstance();$filter=$worldId===null?'':' AND world_id=?';$params=$worldId===null?[]:[$worldId];
        $db->execute("UPDATE community_treaty_proposals SET status='expired',resolved_at=UTC_TIMESTAMP() WHERE status='pending' AND expires_at<=UTC_TIMESTAMP()$filter",$params);
        $shipments=$db->query("SELECT * FROM community_shipments WHERE status='travelling' AND arrives_at<=UTC_TIMESTAMP()$filter ORDER BY arrives_at,id LIMIT 100",$params)->fetchAll();
        foreach($shipments as$shipment)$db->transaction(static function(Connection $db)use($shipment):void{
            $ids=[(int)$shipment['source_city_id'],(int)$shipment['target_city_id']];sort($ids);$cities=[];
            foreach($ids as$id)$cities[$id]=$db->query('SELECT * FROM cities WHERE id=? FOR UPDATE',[$id])->fetch();
            $row=$db->query("SELECT * FROM community_shipments WHERE id=? AND status='travelling' AND arrives_at<=UTC_TIMESTAMP() FOR UPDATE",[$shipment['id']])->fetch();if(!$row)return;
            $target=$cities[(int)$row['target_city_id']];$valid=$target&&(int)$target['player_id']===(int)$row['recipient_id']&&(int)$target['world_id']===(int)$row['world_id'];
            $credit=$valid?$target:$cities[(int)$row['source_city_id']];
            if(!$credit||(int)$credit['player_id']!==($valid?(int)$row['recipient_id']:(int)$row['sender_id'])||(int)$credit['world_id']!==(int)$row['world_id'])throw new \DomainException('Die Lieferung kann keiner Stadt zugeordnet werden.');
            self::persistCity((int)$credit['id']);$resource=$row['resource'];self::require(in_array($resource,self::RESOURCES,true),'Ungültige Lieferressource.');
            $db->execute("UPDATE cities SET $resource=$resource+? WHERE id=?",[$row['amount'],$credit['id']]);
            $db->execute('UPDATE community_shipments SET status=?,settled_at=UTC_TIMESTAMP() WHERE id=?',[$valid?'delivered':'refunded',$row['id']]);
        });
        self::tickResearch(null,$worldId);
    }

    public static function tickResearch(?int $allianceId=null,?int $worldId=null):void
    {
        $db=Connection::getInstance();$where='q.is_processed=0 AND q.finishes_at<=UTC_TIMESTAMP()';$params=[];
        if($allianceId!==null){$where.=' AND a.id=?';$params[]=$allianceId;}if($worldId!==null){$where.=' AND a.world_id=?';$params[]=$worldId;}
        $ids=$db->query("SELECT DISTINCT a.id FROM alliances a JOIN alliance_research_queue q ON q.alliance_id=a.id WHERE $where ORDER BY a.id LIMIT 100",$params)->fetchAll(\PDO::FETCH_COLUMN);
        foreach($ids as$id){$settle=static function(Connection $db)use($id):void{$db->query('SELECT id FROM alliances WHERE id=? FOR UPDATE',[$id])->fetch();self::settleResearchLocked((int)$id);};if($db->getPdo()->inTransaction())$settle($db);else$db->transaction($settle);}
    }

    private static function settleResearchLocked(int $alliance):void
    {
        $db=Connection::getInstance();$rows=$db->query('SELECT * FROM alliance_research_queue WHERE alliance_id=? AND is_processed=0 AND finishes_at<=UTC_TIMESTAMP() ORDER BY id FOR UPDATE',[$alliance])->fetchAll();
        foreach($rows as$row){
            $db->execute('INSERT INTO alliance_research(alliance_id,research_code,level)VALUES(?,?,?) ON DUPLICATE KEY UPDATE level=GREATEST(level,VALUES(level))',[$alliance,$row['research_code'],min(AllianceResearchService::MAX_LEVEL,(int)$row['level_to'])]);
            $db->execute('UPDATE alliance_research_queue SET is_processed=1 WHERE id=?',[$row['id']]);
        }
    }

    /** Fractions for combat and flat march slots; production is already applied by ResourceTick. */
    public static function bonuses(int $playerId,?int $worldId=null):array
    {
        $worldId??=\Conquer\Game\World\WorldContext::id();
        $db=Connection::getInstance();$rows=$db->query('SELECT r.research_code,r.level FROM alliance_research r JOIN alliance_members m ON m.alliance_id=r.alliance_id JOIN alliances a ON a.id=m.alliance_id AND a.world_id=? WHERE m.player_id=?',[$worldId,$playerId])->fetchAll();$result=[];
        $map=['ally_troops_hp'=>['troops_hp',.005],'ally_troops_atk'=>['troops_atk',.005],'ally_troops_def'=>['troops_def',.005],'ally_march_speed'=>['march_speed',.005],'ally_gathering_speed'=>['gathering_speed',.01],'ally_construction_speed'=>['construction_speed',.005],'ally_research_speed'=>['research_speed',.005],'ally_training_speed'=>['training_speed',.005]];
        foreach($rows as$r){$level=max(0,min(AllianceResearchService::MAX_LEVEL,(int)$r['level']));if($r['research_code']==='ally_march_limit'){$result['march_limit']=intdiv($level,10);continue;}if(isset($map[$r['research_code']])){[$key,$amount]=$map[$r['research_code']];$result[$key]=($result[$key]??0)+$level*$amount;}}
        return $result;
    }

    /** Only reciprocal accepted non-aggression relations protect another alliance. */
    public static function protectedRelation(int $playerA,int $playerB,?int $worldId=null):?string
    {
        $worldId??=\Conquer\Game\World\WorldContext::id();
        $db=Connection::getInstance();$lock=$db->getPdo()->inTransaction()?' FOR UPDATE':'';$row=$db->query("SELECT d.relation FROM alliance_members ma JOIN alliance_members mb ON mb.player_id=? JOIN alliances a ON a.id=ma.alliance_id AND a.world_id=? JOIN alliances b ON b.id=mb.alliance_id AND b.world_id=a.world_id JOIN alliance_diplomacy d ON d.alliance_id=a.id AND d.target_id=b.id JOIN alliance_diplomacy reciprocal ON reciprocal.alliance_id=b.id AND reciprocal.target_id=a.id AND reciprocal.relation=d.relation WHERE ma.player_id=? AND d.relation IN ('ally','nap')".$lock,[$playerB,$worldId,$playerA])->fetchColumn();return $row===false?null:(string)$row;
    }

    private static function persistCity(int $cityId):void
    {
        $db=Connection::getInstance();$city=$db->query('SELECT * FROM cities WHERE id=? FOR UPDATE',[$cityId])->fetch();self::require((bool)$city,'Die Stadt existiert nicht mehr.');$buildings=[];
        foreach($db->query('SELECT building_code,level FROM city_buildings WHERE city_id=?',[$cityId])->fetchAll()as$row)$buildings[$row['building_code']]=['level'=>(int)$row['level']];
        $owner=(int)$city['player_id'];$world=(int)$city['world_id'];$vip=\Conquer\Game\Vip\VipService::status($owner)['bonuses'];
        $buffs=\Conquer\Game\Research\BuffEngine::getBuffs($owner,$world);$member=self::member($owner,$world);$shared=$member?AllianceResearchService::getProductionBonuses((int)$member['alliance_id']):[];
        foreach(self::RESOURCES as$resource){$vip[$resource.'_prod_pct']=(float)($buffs[$resource.'_production']??0)+(float)($shared[$resource.'_pct']??0);$vip[$resource.'_capacity_pct']=(float)($buffs[$resource.'_capacity']??0)+(float)($buffs['resource_capacity']??0);}
        $speed=(float)$db->query('SELECT speed_factor FROM worlds WHERE id=?',[$world])->fetchColumn();ResourceTick::persist($city,$buildings,max(.01,$speed),$vip);
    }

    private static function city(int $player,int $world):array
    {
        $city=Connection::getInstance()->query('SELECT * FROM cities WHERE player_id=? AND world_id=?',[$player,$world])->fetch();self::require((bool)$city,'Dieses Königreich existiert nicht in der gewählten Welt.',403);return $city;
    }

    private static function member(int $player,int $world,bool $lock=false):?array
    {
        $db=Connection::getInstance();$row=$db->query('SELECT m.alliance_id,m.role,a.tag FROM alliance_members m JOIN alliances a ON a.id=m.alliance_id AND a.world_id=? WHERE m.player_id=?',[$world,$player])->fetch();
        if($row&&$lock){$db->query('SELECT id FROM alliances WHERE id=? FOR UPDATE',[$row['alliance_id']])->fetch();$row=$db->query('SELECT m.alliance_id,m.role,a.tag FROM alliance_members m JOIN alliances a ON a.id=m.alliance_id AND a.world_id=? WHERE m.player_id=? FOR UPDATE',[$world,$player])->fetch();}
        return $row?:null;
    }

    private static function mustMember(int $player,int $world):array{$member=self::member($player,$world,true);self::require((bool)$member,'Tritt zuerst einer Allianz in dieser Welt bei.');return $member;}
    private static function queueType(array $body):string{$type=self::string($body,'queue_type',1,16);self::require(in_array($type,['building','research'],true),'Dieser Auftrag unterstützt keine Allianz-Hilfe.');return $type;}
    private static function queue(string $type,int $id,int $player,int $city,int $world):array
    {
        $db=Connection::getInstance();$owner=self::city($player,$world);self::require((int)$owner['id']===$city,'Dieser Auftrag gehört zu einer anderen Stadt.');$job=$type==='building'?$db->query('SELECT * FROM building_queue WHERE id=? AND city_id=? AND is_processed=0 AND finishes_at>UTC_TIMESTAMP() FOR UPDATE',[$id,$city])->fetch():$db->query('SELECT * FROM research_queue WHERE id=? AND player_id=? AND world_id=? AND is_processed=0 AND finishes_at>UTC_TIMESTAMP() FOR UPDATE',[$id,$player,$world])->fetch();self::require((bool)$job,'Dieser Auftrag ist nicht mehr aktiv oder gehört nicht zu deinem Königreich.');return $job;
    }
    private static function resource(array $body):string{$resource=self::string($body,'resource',1,12);self::require(in_array($resource,self::RESOURCES,true),'Ungültige Ressource.');return $resource;}
    private static function integer(array $body,string $key,int $min=1,int $max=2147483647):int
    {
        $value=$body[$key]??null;self::require(is_int($value)&&$value>=$min&&$value<=$max,'Ungültiger ganzzahliger Wert: '.$key.'.');return $value;
    }
    private static function string(array $body,string $key,int $min,int $max):string
    {
        $value=$body[$key]??null;self::require(is_string($value),'Ungültiger Text: '.$key.'.');$value=trim($value);self::require(mb_check_encoding($value,'UTF-8')&&mb_strlen($value)>=$min&&mb_strlen($value)<=$max&&!preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u',$value),'Ungültige Textlänge oder Steuerzeichen: '.$key.'.');return $value;
    }
    private static function require(bool $ok,string $message,int $code=0):void{if(!$ok)throw new \DomainException($message,$code);}
}
