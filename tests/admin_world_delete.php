<?php
declare(strict_types=1);
/** Synthetic accounts in a disposable schema; no live worlds or players are mutated. */
if(PHP_SAPI!=='cli')exit(1);
ob_start();
define('ROOT_DIR',dirname(__DIR__));define('APP_BASE','');date_default_timezone_set('UTC');
require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';
use Conquer\Db\Connection;
use Conquer\Admin\AdminService;
use Conquer\Game\Locale;
$fixture=null;$checks=0;$exit=0;
function wdCheck(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException($label);$checks++;echo "PASS $label\n";}
function wdAction(array $input=[],?string $op=null,int $admin=1):array{return AdminService::execute($admin,'world-delete',array_replace(['world_id'=>2,'player_id'=>0,'confirm_name'=>'Delete Realm','confirm_delete'=>'1','reason'=>'Isolated world deletion check','operation_id'=>$op??bin2hex(random_bytes(16))],$input));}
function wdReject(callable $fn,string $label):void{try{$fn();}catch(InvalidArgumentException|DomainException $e){wdCheck(true,$label);return;}throw new RuntimeException('Accepted invalid request: '.$label);}
function wdSeed(Connection $db,string $table,array $values):void{
    // Fill otherwise-required fixture-only fields from their schema, respecting enums and defaults.
    foreach($db->query('SHOW COLUMNS FROM `'.$table.'`')->fetchAll() as $field){
        $key=$field['Field'];if(array_key_exists($key,$values)||$field['Null']==='YES'||$field['Default']!==null||str_contains($field['Extra'],'auto_increment'))continue;
        $type=$field['Type'];
        if(str_starts_with($type,'enum(')){preg_match("/'([^']+)'/",$type,$match);$values[$key]=$match[1];}
        elseif(preg_match('/int|decimal|float|double|bit/',$type))$values[$key]=1;
        elseif(str_contains($type,'date')||str_contains($type,'time'))$values[$key]='2026-10-09 12:00:00';
        elseif(str_contains($key,'json')||$type==='json')$values[$key]='{}';
        else $values[$key]='fixture';
    }
    $names=implode(',',array_map(static fn($key)=>'`'.$key.'`',array_keys($values)));
    $db->execute('INSERT INTO `'.$table.'`('.$names.') VALUES('.implode(',',array_fill(0,count($values),'?')).')',array_values($values));
}
try{
    $fixture=new \ConquerTests\FeatureDatabase();$db=Connection::getInstance();
    $db->execute("INSERT INTO worlds(id,name,slug,status,map_size,map_seed) VALUES(2,'Delete Realm','delete-realm','closed',256,42),(3,'Browser Realm','browser-realm','closed',256,42)");
    $db->execute("INSERT INTO admin_users(id,username,password_hash,role) VALUES(1,'WorldAdmin',?,'superadmin'),(2,'WorldModerator',?,'moderator')",[password_hash('Fixture-World-123!',PASSWORD_DEFAULT),password_hash('Fixture-World-123!',PASSWORD_DEFAULT)]);
    wdSeed($db,'players',['id'=>1,'username'=>'WorldPlayer','email'=>'world@tests.invalid','gems'=>777]);
    foreach([1,2] as $world){
        wdSeed($db,'cities',['id'=>$world,'player_id'=>1,'world_id'=>$world,'name'=>'Fixture city '.$world,'coord_x'=>10,'coord_y'=>10]);
        wdSeed($db,'alliances',['id'=>$world,'world_id'=>$world,'name'=>'Fixture alliance '.$world,'tag'=>'F'.$world,'leader_id'=>1]);
        wdSeed($db,'city_buildings',['city_id'=>$world,'building_code'=>30100001,'level'=>1]);
        wdSeed($db,'building_queue',['id'=>$world,'city_id'=>$world,'building_code'=>30100001]);
        wdSeed($db,'hospital_wounded',['id'=>$world,'city_id'=>$world,'troop_code'=>40100101]);
        wdSeed($db,'marches',['id'=>$world,'world_id'=>$world,'player_id'=>1,'origin_city_id'=>$world,'target_x'=>20,'target_y'=>20]);
        wdSeed($db,'reinforcements',['id'=>$world,'march_id'=>$world,'sender_id'=>1,'sender_city_id'=>$world,'target_player_id'=>1,'target_city_id'=>$world]);
        wdSeed($db,'alliance_members',['id'=>$world,'alliance_id'=>$world,'player_id'=>1,'world_id'=>$world]);
        wdSeed($db,'alliance_gifts',['id'=>$world,'alliance_id'=>$world,'trigger_type'=>'fixture','gift_json'=>'{}','expires_at'=>'2026-10-20 00:00:00']);
        wdSeed($db,'alliance_gift_claims',['gift_id'=>$world,'player_id'=>1]);
        wdSeed($db,'alliance_help_requests',['id'=>$world,'player_id'=>1,'city_id'=>$world,'queue_id'=>$world]);
        wdSeed($db,'alliance_help_log',['id'=>$world,'request_id'=>$world,'helper_id'=>1]);
        wdSeed($db,'community_help_requests',['id'=>$world,'world_id'=>$world,'alliance_id'=>$world,'player_id'=>1,'city_id'=>$world,'queue_id'=>$world]);
        wdSeed($db,'community_help_log',['request_id'=>$world,'helper_id'=>1]);
        wdSeed($db,'alliance_calendar_events',['id'=>$world,'world_id'=>$world,'alliance_id'=>$world,'creator_id'=>1]);
        wdSeed($db,'alliance_event_rsvps',['event_id'=>$world,'player_id'=>1]);
        wdSeed($db,'alliance_polls',['id'=>$world,'world_id'=>$world,'alliance_id'=>$world,'creator_id'=>1]);
        wdSeed($db,'alliance_poll_votes',['poll_id'=>$world,'player_id'=>1]);
        wdSeed($db,'shrines',['id'=>$world,'world_id'=>$world,'shrine_code'=>'FIX'.$world,'coord_x'=>40,'coord_y'=>40]);
        wdSeed($db,'shrine_captures',['id'=>$world,'shrine_id'=>$world,'alliance_id'=>null]);
        wdSeed($db,'conquest_events',['id'=>$world,'world_id'=>$world]);
        wdSeed($db,'conquest_results',['event_id'=>$world]);
        wdSeed($db,'dungeon_runs',['id'=>$world,'world_id'=>$world,'dungeon_code'=>'fixture','week_key'=>'fixture'.$world,'leader_player_id'=>1]);
        wdSeed($db,'dungeon_rewards',['run_id'=>$world,'player_id'=>1]);
        wdSeed($db,'expeditions',['id'=>$world,'world_id'=>$world,'host_alliance_id'=>$world,'created_by'=>1]);
        wdSeed($db,'expedition_logs',['id'=>$world,'expedition_id'=>$world]);
        wdSeed($db,'territory_crown_cycles',['id'=>$world,'world_id'=>$world]);
        wdSeed($db,'territory_crown_scores',['cycle_id'=>$world,'alliance_id'=>0]);
        wdSeed($db,'territory_crown_control',['cycle_id'=>$world,'objective'=>'fixture','alliance_id'=>null]);
        wdSeed($db,'player_world_vip',['player_id'=>1,'world_id'=>$world,'vip_points'=>100]);
        wdSeed($db,'player_inventory',['player_id'=>1,'world_id'=>$world,'item_code'=>10106001,'quantity'=>3]);
        wdSeed($db,'trading_shop_purchases',['player_id'=>1,'scope_world_id'=>$world,'shop_mode'=>'vip','rotation'=>'fixture','offer_id'=>'fixture']);
        wdSeed($db,'reward_rule_revisions',['scope_world_id'=>$world,'source_type'=>'farm','source_key'=>'fixture','revision'=>1]);
        wdSeed($db,'notifications',['id'=>$world,'player_id'=>1,'type'=>'fixture','data_json'=>json_encode(['world_id'=>$world])]);
        wdSeed($db,'sessions',['id'=>$world,'player_id'=>1,'active_world_id'=>$world,'token'=>str_repeat((string)$world,64),'csrf_token'=>str_repeat('a',64),'expires_at'=>'2027-01-01 00:00:00']);
    }
    wdSeed($db,'notifications',['id'=>3,'player_id'=>1,'type'=>'legacy','data_json'=>'{}']);
    wdSeed($db,'player_inventory',['player_id'=>1,'world_id'=>0,'item_code'=>10103001,'quantity'=>9]);
    wdSeed($db,'trading_shop_purchases',['player_id'=>1,'scope_world_id'=>0,'shop_mode'=>'caravan','rotation'=>'fixture','offer_id'=>'fixture']);
    wdSeed($db,'reward_rule_revisions',['scope_world_id'=>0,'source_type'=>'farm','source_key'=>'fixture','revision'=>1]);
    // CREATE TABLE LIKE omits FKs. Restore representative real constraints, including non-cascading purchases.
    foreach([
        'cities'=>'ADD CONSTRAINT wd_city_world FOREIGN KEY(world_id) REFERENCES worlds(id)',
        'city_buildings'=>'ADD CONSTRAINT wd_building_city FOREIGN KEY(city_id) REFERENCES cities(id)',
        'marches'=>'ADD CONSTRAINT wd_march_city FOREIGN KEY(origin_city_id) REFERENCES cities(id)',
        'dungeon_rewards'=>'ADD CONSTRAINT wd_reward_run FOREIGN KEY(run_id) REFERENCES dungeon_runs(id)',
        'theme_bundle_orders'=>'ADD CONSTRAINT fk_theme_bundle_order_world FOREIGN KEY(world_id) REFERENCES worlds(id), ADD CONSTRAINT fk_theme_bundle_order_city FOREIGN KEY(city_id) REFERENCES cities(id)',
        'player_theme_bundle_purchases'=>'ADD CONSTRAINT wd_purchase_order FOREIGN KEY(order_id) REFERENCES theme_bundle_orders(id)',
        'theme_bundle_provider_events'=>'ADD CONSTRAINT wd_provider_order FOREIGN KEY(order_id) REFERENCES theme_bundle_orders(id)',
    ] as $table=>$clause)$db->execute('ALTER TABLE `'.$table.'` '.$clause);
    \Conquer\Db\MigrationSql::apply($db->getPdo(),(string)file_get_contents(ROOT_DIR.'/migrations/0137_world_deletion_purchase_history.sql'));
    $order='pb_'.str_repeat('a',32);
    wdSeed($db,'theme_bundle_orders',['id'=>$order,'player_id'=>1,'world_id'=>2,'city_id'=>2,'skin_code'=>'ironkeep','step'=>1,'status'=>'fulfilled']);
    wdSeed($db,'player_theme_bundle_purchases',['player_id'=>1,'skin_code'=>'ironkeep','step'=>1,'order_id'=>$order]);
    wdSeed($db,'theme_bundle_provider_events',['provider'=>'fixture','event_id'=>'fixture','order_id'=>$order]);
    wdSeed($db,'player_castle_skins',['player_id'=>1,'skin_code'=>'ironkeep']);
    // Future scoped tables and their declared descendants must participate automatically.
    $db->execute('CREATE TABLE wd_future_world(id INT PRIMARY KEY,world_id INT NOT NULL,FOREIGN KEY(world_id) REFERENCES worlds(id)) ENGINE=InnoDB');
    $db->execute('CREATE TABLE wd_future_child(id INT PRIMARY KEY,parent_id INT NOT NULL,FOREIGN KEY(parent_id) REFERENCES wd_future_world(id)) ENGINE=InnoDB');
    $db->execute('INSERT INTO wd_future_world VALUES(1,1),(2,2)');$db->execute('INSERT INTO wd_future_child VALUES(1,1),(2,2)');
    wdReject(fn()=>wdAction([],null,2),'Moderator cannot delete worlds');
    wdReject(fn()=>wdAction(['world_id'=>999]),'Missing world rejects deletion');
    wdReject(fn()=>wdAction(['world_id'=>1,'confirm_name'=>'Fixture Realm']),'Running world must be closed first');
    wdReject(fn()=>wdAction(['confirm_name'=>'delete realm']),'World name confirmation is exact');
    wdReject(fn()=>wdAction(['confirm_name'=>['Delete Realm']]),'Array confirmation rejected');
    wdReject(fn()=>wdAction(['confirm_delete'=>'0']),'Explicit acknowledgement required');
    wdReject(fn()=>wdAction(['reason'=>'']),'Audit reason required');
    $db->execute("UPDATE theme_bundle_orders SET status='pending' WHERE id=?",[$order]);
    wdReject(fn()=>wdAction(),'Unfinished purchases block deletion');
    $db->execute("UPDATE theme_bundle_orders SET status='fulfilled' WHERE id=?",[$order]);
    wdCheck((int)$db->query('SELECT COUNT(*) FROM admin_operations')->fetchColumn()===0,'Rejected deletion leaves no operation receipts');
    $snapshot=[];foreach($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table)$snapshot[$table]=$db->query('SELECT * FROM `'.$table.'`')->fetchAll();
    $db->execute("CREATE TRIGGER wd_fail_delete BEFORE DELETE ON worlds FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Controlled late deletion failure'");
    try{wdAction();throw new RuntimeException('Late failure was ignored');}catch(PDOException $e){wdCheck(str_contains($e->getMessage(),'Controlled late deletion failure'),'Database failure aborts deletion');}
    foreach($snapshot as $table=>$rows)wdCheck($db->query('SELECT * FROM `'.$table.'`')->fetchAll()===$rows,'Rollback preserves '.$table);
    $db->execute('DROP TRIGGER wd_fail_delete');
    $op=bin2hex(random_bytes(16));$result=wdAction([],$op);$replay=wdAction([],$op);
    wdCheck(!$result['duplicate']&&$replay['duplicate'],'Successful deletion replays its receipt after the world is gone');
    wdCheck((int)$db->query("SELECT COUNT(*) FROM admin_audit_log WHERE action='admin.world-delete'")->fetchColumn()===1,'Deletion has one durable audit');
    wdCheck((int)$db->query('SELECT COUNT(*) FROM worlds WHERE id=2')->fetchColumn()===0,'Selected world removed');
    foreach($result['after']['deleted_rows'] as $table=>$count){
        if($table==='worlds')continue;
        wdCheck((int)$db->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn()===count($snapshot[$table])-$count,'Deletion removes only owned rows in '.$table);
    }
    wdCheck((int)$db->query('SELECT COUNT(*) FROM cities WHERE id=1')->fetchColumn()===1&&(int)$db->query('SELECT COUNT(*) FROM sessions WHERE id=1')->fetchColumn()===1,'Other world and its sessions survive');
    wdCheck((int)$db->query('SELECT COUNT(*) FROM sessions WHERE id=2')->fetchColumn()===0,'Sessions using deleted world revoked');
    wdCheck((int)$db->query('SELECT gems FROM players WHERE id=1')->fetchColumn()===777&&(int)$db->query('SELECT quantity FROM player_inventory WHERE world_id=0')->fetchColumn()===9,'Accounts and global inventory preserved');
    wdCheck($db->query('SELECT world_id,city_id FROM theme_bundle_orders WHERE id=?',[$order])->fetch()===['world_id'=>null,'city_id'=>null]&&(int)$db->query('SELECT COUNT(*) FROM player_theme_bundle_purchases')->fetchColumn()===1&&(int)$db->query('SELECT COUNT(*) FROM theme_bundle_provider_events')->fetchColumn()===1,'Purchase receipts, entitlements and provider events preserved');
    wdReject(fn()=>wdAction(['reason'=>'Changed payload'],$op),'Receipt cannot be replayed with another payload');
    foreach(['en','de','fr','lb'] as $locale){$catalog=json_decode((string)file_get_contents(ROOT_DIR.'/data/i18n/'.$locale.'.json'),true,512,JSON_THROW_ON_ERROR);foreach(Locale::catalog('en') as $key=>$value)if(str_starts_with($key,'admin.world_delete.'))wdCheck(isset($catalog[$key]),$locale.' supplies '.$key);}

    if(in_array('--browser',$argv,true)){
        $routes= <<<'PHP'
        define('APP_BASE','');$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
        if(str_starts_with($path,'/assets/')){$file=realpath(ROOT_DIR.$path);if(!$file||!str_starts_with($file,realpath(ROOT_DIR.'/assets').DIRECTORY_SEPARATOR)||!is_file($file)){http_response_code(404);exit;}header('Content-Type: '.(['css'=>'text/css','js'=>'application/javascript','svg'=>'image/svg+xml','png'=>'image/png','webp'=>'image/webp','woff2'=>'font/woff2'][pathinfo($file,PATHINFO_EXTENSION)]??'application/octet-stream'));readfile($file);exit;}
        session_name('conquer_world_delete_fixture');session_start();
        if($path==='/admin/login'){if($_SERVER['REQUEST_METHOD']==='POST')\Conquer\Admin\AdminController::loginPost();else \Conquer\Admin\AdminController::loginPage();}
        elseif($path==='/admin/logout')\Conquer\Admin\AdminController::logout();
        elseif(str_starts_with($path,'/admin/action/'))\Conquer\Admin\AdminController::handleAction();
        elseif($path==='/admin/world')\Conquer\Admin\AdminController::world();
        elseif($path==='/admin')\Conquer\Admin\AdminController::dashboard();
        else http_response_code(404);
        PHP;
        $url=$fixture->serve($routes);
        if(in_array('--serve',$argv,true)){echo "FIXTURE_URL $url\n";flush();fgets(STDIN);}
        else{
            $process=proc_open(['node',ROOT_DIR.'/tests/admin_world_delete.cjs',$url],[0=>['pipe','r'],1=>STDOUT,2=>STDERR],$pipes,ROOT_DIR,null,['bypass_shell'=>true]);
            if(!is_resource($process))throw new RuntimeException('Browser checks did not start');fclose($pipes[0]);if(proc_close($process)!==0)throw new RuntimeException('Browser checks failed');
        }
    }
    $db->execute("UPDATE worlds SET status='closed' WHERE id=1");
    if($db->query('SELECT id FROM worlds WHERE id=3')->fetchColumn())wdAction(['world_id'=>3,'confirm_name'=>'Browser Realm']);
    wdReject(fn()=>wdAction(['world_id'=>1,'confirm_name'=>'Fixture Realm']),'The final world is protected');
    $db->execute("INSERT INTO worlds(id,name,slug,status,map_size,map_seed) VALUES(4,'Surviving Realm','surviving-realm','running',256,42)");
    wdAction(['world_id'=>1,'confirm_name'=>'Fixture Realm']);
    \Conquer\Auth\Session::create(1,'127.0.0.1','isolated world deletion');
    $newSession=$db->query('SELECT * FROM sessions WHERE player_id=1 ORDER BY id DESC LIMIT 1')->fetch();
    wdCheck((int)$newSession['active_world_id']===4,'Login uses a surviving world after original world 1 is deleted');
    $_COOKIE[\Conquer\Auth\Session::COOKIE_NAME]=$newSession['token'];$session=\Conquer\Auth\Session::current();
    wdCheck($session!==null&&\Conquer\Game\World\WorldContext::id()===4,'Account without a remaining city has a valid world context');
    $joined=\Conquer\Game\World\WorldService::action($session,['action'=>'join','world_id'=>4,'expected_world_id'=>4,'request_id'=>'world_delete_reentry_0001']);
    wdCheck($joined['city_id']>0&&(int)$db->query('SELECT COUNT(*) FROM cities WHERE player_id=1 AND world_id=4')->fetchColumn()===1,'Preserved account can explicitly start again in a surviving world');
    echo "PASS $checks world deletion checks\n";
}catch(Throwable $e){$exit=1;fwrite(STDERR,'FAIL '.$e->getMessage().' at '.$e->getFile().':'.$e->getLine()."\n");}
finally{if($fixture)$fixture->close();}
exit($exit);
