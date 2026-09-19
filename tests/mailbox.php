<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();date_default_timezone_set('UTC');
require ROOT_DIR.'/tests/Support/FeatureDatabase.php';require ROOT_DIR.'/tests/Support/MailboxFixture.php';
use Conquer\Db\Connection;
use Conquer\Game\Community\MailboxService as Mail;
use Conquer\Game\Community\CommunityService as Community;
use Conquer\Game\World\WorldContext;
function checkMail(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo 'PASS '.$label."\n";}
function denyMail(callable $fn,string $label):void{try{$fn();}catch(DomainException){checkMail(true,$label);return;}throw new RuntimeException('Expected rejection: '.$label);}
function actMail(string $action,array $payload=[],int $player=1):array{return Community::action($player,['action'=>$action,'request_id'=>bin2hex(random_bytes(16))]+$payload,1)['result'];}
$fixture=new \ConquerTests\FeatureDatabase();
try{
    \ConquerTests\MailboxFixture::seed();$db=Connection::getInstance();
    $state=Mail::state(1,1,['category'=>'system']);
    checkMail(count($state['entries'])===50&&$state['next']!==null&&$state['counts']['system']['total']===59,'complete counters and stable scrolling beyond 50 messages');
    $second=Mail::state(1,1,['category'=>'system']+$state['next']);
    checkMail(count($second['entries'])===9&&!array_intersect(array_column($state['entries'],'id'),array_column($second['entries'],'id')),'cursor has no overlap or missing tail');
    $n=(int)$db->query('SELECT COUNT(*) FROM mailbox_entries')->fetchColumn();Mail::state(1,1);
    checkMail((int)$db->query('SELECT COUNT(*) FROM mailbox_entries')->fetchColumn()===$n,'repeated synchronization creates no duplicates');
    $private=Mail::state(1,1,['category'=>'private'])['entries'][0];$id=(int)$private['id'];
    checkMail(Mail::state(3,1)['counts']['private']['total']===0,'private mail stays private');
    denyMail(fn()=>Mail::message(3,1,$id),'outsider cannot read message body');
    denyMail(fn()=>actMail('mailbox.star',['mail_id'=>$id,'starred'=>true],3),'outsider cannot change favorite');
    actMail('mailbox.read',['mail_id'=>$id]);actMail('mailbox.star',['mail_id'=>$id,'starred'=>true]);
    actMail('mailbox.delete_read',['category'=>'private','snapshot'=>$state['snapshot']]);
    checkMail(Mail::message(1,1,$id)['starred']==1&&Mail::state(1,1,['category'=>'starred'])['counts']['starred']['total']===1,'read favorites survive bulk deletion');
    actMail('mailbox.star',['mail_id'=>$id,'starred'=>false]);actMail('mailbox.delete_read',['category'=>'private','snapshot'=>$state['snapshot']]);
    checkMail(Mail::state(1,1,['category'=>'private'])['counts']['private']['total']===0&&Mail::state(2,1,['category'=>'sent'])['counts']['sent']['total']===1,'deletion is durable and affects only the acting recipient');
    $s=Mail::state(1,1,['category'=>'alliance']);$gift=array_values(array_filter($s['entries'],fn($e)=>$e['source']==='alliance_gift'))[0];$gid=(int)$gift['id'];
    actMail('mailbox.read',['mail_id'=>$gid]);actMail('mailbox.delete_read',['category'=>'alliance','snapshot'=>$s['snapshot']]);
    checkMail(Mail::message(1,1,$gid)['reward_status']==='pending','reading and cleanup never lose an unclaimed gift');
    actMail('mailbox.read_all',['category'=>'alliance','snapshot'=>$s['snapshot']]);
    checkMail(Mail::message(1,1,$gid)['reward_status']==='pending','read all does not collect outstanding rewards');
    $db->execute("INSERT INTO notifications(player_id,type,data_json)VALUES(1,'alliance_notice','{\"world_id\":1,\"title\":\"Neue Allianzpost\"}')");
    $s=Mail::state(1,1,['category'=>'alliance']);
    $payload=['action'=>'mailbox.claim_all','category'=>'alliance','snapshot'=>$s['snapshot'],'request_id'=>'mailbox_receipt_retry_0001'];
    $a=Community::action(1,$payload,1);$b=Community::action(1,$payload,1);actMail('mailbox.claim',['mail_id'=>$gid]);
    checkMail($a===$b&&(int)$db->query('SELECT quantity FROM player_inventory WHERE player_id=1 AND item_code=10103001')->fetchColumn()===2,'bulk claims, retries and repeated individual claims credit exactly once');
    checkMail(Mail::state(1,1,['category'=>'alliance'])['counts']['alliance']['unread']===1,'collect all leaves unrelated messages unread');
    $admin=Mail::state(1,1,['category'=>'system']);$before=(int)$db->query('SELECT gems FROM players WHERE id=1')->fetchColumn();
    actMail('mailbox.read_all',['category'=>'system','snapshot'=>$admin['snapshot']]);
    checkMail((int)$db->query('SELECT gems FROM players WHERE id=1')->fetchColumn()===$before,'admin gift receipts never credit rewards twice');
    $notification=$db->query("SELECT id,source_id FROM mailbox_entries WHERE player_id=1 AND source='notification' AND category='system' LIMIT 1")->fetch();
    actMail('mailbox.star',['mail_id'=>(int)$notification['id'],'starred'=>true]);$db->execute('DELETE FROM notifications WHERE id=?',[$notification['source_id']]);
    checkMail(Mail::message(1,1,(int)$notification['id'])['starred']==1,'favorite retains its text after source notification retention');
    $db->execute("INSERT INTO notifications(player_id,type,data_json)VALUES(1,'build_complete','{\"world_id\":1}')");
    $new=Mail::state(1,1,['category'=>'system']);actMail('mailbox.read_all',['category'=>'system','snapshot'=>$admin['snapshot']]);
    checkMail(Mail::state(1,1,['category'=>'system'])['counts']['system']['unread']===1,'bulk snapshot excludes messages arriving after it was displayed');
    $war=Mail::state(1,1,['category'=>'war']);$scout=array_values(array_filter($war['entries'],fn($e)=>$e['subject']==='Deine Stadt wurde ausgespäht'))[0];
    checkMail($war['counts']['war']['total']===3&&in_array('Verteidigung: Sieg',array_column($war['entries'],'subject'),true),'personal defender record uses its owner and correct victory perspective');
    $monster=Mail::state(1,1,['category'=>'reports'])['entries'][0];
    checkMail($monster['subject']==='Angriff: Sieg gegen Ork-Späher Lv. 8'&&$monster['monster_name']==='Ork-Späher'&&$monster['monster_level']===8&&$monster['monster_art']==='orc','monster list entry names the historical target and level and exposes its artwork');
    checkMail(!str_contains(json_encode(Mail::message(1,1,(int)$scout['id'])),'987654321'),'defender never receives the attacker scout intelligence');
    $db->execute("INSERT INTO battle_reports(world_id,attacker_id,attacker_city_id,defender_id,target_type,target_x,target_y,outcome,data_json)VALUES(1,2,2,1,5,65,65,'attacker_wins',?)",[json_encode(['battle_kind'=>'field','perspective'=>'defender','troops'=>[['code'=>50100101,'sent'=>77777]]])]);
    checkMail(Mail::state(1,1,['category'=>'war'])['counts']['war']['total']===3,'opponent field record is not duplicated into the recipient inbox');
    $db->execute("INSERT INTO worlds(id,name,slug,status)VALUES(2,'Other world','mail-other','running')");$db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y)VALUES(101,1,2,'Other city',70,70)");
    WorldContext::run(2,function()use($id){checkMail(Mail::state(1,2)['unread']===0,'mail and notifications are isolated between worlds');denyMail(fn()=>Mail::message(1,2,$id),'cross-world detail rejected');});
    denyMail(fn()=>Mail::state(1,1,['category'=>"system' OR 1=1"]),'category allowlist blocks query injection');
    $token=bin2hex(random_bytes(32));$csrf=bin2hex(random_bytes(32));$db->execute("INSERT INTO sessions(player_id,token,csrf_token,ip_address,user_agent,expires_at)VALUES(1,?,?,'127.0.0.1','Mailbox QA',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR))",[$token,$csrf]);
    $url=$fixture->serve('$path=parse_url($_SERVER["REQUEST_URI"],PHP_URL_PATH);if($path==="/state")\\Conquer\\Api\\Handlers\\MailboxHandler::state([]);if($path==="/message")\\Conquer\\Api\\Handlers\\MailboxHandler::message([]);if($path==="/action")\\Conquer\\Api\\Handlers\\CommunityHandler::action([]);');
    $http=static function(string $path,?array $payload=null,bool $auth=true,bool $validCsrf=true)use($url,$token,$csrf):int{
        $c=curl_init($url.$path);curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10,CURLOPT_COOKIE=>$auth?'conquer_session='.$token:'',CURLOPT_HTTPHEADER=>['Content-Type: application/json','X-CSRF-Token: '.($validCsrf?$csrf:'invalid')]]);if($payload!==null)curl_setopt_array($c,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($payload)]);curl_exec($c);$code=(int)curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);return $code;
    };
    checkMail($http('/state',null,false)===401,'mailbox HTTP requires login');
    checkMail($http('/state')===200,'authenticated mailbox HTTP state succeeds');
    checkMail($http('/action',$payload,true,false)===403,'all mailbox writes require CSRF');
    checkMail($http('/action',$payload)===200,'authenticated repeated bulk command succeeds over HTTP');
}finally{$fixture->close();}
