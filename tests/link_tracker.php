<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR . '/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR . '/src'))->register();
require __DIR__ . '/Support/FeatureDatabase.php';
require __DIR__ . '/Support/HttpApp.php';
use Conquer\Analytics\LinkTracker;
use Conquer\Admin\{AdminService,LinkTrackerAdmin};
use Conquer\Db\{Connection,MigrationSql};
use ConquerTests\HttpApp;
function trackerCheck(bool $ok, string $label): void { if (!$ok) throw new RuntimeException($label); echo "PASS $label\n"; }
$fixture = new \ConquerTests\FeatureDatabase();
try {
    $db = Connection::getInstance();
    \Conquer\Logger::init($fixture->sessionPath().'/tracker.log');
    $migration = (string)file_get_contents(ROOT_DIR.'/migrations/0140_link_tracker.sql');
    MigrationSql::apply($db->getPdo(),$migration);
    MigrationSql::apply($db->getPdo(),$migration);
    trackerCheck((int)$db->query('SELECT COUNT(*) FROM link_tracker_links')->fetchColumn()===13, 'migration replay preserves the 13 built-in links');
    foreach(['city'=>'site-gallery-city','01-build'=>'site-gallery-city','03-infantry'=>'site-gallery-army','08-monsters'=>'site-gallery-world','10-alpha'=>'site-register-guide'] as $art=>$target)
        trackerCheck(LinkTracker::galleryTarget($art)===$target,'both gallery layouts map to known counter '.$art);
    $db->execute("INSERT INTO admin_users(username,password_hash,role) VALUES('LinkAdmin',?,'superadmin'),('LinkModerator',?,'moderator')", [password_hash('Link-Fixture-2026!',PASSWORD_DEFAULT),password_hash('Link-Fixture-2026!',PASSWORD_DEFAULT)]);
    $input = ['operation_id'=>bin2hex(random_bytes(16)),'reason'=>'Fixture campaign','label'=>'Instagram Alpha','slug'=>'instagram-alpha','destination'=>'/?mode=register','enabled'=>'1'];
    $saved = AdminService::execute(1,'link-save',$input);
    $id = (int)$saved['target_id'];
    trackerCheck(AdminService::execute(1,'link-save',$input)['duplicate']===true && (int)$db->query("SELECT COUNT(*) FROM link_tracker_links WHERE kind='campaign'")->fetchColumn()===1, 'campaign creation replay cannot duplicate a link');
    foreach (['http://evil.invalid','//evil.invalid','javascript:alert(1)',"/\r\nLocation: x",'/admin','/go/loop','https://user:password@evil.invalid/','https://example.invalid/api/remove','/city/../admin','https://example.invalid\\@evil.invalid/','https://example.invalid/conquer/go/loop','https://example.invalid/%2561pi/remove'] as $target) {
        $rejected=false; try { LinkTracker::destination($target); } catch (InvalidArgumentException) { $rejected=true; }
        trackerCheck($rejected,'reject unsafe target '.json_encode($target));
    }
    trackerCheck(LinkTracker::destination('https://play.unionofkingdoms.com/?mode=register')==='https://play.unionofkingdoms.com/?mode=register', 'game registration HTTPS target is accepted');
    foreach (['slug'=>'site-reserved','destination'=>'//evil.invalid','label'=>[], 'enabled'=>'9'] as $field=>$value) {
        $rejected=false; try { AdminService::execute(1,'link-save',array_replace($input,[$field=>$value,'operation_id'=>bin2hex(random_bytes(16))])); } catch (InvalidArgumentException) { $rejected=true; }
        trackerCheck($rejected,'invalid '.$field.' rolls back receipt and link');
    }
    $rejected=false;try { AdminService::execute(2,'link-save',array_replace($input,['operation_id'=>bin2hex(random_bytes(16))])); } catch(InvalidArgumentException) {$rejected=true;}
    trackerCheck($rejected,'moderator cannot create campaigns');
    $update=array_replace($input,['operation_id'=>bin2hex(random_bytes(16)),'link_id'=>(string)$id,'revision'=>'1','label'=>'Instagram Alpha updated']);
    AdminService::execute(1,'link-save',$update);
    $rejected=false;try { AdminService::execute(1,'link-save',array_replace($update,['operation_id'=>bin2hex(random_bytes(16))])); } catch(DomainException) {$rejected=true;}
    trackerCheck($rejected,'stale campaign edit is rejected');
    $ua='Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/131.0.0.0 Safari/537.36';
    foreach (['discord.com'=>'discord','l.instagram.com'=>'instagram','instagram.com.evil.invalid'=>'other',''=>'direct'] as $host=>$source) trackerCheck(LinkTracker::source($host?'https://'.$host.'/secret?token=hidden':'')===$source,'source classification '.$host);
    foreach (['desktop'=>$ua,'mobile'=>'Mozilla/5.0 (iPhone) Mobile','tablet'=>'Mozilla/5.0 (Linux; Android 13) Safari','other'=>'UnknownBrowser'] as $device=>$agent) trackerCheck(LinkTracker::device($agent)===$device,'device category '.$device);
    foreach ([['HTTP_DNT'=>'1'],['HTTP_SEC_GPC'=>'1'],['HTTP_SEC_PURPOSE'=>'prefetch'],['HTTP_USER_AGENT'=>'Discordbot/2.0']] as $changes) trackerCheck(LinkTracker::ignored(array_replace(['HTTP_USER_AGENT'=>$ua],$changes)),'privacy/bot/prefetch suppression');
    LinkTracker::record($id,'instagram',['HTTP_USER_AGENT'=>$ua]);
    LinkTracker::record($id,'instagram',['HTTP_USER_AGENT'=>$ua]);
    trackerCheck((int)$db->query('SELECT clicks FROM link_tracker_daily WHERE link_id=?',[$id])->fetchColumn()===2,'repeat visits atomically increment one aggregate row');
    $db->execute("INSERT INTO link_tracker_daily(link_id,day,device,source,clicks,last_click_at) VALUES(?,DATE_SUB(UTC_DATE(),INTERVAL 8 DAY),'mobile','discord',5,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 8 DAY))",[$id]);
    $list=LinkTrackerAdmin::listing($db,['days'=>7,'link_id'=>$id]);
    trackerCheck((int)$list['summary']['clicks']===2 && (int)array_column($list['rows'],null,'id')[$id]['lifetime']===7,'selected calendar period and lifetime stay distinct');
    $list=LinkTrackerAdmin::listing($db,['days'=>30,'kind'=>'website']);
    trackerCheck((int)$list['summary']['clicks']===0 && count($list['rows'])===13,'website filters exclude campaign visits');
    $columns=$db->query('SHOW COLUMNS FROM link_tracker_daily')->fetchAll(PDO::FETCH_COLUMN);
    trackerCheck($columns===['link_id','day','device','source','clicks','last_click_at'],'statistics contain no visitor identifiers or request URLs');
    $configProperty=new ReflectionProperty(\Conquer\Bootstrap::class,'config');
    $configProperty->setValue(null,['link_tracking_enabled'=>false]);
    LinkTracker::record($id,'instagram',['HTTP_USER_AGENT'=>$ua]);
    trackerCheck((int)$db->query('SELECT SUM(clicks) FROM link_tracker_daily WHERE link_id=?',[$id])->fetchColumn()===7,'configuration switch disables counters');
    $configProperty->setValue(null,[]);
    $configProperty->setValue(null,['base_url'=>'https://retired.example.invalid']);
    $_SERVER['HTTP_HOST']='play.unionofkingdoms.com';
    trackerCheck(LinkTrackerAdmin::publicUrl('instagram-alpha')==='https://unionofkingdoms.com/go/instagram-alpha','campaign share URL uses the current public website instead of a retired game host');
    $_SERVER['HTTP_HOST']='localhost:8080';
    trackerCheck(LinkTrackerAdmin::publicUrl('instagram-alpha')==='http://localhost:8080/go/instagram-alpha','local share URL retains the current port');
    unset($_SERVER['HTTP_HOST']);$configProperty->setValue(null,[]);
    foreach(['en','de','fr'] as $lang){$catalog=\Conquer\Game\Locale::catalog($lang);trackerCheck(isset($catalog['links.error_target'],$catalog['links.title'],$catalog['legal.privacy.link_stats']),'complete tracker localization '.$lang);}

    $prefix= <<<'PHP'
    $path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
    if(str_starts_with($path,'/conquer/assets/')){
        $file=realpath(ROOT_DIR.substr($path,strlen('/conquer')));$assetRoot=realpath(ROOT_DIR.'/assets').DIRECTORY_SEPARATOR;
        if(!$file||!str_starts_with($file,$assetRoot)||!is_file($file)){http_response_code(404);exit;}
        header('Content-Type: '.(['css'=>'text/css','js'=>'application/javascript','mjs'=>'application/javascript','svg'=>'image/svg+xml','png'=>'image/png','webp'=>'image/webp','woff2'=>'font/woff2'][pathinfo($file,PATHINFO_EXTENSION)]??'application/octet-stream'));readfile($file);exit;
    }
    if($path==='/conquer/website'){
        define('APP_BASE','/conquer');(new ReflectionProperty(\Conquer\Bootstrap::class,'config'))->setValue(null,['base_url'=>'']);
        $landingCspNonce=base64_encode(random_bytes(18));require ROOT_DIR.'/views/welcome.php';exit;
    }
    PHP;
    $source=HttpApp::source(['base_url'=>'']);
    $source=str_replace("\$_SERVER['SCRIPT_NAME']='/index.php'", "\$_SERVER['SCRIPT_NAME']='/conquer/index.php'", $source);
    $base=$fixture->serve($prefix.$source).'/conquer';
    $headers=['User-Agent: '.$ua,'Referer: https://l.instagram.com/?private=not-stored'];
    $before=(int)$db->query('SELECT SUM(clicks) FROM link_tracker_daily WHERE link_id=?',[$id])->fetchColumn();
    $r=HttpApp::request($base,'/go/instagram-alpha?destination=https://evil.invalid/','GET',$headers);
    trackerCheck($r['status']===302&&in_array('location: /conquer/?mode=register',$r['headers'],true),'real front controller redirects to saved target and respects subfolder');
    trackerCheck(!preg_grep('/^set-cookie:/',$r['headers'])&&in_array('cache-control: private, no-store',$r['headers'],true),'campaign route creates no session cookie and cannot be cached');
    trackerCheck((int)$db->query('SELECT SUM(clicks) FROM link_tracker_daily WHERE link_id=?',[$id])->fetchColumn()===$before+1,'campaign GET counted');
    foreach ([['HEAD',$headers],['GET',['User-Agent: Discordbot']],['GET',[...$headers,'Sec-GPC: 1']],['GET',[...$headers,'Purpose: prefetch']]] as [$method,$h]) { $r=HttpApp::request($base,'/go/instagram-alpha',$method,$h); trackerCheck($r['status']===302,'HEAD, bots and opt-outs still redirect'); }
    trackerCheck((int)$db->query('SELECT SUM(clicks) FROM link_tracker_daily WHERE link_id=?',[$id])->fetchColumn()===$before+1,'HEAD, preview, privacy and prefetch requests add no clicks');
    trackerCheck(HttpApp::request($base,'/go/instagram-alpha','POST',$headers)['status']===405 && HttpApp::request($base,'/go/site-login','GET',$headers)['status']===404,'wrong method and built-in slugs cannot redirect');
    $origin=preg_replace('~/conquer$~','',$base);
    $payload=json_encode(['link'=>'site-login','source'=>'discord']);
    $r=HttpApp::request($base,'/api/links/click','POST',[...$headers,'Origin: '.$origin,'Content-Type: application/json'],$payload);
    trackerCheck($r['status']===204&&!preg_grep('/^set-cookie:/',$r['headers']),'anonymous same-origin website click succeeds without cookies');
    trackerCheck(HttpApp::request($base,'/api/links/click','POST',[...$headers,'Origin: https://evil.invalid'],$payload)['status']===403,'cross-origin counter requests are rejected');
    trackerCheck(HttpApp::request($base,'/api/links/click','POST',[...$headers,'Origin: '.$origin],str_repeat('x',1025))['status']===413,'oversized events are rejected');
    trackerCheck(HttpApp::request($base,'/api/links/click','POST',[...$headers,'Origin: '.$origin],json_encode(['link'=>'instagram-alpha']))['status']===422,'campaigns cannot be counted as website clicks');
    trackerCheck(HttpApp::request($base,'/admin/links')['status']===302,'anonymous admin overview requires authentication');
    $db->execute('UPDATE link_tracker_links SET enabled=0 WHERE id=?',[$id]);
    trackerCheck(HttpApp::request($base,'/go/instagram-alpha','GET',$headers)['status']===404,'paused campaign does not redirect');
    $db->execute('UPDATE link_tracker_links SET enabled=1 WHERE id=?',[$id]);
    if(in_array('--browser',$argv,true)){
        $process=proc_open(['node',ROOT_DIR.'/tests/link_tracker_app.cjs',$base],[0=>['pipe','r'],1=>STDOUT,2=>STDERR],$pipes,ROOT_DIR,null,['bypass_shell'=>true]);
        if(!is_resource($process))throw new RuntimeException('Browser test did not start.');fclose($pipes[0]);
        if(proc_close($process)!==0)throw new RuntimeException('Link tracker browser checks failed.');
    }
    // Redirect robustness: counters fail independently of valid campaign lookup.
    $db->execute('DROP TABLE link_tracker_daily');
    trackerCheck(HttpApp::request($base,'/go/instagram-alpha','GET',$headers)['status']===302,'counter storage failure never blocks a saved redirect');
} finally { $fixture->close(); }
