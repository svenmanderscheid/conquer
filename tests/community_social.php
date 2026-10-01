<?php
declare(strict_types=1);
/** Social contracts, reference authorization and replay safety, using synthetic data only. */
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();date_default_timezone_set('UTC');
require ROOT_DIR.'/tests/Support/FeatureDatabase.php';
use Conquer\Db\Connection;
use Conquer\Game\Community\SocialService as Social;
use Conquer\Game\Community\CommunityService as Community;
use Conquer\Game\World\WorldContext;
function checkSocial(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);echo "PASS $message\n";}
function denySocial(callable $fn,string $message):void{try{$fn();}catch(DomainException){checkSocial(true,$message);return;}throw new RuntimeException('Expected rejection: '.$message);}
function socialAction(int $player,string $action,array $body=[]):array{return Social::action($player,['action'=>$action,'world_id'=>1,'expected_world_id'=>1,'request_id'=>bin2hex(random_bytes(16))]+$body,1)['result'];}
function chatAction(int $player,string $channel,string $text,array $body=[]):array{
    $db=Connection::getInstance();foreach(['world_chat'=>'created_at','alliance_messages'=>'sent_at','private_chat_messages'=>'created_at'] as $table=>$column)$db->execute("UPDATE $table SET $column=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 5 SECOND)");
    return Community::action($player,['action'=>'chat.send','channel'=>$channel,'message'=>$text,'world_id'=>1,'expected_world_id'=>1,'request_id'=>bin2hex(random_bytes(16))]+$body,1)['result'];
}
function socialHttp(string $path,?array $body=null,bool $auth=true,bool $csrf=true):array{
    global $url,$sessionToken,$csrfToken;$handle=curl_init($url.$path);$headers=['Content-Type: application/json'];if($csrf)$headers[]='X-CSRF-Token: '.$csrfToken;
    curl_setopt_array($handle,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_HTTPHEADER=>$headers,CURLOPT_COOKIE=>$auth?'conquer_session='.$sessionToken:'']);if($body!==null)curl_setopt_array($handle,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($body,JSON_THROW_ON_ERROR)]);
    $raw=curl_exec($handle);if($raw===false)throw new RuntimeException(curl_error($handle));$status=(int)curl_getinfo($handle,CURLINFO_HTTP_CODE);curl_close($handle);$json=json_decode($raw,true);if(!is_array($json))throw new RuntimeException('Non-JSON HTTP response: '.$raw);return [$status,$json];
}
$fixture=new \ConquerTests\FeatureDatabase();
try{
    $db=Connection::getInstance();\Conquer\Db\MigrationSql::apply($db->getPdo(),(string)file_get_contents(ROOT_DIR.'/migrations/0121_community_social.sql'));
    $db->execute("INSERT INTO worlds(id,name,slug,status) VALUES(2,'Social remote','social-remote','running')");
    for($id=1;$id<=5;$id++){$db->execute('INSERT INTO players(id,username,email,password_hash) VALUES(?,?,?,?)',[$id,'Social'.$id,'social'.$id.'@tests.invalid','unused']);$db->execute("INSERT INTO cities(player_id,world_id,name,coord_x,coord_y) VALUES(?,?,'Social fixture',?,50)",[$id,$id===5?2:1,40+$id]);}
    $db->execute("INSERT INTO alliances(id,world_id,name,tag,leader_id) VALUES(1,1,'Social friends','SOC',1),(2,1,'Other friends','OTH',3),(3,2,'Remote friends','REM',5)");
    $db->execute("INSERT INTO alliance_members(alliance_id,player_id,world_id,role) VALUES(1,1,1,'leader'),(1,2,1,'member'),(2,3,1,'leader'),(3,5,2,'leader')");
    $db->execute("INSERT INTO admin_users(id,username,password_hash,role) VALUES(1,'SocialAdmin','unused','superadmin'),(2,'SocialModerator','unused','moderator')");
    WorldContext::bind(1);

    checkSocial(Social::state(1,1)['preferences']['private_messages']==='everyone','Default private communication is open without an implicit read');
    checkSocial(count(Social::state(1,1,'Social')['search'])===3&&Social::state(1,1,'%')['search']===[],'Player search is world scoped and escapes wildcard input');
    denySocial(fn()=>socialAction(1,'friend.request',['player_id'=>5]),'Cross-world friend requests are rejected');
    denySocial(fn()=>Social::state(1,2),'Stale world reads are rejected');
    denySocial(fn()=>socialAction(1,'friend.request',['player_id'=>1]),'Self friend requests are rejected');
    $body=['action'=>'friend.request','player_id'=>2,'request_id'=>'social_request_once_0001','expected_world_id'=>1];$first=Social::action(1,$body,1);$again=Social::action(1,$body,1);
    checkSocial($first===$again&&(int)$db->query('SELECT COUNT(*) FROM community_friends')->fetchColumn()===1,'Replaying a friend request produces a single relationship');
    denySocial(fn()=>Social::action(1,array_replace($body,['player_id'=>3]),1),'Reusing an operation ID for another target is rejected');
    checkSocial(count(Social::state(2,1)['requests']['incoming'])===1&&count(Social::state(1,1)['requests']['outgoing'])===1,'Pending requests persist for both players');
    denySocial(fn()=>socialAction(1,'friend.accept',['player_id'=>2]),'Only the recipient can accept a friend request');
    socialAction(2,'friend.accept',['player_id'=>1]);checkSocial(count(Social::state(1,1)['friends'])===1&&count(Social::state(2,1)['friends'])===1,'Accepted friendship is symmetric');

    socialAction(2,'preferences.save',['private_messages'=>'friends']);$sent=chatAction(1,'private','Friends can write',['player_id'=>2]);
    denySocial(fn()=>chatAction(3,'private','Not a friend',['player_id'=>2]),'Private messages enforce recipient friends-only preference');
    denySocial(fn()=>Community::action(3,['action'=>'mail.send','player_id'=>2,'subject'=>'Bypass','body'=>'No bypass','request_id'=>'mail_privacy_check_0001'],1),'Private letters enforce the same recipient privacy');
    $conversation=Social::state(2,1)['conversations'][0];checkSocial($conversation['player_id']===1&&$conversation['unread']===1,'Receiving a message creates a durable unread conversation');
    Community::chatState(2,1,1);Community::chatState(2,1,1);checkSocial(Social::state(2,1)['conversations'][0]['unread']===1,'Repeated GET snapshots never mark private messages read');
    socialAction(2,'chat.read',['channel'=>'private','player_id'=>1,'message_id'=>$sent['id']]);checkSocial(Social::state(2,1)['conversations'][0]['unread']===0,'Explicit read receipt is shared across subsequent sessions');
    $newer=chatAction(1,'private','Second private message',['player_id'=>2]);socialAction(2,'chat.read',['channel'=>'private','player_id'=>1,'message_id'=>$newer['id']]);socialAction(2,'chat.read',['channel'=>'private','player_id'=>1,'message_id'=>$sent['id']]);
    checkSocial(Community::chatState(2,1,1)['read_cursors']['private']===$newer['id'],'Delayed read receipts cannot move the cursor backwards');
    $other=chatAction(1,'private','Secret third-player conversation',['player_id'=>4]);
    denySocial(fn()=>socialAction(2,'chat.read',['channel'=>'private','player_id'=>1,'message_id'=>$other['id']]),'A cursor cannot acknowledge a different private conversation');
    denySocial(fn()=>chatAction(2,'private','Leaked reply',['player_id'=>1,'reply_to_id'=>$other['id']]),'Reply references cannot expose another private conversation');
    denySocial(fn()=>socialAction(2,'chat.react',['channel'=>'private','player_id'=>1,'message_id'=>$other['id'],'reaction'=>'like','active'=>true]),'Reactions cannot target an unrelated private conversation');
    denySocial(fn()=>socialAction(2,'report.submit',['channel'=>'private','player_id'=>1,'message_id'=>$other['id'],'reason'=>'spam']),'Reports cannot snapshot unrelated private messages');

    $reply=chatAction(2,'private','Thanks @Social1',['player_id'=>1,'reply_to_id'=>$newer['id'],'mention_ids'=>[1]]);$rows=Social::history(1,1,'private',2)['messages'];$last=end($rows);
    checkSocial((int)$last['reply_to']['id']===$newer['id']&&$last['mentions'][0]['player_id']===1,'Replies and mentions preserve structured validated identities');
    denySocial(fn()=>chatAction(2,'private','Hidden mention',['player_id'=>1,'mention_ids'=>[4]]),'Private mentions are limited to the conversation participants');
    denySocial(fn()=>chatAction(1,'alliance','Outside member',['mention_ids'=>[3]]),'Alliance mentions cannot target an outsider');
    denySocial(fn()=>chatAction(1,'world','Remote member',['mention_ids'=>[5]]),'World mentions cannot target another world');
    $reacted=socialAction(1,'chat.react',['channel'=>'private','player_id'=>2,'message_id'=>$reply['id'],'reaction'=>'heart','active'=>true]);socialAction(1,'chat.react',['channel'=>'private','player_id'=>2,'message_id'=>$reply['id'],'reaction'=>'heart','active'=>true]);
    checkSocial($reacted['chat_message']['reactions']===[['reaction'=>'heart','count'=>1,'mine'=>true]],'Reaction response returns the updated authorized message for older loaded history');
    $rows=Social::history(1,1,'private',2)['messages'];$last=end($rows);checkSocial($last['reactions']===[['reaction'=>'heart','count'=>1,'mine'=>true]],'Explicit reaction states do not toggle twice when repeated');
    denySocial(fn()=>socialAction(1,'chat.react',['channel'=>'private','player_id'=>2,'message_id'=>$reply['id'],'reaction'=>'<script>','active'=>true]),'Reactions use a fixed allowlist');

    $alliance=chatAction(1,'alliance','Pinned plan');socialAction(1,'chat.pin',['channel'=>'alliance','message_id'=>$alliance['id'],'active'=>true]);
    checkSocial(count(Community::chatState(2,1)['pins'])===1,'Alliance announcements persist as pinned chat messages');
    $rankState=Community::chatState(2,1);$rankMessage=$rankState['alliance_chat'][0];
    checkSocial($rankState['alliance_member_roles']===[['player_id'=>1,'role'=>'leader'],['player_id'=>2,'role'=>'member']],'Live rank list includes every current alliance member, even without a recent message, and no other alliance');
    checkSocial(Community::chatState(4,1)['alliance_member_roles']===[],'Unaffiliated chat snapshots return an authoritative empty rank list');
    checkSocial($rankState['role_level']===1&&$rankMessage['alliance_role']==='leader'&&$rankMessage['alliance_role_level']===5,'Chat identifies the sender rank independently of the viewer rank');
    checkSocial(Social::history(2,1,'alliance')['messages'][0]['alliance_role_level']===5&&$rankState['pins'][0]['alliance_role_level']===5,'History and pinned messages share the live rank contract');
    $rankReaction=socialAction(2,'chat.react',['channel'=>'alliance','message_id'=>$alliance['id'],'reaction'=>'cheer','active'=>true]);
    checkSocial($rankReaction['chat_message']['alliance_role_level']===5,'Message action responses preserve the sender rank');
    $db->execute("UPDATE alliance_members SET role='vice_leader',role_level=5 WHERE player_id=1 AND world_id=1");
    $db->execute("INSERT INTO alliance_members(alliance_id,player_id,world_id,role,role_level) VALUES(3,1,2,'veteran',2)");
    checkSocial(Community::chatState(2,1)['alliance_chat'][0]['alliance_role_level']===4,'Chat derives the current rank from the role in the correct world, ignoring stale numeric data');
    checkSocial(Community::chatState(2,1)['alliance_member_roles']===[['player_id'=>1,'role'=>'vice_leader'],['player_id'=>2,'role'=>'member']],'Complete rank list follows current roles in this world rather than stale numbers or remote membership');
    $db->execute("UPDATE alliance_members SET alliance_id=2,role='member',role_level=1 WHERE player_id=1 AND world_id=1");
    $former=Community::chatState(2,1);
    checkSocial($former['alliance_chat'][0]['alliance_role']===null&&$former['pins'][0]['alliance_role_level']===0,'Former members do not display another alliance rank on retained messages');
    checkSocial($former['alliance_member_roles']===[['player_id'=>2,'role'=>'member']],'Complete rank list removes a departed author even though their messages remain');
    $db->execute("UPDATE alliance_members SET alliance_id=1,role='leader',role_level=5 WHERE player_id=1 AND world_id=1");
    $db->execute('DELETE FROM alliance_members WHERE player_id=1 AND world_id=2');
    denySocial(fn()=>socialAction(2,'chat.pin',['channel'=>'alliance','message_id'=>$alliance['id'],'active'=>false]),'Regular members cannot change alliance pins');
    denySocial(fn()=>socialAction(3,'chat.pin',['channel'=>'alliance','message_id'=>$alliance['id'],'active'=>true]),'Officers cannot pin messages from another alliance');
    denySocial(fn()=>socialAction(3,'report.submit',['channel'=>'alliance','message_id'=>$alliance['id'],'reason'=>'spam']),'Reports cannot read another alliance chat');
    checkSocial(Social::history(3,1,'alliance')['messages']===[],'History stays scoped to the current alliance');

    for($i=1;$i<=5;$i++)$db->execute("INSERT INTO world_chat(world_id,player_id,username,message) VALUES(1,4,'Social4',?)",['Older visible '.$i]);
    for($i=1;$i<=55;$i++)$db->execute("INSERT INTO world_chat(world_id,player_id,username,message) VALUES(1,3,'Social3',?)",['History '.$i]);
    $page1=Social::history(1,1,'world');$page2=Social::history(1,1,'world',null,$page1['next_before_id']);
    checkSocial(count($page1['messages'])===50&&$page1['has_more']&&count($page2['messages'])===10&&!$page2['has_more']&&!array_intersect(array_column($page1['messages'],'id'),array_column($page2['messages'],'id')),'Older history paginates chronologically without gaps or duplicates');
    socialAction(1,'block.add',['player_id'=>3]);checkSocial(count(Community::chatState(1,1)['world_chat'])===5&&count(Social::history(1,1,'world')['messages'])===5,'Blocked recent messages cannot hide older visible chat messages');socialAction(1,'block.remove',['player_id'=>3]);
    socialAction(1,'preferences.save',['channels'=>['world'=>'mentions']]);checkSocial(Community::chatState(1,1)['channel_unread']['world']===0,'Mention-only notifications exclude unrelated chat');
    chatAction(3,'world','Hello @Social1',['mention_ids'=>[1]]);checkSocial(Community::chatState(1,1)['channel_unread']['world']===1,'Mention-only notifications include validated mentions');
    socialAction(1,'preferences.save',['channels'=>['world'=>'off']]);checkSocial(Community::chatState(1,1)['channel_unread']['world']===0,'Channel notification preferences persist on the server');
    socialAction(1,'preferences.save',['channels'=>['private'=>'mentions']]);checkSocial(Community::chatState(1,1)['channel_unread']['private']===1,'Private mention preferences count only unread validated mentions across conversations');

    socialAction(2,'block.add',['player_id'=>1]);checkSocial(Social::state(1,1)['friends']===[]&&Social::state(2,1)['friends']===[],'Blocking removes friendship for both players');
    checkSocial(Community::chatState(2,1)['alliance_chat']===[]&&Social::history(2,1,'alliance')['messages']===[],'Blocked authors disappear from live and paginated alliance chat');
    denySocial(fn()=>chatAction(1,'private','Blocked recipient',['player_id'=>2]),'Blocked senders cannot deliver private chat');
    denySocial(fn()=>chatAction(2,'private','Blocker cannot send',['player_id'=>1]),'Blocking also prevents reverse private delivery');
    denySocial(fn()=>socialAction(1,'friend.request',['player_id'=>2]),'Blocked senders cannot create friend requests');
    denySocial(fn()=>socialAction(1,'chat.react',['channel'=>'private','player_id'=>2,'message_id'=>$reply['id'],'reaction'=>'cheer','active'=>true]),'Blocked senders cannot send reactions through private history');
    checkSocial(Social::history(2,1,'private',1)['messages']!==[],'Blocked private conversation remains readable as report evidence');
    $report=socialAction(2,'report.submit',['channel'=>'private','player_id'=>1,'message_id'=>$sent['id'],'reason'=>'harassment','details'=>'Fixture report']);
    $db->execute('UPDATE private_chat_messages SET message=? WHERE id=?',['Edited after report',$sent['id']]);$reports=Social::moderationState(1)['reports'];
    checkSocial($reports[0]['snapshot']['message']==='Friends can write','Moderation preserves the exact reported message snapshot');
    denySocial(fn()=>socialAction(2,'report.submit',['channel'=>'alliance','player_id'=>3,'message_id'=>$alliance['id'],'reason'=>'spam']),'Reported player cannot be forged independently of the message author');
    Social::moderate(1,['action'=>'report.update','report_id'=>$report['id'],'status'=>'resolved','admin_note'=>'Reviewed fixture']);checkSocial(Social::moderationState(1)['reports'][0]['status']==='resolved','Moderators can resolve reports with an internal note');
    denySocial(fn()=>Social::moderate(99,['action'=>'chat.ban','world_id'=>1,'player_id'=>1,'minutes'=>60,'reason'=>'Invalid actor']),'Unprivileged callers cannot moderate chat');
    Social::moderate(1,['action'=>'chat.ban','world_id'=>1,'player_id'=>1,'minutes'=>60,'reason'=>'Fixture ban']);
    denySocial(fn()=>chatAction(1,'world','Banned sender'),'Active chat bans stop world messages');
    denySocial(fn()=>chatAction(1,'alliance','Banned sender'),'Active chat bans stop alliance messages');
    denySocial(fn()=>Community::action(1,['action'=>'mail.send','player_id'=>4,'subject'=>'Banned','body'=>'Sender','request_id'=>'social_banned_mail_0001'],1),'Active chat bans also stop private letters');
    checkSocial(Social::state(1,1)['chat_ban']!==null&&count(Social::moderationState(1)['bans'])===1,'Active ban is visible to its player and moderation');
    Social::moderate(1,['action'=>'chat.unban','world_id'=>1,'player_id'=>1,'reason'=>'Fixture release']);socialAction(2,'block.remove',['player_id'=>1]);socialAction(2,'preferences.save',['private_messages'=>'alliance']);chatAction(1,'private','Alliance messages allowed',['player_id'=>2]);
    denySocial(fn()=>chatAction(3,'private','Outsider blocked',['player_id'=>2]),'Alliance-only privacy rejects outsiders');
    socialAction(2,'preferences.save',['private_messages'=>'nobody']);denySocial(fn()=>chatAction(1,'private','No one allowed',['player_id'=>2]),'Nobody privacy rejects even alliance members');
    socialAction(2,'preferences.save',['private_messages'=>'everyone']);socialAction(1,'friend.request',['player_id'=>4]);socialAction(4,'friend.decline',['player_id'=>1]);checkSocial(Social::state(1,1)['requests']['outgoing']===[],'Declined friend requests are removed');

    $sessionToken=bin2hex(random_bytes(32));$csrfToken=bin2hex(random_bytes(32));$db->execute("INSERT INTO sessions(player_id,token,csrf_token,ip_address,user_agent,expires_at,active_world_id) VALUES(1,?,?,'127.0.0.1','Social test',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),1)",[$sessionToken,$csrfToken]);
    $url=$fixture->serve('$path=parse_url($_SERVER["REQUEST_URI"],PHP_URL_PATH); if($path==="/state")\\Conquer\\Api\\Handlers\\SocialHandler::state([]);elseif($path==="/history")\\Conquer\\Api\\Handlers\\SocialHandler::history([]);elseif($path==="/action")\\Conquer\\Api\\Handlers\\SocialHandler::action([]);else http_response_code(404);');
    checkSocial(socialHttp('/state?world_id=1',null,false)[0]===401,'HTTP social state requires authentication');
    checkSocial(socialHttp('/action',['action'=>'block.add','player_id'=>2,'request_id'=>'social_http_block_0001'],true,false)[0]===403,'HTTP mutations require a valid CSRF token');
    checkSocial(socialHttp('/state?world_id=2')[0]===409,'HTTP stale world request is rejected');
    checkSocial(socialHttp('/history?world_id=1&channel=private&player_id=2')[0]===200,'Authenticated HTTP private history is available');
    checkSocial(socialHttp('/history?channel[]=private')[0]===422,'Malformed channel values fail as validation errors');
    checkSocial(socialHttp('/action',['action'=>'conversation.open','player_id'=>3,'world_id'=>1,'request_id'=>'social_http_open_0001'])[0]===200,'Authenticated HTTP actions use durable receipts');
}finally{$fixture->close();}
