<?php
declare(strict_types=1);
namespace Conquer\Game\Community;

use Conquer\Db\Connection;
use Conquer\Game\Inventory\InventoryService;
use Conquer\Game\World\WorldContext;

/** A durable inbox over existing game records. Reward authority stays with the source. */
final class MailboxService
{
    public const CATEGORIES = ['war','alliance','system','reports','starred','private','sent'];

    private static function authorize(int $player, int $world): void
    {
        WorldContext::current($world);
        if (!Connection::getInstance()->query('SELECT id FROM cities WHERE player_id=? AND world_id=?',[$player,$world])->fetchColumn()) {
            throw new \DomainException('Du hast keine Stadt in dieser Welt.',403);
        }
    }

    private static function decode(?string $json): array
    {
        $value=json_decode($json??'',true);
        return is_array($value)?$value:[];
    }

    /** Give monster mail a useful list label without depending on the current map spawn. */
    private static function presentListEntry(array $row): array
    {
        if(($row['source']??'')!=='battle'||($row['category']??'')!=='reports'){
            unset($row['metadata_json']);
            return $row;
        }
        $meta=self::decode($row['metadata_json']??null);$details=$meta['details']??[];
        if(!is_array($details))$details=[];
        $snapshot=is_array($details['monster_snapshot']??null)?$details['monster_snapshot']:[];
        $rawName=trim((string)($snapshot['name']??$details['target_name']??$details['monster_name']??'Monster'));
        $level=(int)($snapshot['level']??0);
        if(!$level&&preg_match('/\bLv\.?\s*(\d+)\b/iu',$rawName,$match))$level=(int)$match[1];
        $name=trim((string)preg_replace('/\s*[·-]?\s*Lv\.?\s*\d+\s*$/iu','',$rawName));
        if($name==='')$name='Monster';
        $row['subject'].=' gegen '.$name.($level>0?' Lv. '.$level:'');
        $row['monster_name']=$name;
        $row['monster_level']=$level?:null;
        $row['monster_art']=(string)($snapshot['art']??'');
        unset($row['metadata_json']);
        return $row;
    }

    private static function insert(int $player,int $world,string $source,array $row,string $category,string $subject,string $body,array $meta=[],string $reward='none',?string $expiry=null): void
    {
        Connection::getInstance()->execute('INSERT IGNORE INTO mailbox_entries(player_id,world_id,source,source_id,category,subject,body,metadata_json,reward_status,expires_at,created_at,read_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)',
            [$player,$world,$source,$row['id'],$category,mb_substr($subject,0,180),$body,json_encode($meta,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$reward,$expiry,$row['created_at'],$row['read_at']??null]);
    }

    /** Import only missing source records. Copies survive source retention and preserve favorites. */
    public static function sync(int $player,int $world): void
    {
        self::authorize($player,$world);
        $db=Connection::getInstance();
        $missing=static fn(string $source):string=>" NOT EXISTS(SELECT 1 FROM mailbox_entries e WHERE e.player_id=? AND e.world_id=? AND e.source='$source' AND e.source_id=s.id)";
        $rows=$db->query('SELECT s.*,p.username AS sender_name,q.username AS recipient_name FROM community_mail s JOIN players p ON p.id=s.sender_id JOIN players q ON q.id=s.recipient_id WHERE s.world_id=? AND (s.sender_id=? OR s.recipient_id=?) AND'.$missing('letter'),[$world,$player,$player,$player,$world])->fetchAll();
        foreach($rows as $r){
            $incoming=(int)$r['recipient_id']===$player;
            if(!$incoming)$r['read_at']=$r['created_at'];
            self::insert($player,$world,'letter',$r,$incoming?'private':'sent',$r['subject'],$r['body'],['sender'=>$r['sender_name'],'recipient'=>$r['recipient_name'],'reply_id'=>$incoming?(int)$r['sender_id']:(int)$r['recipient_id']]);
        }
        $rows=$db->query('SELECT s.* FROM admin_gifts s WHERE s.player_id=? AND s.world_id=? AND'.$missing('admin_gift'),[$player,$world,$player,$world])->fetchAll();
        foreach($rows as $r){$r['created_at']=$r['delivered_at'];self::insert($player,$world,'admin_gift',$r,'system',$r['title'],$r['message'],['sender'=>'Spielleitung','rewards'=>self::decode($r['rewards_json'])],'credited');}

        $db->execute("UPDATE mailbox_entries e JOIN battle_reports b ON b.id=e.source_id AND b.attacker_id=e.player_id AND b.world_id=e.world_id SET e.deleted_at=COALESCE(e.deleted_at,UTC_TIMESTAMP()) WHERE e.player_id=? AND e.world_id=? AND e.source='battle' AND JSON_UNQUOTE(JSON_EXTRACT(b.data_json,'$.hidden_by_attacker'))='true'",[$player,$world]);
        $db->execute("UPDATE mailbox_entries e JOIN battle_reports b ON b.id=e.source_id AND b.attacker_id=e.player_id AND b.world_id=e.world_id SET e.read_at=COALESCE(e.read_at,UTC_TIMESTAMP()) WHERE e.player_id=? AND e.world_id=? AND e.source='battle' AND b.target_type=3 AND b.attacker_read=1 AND e.read_at IS NULL",[$player,$world]);
        $rows=$db->query("SELECT s.* FROM battle_reports s WHERE s.world_id=? AND (s.attacker_id=? OR s.defender_id=?) AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(s.data_json,'$.hidden_by_attacker')),'false')<>'true' AND".$missing('battle'),[$world,$player,$player,$player,$world])->fetchAll();
        foreach($rows as $r){
            $d=self::decode($r['data_json']);$owned=(int)$r['attacker_id']===$player;
            // CityCombat and FieldCombat write one perspective-specific record per owner.
            $personal=in_array($d['battle_kind']??'',['city','rally','field'],true);
            if($personal&&!$owned)continue;
            $defender=$personal?($d['perspective']??'')==='defender':!$owned;
            $scout=$r['outcome']==='scouted'||($d['type']??'')==='scout';
            $won=$r['outcome']===($defender&&!$personal?'defender_wins':'attacker_wins');
            $title=$scout?($defender?'Deine Stadt wurde ausgespäht':'Spähbericht'):($r['outcome']==='draw'?'Unentschieden':($defender?'Verteidigung: ':'Angriff: ').($won?'Sieg':'Niederlage'));
            $r['read_at']=($owned?$r['attacker_read']:$r['defender_read'])?$r['created_at']:null;
            // Scout intelligence belongs only to the scouting player.
            if($defender&&$scout)$d=[];
            if($defender&&($d['perspective']??'')!=='defender'){
                $d=['target_name'=>'Deine Stadt','perspective'=>'defender'];
            }
            $d['outcome']=$r['outcome'];
            self::insert($player,$world,'battle',$r,($r['defender_id']||in_array($d['battle_kind']??'',['city','rally'],true))?'war':'reports',$title,
                ($d['target_name']??$d['monster_name']??'Gefecht').' · ('.$r['target_x'].', '.$r['target_y'].')',
                ['sender'=>'Kampfbericht','details'=>$d,'x'=>(int)$r['target_x'],'y'=>(int)$r['target_y'],'defender'=>$defender,'scout'=>$scout]);
        }

        $rows=$db->query('SELECT s.*,p.username AS sender_name FROM alliance_gifts s JOIN alliances a ON a.id=s.alliance_id JOIN alliance_members m ON m.alliance_id=a.id AND m.player_id=? JOIN players p ON p.id=s.created_by WHERE a.world_id=? AND s.expires_at>UTC_TIMESTAMP() AND'.$missing('alliance_gift'),[$player,$world,$player,$world])->fetchAll();
        foreach($rows as $r)self::insert($player,$world,'alliance_gift',$r,'alliance','Geschenk deiner Allianz','Gemeinsam erkämpft: Eine Belohnung deiner Allianz wartet auf dich.',['sender'=>$r['sender_name'],'rewards'=>self::decode($r['gift_json'])],'pending',$r['expires_at']);

        $titles=['build_complete'=>'Bau abgeschlossen','research_complete'=>'Forschung abgeschlossen','train_complete'=>'Ausbildung abgeschlossen','march_returned'=>'Truppen zurückgekehrt','battle_incoming'=>'Angriff auf deine Stadt','help_received'=>'Allianz-Hilfe erhalten','scouted'=>'Deine Stadt wurde ausgespäht','wall_destroyed'=>'Stadtmauer zerstört','rally_required'=>'Rally erforderlich'];
        $rows=$db->query('SELECT s.* FROM notifications s WHERE s.player_id=? AND'.$missing('notification'),[$player,$player,$world])->fetchAll();
        $singleWorld=(int)$db->query('SELECT COUNT(*) FROM cities WHERE player_id=?',[$player])->fetchColumn()===1;
        foreach($rows as $r){
            $d=self::decode($r['data_json']);$scope=(int)($d['world_id']??0);
            if(!$scope&&isset($d['city_id']))$scope=(int)$db->query('SELECT world_id FROM cities WHERE id=? AND player_id=?',[$d['city_id'],$player])->fetchColumn();
            if(($scope&&$scope!==$world)||(!$scope&&!$singleWorld)||$r['type']==='battle_report')continue;
            $category=in_array($r['type'],['battle_incoming','scouted','wall_destroyed','rally_required'],true)?'war':(str_starts_with($r['type'],'alliance_')||$r['type']==='help_received'?'alliance':'system');
            $title=$titles[$r['type']]??($d['title']??'Nachricht aus deinem Reich');
            $body=(string)($d['message']??$d['text']??$title);
            if(isset($d['attacker_name']))$body.="\nSpieler: ".$d['attacker_name'];
            if(isset($d['x'],$d['y']))$body.="\nPosition: (".(int)$d['x'].', '.(int)$d['y'].')';
            self::insert($player,$world,'notification',$r,$category,(string)$title,$body,['sender'=>'Dein Reich']);
        }

        $rows=$db->query("SELECT s.*,p.username AS challenger_name,q.username AS opponent_name FROM kingdom_arena_challenges s JOIN players p ON p.id=s.challenger_id JOIN players q ON q.id=s.opponent_id WHERE s.world_id=? AND s.status='completed' AND (s.challenger_id=? OR s.opponent_id=?) AND".$missing('arena'),[$world,$player,$player,$player,$world])->fetchAll();
        foreach($rows as $r){$r['created_at']=$r['resolved_at']??$r['created_at'];self::insert($player,$world,'arena',$r,'reports','Arena: '.((int)$r['winner_id']===$player?'Sieg':'Duell beendet'),$r['challenger_name'].' gegen '.$r['opponent_name'],['sender'=>'Arena','details'=>self::decode($r['result_json'])]);}
        $rows=$db->query('SELECT s.*,e.name FROM expedition_rewards s JOIN expeditions e ON e.id=s.expedition_id WHERE s.player_id=? AND e.world_id=? AND'.$missing('expedition'),[$player,$world,$player,$world])->fetchAll();
        foreach($rows as $r)self::insert($player,$world,'expedition',$r,'reports','Feldzug abgeschlossen',$r['name'],['sender'=>'Feldzug','rewards'=>self::decode($r['reward_json']),'expedition_id'=>(int)$r['expedition_id']],'credited');

        // Reflect reading/claiming through the existing interfaces as well.
        $db->execute("UPDATE mailbox_entries e JOIN community_mail s ON s.id=e.source_id SET e.read_at=s.read_at WHERE e.player_id=? AND e.world_id=? AND e.source='letter' AND e.read_at IS NULL AND s.read_at IS NOT NULL",[$player,$world]);
        $db->execute("UPDATE mailbox_entries e JOIN notifications s ON s.id=e.source_id AND s.player_id=e.player_id SET e.read_at=s.read_at WHERE e.player_id=? AND e.world_id=? AND e.source='notification' AND e.read_at IS NULL AND s.read_at IS NOT NULL",[$player,$world]);
        $db->execute("UPDATE mailbox_entries e JOIN battle_reports s ON s.id=e.source_id AND s.world_id=e.world_id SET e.read_at=UTC_TIMESTAMP() WHERE e.player_id=? AND e.world_id=? AND e.source='battle' AND e.read_at IS NULL AND ((s.attacker_id=e.player_id AND s.attacker_read=1) OR (s.attacker_id<>e.player_id AND s.defender_id=e.player_id AND s.defender_read=1))",[$player,$world]);
        $db->execute("UPDATE mailbox_entries e JOIN alliance_gift_claims c ON c.gift_id=e.source_id AND c.player_id=e.player_id SET e.reward_status='claimed' WHERE e.player_id=? AND e.world_id=? AND e.source='alliance_gift' AND e.reward_status<>'claimed'",[$player,$world]);
        $db->execute("UPDATE mailbox_entries e SET e.reward_status='expired' WHERE e.player_id=? AND e.world_id=? AND e.source='alliance_gift' AND e.reward_status='pending' AND (e.expires_at<=UTC_TIMESTAMP() OR NOT EXISTS(SELECT 1 FROM alliance_gifts g JOIN alliance_members m ON m.alliance_id=g.alliance_id JOIN alliances a ON a.id=m.alliance_id WHERE g.id=e.source_id AND m.player_id=e.player_id AND a.world_id=e.world_id))",[$player,$world]);
    }

    private static function filter(string $category): string
    {
        if(!in_array($category,self::CATEGORIES,true))throw new \DomainException('Unbekannter Postbereich.');
        return $category==='starred'?'starred=1':"category='$category'";
    }

    public static function state(int $player,int $world,array $query=[]): array
    {
        $category=$query['category']??'war';
        if(!is_string($category))throw new \DomainException('Ungültiger Postbereich.');
        $filter=self::filter($category);self::sync($player,$world);$db=Connection::getInstance();
        $counts=array_fill_keys(self::CATEGORIES,['total'=>0,'unread'=>0,'claimable'=>0]);
        foreach($db->query('SELECT category,COUNT(*) AS total,SUM(read_at IS NULL) AS unread,SUM(reward_status=\'pending\') AS claimable FROM mailbox_entries WHERE player_id=? AND world_id=? AND deleted_at IS NULL GROUP BY category',[$player,$world])->fetchAll() as $r)$counts[$r['category']]=array_map('intval',array_intersect_key($r,['total'=>0,'unread'=>0,'claimable'=>0]));
        $counts['starred']=array_map('intval',$db->query("SELECT COUNT(*) AS total,COALESCE(SUM(read_at IS NULL),0) AS unread,COALESCE(SUM(reward_status='pending'),0) AS claimable FROM mailbox_entries WHERE player_id=? AND world_id=? AND deleted_at IS NULL AND starred=1",[$player,$world])->fetch());
        $params=[$player,$world];$cursor='';
        if(isset($query['before_date'],$query['before_id'])){
            $date=$query['before_date'];$id=self::id($query['before_id']);
            if(!is_string($date))throw new \DomainException('Ungültiger Postcursor.');
            if(!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D',$date))throw new \DomainException('Ungültiger Postcursor.');
            $cursor=' AND (created_at<? OR (created_at=? AND id<?))';array_push($params,$date,$date,$id);
        }
        $rows=$db->query("SELECT id,category,source,subject,metadata_json,created_at,read_at,starred,reward_status FROM mailbox_entries WHERE player_id=? AND world_id=? AND deleted_at IS NULL AND $filter $cursor ORDER BY created_at DESC,id DESC LIMIT 51",$params)->fetchAll();
        $more=count($rows)>50;$rows=array_slice($rows,0,50);$last=$rows?end($rows):null;
        $rows=array_map([self::class,'presentListEntry'],$rows);
        $unread=0;foreach($counts as $key=>$count)if($key!=='starred')$unread+=$count['unread'];
        return ['world_id'=>$world,'category'=>$category,'entries'=>$rows,'counts'=>$counts,'unread'=>$unread,'snapshot'=>(int)$db->query('SELECT COALESCE(MAX(id),0) FROM mailbox_entries WHERE player_id=? AND world_id=?',[$player,$world])->fetchColumn(),'next'=>$more?['before_date'=>$last['created_at'],'before_id'=>(int)$last['id']]:null];
    }

    public static function message(int $player,int $world,int $id): array
    {
        self::authorize($player,$world);$r=Connection::getInstance()->query('SELECT * FROM mailbox_entries WHERE id=? AND player_id=? AND world_id=? AND deleted_at IS NULL',[$id,$player,$world])->fetch();
        if(!$r)throw new \DomainException('Diese Nachricht ist nicht verfügbar.',403);
        $r['metadata']=self::decode($r['metadata_json']);unset($r['metadata_json'],$r['player_id']);
        if ($r['source']==='battle' && is_array($r['metadata']['details'] ?? null)) {
            $r['metadata']['details']=\Conquer\Game\Rewards\RewardPresentation::report($r['metadata']['details']);
        }
        $item=(int)($r['metadata']['rewards']['item_code']??0);
        if($item)$r['metadata']['item_name']=InventoryService::getItemDef($item)['name']??('Gegenstand #'.$item);
        return $r;
    }

    private static function id(mixed $value): int
    {
        if((!is_string($value)&&!is_int($value))||filter_var($value,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])===false)throw new \DomainException('Ungültige Nachrichtenkennung.');
        return (int)$value;
    }

    /** Called inside CommunityService's authenticated transaction and replay receipt. */
    public static function action(int $player,int $world,array $body): array
    {
        self::authorize($player,$world);$db=Connection::getInstance();$action=$body['action'];
        if(in_array($action,['mailbox.read_all','mailbox.claim_all','mailbox.delete_read'],true)){
            if(!is_string($body['category']??null))throw new \DomainException('Ungültiger Postbereich.');
            $filter=self::filter($body['category']);$snapshot=self::id($body['snapshot']??null);
            $rows=$db->query("SELECT * FROM mailbox_entries WHERE player_id=? AND world_id=? AND deleted_at IS NULL AND id<=? AND $filter ORDER BY id FOR UPDATE",[$player,$world,$snapshot])->fetchAll();
        }else{
            $id=self::id($body['mail_id']??null);
            $rows=$db->query('SELECT * FROM mailbox_entries WHERE id=? AND player_id=? AND world_id=? AND deleted_at IS NULL FOR UPDATE',[$id,$player,$world])->fetchAll();
            if(!$rows)throw new \DomainException('Diese Nachricht gehört nicht zu deinem Postfach.',403);
        }
        $changed=0;$claimed=0;
        foreach($rows as $r){
            if($action==='mailbox.star'){
                if(!is_bool($body['starred']??null))throw new \DomainException('Ungültiger Favoritenstatus.');
                $changed+=$db->execute('UPDATE mailbox_entries SET starred=? WHERE id=?',[(int)$body['starred'],$r['id']]);continue;
            }
            if($action==='mailbox.delete_read'){
                if(!$r['read_at']||$r['starred']||$r['reward_status']==='pending')continue;
                $changed+=$db->execute('UPDATE mailbox_entries SET deleted_at=UTC_TIMESTAMP() WHERE id=?',[$r['id']]);continue;
            }
            if($action==='mailbox.claim_all'&&$r['reward_status']!=='pending')continue;
            if(in_array($action,['mailbox.claim','mailbox.claim_all'],true)&&$r['source']==='alliance_gift')$claimed+=self::claim($player,$world,$r);
            if(!in_array($action,['mailbox.read','mailbox.claim','mailbox.read_all','mailbox.claim_all'],true))throw new \DomainException('Unbekannte Postaktion.');
            $changed+=$db->execute('UPDATE mailbox_entries SET read_at=COALESCE(read_at,UTC_TIMESTAMP()) WHERE id=?',[$r['id']]);
            if($r['source']==='letter')$db->execute('UPDATE community_mail SET read_at=COALESCE(read_at,UTC_TIMESTAMP()) WHERE id=? AND recipient_id=? AND world_id=?',[$r['source_id'],$player,$world]);
            if($r['source']==='notification')$db->execute('UPDATE notifications SET read_at=COALESCE(read_at,UTC_TIMESTAMP()) WHERE id=? AND player_id=?',[$r['source_id'],$player]);
            if($r['source']==='battle')$db->execute('UPDATE battle_reports SET attacker_read=IF(attacker_id=?,1,attacker_read),defender_read=IF(defender_id=?,1,defender_read) WHERE id=? AND world_id=?',[$player,$player,$r['source_id'],$world]);
        }
        return ['changed'=>$changed,'claimed'=>$claimed,'message'=>match($action){'mailbox.star'=>($body['starred']?'Als Favorit gespeichert.':'Favorit entfernt.'),'mailbox.delete_read'=>$changed.' gelesene Nachrichten entfernt.','mailbox.read_all'=>'Bereich als gelesen markiert.','mailbox.claim_all'=>$claimed.' Belohnungen eingesammelt.','mailbox.claim'=>$claimed?'Belohnung abgeholt.':'Diese Belohnung ist bereits abgeholt oder nicht mehr verfügbar.',default=>'Nachricht gelesen.'}];
    }

    private static function claim(int $player,int $world,array $entry): int
    {
        $db=Connection::getInstance();
        $gift=$db->query('SELECT g.gift_json FROM alliance_gifts g JOIN alliances a ON a.id=g.alliance_id JOIN alliance_members m ON m.alliance_id=a.id AND m.player_id=? WHERE g.id=? AND a.world_id=? AND g.expires_at>UTC_TIMESTAMP() FOR UPDATE',[$player,$entry['source_id'],$world])->fetch();
        if(!$gift)return 0;
        $reward=self::decode($gift['gift_json']);$code=(int)($reward['item_code']??0);$quantity=(int)($reward['quantity']??0);
        if($code<=0||$quantity<=0||!InventoryService::getItemDef($code))throw new \DomainException('Diese Belohnung ist derzeit nicht verfügbar.');
        $added=$db->execute('INSERT IGNORE INTO alliance_gift_claims(gift_id,player_id)VALUES(?,?)',[$entry['source_id'],$player]);
        if($added)InventoryService::addItems($player,$code,$quantity);
        $db->execute("UPDATE mailbox_entries SET reward_status='claimed' WHERE id=?",[$entry['id']]);
        return $added?1:0;
    }
}
