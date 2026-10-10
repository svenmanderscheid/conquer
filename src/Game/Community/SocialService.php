<?php
declare(strict_types=1);
namespace Conquer\Game\Community;

use Conquer\Db\Connection;
use Conquer\Game\Alliance\AllianceRank;
use Conquer\Game\Locale;
use Conquer\Game\World\WorldContext;

/** Social relationships, chat visibility and explicit read receipts. Reads never acknowledge messages. */
final class SocialService
{
    public const REACTIONS=['like','heart','laugh','cheer'];
    /** Shared catalog copy; the keys are also used by the browser. */
    public const TEXTS=[
        'invalid'=>'Please check the submitted values.', 'unavailable'=>'This player or message is not available in this world.',
        'denied'=>'You do not have permission for this action.', 'blocked'=>'Private communication with this player is blocked.',
        'privacy'=>'This player is not accepting private messages from you.', 'banned'=>'Your chat access is temporarily suspended.',
        'saved'=>'Saved.', 'request_sent'=>'Friend request sent.', 'friend_added'=>'Friend request accepted.',
        'friend_removed'=>'Friendship or request removed.', 'pending'=>'There is no matching friend request.',
        'block_added'=>'Player blocked.', 'block_removed'=>'Player unblocked.', 'reported'=>'Your report has been sent to the moderators.',
        'report_limit'=>'You can submit at most 20 reports per day.', 'friend_limit'=>'Your friend and request list is full.',
        'request_limit'=>'You can send at most 20 pending friend requests.', 'pin_limit'=>'Your alliance can pin up to five messages.',
        'replay'=>'This request identifier was already used for another action.', 'moderated'=>'Community moderation updated.',
    ];

    public static function state(int $player,int $world,string $query=''):array
    {
        WorldContext::current($world); self::city($player,$world);
        $db=Connection::getInstance();$relations=$db->query("SELECT f.*,p.id AS player_id,COALESCE(k.display_name,p.username) AS username,COALESCE(k.avatar,'knight') AS avatar FROM community_friends f JOIN players p ON p.id=IF(f.player_low=?,f.player_high,f.player_low) JOIN cities c ON c.player_id=p.id AND c.world_id=f.world_id LEFT JOIN kingdom_profiles k ON k.player_id=p.id WHERE f.world_id=? AND (f.player_low=? OR f.player_high=?) ORDER BY username",[$player,$world,$player,$player])->fetchAll();
        $friends=[];$incoming=[];$outgoing=[];
        foreach($relations as $row){$profile=['player_id'=>(int)$row['player_id'],'username'=>$row['username'],'avatar'=>$row['avatar'],'requested_at'=>$row['created_at']];if($row['status']==='accepted')$friends[]=$profile;elseif((int)$row['requested_by']===$player)$outgoing[]=$profile;else$incoming[]=$profile;}
        $blocks=$db->query("SELECT b.blocked_id AS player_id,COALESCE(k.display_name,p.username) AS username,COALESCE(k.avatar,'knight') AS avatar FROM community_blocks b JOIN players p ON p.id=b.blocked_id LEFT JOIN kingdom_profiles k ON k.player_id=p.id WHERE b.world_id=? AND b.player_id=? ORDER BY p.username",[$world,$player])->fetchAll();
        $search=[];$query=trim($query);
        self::check(mb_check_encoding($query,'UTF-8')&&mb_strlen($query)<=60,'invalid');
        if($query!==''){
            // Escape LIKE metacharacters so a literal player name cannot expand the entire directory.
            $pattern='%'.strtr($query,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';
            $search=$db->query("SELECT DISTINCT p.id AS player_id,COALESCE(k.display_name,p.username) AS username,COALESCE(k.avatar,'knight') AS avatar FROM players p JOIN cities c ON c.player_id=p.id AND c.world_id=? LEFT JOIN kingdom_profiles k ON k.player_id=p.id WHERE p.id<>? AND (p.username LIKE ? ESCAPE '!' OR k.display_name LIKE ? ESCAPE '!') ORDER BY username LIMIT 30",[$world,$player,$pattern,$pattern])->fetchAll();
        }
        return ['player_id'=>$player,'world_id'=>$world,'friends'=>$friends,'requests'=>['incoming'=>$incoming,'outgoing'=>$outgoing],'blocks'=>$blocks,'preferences'=>self::preferences($player,$world),'conversations'=>self::conversations($player,$world),'search'=>$search,'chat_ban'=>self::ban($player,$world)];
    }

    public static function preferences(int $player,int $world):array
    {
        $row=Connection::getInstance()->query('SELECT * FROM community_preferences WHERE world_id=? AND player_id=?',[$world,$player])->fetch()?:[];
        return ['private_messages'=>$row['private_messages']??'everyone','channels'=>['world'=>$row['world_notifications']??'all','alliance'=>$row['alliance_notifications']??'all','private'=>$row['private_notifications']??'all']];
    }

    private static function conversations(int $player,int $world):array
    {
        $db=Connection::getInstance();
        $rows=$db->query("SELECT x.partner_id AS player_id,COALESCE(k.display_name,p.username) AS username,COALESCE(k.avatar,'knight') AS avatar,m.message AS last_message,COALESCE(m.created_at,x.started_at) AS last_at,COALESCE(r.message_id,0) AS read_id,(SELECT COUNT(*) FROM private_chat_messages u WHERE u.world_id=? AND u.sender_id=x.partner_id AND u.recipient_id=? AND u.id>COALESCE(r.message_id,0)) AS unread FROM (SELECT partner_id,MAX(last_id) AS last_id,MAX(started_at) AS started_at FROM (SELECT IF(sender_id=?,recipient_id,sender_id) AS partner_id,MAX(id) AS last_id,MAX(created_at) AS started_at FROM private_chat_messages WHERE world_id=? AND (sender_id=? OR recipient_id=?) GROUP BY partner_id UNION ALL SELECT partner_id,NULL,created_at FROM community_conversations WHERE world_id=? AND player_id=?) sources GROUP BY partner_id) x JOIN players p ON p.id=x.partner_id JOIN cities c ON c.player_id=p.id AND c.world_id=? LEFT JOIN kingdom_profiles k ON k.player_id=p.id LEFT JOIN private_chat_messages m ON m.id=x.last_id LEFT JOIN community_read_cursors r ON r.world_id=? AND r.player_id=? AND r.channel='private' AND r.scope_id=x.partner_id ORDER BY last_at DESC,x.partner_id LIMIT 200",[$world,$player,$player,$world,$player,$player,$world,$player,$world,$world,$player])->fetchAll();
        $blocked=array_fill_keys($db->query('SELECT IF(player_id=?,blocked_id,player_id) FROM community_blocks WHERE world_id=? AND (player_id=? OR blocked_id=?)',[$player,$world,$player,$player])->fetchAll(\PDO::FETCH_COLUMN),true);
        foreach($rows as &$row){$row['unread']=(int)$row['unread'];$row['player_id']=(int)$row['player_id'];$row['blocked']=isset($blocked[$row['player_id']]);if($row['blocked'])$row['unread']=0;}unset($row);
        return $rows;
    }

    /** Add compact durable social data to the existing chat response. */
    public static function chatState(int $player,int $world,?int $peer,array $base):array
    {
        $social=self::state($player,$world);$member=self::member($player,$world);$cursors=['world'=>self::cursor($player,$world,'world',0),'alliance'=>$member?self::cursor($player,$world,'alliance',(int)$member['alliance_id']):0,'private'=>$peer?self::cursor($player,$world,'private',$peer):0];
        $base['world_chat']=self::enrich($player,$world,'world',null,$base['world_chat']);
        $base['alliance_chat']=self::enrich($player,$world,'alliance',null,$base['alliance_chat']);
        $base['private_chat']=self::enrich($player,$world,'private',$peer,$base['private_chat']);
        $pins=[];
        if($member){$ids=Connection::getInstance()->query('SELECT message_id FROM community_pins WHERE world_id=? AND alliance_id=? ORDER BY created_at DESC LIMIT 5',[$world,$member['alliance_id']])->fetchAll(\PDO::FETCH_COLUMN);foreach($ids as $id){$row=self::message($player,$world,'alliance',null,(int)$id);if($row)$pins[]=$row;}$pins=self::enrich($player,$world,'alliance',null,$pins);}
        $unread=['world'=>self::unread($player,$world,'world',null,$social['preferences']['channels']['world']),'alliance'=>$member?self::unread($player,$world,'alliance',null,$social['preferences']['channels']['alliance']):0,'private'=>self::privateUnread($player,$world,$social['preferences']['channels']['private'])];
        $memberRoles=$member?Connection::getInstance()->query('SELECT m.player_id,m.role FROM alliance_members m JOIN alliances a ON a.id=m.alliance_id AND a.world_id=m.world_id WHERE m.alliance_id=? AND m.world_id=? ORDER BY m.player_id',[$member['alliance_id'],$world])->fetchAll():[];
        foreach($memberRoles as &$entry)$entry['player_id']=(int)$entry['player_id'];unset($entry);
        $allowed=true;if($peer!==null){try{self::assertCanCommunicate($player,$peer,$world);}catch(\DomainException){$allowed=false;}}
        return array_merge($base,$social,['role'=>$member['role']??null,'role_level'=>AllianceRank::level($member['role']??''),'alliance_member_roles'=>$memberRoles,'read_cursors'=>$cursors,'channel_unread'=>$unread,'pins'=>$pins,'private_blocked'=>$peer!==null&&self::blocked($player,$peer,$world),'can_send_private'=>$peer!==null&&$allowed]);
    }

    /** Older messages retain exactly the same authorization and shape as the live snapshot. */
    public static function history(int $player,int $world,string $channel,?int $peer=null,?int $before=null):array
    {
        WorldContext::current($world);self::city($player,$world);$scope=self::scope($player,$world,$channel,$peer);
        self::check($before===null||$before>0,'invalid');$where=$scope['where'];$params=$scope['params'];
        if($before!==null){$where.=' AND m.id<?';$params[]=$before;}
        $rows=Connection::getInstance()->query(self::select($scope)." WHERE $where".self::visibleSql($channel,$scope['sender'],$player,$world).' ORDER BY m.id DESC LIMIT 51',$params)->fetchAll();
        $more=count($rows)>50;if($more)array_pop($rows);$rows=array_reverse($rows);
        return ['messages'=>self::enrich($player,$world,$channel,$peer,$rows),'has_more'=>$more,'next_before_id'=>$rows?(int)$rows[0]['id']:null];
    }

    public static function action(int $player,array $body,int $world):array
    {
        WorldContext::current($world);WorldContext::current($body['expected_world_id']??$world);WorldContext::assertActionAvailable($world);self::city($player,$world);
        $request=self::string($body,'request_id',16,80);self::check((bool)preg_match('/^[a-zA-Z0-9_-]+$/D',$request),'invalid');
        $hashBody=$body;unset($hashBody['request_id']);ksort($hashBody);$hash=hash('sha256',json_encode(['social',$world,$hashBody],JSON_THROW_ON_ERROR));
        $result=Connection::getInstance()->transaction(static function(Connection $db)use($player,$body,$world,$request,$hash):array{
            // Pair order is shared with private chat/mail to make privacy changes atomic with delivery.
            $locks=[$player];if(isset($body['player_id']))$locks[]=self::integer($body,'player_id');$locks=array_unique($locks);sort($locks);
            foreach($locks as $id)self::check((bool)$db->query('SELECT id FROM players WHERE id=? FOR UPDATE',[$id])->fetchColumn(),'unavailable',403);
            $receipt=$db->query('SELECT payload_hash,result_json FROM community_operations WHERE player_id=? AND request_id=?',[$player,$request])->fetch();
            if($receipt){self::check(hash_equals($receipt['payload_hash'],$hash),'replay');return json_decode($receipt['result_json'],true,32,JSON_THROW_ON_ERROR);}
            $action=$body['action']??'';
            $result=match($action){
                'friend.request','friend.accept','friend.decline','friend.remove'=>self::friendAction($player,$world,$action,$body),
                'block.add','block.remove'=>self::blockAction($player,$world,$action,$body),
                'preferences.save'=>self::savePreferences($player,$world,$body),
                'conversation.open'=>self::openConversation($player,$world,$body),
                'chat.read','chat.react','chat.pin'=>self::messageAction($player,$world,$action,$body),
                'report.submit'=>self::report($player,$world,$body),
                default=>throw new \DomainException(self::t('invalid')),
            };
            $db->execute('INSERT INTO community_operations(player_id,request_id,payload_hash,result_json) VALUES(?,?,?,?)',[$player,$request,$hash,json_encode($result,JSON_THROW_ON_ERROR)]);return $result;
        });
        return ['message'=>$result['message']??self::t('saved'),'result'=>$result];
    }

    private static function friendAction(int $player,int $world,string $action,array $body):array
    {
        $target=self::target($player,$world,$body);$db=Connection::getInstance();[$low,$high]=[min($player,$target),max($player,$target)];
        $row=$db->query('SELECT * FROM community_friends WHERE world_id=? AND player_low=? AND player_high=? FOR UPDATE',[$world,$low,$high])->fetch();
        if($action==='friend.request'){
            self::check(!self::blocked($player,$target,$world),'blocked',403);
            if($row)return ['message'=>self::t('saved')];
            self::check((int)$db->query("SELECT COUNT(*) FROM community_friends WHERE world_id=? AND requested_by=? AND status='pending'",[$world,$player])->fetchColumn()<20,'request_limit');
            foreach([$player,$target] as $id)self::check((int)$db->query('SELECT COUNT(*) FROM community_friends WHERE world_id=? AND (player_low=? OR player_high=?)',[$world,$id,$id])->fetchColumn()<200,'friend_limit');
            $db->execute('INSERT INTO community_friends(world_id,player_low,player_high,requested_by) VALUES(?,?,?,?)',[$world,$low,$high,$player]);return ['message'=>self::t('request_sent')];
        }
        if($action==='friend.accept'){
            self::check(!self::blocked($player,$target,$world),'blocked',403);self::check($row&&$row['status']==='pending'&&(int)$row['requested_by']===$target,'pending');
            $db->execute("UPDATE community_friends SET status='accepted',accepted_at=UTC_TIMESTAMP() WHERE world_id=? AND player_low=? AND player_high=?",[$world,$low,$high]);return ['message'=>self::t('friend_added')];
        }
        if($action==='friend.decline')self::check(!$row||$row['status']==='pending','pending');
        $db->execute('DELETE FROM community_friends WHERE world_id=? AND player_low=? AND player_high=?',[$world,$low,$high]);return ['message'=>self::t('friend_removed')];
    }

    private static function blockAction(int $player,int $world,string $action,array $body):array
    {
        $target=self::target($player,$world,$body);$db=Connection::getInstance();
        if($action==='block.add'){
            $db->execute('INSERT IGNORE INTO community_blocks(world_id,player_id,blocked_id) VALUES(?,?,?)',[$world,$player,$target]);
            $db->execute('DELETE FROM community_friends WHERE world_id=? AND player_low=? AND player_high=?',[$world,min($player,$target),max($player,$target)]);
        }else $db->execute('DELETE FROM community_blocks WHERE world_id=? AND player_id=? AND blocked_id=?',[$world,$player,$target]);
        return ['message'=>self::t($action==='block.add'?'block_added':'block_removed')];
    }

    private static function savePreferences(int $player,int $world,array $body):array
    {
        $prefs=self::preferences($player,$world);$privacy=$body['private_messages']??$prefs['private_messages'];self::check(is_string($privacy)&&in_array($privacy,['everyone','friends','alliance','nobody'],true),'invalid');
        $channels=$body['channels']??[];self::check(is_array($channels)&&!array_diff(array_keys($channels),['world','alliance','private']),'invalid');
        foreach($channels as $channel=>$mode){self::check(is_string($mode)&&in_array($mode,['all','mentions','off'],true),'invalid');$prefs['channels'][$channel]=$mode;}
        Connection::getInstance()->execute('INSERT INTO community_preferences(world_id,player_id,private_messages,world_notifications,alliance_notifications,private_notifications) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE private_messages=VALUES(private_messages),world_notifications=VALUES(world_notifications),alliance_notifications=VALUES(alliance_notifications),private_notifications=VALUES(private_notifications)',[$world,$player,$privacy,$prefs['channels']['world'],$prefs['channels']['alliance'],$prefs['channels']['private']]);
        return ['message'=>self::t('saved')];
    }

    private static function openConversation(int $player,int $world,array $body):array
    {
        $target=self::target($player,$world,$body);self::check(!self::blocked($player,$target,$world),'blocked',403);
        Connection::getInstance()->execute('INSERT IGNORE INTO community_conversations(world_id,player_id,partner_id) VALUES(?,?,?)',[$world,$player,$target]);return ['message'=>self::t('saved'),'player_id'=>$target];
    }

    private static function messageAction(int $player,int $world,string $action,array $body):array
    {
        $channel=self::string($body,'channel',1,12);$peer=$channel==='private'?self::integer($body,'player_id'):null;$id=self::integer($body,'message_id');$row=self::message($player,$world,$channel,$peer,$id);self::check((bool)$row,'unavailable',403);$scope=self::scope($player,$world,$channel,$peer);$db=Connection::getInstance();
        if($action==='chat.read'){
            $db->execute('INSERT INTO community_read_cursors(world_id,player_id,channel,scope_id,message_id) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE message_id=GREATEST(message_id,VALUES(message_id)),updated_at=UTC_TIMESTAMP()',[$world,$player,$channel,$scope['id'],$id]);return ['message'=>self::t('saved'),'message_id'=>max($id,self::cursor($player,$world,$channel,$scope['id']))];
        }
        self::assertNotBanned($player,$world);$active=$body['active']??null;self::check(is_bool($active),'invalid');
        if($action==='chat.react'){
            if($channel==='private')self::assertCanCommunicate($player,(int)$peer,$world);
            self::check(!self::blocked($player,(int)$row['player_id'],$world),'blocked',403);$reaction=self::string($body,'reaction',1,10);self::check(in_array($reaction,self::REACTIONS,true),'invalid');
            if($active)$db->execute('INSERT IGNORE INTO community_reactions(world_id,channel,message_id,player_id,reaction) VALUES(?,?,?,?,?)',[$world,$channel,$id,$player,$reaction]);
            else $db->execute('DELETE FROM community_reactions WHERE world_id=? AND channel=? AND message_id=? AND player_id=? AND reaction=?',[$world,$channel,$id,$player,$reaction]);
        }else{
            self::check($channel==='alliance','denied',403);$member=self::member($player,$world);$db->query('SELECT id FROM alliances WHERE id=? FOR UPDATE',[$scope['id']])->fetch();$member=self::member($player,$world);
            self::check($member&&(int)$member['alliance_id']===$scope['id']&&in_array($member['role'],['officer','vice_leader','leader'],true),'denied',403);
            if($active){$exists=$db->query('SELECT message_id FROM community_pins WHERE world_id=? AND alliance_id=? AND message_id=?',[$world,$scope['id'],$id])->fetchColumn();self::check($exists||(int)$db->query('SELECT COUNT(*) FROM community_pins WHERE world_id=? AND alliance_id=?',[$world,$scope['id']])->fetchColumn()<5,'pin_limit');$db->execute('INSERT IGNORE INTO community_pins(world_id,alliance_id,message_id,pinned_by) VALUES(?,?,?,?)',[$world,$scope['id'],$id,$player]);}
            else $db->execute('DELETE FROM community_pins WHERE world_id=? AND alliance_id=? AND message_id=?',[$world,$scope['id'],$id]);
        }
        return ['message'=>self::t('saved'),'chat_message'=>self::enrich($player,$world,$channel,$peer,[$row])[0]??null];
    }

    private static function report(int $player,int $world,array $body):array
    {
        $db=Connection::getInstance();$reason=self::string($body,'reason',1,20);self::check(in_array($reason,['harassment','spam','cheating','inappropriate','other'],true),'invalid');$details=self::string($body,'details',0,2000,'');
        $channel=null;$id=null;$snapshot=[];
        if(isset($body['message_id'])){
            $channel=self::string($body,'channel',1,12);$id=self::integer($body,'message_id');
            $peer=$channel==='private'?self::integer($body,'player_id'):null;$row=self::message($player,$world,$channel,$peer,$id);self::check((bool)$row,'unavailable',403);
            $target=(int)$row['player_id'];self::check($target!==$player,'invalid');$snapshot=$row;
            // Public/alliance reports may include a target, but it must match the actual author.
            if($channel!=='private'&&isset($body['player_id']))self::check(self::integer($body,'player_id')===$target,'invalid');
        }else{$target=self::target($player,$world,$body);$snapshot=self::profile($target,$world)??[];}
        self::check((int)$db->query('SELECT COUNT(*) FROM community_reports WHERE reporter_id=? AND created_at>=UTC_DATE()',[$player])->fetchColumn()<20,'report_limit');
        $db->execute('INSERT INTO community_reports(world_id,reporter_id,player_id,channel,message_id,reason,details,snapshot_json) VALUES(?,?,?,?,?,?,?,?)',[$world,$player,$target,$channel,$id,$reason,$details,json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
        return ['message'=>self::t('reported'),'id'=>$db->lastInsertId()];
    }

    /** Called inside the original chat transaction before the message is inserted. */
    public static function prepareMessage(int $player,int $world,string $channel,?int $peer,array $body):array
    {
        self::assertNotBanned($player,$world);self::scope($player,$world,$channel,$peer);
        if($channel==='private')self::assertCanCommunicate($player,(int)$peer,$world);
        $reply=null;if(isset($body['reply_to_id'])){$reply=self::integer($body,'reply_to_id');$row=self::message($player,$world,$channel,$peer,$reply);self::check((bool)$row&&!self::blocked($player,(int)$row['player_id'],$world),'unavailable',403);}
        $ids=$body['mention_ids']??[];self::check(is_array($ids)&&array_is_list($ids)&&count($ids)<=5,'invalid');$mentions=[];$member=$channel==='alliance'?self::member($player,$world):null;
        foreach($ids as $id){self::check(is_int($id)&&$id>0&&$id!==$player,'invalid');self::city($id,$world);self::check(!self::blocked($player,$id,$world),'blocked',403);if($channel==='private')self::check($id===$peer,'denied',403);if($channel==='alliance'){$other=self::member($id,$world);self::check($other&&(int)$other['alliance_id']===(int)$member['alliance_id'],'denied',403);}$mentions[$id]=$id;}
        return ['reply_to_id'=>$reply,'mentions'=>array_values($mentions)];
    }

    public static function recordMessage(int $player,int $world,string $channel,?int $peer,int $id,array $metadata):void
    {
        $db=Connection::getInstance();$db->execute('INSERT INTO community_message_meta(world_id,channel,message_id,reply_to_id,mentions_json) VALUES(?,?,?,?,?)',[$world,$channel,$id,$metadata['reply_to_id'],json_encode($metadata['mentions'],JSON_THROW_ON_ERROR)]);
        if($channel==='private')foreach([[$player,$peer],[$peer,$player]] as [$owner,$partner])$db->execute('INSERT IGNORE INTO community_conversations(world_id,player_id,partner_id) VALUES(?,?,?)',[$world,$owner,$partner]);
    }

    public static function assertCanCommunicate(int $sender,int $recipient,int $world):void
    {
        self::city($sender,$world);self::city($recipient,$world);self::check($sender!==$recipient,'invalid');self::assertNotBanned($sender,$world);self::check(!self::blocked($sender,$recipient,$world),'blocked',403);
        $privacy=self::preferences($recipient,$world)['private_messages'];if($privacy==='everyone')return;
        if($privacy==='friends'){$accepted=Connection::getInstance()->query("SELECT 1 FROM community_friends WHERE world_id=? AND player_low=? AND player_high=? AND status='accepted'",[$world,min($sender,$recipient),max($sender,$recipient)])->fetchColumn();self::check((bool)$accepted,'privacy',403);return;}
        if($privacy==='alliance'){$a=self::member($sender,$world);$b=self::member($recipient,$world);self::check($a&&$b&&(int)$a['alliance_id']===(int)$b['alliance_id'],'privacy',403);return;}
        self::check(false,'privacy',403);
    }

    public static function blocked(int $a,int $b,int $world):bool
    {
        return (bool)Connection::getInstance()->query('SELECT 1 FROM community_blocks WHERE world_id=? AND ((player_id=? AND blocked_id=?) OR (player_id=? AND blocked_id=?)) LIMIT 1',[$world,$a,$b,$b,$a])->fetchColumn();
    }

    private static function ban(int $player,int $world):?array{return Connection::getInstance()->query('SELECT reason,expires_at FROM community_chat_bans WHERE player_id=? AND world_id=? AND expires_at>UTC_TIMESTAMP()',[$player,$world])->fetch()?:null;}
    public static function assertNotBanned(int $player,int $world):void{self::check(self::ban($player,$world)===null,'banned',403);}

    /** Service entry used by the authenticated backoffice, never by player routes. */
    public static function moderationState(?int $world=null):array
    {
        $db=Connection::getInstance();$filter=$world===null?'':' WHERE r.world_id=?';$params=$world===null?[]:[$world];
        $reports=$db->query("SELECT r.*,p.username AS player_name,q.username AS reporter_name FROM community_reports r JOIN players p ON p.id=r.player_id JOIN players q ON q.id=r.reporter_id$filter ORDER BY FIELD(r.status,'new','reviewing','resolved','dismissed'),r.id DESC LIMIT 200",$params)->fetchAll();
        foreach($reports as &$report){$report['snapshot']=json_decode($report['snapshot_json'],true,32,JSON_THROW_ON_ERROR);unset($report['snapshot_json']);}unset($report);
        $bans=$db->query('SELECT b.*,p.username FROM community_chat_bans b JOIN players p ON p.id=b.player_id WHERE b.expires_at>UTC_TIMESTAMP()'.($world===null?'':' AND b.world_id=?').' ORDER BY b.expires_at DESC LIMIT 200',$params)->fetchAll();return ['reports'=>$reports,'bans'=>$bans];
    }

    /** AdminService owns the outer replay receipt and audit; this also validates its actor. */
    public static function moderate(int $admin,array $body):array
    {
        return self::moderateAuthorized($admin,$body,false);
    }

    /** Narrow team-case entry; existing generic admin routes still require superadmin. */
    public static function moderateCase(int $admin,array $body):array
    {
        return self::moderateAuthorized($admin,$body,true);
    }

    private static function moderateAuthorized(int $admin,array $body,bool $caseScope):array
    {
        return Connection::getInstance()->transaction(static function(Connection $db)use($admin,$body,$caseScope):array{
            $role=$db->query('SELECT role FROM admin_users WHERE id=? FOR UPDATE',[$admin])->fetchColumn();
            self::check($role==='superadmin'||($caseScope&&$role==='moderator'),'denied',403);$action=$body['action']??'';
            if($action==='report.update'){
                $id=self::integer($body,'report_id');$status=self::string($body,'status',1,12);self::check(in_array($status,$caseScope?['new','reviewing','waiting','resolved','dismissed']:['new','reviewing','resolved','dismissed'],true),'invalid');$note=self::string($body,'admin_note',0,2000,'');
                $before=$db->query('SELECT id,world_id,status,admin_note,handled_by,handled_at FROM community_reports WHERE id=? FOR UPDATE',[$id])->fetch();self::check((bool)$before,'unavailable');
                $after=['status'=>$status,'admin_note'=>$note,'handled_by'=>$admin,'handled_at'=>in_array($status,['resolved','dismissed'],true)?gmdate('Y-m-d H:i:s'):null];
                $db->execute('UPDATE community_reports SET status=?,admin_note=?,handled_by=?,handled_at=? WHERE id=?',[$status,$note,$admin,$after['handled_at'],$id]);return ['target_type'=>'community_report','target_id'=>$id,'world_id'=>(int)$before['world_id'],'before'=>$before,'after'=>$after,'message'=>self::t('moderated')];
            }
            self::check(in_array($action,['chat.ban','chat.unban'],true),'invalid');$player=self::integer($body,'player_id');$world=self::integer($body,'world_id');self::city($player,$world);$db->query('SELECT id FROM players WHERE id=? FOR UPDATE',[$player])->fetch();$reason=self::string($body,'reason',3,500);
            $before=$db->query('SELECT * FROM community_chat_bans WHERE world_id=? AND player_id=? FOR UPDATE',[$world,$player])->fetch()?:null;$after=null;
            if($action==='chat.ban'){$minutes=self::integer($body,'minutes',1,43200);$expires=gmdate('Y-m-d H:i:s',time()+$minutes*60);$after=['reason'=>$reason,'expires_at'=>$expires,'created_by'=>$admin];$db->execute('INSERT INTO community_chat_bans(world_id,player_id,reason,expires_at,created_by) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE reason=VALUES(reason),expires_at=VALUES(expires_at),created_by=VALUES(created_by),created_at=UTC_TIMESTAMP()',[$world,$player,$reason,$expires,$admin]);}
            else $db->execute('DELETE FROM community_chat_bans WHERE world_id=? AND player_id=?',[$world,$player]);
            return ['target_type'=>'player','target_id'=>$player,'world_id'=>$world,'before'=>$before,'after'=>$after,'message'=>self::t('moderated')];
        });
    }

    /** Authorization for every polymorphic message reference lives here. */
    private static function scope(int $player,int $world,string $channel,?int $peer):array
    {
        self::city($player,$world);self::check(in_array($channel,['world','alliance','private'],true),'invalid');
        if($channel==='world')return ['channel'=>$channel,'table'=>'world_chat','sender'=>'m.player_id','created'=>'m.created_at','where'=>'m.world_id=?','params'=>[$world],'id'=>0,'tag'=>true];
        if($channel==='alliance'){$member=self::member($player,$world);self::check((bool)$member,'denied',403);return ['channel'=>$channel,'table'=>'alliance_messages','sender'=>'m.player_id','created'=>'m.sent_at','where'=>'m.alliance_id=?','params'=>[(int)$member['alliance_id']],'id'=>(int)$member['alliance_id'],'tag'=>false];}
        self::check($peer!==null&&$peer>0&&$peer!==$player,'invalid');self::city($peer,$world);
        return ['channel'=>$channel,'table'=>'private_chat_messages','sender'=>'m.sender_id','created'=>'m.created_at','where'=>'m.world_id=? AND ((m.sender_id=? AND m.recipient_id=?) OR (m.sender_id=? AND m.recipient_id=?))','params'=>[$world,$player,$peer,$peer,$player],'id'=>$peer,'tag'=>false];
    }

    private static function select(array $scope):string
    {
        $channel=$scope['channel'];$extra=$scope['tag']?',COALESCE(a.tag,m.alliance_tag) AS alliance_tag':'';
        $joins=$scope['tag']?' LEFT JOIN alliance_members am ON am.player_id=m.player_id AND am.world_id=m.world_id LEFT JOIN alliances a ON a.id=am.alliance_id AND a.world_id=m.world_id':'';
        return "SELECT m.id,{$scope['sender']} AS player_id,COALESCE(k.display_name,p.username) AS username,COALESCE(k.avatar,'knight') AS avatar,m.message,{$scope['created']} AS created_at,s.id AS shared_report_id$extra FROM {$scope['table']} m JOIN players p ON p.id={$scope['sender']} LEFT JOIN kingdom_profiles k ON k.player_id=p.id LEFT JOIN battle_report_shares s ON s.channel='$channel' AND s.message_id=m.id$joins";
    }

    private static function message(int $player,int $world,string $channel,?int $peer,int $id):?array
    {
        $scope=self::scope($player,$world,$channel,$peer);$params=$scope['params'];$params[]=$id;return Connection::getInstance()->query(self::select($scope)." WHERE {$scope['where']} AND m.id=?",$params)->fetch()?:null;
    }

    private static function visibleSql(string $channel,string $sender,int $player,int $world):string
    {
        // History of a blocked private conversation stays accessible for evidence/reporting.
        if($channel==='private')return '';
        return " AND NOT EXISTS(SELECT 1 FROM community_blocks b WHERE b.world_id=$world AND b.player_id=$player AND b.blocked_id=$sender)";
    }

    public static function enrich(int $player,int $world,string $channel,?int $peer,array $rows):array
    {
        if(!$rows)return [];
        $db=Connection::getInstance();$ids=array_map('intval',array_column($rows,'id'));$marks=implode(',',array_fill(0,count($ids),'?'));
        // Show the sender's current rank in this world, including on older/pinned messages.
        // An author who left this alliance must not wear their new alliance's rank here.
        $senders=array_values(array_unique(array_map('intval',array_column($rows,'player_id'))));
        $senderMarks=implode(',',array_fill(0,count($senders),'?'));$members=[];
        $allianceId=$channel==='alliance'?(int)(self::member($player,$world)['alliance_id']??0):null;
        foreach($db->query("SELECT m.player_id,m.alliance_id,m.role FROM alliance_members m JOIN alliances a ON a.id=m.alliance_id AND a.world_id=m.world_id WHERE m.world_id=? AND m.player_id IN ($senderMarks)",array_merge([$world],$senders))->fetchAll() as $member){
            if($allianceId===null||(int)$member['alliance_id']===$allianceId)$members[(int)$member['player_id']]=$member;
        }
        $metadata=$db->query("SELECT message_id,reply_to_id,mentions_json FROM community_message_meta WHERE world_id=? AND channel=? AND message_id IN ($marks)",array_merge([$world,$channel],$ids))->fetchAll();
        $metaById=[];$replyIds=[];$mentionIds=[];
        foreach($metadata as $meta){$meta['mentions']=json_decode($meta['mentions_json'],true,8,JSON_THROW_ON_ERROR);$metaById[(int)$meta['message_id']]=$meta;if($meta['reply_to_id'])$replyIds[(int)$meta['reply_to_id']]=(int)$meta['reply_to_id'];foreach($meta['mentions'] as $id)$mentionIds[(int)$id]=(int)$id;}
        $blockedRows=$db->query('SELECT player_id,blocked_id FROM community_blocks WHERE world_id=? AND (player_id=? OR blocked_id=?)',[$world,$player,$player])->fetchAll();$blocked=[];$hidden=[];
        foreach($blockedRows as $block){$other=(int)$block['player_id']===$player?(int)$block['blocked_id']:(int)$block['player_id'];$blocked[$other]=true;if((int)$block['player_id']===$player)$hidden[$other]=true;}
        $replies=[];
        if($replyIds){$scope=self::scope($player,$world,$channel,$peer);$replyMarks=implode(',',array_fill(0,count($replyIds),'?'));foreach($db->query(self::select($scope)." WHERE {$scope['where']} AND m.id IN ($replyMarks)",array_merge($scope['params'],array_values($replyIds)))->fetchAll() as $reply)if(!isset($blocked[(int)$reply['player_id']]))$replies[(int)$reply['id']]=array_intersect_key($reply,array_flip(['id','player_id','username','message']));}
        $profiles=[];
        if($mentionIds){$mentionMarks=implode(',',array_fill(0,count($mentionIds),'?'));foreach($db->query("SELECT p.id AS player_id,COALESCE(k.display_name,p.username) AS username FROM players p JOIN cities c ON c.player_id=p.id AND c.world_id=? LEFT JOIN kingdom_profiles k ON k.player_id=p.id WHERE p.id IN ($mentionMarks)",array_merge([$world],array_values($mentionIds)))->fetchAll() as $profile)$profiles[(int)$profile['player_id']]=$profile;}
        $reactions=[];
        foreach($db->query("SELECT message_id,reaction,COUNT(*) AS total,MAX(player_id=?) AS mine FROM community_reactions WHERE world_id=? AND channel=? AND message_id IN ($marks) GROUP BY message_id,reaction",array_merge([$player,$world,$channel],$ids))->fetchAll() as $reaction)$reactions[(int)$reaction['message_id']][]=['reaction'=>$reaction['reaction'],'count'=>(int)$reaction['total'],'mine'=>(bool)$reaction['mine']];
        $pins=$channel==='alliance'?array_fill_keys($db->query("SELECT message_id FROM community_pins WHERE world_id=? AND message_id IN ($marks)",array_merge([$world],$ids))->fetchAll(\PDO::FETCH_COLUMN),true):[];
        $result=[];
        foreach($rows as $row){
            if($channel!=='private'&&isset($hidden[(int)$row['player_id']]))continue;
            $sender=$members[(int)$row['player_id']]??null;
            $row['alliance_role']=$sender['role']??null;$row['alliance_role_level']=AllianceRank::level($sender['role']??'');
            $id=(int)$row['id'];$meta=$metaById[$id]??null;$row['reply_to']=$meta&&$meta['reply_to_id']?($replies[(int)$meta['reply_to_id']]??null):null;$row['mentions']=[];
            foreach($meta['mentions']??[] as $mention)if(isset($profiles[(int)$mention]))$row['mentions'][]=$profiles[(int)$mention];
            $row['reactions']=$reactions[$id]??[];$row['pinned']=isset($pins[$id]);$result[]=$row;
        }
        return $result;
    }
    private static function unread(int $player,int $world,string $channel,?int $peer,string $mode):int
    {
        if($mode==='off')return 0;$scope=self::scope($player,$world,$channel,$peer);$params=$scope['params'];$params[]=self::cursor($player,$world,$channel,$scope['id']);$params[]=$player;$where="{$scope['where']} AND m.id>? AND {$scope['sender']}<>?";
        if($mode==='mentions'){$where.=" AND EXISTS(SELECT 1 FROM community_message_meta mm WHERE mm.world_id=? AND mm.channel=? AND mm.message_id=m.id AND JSON_CONTAINS(mm.mentions_json,?))";array_push($params,$world,$channel,(string)$player);}
        return (int)Connection::getInstance()->query("SELECT COUNT(*) FROM {$scope['table']} m WHERE $where".self::visibleSql($channel,$scope['sender'],$player,$world),$params)->fetchColumn();
    }

    private static function privateUnread(int $player,int $world,string $mode):int
    {
        if($mode==='off')return 0;
        $params=[$world,$player,$world,$player,$world,$player,$player];
        $where="m.world_id=? AND m.recipient_id=? AND m.id>COALESCE(r.message_id,0) AND NOT EXISTS(SELECT 1 FROM community_blocks b WHERE b.world_id=? AND ((b.player_id=? AND b.blocked_id=m.sender_id) OR (b.blocked_id=? AND b.player_id=m.sender_id)))";
        if($mode==='mentions'){$where.=" AND EXISTS(SELECT 1 FROM community_message_meta mm WHERE mm.world_id=m.world_id AND mm.channel='private' AND mm.message_id=m.id AND JSON_CONTAINS(mm.mentions_json,?))";$params[]=(string)$player;}
        return (int)Connection::getInstance()->query("SELECT COUNT(*) FROM private_chat_messages m LEFT JOIN community_read_cursors r ON r.world_id=? AND r.player_id=? AND r.channel='private' AND r.scope_id=m.sender_id WHERE $where",$params)->fetchColumn();
    }

    private static function cursor(int $player,int $world,string $channel,int $scope):int{return (int)(Connection::getInstance()->query('SELECT message_id FROM community_read_cursors WHERE world_id=? AND player_id=? AND channel=? AND scope_id=?',[$world,$player,$channel,$scope])->fetchColumn()?:0);}
    private static function profile(int $player,int $world):?array{return Connection::getInstance()->query("SELECT p.id AS player_id,COALESCE(k.display_name,p.username) AS username,COALESCE(k.avatar,'knight') AS avatar FROM players p JOIN cities c ON c.player_id=p.id AND c.world_id=? LEFT JOIN kingdom_profiles k ON k.player_id=p.id WHERE p.id=?",[$world,$player])->fetch()?:null;}
    private static function city(int $player,int $world):void{self::check((bool)Connection::getInstance()->query('SELECT id FROM cities WHERE player_id=? AND world_id=?',[$player,$world])->fetchColumn(),'unavailable',403);}
    private static function member(int $player,int $world):?array{return Connection::getInstance()->query('SELECT m.alliance_id,m.role FROM alliance_members m JOIN alliances a ON a.id=m.alliance_id AND a.world_id=? WHERE m.player_id=? AND m.world_id=?',[$world,$player,$world])->fetch()?:null;}
    private static function target(int $player,int $world,array $body):int{$id=self::integer($body,'player_id');self::check($id!==$player,'invalid');self::city($id,$world);return $id;}
    private static function integer(array $body,string $key,int $min=1,int $max=2147483647):int{$value=$body[$key]??null;self::check(is_int($value)&&$value>=$min&&$value<=$max,'invalid');return $value;}
    private static function string(array $body,string $key,int $min,int $max,?string $default=null):string{$value=$body[$key]??$default;self::check(is_string($value),'invalid');$value=trim($value);self::check(mb_check_encoding($value,'UTF-8')&&mb_strlen($value)>=$min&&mb_strlen($value)<=$max&&!preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u',$value),'invalid');return $value;}
    private static function t(string $key):string{return Locale::t('social.'.$key);}
    private static function check(bool $valid,string $key,int $code=0):void{if(!$valid)throw new \DomainException(self::t($key),$code);}
}
