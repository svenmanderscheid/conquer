<?php
declare(strict_types=1);
/** In-game news and community moderation through the audited backoffice boundary. */
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();date_default_timezone_set('UTC');
require ROOT_DIR.'/tests/Support/FeatureDatabase.php';
use Conquer\Db\Connection;
use Conquer\Admin\AdminService;
use Conquer\Game\Community\CommunityNewsService as News;
use Conquer\Game\Community\SocialService as Social;
function newsCheck(bool $valid,string $message):void{if(!$valid)throw new RuntimeException($message);echo "PASS $message\n";}
function newsDeny(callable $callback,string $message):void{try{$callback();}catch(DomainException|InvalidArgumentException){newsCheck(true,$message);return;}throw new RuntimeException('Expected rejection: '.$message);}
function newsAdmin(string $action,array $input=[],int $admin=1):array{return AdminService::execute($admin,$action,['action'=>$action,'operation_id'=>bin2hex(random_bytes(16)),'reason'=>'Community fixture change']+$input);}
$fixture=new \ConquerTests\FeatureDatabase();$previousInvite=getenv('CONQUER_DISCORD_INVITE');
try{
    $db=Connection::getInstance();foreach(['0121_community_social.sql','0123_community_news.sql'] as $migration)\Conquer\Db\MigrationSql::apply($db->getPdo(),(string)file_get_contents(ROOT_DIR.'/migrations/'.$migration));
    $db->execute("INSERT INTO worlds(id,name,slug,status) VALUES(2,'News remote','news-remote','running')");
    $db->execute("INSERT INTO players(id,username,email,password_hash) VALUES(1,'NewsOne','news1@tests.invalid','unused'),(2,'NewsTwo','news2@tests.invalid','unused')");
    $db->execute("INSERT INTO cities(player_id,world_id,name,coord_x,coord_y) VALUES(1,1,'News One',30,30),(2,1,'News Two',35,30)");
    $db->execute("INSERT INTO admin_users(id,username,password_hash,role) VALUES(1,'NewsAdmin','unused','superadmin'),(2,'NewsModerator','unused','moderator')");
    putenv('CONQUER_DISCORD_INVITE=https://discord.gg/fixture-code');newsCheck(News::discordInvite()==='https://discord.gg/fixture-code','Official Discord invite accepts only a supported HTTPS Discord URL');
    foreach(['javascript:alert(1)','https://discord.gg.evil.invalid/fixture','https://discord.gg/fixture?redirect=evil','http://discord.gg/fixture','https://discord.com@evil.invalid/invite/fixture'] as $invalid){putenv('CONQUER_DISCORD_INVITE='.$invalid);newsCheck(News::discordInvite()===null,'Invalid Discord invite is withheld: '.$invalid);}
    $payload=['action'=>'community-news-save','operation_id'=>'0123456789abcdef0123456789abcdef','reason'=>'Publish the fixture announcement','world_id'=>'1','news_id'=>'0','title'=>'Server news','body'=>'A fixture announcement in English.','published'=>'1'];
    $published=AdminService::execute(1,'community-news-save',$payload);$replay=AdminService::execute(1,'community-news-save',$payload);
    newsCheck($published['target_id']===$replay['target_id']&&$replay['duplicate']&&(int)$db->query('SELECT COUNT(*) FROM community_news')->fetchColumn()===1,'Backoffice announcement creation is replay safe');
    newsCheck(count(News::state(1)['posts'])===1&&News::state(2)['posts']===[],'Published announcements are scoped to their world');
    newsAdmin('community-news-save',['world_id'=>'2','news_id'=>'0','title'=>'Remote announcement','body'=>'Only players of the other world see this.','published'=>'1']);
    newsAdmin('community-news-save',['world_id'=>'1','news_id'=>'0','title'=>'Draft announcement','body'=>'This is not public yet.','published'=>'0']);
    newsCheck(count(News::state(1)['posts'])===1&&count(News::state(2)['posts'])===1,'Drafts stay private and another world stays isolated');
    newsDeny(fn()=>newsAdmin('community-news-save',['world_id'=>'2','news_id'=>(string)$published['target_id'],'title'=>'Wrong world edit','body'=>'Wrong world mutation','published'=>'1']),'Announcement edits cannot change a record selected in another world');
    newsDeny(fn()=>newsAdmin('community-news-save',['world_id'=>'1','news_id'=>'0','title'=>'Invalid announcement','body'=>"Invalid\0control character",'published'=>'1']),'Announcement text rejects control characters');
    newsDeny(fn()=>newsAdmin('community-news-save',['world_id'=>'1','news_id'=>'0','title'=>'Unauthorized','body'=>'Unauthorized publication','published'=>'1'],2),'Non-superadmin roles cannot publish official announcements');
    newsAdmin('community-news-save',['world_id'=>'1','news_id'=>(string)$published['target_id'],'title'=>'Edited announcement','body'=>'The corrected announcement.','published'=>'0']);newsCheck(News::state(1)['posts']===[],'Unpublishing removes an announcement from game reads');
    $report=Social::action(1,['action'=>'report.submit','player_id'=>2,'reason'=>'spam','details'=>'Fixture report','request_id'=>'news_report_fixture_0001'],1)['result'];
    $reportUpdate=newsAdmin('community-report-update',['report_id'=>(string)$report['id'],'status'=>'resolved','admin_note'=>'Handled in fixture']);newsCheck($reportUpdate['after']['status']==='resolved','Backoffice accepts real HTML form report IDs and resolves the report');
    $banPayload=['action'=>'community-chat-ban','operation_id'=>'fedcba9876543210fedcba9876543210','reason'=>'Repeated chat spam','world_id'=>'1','player_id'=>'2','minutes'=>'60'];
    $ban=AdminService::execute(1,'community-chat-ban',$banPayload);$banAgain=AdminService::execute(1,'community-chat-ban',$banPayload);newsCheck($banAgain['duplicate']&&count(Social::moderationState(1)['bans'])===1,'Backoffice chat bans accept form numbers and commit once');
    newsCheck((int)$db->query("SELECT COUNT(*) FROM admin_audit_log WHERE action IN ('admin.community-report-update','admin.community-chat-ban')")->fetchColumn()===2,'Report resolution and chat ban have one audit record each');
    newsAdmin('community-chat-unban',['world_id'=>'1','player_id'=>'2']);newsCheck(Social::moderationState(1)['bans']===[],'Backoffice unban restores chat access');
    newsDeny(fn()=>newsAdmin('community-chat-ban',['world_id'=>'1','player_id'=>'2','minutes'=>'1.5']),'Fractional moderation durations are rejected');
    newsDeny(fn()=>newsAdmin('community-chat-ban',['world_id'=>'1','player_id'=>'2','minutes'=>'60'],2),'Non-superadmin moderation follows the established backoffice authorization boundary');
    $token=bin2hex(random_bytes(32));$csrf=bin2hex(random_bytes(32));$db->execute("INSERT INTO sessions(player_id,token,csrf_token,ip_address,user_agent,expires_at,active_world_id) VALUES(1,?,?,'127.0.0.1','News test',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),1)",[$token,$csrf]);
    $url=$fixture->serve('\\Conquer\\Api\\Handlers\\CommunityNewsHandler::state([]);');
    $request=static function(string $query,bool $auth=true)use($url,$token):array{$h=curl_init($url.'/?'.$query);curl_setopt_array($h,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_COOKIE=>$auth?'conquer_session='.$token:'']);$raw=curl_exec($h);if($raw===false)throw new RuntimeException(curl_error($h));$status=(int)curl_getinfo($h,CURLINFO_HTTP_CODE);curl_close($h);$json=json_decode($raw,true);if(!is_array($json))throw new RuntimeException('Unexpected news response: '.$raw);return [$status,$json];};
    newsCheck($request('world_id=1',false)[0]===401,'News HTTP endpoint requires authentication');newsCheck($request('world_id=1')[0]===200,'News HTTP endpoint returns the authenticated world');newsCheck($request('world_id=2')[0]===409,'News HTTP endpoint consistently rejects a stale world');
}finally{if($previousInvite===false)putenv('CONQUER_DISCORD_INVITE');else putenv('CONQUER_DISCORD_INVITE='.$previousInvite);$fixture->close();}
