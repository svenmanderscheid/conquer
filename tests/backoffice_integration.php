<?php
declare(strict_types=1);
/** Empty throwaway database: copies schema only; never creates or changes live players. */
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
define('ROOT_DIR',dirname(__DIR__));define('APP_BASE','/conquer');date_default_timezone_set('UTC');
require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
use Conquer\Db\Connection;
use Conquer\Admin\AdminService;
use Conquer\Game\World\{WorldSettings,WorldSpawnService};
set_error_handler(static function(int $severity,string $message,string $file,int $line):never{throw new ErrorException($message,0,$severity,$file,$line);});
$cfg=require ROOT_DIR.'/config/database.php';
$server=new PDO('mysql:host='.$cfg['host'].';port='.($cfg['port']??3306).';charset=utf8mb4',$cfg['username'],$cfg['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$schema='conquer_admin_test_'.bin2hex(random_bytes(6));$original=$cfg['database'];$created=false;$failed=false;
function verifyAdmin(bool $condition,string $label):void{if(!$condition)throw new RuntimeException($label);echo "PASS $label\n";}
function rejectsAdmin(callable $callback,string $label):void{try{$callback();}catch(InvalidArgumentException|DomainException $e){verifyAdmin(true,$label);return;}throw new RuntimeException('Accepted invalid request: '.$label);}
function adminAction(string $action,array $body=[],?string $op=null,int $admin=1):array{return AdminService::execute($admin,$action,array_replace(['world_id'=>1,'player_id'=>1,'reason'=>'Isolated integration test','operation_id'=>$op??bin2hex(random_bytes(16))],$body));}
try {
    if(!preg_match('/^[A-Za-z0-9_]+$/D',$original))throw new RuntimeException('Unsupported database name.');
    $server->exec('CREATE DATABASE `'.$schema.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');$created=true;
    $tables=$server->query('SHOW TABLES FROM `'.$original.'`')->fetchAll(PDO::FETCH_COLUMN);
    foreach($tables as $table){if(!preg_match('/^[a-zA-Z0-9_]+$/D',$table))continue;$server->exec('CREATE TABLE `'.$schema.'`.`'.$table.'` LIKE `'.$original.'`.`'.$table.'`');}
    $pdo=new PDO('mysql:host='.$cfg['host'].';port='.($cfg['port']??3306).';dbname='.$schema.';charset=utf8mb4',$cfg['username'],$cfg['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    $pdo->exec("SET time_zone='+00:00'");
    foreach(['0063_world_spawn_settings.sql'=>'world_spawn_settings','0064_admin_operations_gifts.sql'=>'admin_operations','0068_progression_and_events.sql'=>'world_event_settings','0099_bug_reports.sql'=>'bug_reports'] as $file=>$table) {
        if(in_array($table,$tables,true)||!is_file(ROOT_DIR.'/migrations/'.$file))continue;
        \Conquer\Db\MigrationSql::apply($pdo,(string)file_get_contents(ROOT_DIR.'/migrations/'.$file));
    }
    $reflection=new ReflectionClass(Connection::class);$db=$reflection->newInstanceWithoutConstructor();$reflection->getProperty('pdo')->setValue($db,$pdo);$reflection->getProperty('instance')->setValue(null,$db);
    \Conquer\Db\MigrationSql::apply($pdo,(string)file_get_contents(ROOT_DIR.'/migrations/0065_reconcile_admin_audit.sql'));
    // Independent, synthetic fixtures only.
    $db->execute("INSERT INTO admin_users(id,username,password_hash,role) VALUES(1,'TestAdmin','unused','superadmin'),(2,'TestModerator','unused','moderator')");
    $db->execute("INSERT INTO worlds(id,name,slug,status,map_size,map_seed) VALUES(1,'Test Realm','test-one','running',256,42),(2,'Other Realm','test-two','running',256,42)");
    $db->execute("INSERT INTO players(id,username,email,password_hash) VALUES(1,'Aster','aster@example.invalid','unused'),(2,'Birch','birch@example.invalid','unused'),(3,'Cedar','cedar@example.invalid','unused')");
    $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y) VALUES(1,1,1,'Aster Home',110,110),(2,1,2,'Other Home',110,110),(3,2,1,'Birch Home',140,140),(4,3,2,'Cedar Home',150,150)");
    foreach([1,2,3,4] as $city)foreach(\Conquer\Game\City\CityState::BUILDING_CODES as $code)$db->execute('INSERT INTO city_buildings(city_id,building_code,level) VALUES(?,?,1)',[$city,$code]);
    $beforeOther=$db->query('SELECT food FROM cities WHERE id=2')->fetchColumn();
    adminAction('set-resources',['food'=>123,'lumber'=>456,'stone'=>789,'gold'=>321]);
    verifyAdmin((int)$db->query('SELECT food FROM cities WHERE id=1')->fetchColumn()===123&&(int)$db->query('SELECT food FROM cities WHERE id=2')->fetchColumn()===(int)$beforeOther,'resource edit uses selected world only');
    rejectsAdmin(static fn()=>adminAction('set-resources',['food'=>-1,'lumber'=>0,'stone'=>0,'gold'=>0]),'negative resources rejected');
    rejectsAdmin(static fn()=>adminAction('set-resources',['food'=>0,'lumber'=>0,'stone'=>0,'gold'=>0],null,2),'moderator cannot mutate even with forged session');
    $db->execute("INSERT INTO sessions(player_id,token,csrf_token,ip_address,user_agent,expires_at) VALUES(1,?,?,'127.0.0.1','test',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR))",[bin2hex(random_bytes(32)),bin2hex(random_bytes(32))]);
    adminAction('ban');verifyAdmin((int)$db->query('SELECT is_banned FROM players WHERE id=1')->fetchColumn()===1&&!(int)$db->query('SELECT COUNT(*) FROM sessions WHERE player_id=1')->fetchColumn(),'ban updates real is_banned and revokes sessions');
    adminAction('unban');verifyAdmin(!(int)$db->query('SELECT is_banned FROM players WHERE id=1')->fetchColumn(),'unban restores account');
    $gift=['title'=>'Season Gift','message'=>'Thanks, Aster!','food'=>50,'gems'=>7,'item_code'=>10103001,'quantity'=>2];$op=bin2hex(random_bytes(16));
    $first=adminAction('gift',$gift,$op);$again=adminAction('gift',$gift,$op);
    verifyAdmin(!$first['duplicate']&&$again['duplicate']&&(int)$db->query('SELECT food FROM cities WHERE id=1')->fetchColumn()===173&&(int)$db->query('SELECT quantity FROM player_inventory WHERE player_id=1 AND item_code=10103001')->fetchColumn()===2,'gift retry credits resources and inventory exactly once');
    verifyAdmin((int)$db->query('SELECT COUNT(*) FROM admin_gifts')->fetchColumn()===1&&(int)$db->query("SELECT COUNT(*) FROM notifications WHERE type='admin_gift'")->fetchColumn()===1,'gift creates durable receipt and notification');
    rejectsAdmin(static fn()=>adminAction('gift',array_replace($gift,['food'=>51]),$op),'changed payload cannot reuse an operation ID');
    adminAction('gift',['player_id'=>0,'recipient_count'=>2,'title'=>'World Gift','gold'=>25]);
    verifyAdmin((int)$db->query('SELECT COUNT(*) FROM admin_gifts')->fetchColumn()===3&&(int)$db->query('SELECT gold FROM cities WHERE id=4')->fetchColumn()===5000,'world gifts only credit eligible cities in selected world');
    $db->execute('UPDATE players SET gems=2000000000 WHERE id=2');$giftCount=(int)$db->query('SELECT COUNT(*) FROM admin_gifts')->fetchColumn();$asterGems=(int)$db->query('SELECT gems FROM players WHERE id=1')->fetchColumn();
    rejectsAdmin(static fn()=>adminAction('gift',['player_id'=>0,'recipient_count'=>2,'title'=>'Overflow rollback','gems'=>1]),'invalid world gift recipient rejects complete batch');
    verifyAdmin((int)$db->query('SELECT COUNT(*) FROM admin_gifts')->fetchColumn()===$giftCount&&(int)$db->query('SELECT gems FROM players WHERE id=1')->fetchColumn()===$asterGems,'batch failure rolls back earlier recipients and receipts');$db->execute('UPDATE players SET gems=0 WHERE id=2');
    $audit=$db->query("SELECT details FROM admin_audit_log WHERE action='admin.set-resources' ORDER BY id DESC LIMIT 1")->fetchColumn();
    verifyAdmin(isset(json_decode($audit,true)['before']['food'],json_decode($audit,true)['after']['food']),'audit records before and after values');
    $db->execute("INSERT INTO bug_reports(operation_key,player_id,world_id,category,severity,title,description,reproduction_steps) VALUES('bug_fixture_operation_0001',1,1,'interface','blocking','Button does not react','The primary action remains disabled after selection.','Open panel and select an entry.')");$bugId=$db->lastInsertId();
    $bugUpdate=adminAction('bug-report-update',['report_id'=>$bugId,'status'=>'in_progress','priority'=>'urgent','admin_note'=>'Reproduced in narrow viewport.']);
    $bug=$db->query('SELECT status,priority,admin_note,handled_at FROM bug_reports WHERE id=?',[$bugId])->fetch();
    verifyAdmin($bug['status']==='in_progress'&&$bug['priority']==='urgent'&&$bug['handled_at']===null&&$bugUpdate['target_type']==='bug_report','bug report workflow stores triage state');
    adminAction('bug-report-update',['report_id'=>$bugId,'status'=>'resolved','priority'=>'high','admin_note'=>'Fixed and verified.']);
    verifyAdmin((bool)$db->query('SELECT handled_at FROM bug_reports WHERE id=?',[$bugId])->fetchColumn(),'resolved bug report records completion time');
    $db->execute("INSERT INTO building_queue(city_id,building_code,level_to,started_at,finishes_at) VALUES(1,'farm',2,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR))");
    rejectsAdmin(static fn()=>adminAction('set-building',['building_code'=>'farm','level'=>5]),'building edits cannot overwrite active queue');
    rejectsAdmin(static fn()=>adminAction('set-building',['building_code'=>'fake_building','level'=>5]),'unknown buildings rejected');
    adminAction('set-building',['building_code'=>'castle','level'=>7]);
    verifyAdmin((int)$db->query('SELECT castle_level FROM cities WHERE id=1')->fetchColumn()===7&&(int)$db->query("SELECT level FROM city_buildings WHERE city_id=1 AND building_code='castle'")->fetchColumn()===7,'successful building edit synchronizes castle shortcut');
    $db->execute('UPDATE cities SET wall_hp_current=4000,wall_last_update=UTC_TIMESTAMP() WHERE id=1');
    adminAction('set-building',['building_code'=>'wall','level'=>3]);
    $wall=$db->query('SELECT wall_hp_max,wall_hp_current FROM cities WHERE id=1')->fetch();verifyAdmin((int)$wall['wall_hp_max']===15000&&(int)$wall['wall_hp_current']===14000,'wall upgrade uses canonical defense service and preserves damage');
    $node=array_key_first(\Conquer\Game\Research\ResearchData::allNodes());
    adminAction('set-research',['research_code'=>$node,'level'=>1,'world_id'=>2]);
    verifyAdmin((int)$db->query('SELECT level FROM player_research WHERE player_id=1 AND world_id=2 AND research_code=?',[$node])->fetchColumn()===1&&!(int)$db->query('SELECT COUNT(*) FROM player_research WHERE world_id=1')->fetchColumn(),'research editor respects world');
    $troop=array_key_first(\Conquer\Game\City\TroopData::all());adminAction('set-troops',['troop_code'=>$troop,'count'=>37]);
    verifyAdmin((int)$db->query('SELECT count FROM city_troops WHERE city_id=1 AND troop_code=?',[$troop])->fetchColumn()===37,'troop editor uses real catalog');
    $settings=WorldSettings::defaults();$settings['resource_limit']=4;$settings['monster_limit']=3;$settings['batch_limit']=10;$settings['resource_weights']=['food'=>0,'lumber'=>0,'stone'=>0,'gold'=>100,'gems'=>0];$settings['monster_weights']=['Orc'=>100,'Skeleton'=>0,'Golem'=>0,'Treasure Goblin'=>0,'Deathkar'=>0,'dragon'=>0,'Magdar'=>0];
    adminAction('world-save',['name'=>'Test Realm','status'=>'running','settings'=>$settings]);
    $run=WorldSpawnService::tick(1);
    verifyAdmin($run[1]['resources_spawned']===4&&$run[1]['monsters_spawned']===3,'worker respects independent world caps');
    verifyAdmin((int)$db->query('SELECT COUNT(*) FROM field_objects WHERE object_type<>4')->fetchColumn()===0&&(int)$db->query('SELECT COUNT(*) FROM field_objects WHERE world_id=2')->fetchColumn()===0,'100 percent gold distribution and world isolation');
    verifyAdmin(WorldSpawnService::tick(1)[1]['status']==='not_due','interval prevents duplicate spawn pass');
    $settings['window_start']='22:00';$settings['window_end']='03:00';
    verifyAdmin(WorldSettings::inWindow($settings,strtotime('2026-01-01 23:00 UTC'))&&!WorldSettings::inWindow($settings,strtotime('2026-01-01 12:00 UTC')),'overnight schedule honors UTC boundaries');
    rejectsAdmin(static fn()=>WorldSettings::validate(array_replace($settings,['resource_weights'=>['food'=>99,'lumber'=>0,'stone'=>0,'gold'=>0,'gems'=>0]])),'distribution total must equal 100 percent');
    $settings['window_start']='00:00';$settings['window_end']='00:00';$settings['resource_chance_pct']=0;$settings['monster_chance_pct']=0;$settings['resource_limit']=10;$settings['monster_limit']=10;
    adminAction('world-save',['name'=>'Test Realm','status'=>'running','settings'=>$settings]);$run=WorldSpawnService::tick(1);
    verifyAdmin($run[1]['resources_spawned']===0&&$run[1]['monsters_spawned']===0,'zero chance never spawns');
    $busy=$db->query('SELECT * FROM field_monsters WHERE world_id=1 ORDER BY id LIMIT 1')->fetch();
    $db->execute('UPDATE field_monsters SET expires_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 HOUR) WHERE world_id=1');
    $db->execute("INSERT INTO marches(player_id,world_id,march_type,origin_city_id,target_x,target_y,target_type,target_id,departure_time,arrival_time,state) VALUES(1,1,5,1,?,?,3,?,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),'marching')",[$busy['coord_x'],$busy['coord_y'],$busy['id']]);
    $db->execute('UPDATE world_spawn_settings SET next_run_at=UTC_TIMESTAMP() WHERE world_id=1');WorldSpawnService::tick(1);
    verifyAdmin((int)$db->query('SELECT COUNT(*) FROM field_monsters WHERE world_id=1')->fetchColumn()===1&&(int)$db->query('SELECT id FROM field_monsters WHERE world_id=1')->fetchColumn()===(int)$busy['id'],'expiry cleanup preserves an actively targeted monster');
    $db->execute("UPDATE marches SET state='complete' WHERE player_id=1");$db->execute('UPDATE world_spawn_settings SET next_run_at=UTC_TIMESTAMP() WHERE world_id=1');WorldSpawnService::tick(1);
    verifyAdmin(!(int)$db->query('SELECT COUNT(*) FROM field_monsters WHERE world_id=1')->fetchColumn(),'expired target removed after army completes');
    $settings['resource_limit']=100;$settings['monster_limit']=100;$settings['resource_chance_pct']=100;$settings['monster_chance_pct']=100;$settings['batch_limit']=10;
    adminAction('world-save',['name'=>'Test Realm','status'=>'running','settings'=>$settings]);$mixed=WorldSpawnService::tick(1,false,'game')[1];
    verifyAdmin($mixed['resources_spawned']>0&&$mixed['monsters_spawned']>0&&$mixed['resources_spawned']+$mixed['monsters_spawned']<=10,'bounded pass replenishes both populations without starvation');
    $new=adminAction('world-create',['player_id'=>0,'name'=>'Third Realm','slug'=>'test-three','status'=>'paused','settings'=>$settings]);
    verifyAdmin((int)$new['world_id']>2&&WorldSpawnService::tick((int)$new['world_id'])[$new['world_id']]['status']==='paused','new world has independent persisted settings and starts paused');
    adminAction('world-events',['enabled'=>1,'invasion_enabled'=>1,'next_start'=>'2027-01-01T18:00','invasion_next_start'=>'2027-01-02T19:00','interval_hours'=>336,'duration_hours'=>168,'invasion_interval_hours'=>72]);
    $events=\Conquer\Game\Conquest\EventService::settings(1);verifyAdmin((int)$events['enabled']===1&&$events['next_start']==='2027-01-01 18:00:00','backoffice event form persists validated UTC schedule');
    // Render all pages with synthetic data; warnings are fatal in this test.
    $_SESSION=['admin'=>['id'=>1,'username'=>'TestAdmin','role'=>'superadmin'],'admin_csrf'=>str_repeat('a',64)];$_GET=['world_id'=>1];
    $_SERVER['REQUEST_METHOD']='POST';$_SERVER['REQUEST_URI']='/conquer/admin/action/ban';$_POST=['world_id'=>1,'player_id'=>1,'csrf_token'=>'forged'];
    ob_start();\Conquer\Admin\AdminController::handleAction();ob_end_clean();verifyAdmin(http_response_code()===403&&!(int)$db->query('SELECT is_banned FROM players WHERE id=1')->fetchColumn(),'controller blocks invalid CSRF before mutation');
    $_POST['csrf_token']=str_repeat('a',64);$_SESSION['admin']['role']='moderator';ob_start();\Conquer\Admin\AdminController::handleAction();ob_end_clean();verifyAdmin(http_response_code()===403,'controller blocks moderator mutations');$_SESSION['admin']['role']='superadmin';
    foreach(['dashboard','players','world','alliances','chat','bugReports','auditLog'] as $method){ob_start();\Conquer\Admin\AdminController::$method();$html=ob_get_clean();verifyAdmin(!str_contains($html,'Ansicht konnte nicht geladen')&&str_contains($html,'CONQUER'),'render '.$method);if($method==='world')file_put_contents(sys_get_temp_dir().'/conquer-backoffice-world.html',$html);}
    ob_start();\Conquer\Admin\AdminController::playerDetail(1);$html=ob_get_clean();verifyAdmin(!str_contains($html,'Ansicht konnte nicht geladen')&&str_contains($html,'Aster'),'render player detail');file_put_contents(sys_get_temp_dir().'/conquer-backoffice-player.html',$html);
    echo "ALL BACKOFFICE CHECKS PASSED\n";
}catch(Throwable $e){$failed=true;fwrite(STDERR,'FAIL '.$e->getMessage().' at '.$e->getFile().':'.$e->getLine()."\n");}
finally{if($created&&preg_match('/^conquer_admin_test_[a-f0-9]{12}$/D',$schema))$server->exec('DROP DATABASE `'.$schema.'`');}
exit($failed?1:0);
