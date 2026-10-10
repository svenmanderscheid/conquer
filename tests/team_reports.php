<?php
declare(strict_types=1);
/** Real transactions against a disposable local schema; no production accounts or messages. */
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));define('APP_BASE','/conquer');require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require ROOT_DIR.'/tests/Support/FeatureDatabase.php';date_default_timezone_set('UTC');\Conquer\Logger::init(sys_get_temp_dir().'/conquer-team-test.log','ERROR');
use Conquer\Admin\CaseService as Cases;
use Conquer\Db\Connection;
use Conquer\Game\Support\BugReportService;
$checks=0;$fixture=null;$exit=0;
function caseCheck(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException($label);$checks++;echo 'PASS '.$label.PHP_EOL;}
function caseReject(callable $fn,string $label):void{try{$fn();}catch(DomainException|InvalidArgumentException){caseCheck(true,$label);return;}throw new RuntimeException('Accepted: '.$label);}
function casePayload(int $actor,string $source,int $id,string $action,array $extra=[]):array{return array_replace(['source'=>$source,'case_id'=>$id,'case_action'=>$action,'version'=>Cases::detail(['id'=>$actor],$source,$id)['version'],'operation_id'=>bin2hex(random_bytes(16)),'reason'=>'Isolated support review'],$extra);}
try{
 $fixture=new \ConquerTests\FeatureDatabase();$db=Connection::getInstance();\Conquer\Db\MigrationSql::apply($db->getPdo(),file_get_contents(ROOT_DIR.'/migrations/0143_team_reports.sql'));
 $db->execute("INSERT INTO worlds(id,name,slug,status) VALUES(2,'Remote support realm','remote-support','running')");
 foreach([1,2,3] as $id){$db->execute('INSERT INTO players(id,username,email,password_hash) VALUES(?,?,?,?)',[$id,'CasePlayer'.$id,'case'.$id.'@example.invalid','unused']);$db->execute("INSERT INTO cities(player_id,world_id,name,coord_x,coord_y) VALUES(?,1,'Case home',?,20)",[$id,20+$id]);}
 $hash=password_hash('Fixture-Team-123!',PASSWORD_DEFAULT);
 foreach([1=>'superadmin',2=>'support',3=>'moderator',4=>'support'] as $id=>$role)$db->execute('INSERT INTO admin_users(id,username,password_hash,role) VALUES(?,?,?,?)',[$id,'CaseTeam'.$id,$hash,$role]);
 $base=['report_type'=>'bug','category'=>'gameplay','severity'=>'normal','title'=>'Monster reward missing','description'=>'The army returned without its expected monster reward.','operation_key'=>'case_bug_submit_000001'];
 $bug=BugReportService::submit(1,1,$base,'Fixture','127.0.0.1')['id'];
 $support=BugReportService::submit(2,2,array_replace($base,['report_type'=>'support','operation_key'=>'case_support_submit_0001','title'=>'Support account question']),'Fixture','127.0.0.1')['id'];
 $db->execute("INSERT INTO community_reports(world_id,reporter_id,player_id,channel,message_id,reason,details,snapshot_json) VALUES(1,1,2,'world',123,'harassment','Please review this message',?)",[json_encode(['message'=>'Reported fixture text','private'=>'never expose this snapshot to players'])]);$content=$db->lastInsertId();
 foreach([[1,1,'CASE_EVENT',0],[2,1,'OTHER_PLAYER_SECRET',0],[1,2,'OTHER_WORLD_SECRET',0],[1,1,'OUTSIDE_TIME_SECRET',30]] as [$p,$w,$code,$minutes])$db->execute("INSERT INTO operational_events(event_uid,category,code,message,occurred_at,player_id,world_id,context_json,group_hash) VALUES(?,'error',?,'Safe diagnostic',DATE_SUB(UTC_TIMESTAMP(),INTERVAL $minutes MINUTE),?,?,'{}',?)",[bin2hex(random_bytes(16)),$code,$p,$w,str_repeat('a',64)]);
 foreach([[1,1,'case-source'],[2,1,'other-player-source'],[1,2,'other-world-source']] as [$p,$w,$ref])$db->execute("INSERT INTO reward_grant_ledger(player_id,world_id,item_code,quantity,source_type,source_reference) VALUES(?,?,10103001,2,'monster',?)",[$p,$w,$ref]);
 $evidence=Cases::detail(['id'=>2],'bug',$bug)['evidence'];
 caseCheck(count($evidence['events'])===1&&$evidence['events'][0]['code']==='CASE_EVENT'&&count($evidence['rewards'])===1&&$evidence['rewards'][0]['source_reference']==='case-source','Case evidence is constrained to the case player, world and ten-minute time window');
 caseCheck(!array_key_exists('evidence',Cases::playerDetail(1,'bug',$bug)),'Technical and reward evidence never enters the player conversation DTO');
 $db->execute('RENAME TABLE operational_events TO operational_events_fixture_hold');$db->execute('RENAME TABLE reward_grant_ledger TO reward_grant_ledger_fixture_hold');
 try{$without=Cases::detail(['id'=>2],'bug',$bug)['evidence'];caseCheck(!$without['events_available']&&!$without['rewards_available']&&$without['events']===[]&&$without['rewards']===[],'Cases remain available before optional event and reward migrations');}finally{$db->execute('RENAME TABLE operational_events_fixture_hold TO operational_events');$db->execute('RENAME TABLE reward_grant_ledger_fixture_hold TO reward_grant_ledger');}
 caseCheck(Cases::openCount(['id'=>1])===3&&Cases::openCount(['id'=>2])===2&&Cases::openCount(['id'=>3])===1,'Inbox applies current administrator, support and moderation scopes');
 caseCheck(Cases::inbox(['id'=>2],['case_world'=>2,'kind'=>'support'])['total']===1,'World and report-type filters combine server-side');
 caseReject(fn()=>Cases::detail(['id'=>2],'content',$content),'Support cannot read content moderation evidence');
 caseReject(fn()=>Cases::detail(['id'=>3],'bug',$bug),'Moderators cannot read private support cases');
 $update=casePayload(2,'bug',$bug,'update',['assigned_to'=>2,'status'=>'in_progress','priority'=>'urgent']);$first=Cases::execute(2,$update);$again=Cases::execute(2,$update);
 caseCheck(!$first['duplicate']&&$again['duplicate']&&(int)$db->query('SELECT COUNT(*) FROM support_case_messages')->fetchColumn()===1,'Update receipt replays without duplicate history');
 caseCheck(Cases::inbox(['id'=>2],['scope'=>'mine'])['total']===1&&Cases::overviewCounts(['id'=>1],1)===['open'=>2,'urgent'=>1],'Assignment filters and overview counts reflect authoritative cases');
 caseReject(fn()=>Cases::execute(2,array_replace($update,['status'=>'resolved'])),'Receipt payload mismatch is rejected');
 caseReject(fn()=>Cases::execute(2,array_replace($update,['operation_id'=>bin2hex(random_bytes(16))])),'Stale editing revision is rejected');
 caseReject(fn()=>Cases::execute(2,casePayload(2,'bug',$bug,'update',['assigned_to'=>3,'status'=>'resolved','priority'=>'normal'])),'Assignments enforce the assignee case role');
 $note=casePayload(2,'bug',$bug,'note',['body'=>'Private note that must never reach the player']);Cases::execute(2,$note);
 $reply=casePayload(2,'bug',$bug,'reply',['body'=>'We checked the delivery and are investigating.']);Cases::execute(2,$reply);
 $public=Cases::playerDetail(1,'bug',$bug);$serialized=json_encode($public);
 caseCheck(count($public['messages'])===1&&!str_contains($serialized,'Private note')&&!str_contains($serialized,'assigned_to')&&!str_contains($serialized,'event_json'),'Player conversation returns only public messages and explicit safe fields');
 caseReject(fn()=>Cases::playerDetail(2,'bug',$bug),'Another player cannot read a case');
 caseReject(fn()=>Cases::playerReply(2,'bug',$bug,['body'=>'Forged reply','operation_key'=>'forged_case_reply_0001']),'Another player cannot answer a case');
 $playerBody=['body'=>'The reward still has not arrived.','operation_key'=>'player_case_reply_0001','visibility'=>'internal'];$pr=Cases::playerReply(1,'bug',$bug,$playerBody);$pr2=Cases::playerReply(1,'bug',$bug,$playerBody);
 caseCheck(!$pr['duplicate']&&$pr2['duplicate']&&count(Cases::playerDetail(1,'bug',$bug)['messages'])===2,'Player retries are idempotent and cannot choose internal visibility');
 caseReject(fn()=>Cases::playerReply(1,'bug',$bug,array_replace($playerBody,['body'=>'Different request'])),'Player receipt payload cannot be reused for a different reply');
 caseReject(fn()=>Cases::execute(2,casePayload(2,'bug',$bug,'chat-ban',['minutes'=>60])),'Support case access does not grant moderation powers');
 Cases::execute(2,casePayload(2,'bug',$bug,'update',['assigned_to'=>2,'status'=>'resolved','priority'=>'normal']));
 Cases::playerReply(1,'bug',$bug,['body'=>'Please reopen my case.','operation_key'=>'player_case_reply_0002']);
 caseCheck(Cases::detail(['id'=>2],'bug',$bug)['status']==='new','Player replies reopen resolved cases for the team');
 $legacy=casePayload(2,'bug',$bug,'note',['body'=>'Should be stale']);$db->execute("UPDATE bug_reports SET admin_note='Legacy administrator changed this case' WHERE id=?",[$bug]);caseReject(fn()=>Cases::execute(2,$legacy),'Concurrent legacy report edits invalidate modern forms');
 caseReject(fn()=>\Conquer\Game\Community\SocialService::moderate(3,['action'=>'report.update','report_id'=>$content,'status'=>'resolved','admin_note'=>'No legacy bypass']),'Generic moderation entry keeps its existing superadmin gate');
 Cases::execute(3,casePayload(3,'content',$content,'update',['assigned_to'=>3,'status'=>'in_progress','priority'=>'high']));
 $ban=casePayload(3,'content',$content,'chat-ban',['minutes'=>30]);Cases::execute(3,$ban);Cases::execute(3,$ban);
 caseCheck((int)$db->query('SELECT COUNT(*) FROM community_chat_bans WHERE world_id=1 AND player_id=2')->fetchColumn()===1&&(int)$db->query("SELECT COUNT(*) FROM admin_audit_log WHERE action='case.chat-ban'")->fetchColumn()===1,'Scoped moderator restriction calls authoritative service once with an audit');
 Cases::execute(3,casePayload(3,'content',$content,'chat-unban'));
 caseCheck((int)$db->query('SELECT COUNT(*) FROM community_chat_bans')->fetchColumn()===0,'Moderator can remove a restriction from the reported player');
 caseCheck(!str_contains(json_encode(Cases::playerDetail(1,'content',$content)),'never expose'),'Player content-report conversation does not leak the moderation snapshot');
 $before=Cases::detail(['id'=>2],'bug',$bug);$receiptCount=(int)$db->query('SELECT COUNT(*) FROM admin_operations')->fetchColumn();$rollback=casePayload(2,'bug',$bug,'reply',['body'=>'Must roll back with missing audit storage']);
 $db->execute('RENAME TABLE admin_audit_log TO admin_audit_log_fixture_hold');
 try{try{Cases::execute(2,$rollback);throw new RuntimeException('Expected SQL failure');}catch(PDOException){caseCheck(true,'Late audit write failure rejects the entire operation');}}finally{$db->execute('RENAME TABLE admin_audit_log_fixture_hold TO admin_audit_log');}
 caseCheck(Cases::detail(['id'=>2],'bug',$bug)['version']===$before['version']&&(int)$db->query('SELECT COUNT(*) FROM admin_operations')->fetchColumn()===$receiptCount&&!str_contains(json_encode(Cases::playerDetail(1,'bug',$bug)),'Must roll back'),'Failed audit rolls back case version, public response and receipt');
 $demoted=casePayload(2,'bug',$bug,'note',['body'=>'Should reject after demotion']);$db->execute("UPDATE admin_users SET role='moderator' WHERE id=2");caseReject(fn()=>Cases::execute(2,$demoted),'Role changes apply immediately to stale sessions and forms');$db->execute("UPDATE admin_users SET role='support' WHERE id=2");
 caseReject(fn()=>\Conquer\Admin\AdminService::execute(2,'player-gift',['operation_id'=>bin2hex(random_bytes(16)),'reason'=>'Support must not credit','player_id'=>1]),'Existing resource and gift service remains superadmin-only');
 caseCheck(Cases::inbox(['id'=>1],['status'=>'all','q'=>'%'])['total']===0,'Case search treats wildcards literally');
 $sessionToken=bin2hex(random_bytes(32));$csrfToken=bin2hex(random_bytes(32));$db->execute("INSERT INTO sessions(player_id,token,csrf_token,ip_address,user_agent,expires_at,active_world_id) VALUES(1,?,?,'127.0.0.1','Case fixture',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),1)",[$sessionToken,$csrfToken]);
 $routes= <<<'ROUTES'
define('APP_BASE','/conquer');$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if(str_starts_with($path,'/conquer/assets/')){$file=ROOT_DIR.substr($path,8);if(!is_file($file)){http_response_code(404);exit;}$ext=pathinfo($file,PATHINFO_EXTENSION);header('Content-Type: '.(['css'=>'text/css','js'=>'application/javascript','svg'=>'image/svg+xml','png'=>'image/png','webp'=>'image/webp','woff2'=>'font/woff2'][$ext]??'application/octet-stream'));readfile($file);exit;}
if($path==='/conquer/api/support-cases'){\Conquer\Api\Handlers\SupportCaseHandler::inbox();exit;}
if(preg_match('#^/conquer/api/support-cases/(bug|content)/(\d+)(/reply)?$#',$path,$m)){$params=['source'=>$m[1],'id'=>$m[2]];if(!empty($m[3]))\Conquer\Api\Handlers\SupportCaseHandler::reply($params);else \Conquer\Api\Handlers\SupportCaseHandler::detail($params);exit;}
session_name('conquer_admin');session_start();
if($path==='/conquer/fixture-login'){$id=max(1,min(4,(int)($_GET['id']??1)));$row=\Conquer\Db\Connection::getInstance()->query('SELECT * FROM admin_users WHERE id=?',[$id])->fetch();$_SESSION['admin']=['id'=>$id,'username'=>$row['username'],'role'=>$row['role'],'must_change_password'=>false,'credential_version'=>hash('sha256',$row['password_hash']),'authenticated_at'=>time(),'last_seen_at'=>time()];$_SESSION['admin_csrf']=bin2hex(random_bytes(32));header('Location: /conquer/admin/cases');exit;}
if($path==='/conquer/admin/cases/action'){\Conquer\Admin\CaseController::action();exit;}
if($path==='/conquer/admin/players'){\Conquer\Admin\AdminController::players();exit;}
if($path==='/conquer/admin/world'){\Conquer\Admin\AdminController::world();exit;}
if($path==='/conquer/admin/layout-data'){\Conquer\Admin\AdminController::layoutData();exit;}
if($path==='/conquer/admin/links'){\Conquer\Admin\AdminController::links();exit;}
if($path==='/conquer/admin/bug-reports'){\Conquer\Admin\AdminController::bugReports();exit;}
if(preg_match('#^/conquer/admin/bug-reports/(\d+)/screenshot$#',$path,$m)){\Conquer\Admin\AdminController::bugReportScreenshot((int)$m[1]);exit;}
if($path==='/conquer/admin/cases'){$render=new ReflectionMethod(\Conquer\Admin\AdminController::class,'render');$render->invoke(null,'cases',\Conquer\Admin\CaseService::t('title'));exit;}
if($path==='/conquer/player-support'){echo '<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/conquer/assets/css/game.css"><link rel="stylesheet" href="/conquer/assets/css/bug-reports.css"><link rel="stylesheet" href="/conquer/assets/css/village-theme.css">'.\Conquer\Game\Locale::bootstrapScripts().'<script src="/conquer/assets/js/localization.js" defer></script><script src="/conquer/assets/js/bug-reports.js" defer></script></head><body><main id="content"></main></body></html>';exit;}
http_response_code(404);
ROUTES;
 $url=$fixture->serve($routes);
 $http=static function(string $path,?array $body=null,bool $auth=true,bool $csrf=true)use($url,$sessionToken,$csrfToken):array{$h=curl_init($url.$path);$headers=['Content-Type: application/json'];if($csrf)$headers[]='X-CSRF-Token: '.$csrfToken;curl_setopt_array($h,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_HTTPHEADER=>$headers,CURLOPT_COOKIE=>$auth?'conquer_session='.$sessionToken:'']);if($body!==null)curl_setopt_array($h,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($body)]);$raw=curl_exec($h);$status=curl_getinfo($h,CURLINFO_HTTP_CODE);curl_close($h);return [$status,json_decode($raw,true),$raw];};
 caseCheck($http('/conquer/api/support-cases',null,false)[0]===401,'HTTP player inbox requires authentication');
 caseCheck($http('/conquer/api/support-cases/bug/'.$bug.'/reply',['body'=>'CSRF should reject','operation_key'=>'http_case_csrf_0001'],true,false)[0]===403,'HTTP conversation replies require CSRF');
 $detail=$http('/conquer/api/support-cases/bug/'.$bug);caseCheck($detail[0]===200&&!str_contains($detail[2],'Private note'),'Authenticated HTTP detail excludes internal notes');
 if(in_array('--browser',$argv,true)){
   $env=array_merge(getenv(),['CASE_TEST_BASE'=>$url.'/conquer','CASE_TEST_TOKEN'=>$sessionToken,'CASE_TEST_CSRF'=>$csrfToken,'CASE_TEST_BUG'=>(string)$bug,'CASE_TEST_CONTENT'=>(string)$content]);
   $process=proc_open(['node',ROOT_DIR.'/tests/team_reports_app.cjs'],[0=>['pipe','r'],1=>STDOUT,2=>STDERR],$pipes,ROOT_DIR,$env,['bypass_shell'=>true]);fclose($pipes[0]);if(proc_close($process)!==0)throw new RuntimeException('Browser case checks failed');
 }
 echo "ALL $checks TEAM REPORT CHECKS PASSED\n";
}catch(Throwable $e){$exit=1;fwrite(STDERR,'FAIL '.$e->getMessage().' '.$e->getFile().':'.$e->getLine().PHP_EOL);}
finally{if($fixture)$fixture->close();}exit($exit);
