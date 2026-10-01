<?php
declare(strict_types=1);
/** Recruitment and planning regression tests run solely in an isolated local database. */
if(PHP_SAPI!=='cli')exit(1);
date_default_timezone_set('UTC');define('ROOT_DIR',dirname(__DIR__));require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
use Conquer\Db\Connection;
use Conquer\Game\Community\AllianceCommunityService as A;
use Conquer\Game\Kingdom\KingdomService as K;
use Conquer\Game\World\WorldContext as W;
if(($argv[1]??'')==='--worker'){
    $dir=realpath($argv[2]??'');if(!$dir||dirname($dir)!==realpath(sys_get_temp_dir())||!preg_match('/^conquer_feature_test_[a-f0-9]{12}$/D',basename($dir)))exit(2);
    Connection::init($dir);W::bind(1);try{echo json_encode(['ok'=>true,'result'=>A::action((int)$argv[3],json_decode($argv[4],true,16,JSON_THROW_ON_ERROR))]);}catch(DomainException $e){echo json_encode(['ok'=>false]);}exit;
}
require __DIR__.'/Support/FeatureDatabase.php';$fixture=new \ConquerTests\FeatureDatabase();$db=Connection::getInstance();$serial=0;
function checkAlliance(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
function act(int $player,string $action,array $body=[],?string $request=null):array{global $serial;return A::action($player,['action'=>$action,'request_id'=>$request??'alliance_test_'.str_pad((string)++$serial,8,'0',STR_PAD_LEFT),'world_id'=>W::id()]+$body)['result'];}
function denyAlliance(callable $fn,string $label):void{try{$fn();}catch(DomainException){checkAlliance(true,$label);return;}throw new RuntimeException('Unexpected acceptance: '.$label);}
try{
    \Conquer\Db\MigrationSql::apply($db->getPdo(),(string)file_get_contents(ROOT_DIR.'/migrations/0122_alliance_community.sql'));
    \Conquer\Db\MigrationSql::apply($db->getPdo(),(string)file_get_contents(ROOT_DIR.'/migrations/0122_alliance_community.sql'));
    $db->execute("UPDATE worlds SET status='running' WHERE id=1");$db->execute("INSERT INTO worlds(id,name,slug,status)VALUES(2,'Other world','alliance-other','running')");
    for($id=1;$id<=10;$id++){
        $world=$id===10?2:1;$db->execute('INSERT INTO players(id,username,email,password_hash)VALUES(?,?,?,?)',[$id,'AllianceFixture'.$id,'alliance'.$id.'@test.invalid','unused']);
        $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y)VALUES(?,?,?,'Fixture',?,90)",[$id,$id,$world,50+$id*3]);
        $db->execute("INSERT INTO city_buildings(city_id,building_code,level)VALUES(?,'castle',1)",[$id]);
    }
    $db->execute("INSERT INTO alliances(id,world_id,name,tag,leader_id,max_members)VALUES(1,1,'Test alliance','TST',1,30),(2,1,'Second alliance','SEC',4,30),(3,2,'Other world','OTH',10,30)");
    foreach([[1,1,1,'leader'],[1,2,1,'officer'],[1,3,1,'member'],[2,4,1,'leader'],[3,10,2,'leader']]as$row)$db->execute('INSERT INTO alliance_members(alliance_id,player_id,world_id,role)VALUES(?,?,?,?)',$row);
    W::bind(1);$recruit=['language'=>'en','style'=>'pve','activity'=>'evening','timezone'=>'Europe/Luxembourg','mode'=>'application','minimum_power'=>0];
    denyAlliance(fn()=>act(2,'recruitment.save',$recruit),'officers cannot change recruitment');
    act(1,'recruitment.save',$recruit);$s=A::state(1,1,['language'=>'en','style'=>'pve','activity'=>'evening','mode'=>'application','free_slots'=>'1']);checkAlliance(count($s['search']['items'])===1&&$s['search']['items'][0]['name']==='Test alliance','search combines language, style, activity, admission and capacity filters');
    denyAlliance(fn()=>act(5,'alliance.join',['alliance_id'=>1]),'direct new join cannot bypass application mode');
    $oldJoin=new ReflectionMethod(K::class,'joinAlliance');
    denyAlliance(fn()=>$db->transaction(fn()=>$oldJoin->invoke(null,5,['alliance_id'=>1])),'original kingdom join cannot bypass application mode');
    denyAlliance(fn()=>act(5,'application.submit',['alliance_id'=>3,'message'=>'wrong world']),'cross-world applications rejected');
    $ap=act(5,'application.submit',['alliance_id'=>1,'message'=>'Hello <script>test</script>'],'application_receipt_0001');$retry=act(5,'application.submit',['alliance_id'=>1,'message'=>'Hello <script>test</script>'],'application_receipt_0001');checkAlliance($ap===$retry&&(int)$db->query('SELECT COUNT(*) FROM alliance_applications')->fetchColumn()===1,'application retry returns original receipt without duplicates');
    denyAlliance(fn()=>act(5,'application.submit',['alliance_id'=>1,'message'=>'changed'],'application_receipt_0001'),'changed replay payload rejected');
    checkAlliance(count(A::state(1,1)['pending_applications'])===1&&A::state(3,1)['pending_applications']===[]&&A::state(4,1)['pending_applications']===[],'application messages are visible only to their author and alliance leadership');
    denyAlliance(fn()=>act(2,'application.accept',['application_id'=>$ap['application_id']]),'officers cannot accept applicants');
    act(1,'application.accept',['application_id'=>$ap['application_id']]);checkAlliance((int)$db->query('SELECT alliance_id FROM alliance_members WHERE player_id=5')->fetchColumn()===1,'accepted application creates real membership');
    denyAlliance(fn()=>act(1,'application.accept',['application_id'=>$ap['application_id']]),'accepted application cannot be processed again');
    act(4,'recruitment.save',array_replace($recruit,['mode'=>'open','minimum_power'=>999999]));
    denyAlliance(fn()=>act(6,'alliance.join',['alliance_id'=>2]),'minimum power is enforced for open admission');
    denyAlliance(fn()=>$db->transaction(fn()=>$oldJoin->invoke(null,6,['alliance_id'=>2])),'original kingdom join also enforces minimum power');
    checkAlliance(!array_filter(A::state(6,1,['eligible'=>'1'])['search']['items'],fn($a)=>(int)$a['id']===2),'eligible search omits alliances above player power');
    act(4,'recruitment.save',array_replace($recruit,['mode'=>'open']));act(6,'alliance.join',['alliance_id'=>2]);checkAlliance((int)$db->query('SELECT alliance_id FROM alliance_members WHERE player_id=6')->fetchColumn()===2,'eligible player can join open alliance');
    $notice=act(2,'notice.create',['kind'=>'goal','title'=>'Shared <goal>','body'=>'<svg onload=bad()>','pinned'=>true]);
    denyAlliance(fn()=>act(3,'notice.complete',['notice_id'=>$notice['notice_id']]),'ordinary members cannot complete goals');
    denyAlliance(fn()=>act(4,'notice.archive',['notice_id'=>$notice['notice_id']]),'outsider leader cannot archive another alliance notice');
    act(2,'notice.complete',['notice_id'=>$notice['notice_id']]);checkAlliance((int)A::state(3,1)['notices'][0]['completed']===1,'officers can complete a shared goal');
    $eventBody=['title'=>'Boss hunt','description'=>'Saturday team','starts_at'=>time()+3600,'duration_minutes'=>60,'timezone'=>'Europe/Luxembourg'];
    denyAlliance(fn()=>act(3,'event.create',$eventBody),'ordinary members cannot plan events');
    denyAlliance(fn()=>act(2,'event.create',array_replace($eventBody,['starts_at'=>time()-10])),'past event times rejected');
    $event=act(2,'event.create',$eventBody,'event_create_receipt_0001');act(2,'event.create',$eventBody,'event_create_receipt_0001');checkAlliance((int)$db->query('SELECT COUNT(*) FROM alliance_calendar_events')->fetchColumn()===1,'event creation is replay safe');
    act(3,'event.rsvp',['event_id'=>$event['event_id'],'response'=>'yes']);act(3,'event.rsvp',['event_id'=>$event['event_id'],'response'=>'maybe']);$e=A::state(3,1)['events'][0];checkAlliance(count($e['rsvps'])===1&&$e['my_response']==='maybe'&&$e['reminder']===true,'RSVP changes one response and creates upcoming overview reminder');
    denyAlliance(fn()=>act(4,'event.rsvp',['event_id'=>$event['event_id'],'response'=>'yes']),'outsiders cannot RSVP');
    $poll=act(2,'poll.create',['question'=>'Which target?','options'=>['Forest','Ice'],'closes_at'=>time()+3600]);
    denyAlliance(fn()=>act(3,'poll.vote',['poll_id'=>$poll['poll_id'],'choice'=>2]),'vote validates option range');
    denyAlliance(fn()=>act(4,'poll.vote',['poll_id'=>$poll['poll_id'],'choice'=>0]),'outsiders cannot vote');
    act(3,'poll.vote',['poll_id'=>$poll['poll_id'],'choice'=>0]);act(3,'poll.vote',['poll_id'=>$poll['poll_id'],'choice'=>1]);$p=A::state(3,1)['polls'][0];checkAlliance($p['counts']===[0,1]&&(int)$p['my_choice']===1,'vote replacement preserves one vote per member');
    $times=[time()+86400,time()+172800,time()+259200];$timePoll=act(2,'poll.create',['kind'=>'time','question'=>'When?','options'=>$times,'closes_at'=>time()+3600]);checkAlliance(A::state(3,1)['polls'][0]['options']===$times,'time poll preserves UTC timestamps for local display');
    denyAlliance(fn()=>act(2,'poll.create',['kind'=>'time','question'=>'When?','options'=>$times,'closes_at'=>time()+90000]),'time poll closes before candidate times');
    act(2,'poll.close',['poll_id'=>$poll['poll_id']]);denyAlliance(fn()=>act(3,'poll.vote',['poll_id'=>$poll['poll_id'],'choice'=>0]),'closed polls reject voting');
    $db->execute('DELETE FROM alliance_members WHERE player_id=3');checkAlliance(A::state(2,1)['polls'][1]['counts']===[0,0]&&A::state(2,1)['events'][0]['rsvps']===[],'departed members are excluded from polls and event attendee lists');
    denyAlliance(fn()=>act(3,'event.rsvp',['event_id'=>$event['event_id'],'response'=>'yes']),'departed members lose planning access immediately');
    act(2,'event.cancel',['event_id'=>$event['event_id']]);denyAlliance(fn()=>act(5,'event.rsvp',['event_id'=>$event['event_id'],'response'=>'yes']),'cancelled event rejects RSVP');
    for($i=0;$i<52;$i++)$db->execute("INSERT INTO alliance_calendar_events(alliance_id,world_id,creator_id,title,starts_at,cancelled_at)VALUES(1,1,1,'Old cancelled event',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),UTC_TIMESTAMP())");
    $upcoming=act(2,'event.create',array_replace($eventBody,['title'=>'Later active event','starts_at'=>time()+86400]));
    $events=A::state(2,1)['events'];checkAlliance(count($events)===6&&count(array_filter($events,fn($e)=>(int)$e['id']===$upcoming['event_id']))===1,'cancelled history cannot hide later active events');
    checkAlliance(A::state(4,1)['notices']===[]&&A::state(4,1)['events']===[]&&A::state(4,1)['polls']===[],'alliance planning state is isolated');
    denyAlliance(fn()=>A::state(1,2),'state rejects world mismatch');
    $ap7=act(7,'application.submit',['alliance_id'=>1]);$ap8=act(8,'application.submit',['alliance_id'=>1]);$db->execute('UPDATE alliances SET max_members=4 WHERE id=1');$db->execute("UPDATE alliance_members SET role='vice_leader' WHERE player_id=2");
    $directory=(new ReflectionProperty($fixture,'directory'))->getValue($fixture);$workers=[];
    foreach([[1,$ap7['application_id']],[2,$ap8['application_id']]]as[$pid,$apid]){$payload=['action'=>'application.accept','application_id'=>$apid,'request_id'=>'race_receipt_'.$pid.'_000000'];$process=proc_open([PHP_BINARY,__FILE__,'--worker',$directory,(string)$pid,json_encode($payload)],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,ROOT_DIR,null,['bypass_shell'=>true]);fclose($pipes[0]);$workers[]=[$process,$pipes];}
    $wins=0;foreach($workers as[$process,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($process);checkAlliance($code===0&&is_array(json_decode($out,true)),'concurrent worker completed: '.$err);$wins+=(int)json_decode($out,true)['ok'];}
    checkAlliance($wins===1&&(int)$db->query('SELECT COUNT(*) FROM alliance_members WHERE alliance_id=1')->fetchColumn()===4,'concurrent application approvals cannot exceed alliance capacity');
    $db->execute("UPDATE worlds SET status='closed' WHERE id=1");denyAlliance(fn()=>act(1,'notice.archive',['notice_id'=>$notice['notice_id']]),'closed worlds reject changes');
    echo "PASS alliance community service\n";
}finally{$fixture->close();}
