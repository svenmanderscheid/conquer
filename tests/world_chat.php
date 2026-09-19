<?php
declare(strict_types=1);
/** Chat snapshots must never disclose another world or alliance. Uses synthetic data only. */
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require ROOT_DIR.'/tests/Support/FeatureDatabase.php';
use Conquer\Game\Community\CommunityService;
use Conquer\Game\World\WorldContext;
function verifyChat(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);echo "PASS $message\n";}
$fixture=new \ConquerTests\FeatureDatabase();
try{
 $db=\Conquer\Db\Connection::getInstance();
 $db->execute("INSERT INTO worlds(id,name,slug,status) VALUES(2,'Chat Test','chat-test','running')");
 for($id=1;$id<=4;$id++){
  $db->execute('INSERT INTO players(id,username,email,password_hash) VALUES(?,?,?,?)',[$id,'Chat'.$id,'chat'.$id.'@tests.invalid','unused']);
  $db->execute("INSERT INTO cities(player_id,world_id,name,coord_x,coord_y) VALUES(?,?,'Chat test',?,40)",[$id,$id===3?2:1,40+$id*5]);
 }
 $db->execute("INSERT INTO alliances(id,world_id,name,tag,leader_id) VALUES(1,1,'First','ONE',1),(2,1,'Second','TWO',2),(3,2,'Remote','REM',3)");
 $db->execute("INSERT INTO alliance_members(alliance_id,player_id,world_id,role) VALUES(1,1,1,'leader'),(2,2,1,'leader'),(3,3,2,'leader')");
 foreach([[1,1],[2,2],[3,3]]as[$aid,$pid])$db->execute('INSERT INTO alliance_messages(alliance_id,player_id,username,message) VALUES(?,?,?,?)',[$aid,$pid,'Chat'.$pid,'Private '.$aid]);
 $db->execute("INSERT INTO private_chat_messages(world_id,sender_id,recipient_id,message) VALUES(1,1,2,'Secret hello'),(1,2,1,'Secret reply'),(1,1,4,'Other conversation'),(2,3,3,'Remote private')");
 for($id=1;$id<=55;$id++)$db->execute("INSERT INTO world_chat(world_id,player_id,username,message) VALUES(1,1,'Chat1',?)",['Message '.$id]);
 $db->execute("INSERT INTO world_chat(world_id,player_id,username,message) VALUES(2,3,'Chat3','Remote world')");
 WorldContext::bind(1);
 $state=CommunityService::chatState(1,1);
 verifyChat(count($state['world_chat'])===50 && $state['world_chat'][0]['message']==='Message 6' && $state['world_chat'][49]['message']==='Message 55','Only the latest 50 messages, in chronological order');
 verifyChat($state['world_chat'][0]['avatar']==='knight','Chat messages include the sender portrait');
 verifyChat($state['world_chat'][0]['alliance_tag']==='ONE','World chat resolves the current alliance tag for alliance members');
 verifyChat(count($state['alliance_chat'])===1 && $state['alliance_chat'][0]['message']==='Private 1','Only the current alliance is visible');
 verifyChat(!array_key_exists('players',$state) && !array_key_exists('mail',$state),'Lightweight snapshot excludes unrelated player and mail data');
 $guest=CommunityService::chatState(4,1);verifyChat($guest['alliance']===null && $guest['alliance_chat']===[],'Players without an alliance receive no alliance chat');
 $private=CommunityService::chatState(1,1,2);verifyChat($private['private_player']['username']==='Chat2'&&array_column($private['private_chat'],'message')===['Secret hello','Secret reply'],'Private chat exposes only the selected same-world conversation');
 verifyChat(!in_array('Other conversation',array_column($private['private_chat'],'message'),true),'Other private conversations stay isolated');
 try{CommunityService::chatState(1,1,1);throw new RuntimeException('Self chat accepted');}catch(DomainException){verifyChat(true,'Players cannot open a private chat with themselves');}
 try{CommunityService::chatState(1,1,3);throw new RuntimeException('Cross-world private chat accepted');}catch(DomainException $e){verifyChat($e->getCode()===403,'Private chat partners must belong to the current world');}
 $db->execute('UPDATE world_chat SET created_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 5 SECOND)');
 $db->execute('UPDATE alliance_messages SET sent_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 5 SECOND)');
 $db->execute('UPDATE private_chat_messages SET created_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 5 SECOND)');
 CommunityService::action(1,['action'=>'chat.send','channel'=>'private','player_id'=>2,'message'=>'Fresh whisper','world_id'=>1,'expected_world_id'=>1,'request_id'=>'private_chat_test_0001'],1);
 $sent=CommunityService::chatState(2,1,1);verifyChat(end($sent['private_chat'])['message']==='Fresh whisper','A private chat message is delivered to the selected player');
 $cityId=(int)$db->query('SELECT id FROM cities WHERE player_id=1 AND world_id=1')->fetchColumn();
 $db->execute("INSERT INTO battle_reports(world_id,attacker_id,attacker_city_id,target_type,target_x,target_y,outcome,data_json)VALUES(1,1,?,3,55,40,'attacker_wins',?)",[$cityId,json_encode(['monster_name'=>'Chat-Monster','loot'=>['stone'=>1000]],JSON_THROW_ON_ERROR)]);
 $reportId=$db->lastInsertId();$db->execute('UPDATE private_chat_messages SET created_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 5 SECOND)');
 $share=CommunityService::action(1,['action'=>'chat.send','channel'=>'private','player_id'=>2,'message'=>'Geteilter Kampfbericht','report_id'=>$reportId,'world_id'=>1,'expected_world_id'=>1,'request_id'=>'private_report_share_0001'],1);
 $shareId=(int)$share['result']['shared_report_id'];$sharedState=CommunityService::chatState(2,1,1);
 verifyChat((int)end($sharedState['private_chat'])['shared_report_id']===$shareId,'Private chat exposes a structured shared-report reference');
 verifyChat((int)CommunityService::sharedReport(2,$shareId,1)['id']===$reportId,'Private recipient can open the complete shared report');
 try{CommunityService::sharedReport(4,$shareId,1);throw new RuntimeException('Unrelated player opened private report');}catch(DomainException $e){verifyChat($e->getCode()===403,'Unrelated players cannot open a private shared report');}
 $db->execute('UPDATE private_chat_messages SET created_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 5 SECOND)');
 $worldShare=CommunityService::action(1,['action'=>'chat.send','channel'=>'world','message'=>'Öffentlicher Kampfbericht','report_id'=>$reportId,'world_id'=>1,'expected_world_id'=>1,'request_id'=>'world_report_share_0001'],1);
 verifyChat((int)CommunityService::sharedReport(4,(int)$worldShare['result']['shared_report_id'],1)['id']===$reportId,'Players in the same world can open a world-chat report');
 try{CommunityService::chatState(3,1);throw new RuntimeException('Missing city accepted');}catch(DomainException $e){verifyChat($e->getCode()===403,'Players need a city in the requested world');}
 try{CommunityService::chatState(1,2);throw new RuntimeException('World mismatch accepted');}catch(DomainException $e){verifyChat($e->getCode()===409,'Stale world requests are rejected');}
 WorldContext::bind(2);$remote=CommunityService::chatState(3,2);verifyChat(count($remote['world_chat'])===1 && $remote['world_chat'][0]['message']==='Remote world' && $remote['alliance_chat'][0]['message']==='Private 3','World and alliance stay isolated after a world switch');
}finally{$fixture->close();}
