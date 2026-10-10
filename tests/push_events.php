<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';date_default_timezone_set('UTC');
\Conquer\Logger::init(sys_get_temp_dir().'/uok-push-events-fixture.log','ERROR');
use Conquer\Db\{Connection,MigrationSql};
use Conquer\Game\Notification\{PushService,PushWorker,NotificationEvents};
use Conquer\Game\March\{MarchDispatcher,GatherService};
use Conquer\Game\Rally\{RallyService,MonsterRally};
use Conquer\Game\Community\CommunityService;
use Conquer\Game\World\WorldContext;
function eventCheck(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
$fixture=new \ConquerTests\FeatureDatabase();
try{
 $db=Connection::getInstance();WorldContext::bind(1);\Conquer\Game\World\LandProgressService::ensureWorld(1,true);
 MigrationSql::apply($db->getPdo(),file_get_contents(ROOT_DIR.'/migrations/0145_web_push.sql'));
 foreach([1,2,3]as$player){
  $db->execute("INSERT INTO players(id,username,email,password_hash,beginner_shield_until)VALUES(?,?,?,'unused',NULL)",[$player,'Player'.$player,'event'.$player.'@tests.invalid']);
  $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,food,lumber,stone,gold,castle_level)VALUES(?,?,1,'Event fixture',?,50,2000000,2000000,2000000,2000000,5)",[$player,$player,40+5*$player]);
  foreach(\Conquer\Game\City\CityState::BUILDING_CODES as$code)$db->execute('INSERT INTO city_buildings(city_id,building_code,level)VALUES(?,?,5)',[$player,$code]);
  $db->execute('INSERT INTO city_troops(city_id,troop_code,count)VALUES(?,50100101,20000)',[$player]);
  $db->execute('INSERT INTO kingdom_profiles(player_id,display_name)VALUES(?,?)',[$player,['','Ékki 王','Target Two','Ally Three'][$player]]);
  $db->execute("INSERT INTO sessions(player_id,token,csrf_token,ip_address,expires_at)VALUES(?,?,?,'127.0.0.1',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR))",[$player,bin2hex(random_bytes(32)),bin2hex(random_bytes(32))]);
  PushService::subscribe(['id'=>$db->lastInsertId(),'player_id'=>$player],['platform'=>'android','token'=>str_repeat('events-token-'.$player.'-',3),'locale'=>'en']);
 }
 $db->execute("INSERT INTO alliances(id,world_id,name,tag,leader_id)VALUES(1,1,'Event allies','EVT',1)");
 $db->execute("INSERT INTO alliance_members(alliance_id,player_id,world_id,role)VALUES(1,1,1,'leader'),(1,3,1,'member')");
 $before=(int)$db->query('SELECT COUNT(*) FROM notifications')->fetchColumn();
 try{MarchDispatcher::dispatchPlayerAttack(1,1,45,50,55,50,[50100101=>10]);throw new RuntimeException('Friendly attack accepted');}catch(RuntimeException $e){if($e instanceof PDOException)throw $e;}
 eventCheck((int)$db->query('SELECT COUNT(*) FROM notifications')->fetchColumn()===$before,'rejected attack produces no notification');
 MarchDispatcher::dispatchPlayerAttack(1,1,45,50,50,50,[50100101=>10]);
 MarchDispatcher::dispatchScout(1,1,45,50,50,50);
 $rally=RallyService::start(1,1,2,50,50,[50100101=>10],1,'PRIVATE RALLY MESSAGE');
 $db->execute("UPDATE marches SET state='complete'");
 $definition=\Conquer\Game\Map\MonsterData::get(20202401);
 $db->execute("INSERT INTO field_monsters(world_id,monster_code,coord_x,coord_y,hp_current,monster_type,expires_at)VALUES(1,20202401,60,60,1000000,'rally',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY))");
 MonsterRally::start(1,1,60,60,[50100101=>10],1,'PRIVATE MONSTER RALLY MESSAGE');
 $chat=['action'=>'chat.send','channel'=>'private','player_id'=>2,'message'=>'PRIVATE CHAT BODY','request_id'=>'push_events_chat_replay_001'];
 $first=CommunityService::action(1,$chat);$repeat=CommunityService::action(1,$chat);
 eventCheck($first['result']['id']===$repeat['result']['id']&&(int)$db->query("SELECT COUNT(*)FROM notifications WHERE type='private_message'")->fetchColumn()===1,'replayed private chat creates exactly one recipient notification');
 $db->execute("INSERT INTO building_queue(city_id,building_code,level_to,started_at,finishes_at)VALUES(1,'farm',6,UTC_TIMESTAMP(),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND))");
 $db->execute("INSERT INTO troop_queue(city_id,troop_code,count,barrack_slot,started_at,finishes_at)VALUES(1,50100101,7,1,UTC_TIMESTAMP(),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND))");
 $db->execute("INSERT INTO research_queue(player_id,world_id,research_code,level_to,finishes_at)VALUES(1,1,'food_production',1,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND))");
 PushWorker::settleDue();
 $db->execute("UPDATE rallies SET status='cancelled'");
 $db->execute("INSERT INTO field_objects(world_id,coord_x,coord_y,object_type,level,resource_amount,resource_max,spawned_at,expires_at)VALUES(1,65,60,1,1,100000,100000,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY))");
 $farm=GatherService::dispatch(1,1,65,60,100,[50100101=>100])['march_id'];
 $db->execute('UPDATE marches SET departure_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 20 SECOND),arrival_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 10 SECOND) WHERE id=?',[$farm]);
 PushWorker::settleFarmMarches();
 eventCheck($db->query('SELECT state FROM marches WHERE id=?',[$farm])->fetchColumn()==='arrived','offline push worker starts the canonical gathering lifecycle');
 $db->execute('UPDATE marches SET gathering_finishes_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND)WHERE id=?',[$farm]);PushWorker::settleFarmMarches();
 $db->execute('UPDATE marches SET return_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND)WHERE id=?',[$farm]);PushWorker::settleFarmMarches();PushWorker::settleFarmMarches();
 eventCheck((int)$db->query("SELECT COUNT(*)FROM notifications WHERE type='farm_returned'")->fetchColumn()===1&&$db->query('SELECT state FROM marches WHERE id=?',[$farm])->fetchColumn()==='complete','farm return notifies only after actual homecoming and never twice');
 $notices=$db->query('SELECT player_id,type,data_json FROM notifications ORDER BY id')->fetchAll();
 $expected=['train_complete','build_complete','research_complete','farm_returned','scout_incoming','rally_incoming','battle_incoming','alliance_rally_player','alliance_rally_monster','private_message'];
 $types=array_column($notices,'type');sort($types);$sorted=$expected;sort($sorted);
 eventCheck($types===$sorted,'all ten requested notifications are produced by real successful game actions');
 eventCheck(!array_intersect(array_column(\Conquer\Game\Notification\NotificationService::getPending(2),'type'),['private_message','scout_incoming','battle_incoming','rally_incoming']),'push-only alerts do not duplicate chat or mail inside the game');
 foreach($notices as$n){$recipient=match($n['type']){'scout_incoming','rally_incoming','battle_incoming','private_message'=>2,'alliance_rally_player','alliance_rally_monster'=>3,default=>1};eventCheck((int)$n['player_id']===$recipient,'recipient isolated: '.$n['type']);}
 $db->execute("UPDATE kingdom_profiles SET display_name='Renamed later' WHERE player_id=1");
 eventCheck(PushWorker::capture()===10&&PushWorker::capture()===0,'ten events captured and deduplicated for the correct devices');
 $payloads=[];$stats=PushWorker::dispatch(static function($subscription,$payload)use(&$payloads){$payloads[]=$payload;return 'sent';});
 eventCheck($stats['sent']===10&&count(array_unique(array_column($payloads,'body')))===10,'all ten types deliver distinct texts');
 $copy=implode(' ',array_column($payloads,'body'));
 eventCheck(str_contains($copy,'Ékki 王')&&str_contains($copy,'Target Two')&&str_contains($copy,$definition['name'])&&!str_contains($copy,'Renamed later'),'player and monster names are correct event-time snapshots');
 eventCheck(!str_contains(json_encode($payloads),'PRIVATE')&&!str_contains(json_encode($payloads),'actor_id')&&!str_contains(json_encode($payloads),'message_id'),'message contents, rally comments and internal identifiers never leave the server');
 $bounded=PushWorker::payload(['event_type'=>'private_message','event_data'=>json_encode(['actor_name'=>"Ékki\n\u{202E}".str_repeat('x',200),'message'=>'PRIVATE']),'category'=>'messages','locale'=>'en','delivery_tag'=>str_repeat('b',32)]);
 eventCheck(!str_contains($bounded['body'],"\n")&&!str_contains($bounded['body'],"\u{202E}")&&mb_strlen($bounded['body'])<140,'display names are bounded and cannot add control characters to a notification');
 $fallback=PushWorker::payload(['event_type'=>'battle_incoming','event_data'=>'true','category'=>'security','locale'=>'en','delivery_tag'=>str_repeat('b',32)]);
 eventCheck($fallback['body']==='A player is attacking you.','malformed legacy event data uses localized fallback names');
 foreach(['en','de','fr']as$locale){foreach($notices as$n){$payload=PushWorker::payload(['event_type'=>$n['type'],'event_data'=>$n['data_json'],'category'=>PushService::category($n['type']),'locale'=>$locale,'delivery_tag'=>str_repeat('a',32)]);eventCheck(!str_contains($payload['body'],'push.')&&!preg_match('/\{(?:player|target|monster)\}/',$payload['body']),'localized event with resolved names: '.$locale.'/'.$n['type']);}}
 $db->execute("INSERT INTO community_preferences(world_id,player_id,private_notifications,alliance_notifications)VALUES(1,2,'off','all'),(1,3,'all','off')");
 NotificationEvents::rallyStarted(1,1,$rally,1,2);
 $db->execute('UPDATE private_chat_messages SET created_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 5 SECOND)');
 $muted=$chat;$muted['request_id']='push_events_chat_muted_002';CommunityService::action(1,$muted);
 eventCheck((int)$db->query("SELECT COUNT(*)FROM notifications WHERE type='private_message'")->fetchColumn()===1&&(int)$db->query("SELECT COUNT(*)FROM notifications WHERE type='alliance_rally_player'")->fetchColumn()===1,'muted private and alliance channels do not create a later backfill');
 PushWorker::capture();PushWorker::dispatch(static fn()=>'sent');
 $db->execute("UPDATE community_preferences SET alliance_notifications='all' WHERE player_id=3");
 NotificationEvents::rallyStarted(1,1,$rally,1,2);eventCheck(PushWorker::capture()===2,'new rally queues only its defender and current alliance member');
 $db->execute('DELETE FROM alliance_members WHERE player_id=3');$calls=0;
 $stats=PushWorker::dispatch(static function()use(&$calls){$calls++;return 'sent';});
 eventCheck($calls===1&&$stats['failed']===1,'leaving an alliance suppresses its already queued rally before provider send');
 $db->execute("UPDATE community_preferences SET private_notifications='all' WHERE player_id=2");
 $db->execute('UPDATE private_chat_messages SET created_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 5 SECOND)');
 $blocked=$chat;$blocked['request_id']='push_events_chat_blocked_003';CommunityService::action(1,$blocked);eventCheck(PushWorker::capture()===1,'new private message queues only its recipient');
 $db->execute('INSERT INTO community_blocks(world_id,player_id,blocked_id)VALUES(1,2,1)');$calls=0;
 $stats=PushWorker::dispatch(static function()use(&$calls){$calls++;return 'sent';});eventCheck($calls===0&&$stats['failed']===1,'blocking the sender suppresses already queued private notification');
 $db->execute('DELETE FROM community_blocks');$db->execute('UPDATE private_chat_messages SET created_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 5 SECOND)');
 $read=$chat;$read['request_id']='push_events_chat_read_004';$result=CommunityService::action(1,$read);eventCheck(PushWorker::capture()===1,'unread private message is eligible');
 $db->execute("INSERT INTO community_read_cursors(world_id,player_id,channel,scope_id,message_id)VALUES(1,2,'private',1,?)",[$result['result']['id']]);$calls=0;
 $stats=PushWorker::dispatch(static function()use(&$calls){$calls++;return 'sent';});eventCheck($calls===0&&$stats['failed']===1,'reading a private message before send suppresses its queued notification');
 echo "ALL NAMED PUSH EVENT CHECKS PASSED\n";
}finally{$fixture->close();}
