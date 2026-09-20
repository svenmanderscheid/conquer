<?php
declare(strict_types=1);
/** Isolated schema + synthetic accounts. Never edits live reward settings or players. */
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));define('APP_BASE','/conquer');
require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';
\Conquer\Logger::init(sys_get_temp_dir().'/conquer-reward-test.log','ERROR');date_default_timezone_set('UTC');
set_error_handler(static function(int $severity,string $message,string $file,int $line):never{throw new ErrorException($message,0,$severity,$file,$line);});
use Conquer\Db\Connection;
use Conquer\Game\Rewards\RewardCatalog as R;
use Conquer\Admin\AdminService as A;
$checks=0;$fixture=null;$exit=0;
function ck(bool $ok,string $message):void{global $checks;if(!$ok)throw new RuntimeException($message);$checks++;echo "PASS $message\n";}
function reject(callable $fn,string $message):void{try{$fn();}catch(InvalidArgumentException|DomainException $e){ck(true,$message);return;}throw new RuntimeException('Accepted invalid request: '.$message);}
function formConfig(string $type,array $cfg):array{
    $form=$cfg;$form['rows']=[];
    foreach($cfg[$type==='chest'?'drop_table':($type==='dungeon'?'items':'drops')]as$r)$form['rows'][]=['target'=>isset($r['fragment_grade'])?'fragment:'.$r['fragment_grade']:(string)$r['item_code'],'quantity'=>$r['quantity']??$r['count']??1,'chance'=>($r['probability']??0)*100,'weight'=>$r['weight']??1];
    if($type==='monster'){$form['resources']=$cfg['resource_reward'];$form['gems_chance']=$cfg['gems_drop']['chance']*100;$form['gems_amount']=$cfg['gems_drop']['amount'];$form['charms']['chance']*=100;}
    if($type==='dungeon')$form['item_chance']*=100;
    return $form;
}
function action(string $type,string $key,array $form,int $revision=0,?string $op=null,int $admin=1,string $action='reward-save'):array{
    return A::execute($admin,$action,['world_id'=>1,'player_id'=>0,'reason'=>'Isolated reward test','operation_id'=>$op??bin2hex(random_bytes(16)),'source_type'=>$type,'source_key'=>$key,'revision'=>$revision,'config'=>$form]);
}
try{
    $fixture=new \ConquerTests\FeatureDatabase();$db=Connection::getInstance();
    \Conquer\Db\MigrationSql::apply($db->getPdo(),(string)file_get_contents(ROOT_DIR.'/migrations/0083_reward_overrides.sql'));
    $db->execute("INSERT INTO admin_users(id,username,password_hash,role) VALUES(1,'RewardAdmin',?,'superadmin'),(2,'RewardModerator',?,'moderator')",[password_hash('Fixture-Reward-123!',PASSWORD_DEFAULT),password_hash('Fixture-Reward-123!',PASSWORD_DEFAULT)]);
    foreach(['monster','dungeon','chest','expedition']as$type){foreach(R::sources($type)as$key=>$source){$cfg=R::defaults($type,(string)$key);R::validate($type,(string)$key,formConfig($type,$cfg));}ck(true,'Every '.$type.' source has a valid, editable default');}
    $f=formConfig('monster',R::defaults('monster','20209901'));$f['rows']=[['target'=>'10103001','quantity'=>7,'chance'=>100],['target'=>'10103002','quantity'=>9,'chance'=>0]];$f['gems_chance']=100;$f['gems_amount']=11;
    $op=bin2hex(random_bytes(16));$a=action('monster','20209901',$f,0,$op);$b=action('monster','20209901',$f,0,$op);
    ck(!$a['duplicate']&&$b['duplicate']&&(int)$db->query('SELECT COUNT(*) FROM reward_overrides')->fetchColumn()===1,'Duplicate save creates exactly one revision');
    ck((int)$db->query("SELECT COUNT(*) FROM admin_audit_log WHERE action='admin.reward-save'")->fetchColumn()===1,'Save and audit commit together');
    $d=\Conquer\Game\Map\MonsterData::get(20209901);
    ck($d['gems_drop']['amount']===11&&R::rollItems($d['drops'])===[10103001=>7],'Monster consumer uses exact configured 100% and 0% drops');
    reject(fn()=>action('monster','20209901',$f,0),'Stale version cannot overwrite a newer save');
    reject(fn()=>action('monster','20209901',$f,1,null,2),'Moderator cannot save');
    $bad=$f;$bad['rows'][0]['chance']=101;reject(fn()=>action('monster','20209901',$bad,1),'Chance above 100 is rejected');
    $bad=$f;$bad['rows'][0]['target']='99999999';reject(fn()=>action('monster','20209901',$bad,1),'Unknown item is rejected');
    $bad=$f;$bad['rows'][0]['quantity']=-1;reject(fn()=>action('monster','20209901',$bad,1),'Negative quantity is rejected');
    $bad=$f;$bad['rows'][0]['chance']=['100'];reject(fn()=>action('monster','20209901',$bad,1),'Array payload is rejected');
    $bad=$f;$bad['rows'][]=$bad['rows'][0];reject(fn()=>action('monster','20209901',$bad,1),'Duplicate item rows are rejected');
    reject(fn()=>action('monster','../../config/database.php',$f),'Arbitrary source paths are rejected');
    ck((int)$db->query('SELECT revision FROM reward_overrides')->fetchColumn()===1,'Rejected requests do not change the saved revision');
    action('monster','20209901',[],1,null,1,'reward-reset');
    $resetDrops=array_map(static fn(array $drop):array=>array_intersect_key($drop,array_flip(['item_code','count','probability'])),\Conquer\Game\Map\MonsterData::get(20209901)['drops']);
    ck(R::override('monster','20209901')===null&&$resetDrops===R::defaults('monster','20209901')['drops'],'Reset restores shipped default and clears runtime cache');
    reject(fn()=>action('monster','20209901',$f,0),'Reset tombstone prevents an old form from overwriting it');
    $rallyKey=(string)array_key_first(array_filter(R::sources('monster'),fn($s)=>$s['definition']['type']==='rally'));
    $rallyForm=formConfig('monster',R::defaults('monster',$rallyKey));$rallyForm['rows']=[['target'=>'10103001','quantity'=>13,'chance'=>100]];action('monster',$rallyKey,$rallyForm);
    ck(\Conquer\Game\Rally\MonsterRally::drops(R::sources('monster')[$rallyKey]['definition'])[0]['count']===13,'Rally pool uses the source override');
    $oldDungeon=\Conquer\Game\Dungeon\DungeonRules::catalog()['dungeons'][0];$key=$oldDungeon['dungeon_code'];
    $df=formConfig('dungeon',R::defaults('dungeon',$key));$df['fragments']=17;$df['item_chance']=100;$df['item_quantity']=4;$df['rows']=[['target'=>'10103001','weight'=>1],['target'=>'10103002','weight'=>0]];
    action('dungeon',$key,$df);$newDungeon=\Conquer\Game\Dungeon\DungeonRules::catalog()['dungeons'][0];
    ck(!isset($oldDungeon['reward_config'])&&$newDungeon['reward_config']['fragments']===17,'Dungeon overrides apply to new definitions while old snapshots remain unchanged');
    $result=new ReflectionMethod(\Conquer\Game\Dungeon\DungeonRules::class,'result');$sim=$result->invoke(null,true,[],1000.0,$newDungeon,'normal',0.0,0.0,'skip',false);
    $reward=new ReflectionMethod(\Conquer\Game\Dungeon\DungeonService::class,'reward');$award=$reward->invoke(null,$sim,['player_id'=>1],true);
    ck($award['fragments']===17&&$award['item_code']===10103001&&$award['item_quantity']===4,'Dungeon settlement honors fragment quantity, item weights and item quantity');
    $lose=$reward->invoke(null,$sim,['player_id'=>1],false);ck($lose['fragments']===0&&$lose['item_quantity']===0,'Failed dungeon never awards configured loot');
    $bad=$df;$bad['rows'][0]['weight']=0;reject(fn()=>action('dungeon',$key,$bad,1),'Nonzero dungeon item chance requires a positive pool');
    $cf=['rolls'=>2,'rows'=>[['target'=>'10103001','weight'=>1,'quantity'=>3],['target'=>'10103002','weight'=>0,'quantity'=>5]]];action('chest','silver',$cf);
    $draws=\Conquer\Game\Treasure\ChestService::rollDropTable('silver');ck(count($draws)===2&&array_column($draws,'item_code')===[10103001,10103001],'Daily chest uses configured rolls and never selects zero weight');
    $db->execute("INSERT INTO players(id,username,email,password_hash) VALUES(1,'RewardPlayer','reward@example.invalid','unused')");
    $chest=new ReflectionMethod(\Conquer\Game\Kingdom\KingdomInventory::class,'chest');$db->transaction(fn()=>$chest->invoke(null,1,['chest_type'=>'silver']));
    ck((int)$db->query('SELECT quantity FROM player_inventory WHERE player_id=1 AND item_code=10103001')->fetchColumn()===6,'Owned inventory chests pay out the same configured table');
    $ef=formConfig('expedition',R::defaults('expedition','ashen_lord.normal'));$ef['gems']=19;$ef['resources']['gold']=123;$ef['rows']=[['target'=>'10103001','quantity'=>2,'chance'=>100]];action('expedition','ashen_lord.normal',$ef);
    $e=\Conquer\Game\Expedition\EncounterCatalog::create('ashen_lord','normal',1,1);ck($e['reward']['gold']===123&&$e['reward_gems']===19&&$e['reward_drops'][0]['count']===2,'Expedition snapshots carry configured resources, gems and item drops');
    action('monster','20200101',$f);ck(\Conquer\Game\Map\MonsterData::get(20200100)['drops'][0]['count']===7,'Legacy world codes resolve to the same editable monster definition');
    action('monster','20209901',$f,2);
    $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y) VALUES(1,1,1,'Reward Home',60,60)");
    foreach(\Conquer\Game\City\CityState::BUILDING_CODES as $building)$db->execute('INSERT INTO city_buildings(city_id,building_code,level) VALUES(1,?,1)',[$building]);
    $db->execute("INSERT INTO field_monsters(world_id,monster_code,coord_x,coord_y,hp_current,monster_type,expires_at) VALUES(1,20209901,70,70,1,'solo',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR))");$monsterId=$db->lastInsertId();
    $db->execute("INSERT INTO marches(player_id,world_id,march_type,origin_city_id,target_x,target_y,target_type,target_id,departure_time,arrival_time,state,troops_json) VALUES(1,1,5,1,70,70,3,?,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 120 SECOND),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND),'marching',?)",[$monsterId,json_encode(['50100101'=>1000])]);$marchId=$db->lastInsertId();
    \Conquer\Game\World\WorldContext::bind(1,1);\Conquer\Game\March\MarchTick::runForPlayer(1);
    $haul=json_decode($db->query('SELECT haul_json FROM marches WHERE id=?',[$marchId])->fetchColumn(),true);
    ck(($haul['items'][10103001]??null)===7&&($haul['loot']['gems']??null)===11,'Actual solo combat stores configured items and gems in the return haul');
    ck((int)$db->query('SELECT quantity FROM player_inventory WHERE player_id=1 AND item_code=10103001')->fetchColumn()===6,'Solo rewards wait for the army return');
    $db->execute('UPDATE marches SET return_time=UTC_TIMESTAMP() WHERE id=?',[$marchId]);\Conquer\Game\March\MarchTick::runForPlayer(1);\Conquer\Game\March\MarchTick::runForPlayer(1);
    ck((int)$db->query('SELECT quantity FROM player_inventory WHERE player_id=1 AND item_code=10103001')->fetchColumn()===13&&(int)$db->query('SELECT gems FROM players WHERE id=1')->fetchColumn()===11,'Returning solo army credits items and gems exactly once');
    action('monster','20209901',[],3,null,1,'reward-reset');
    $_SESSION=['admin'=>['id'=>1,'username'=>'RewardAdmin','role'=>'superadmin'],'admin_csrf'=>str_repeat('a',64)];
    foreach(['monster','dungeon','chest','expedition']as$type){$_GET=['world_id'=>1,'type'=>$type];ob_start();\Conquer\Admin\AdminController::rewards();$html=ob_get_clean();ck(str_contains($html,'data-reward-editor')&&!str_contains($html,'Ansicht konnte nicht geladen'),'Render '.$type.' editor');}
    $_GET=[];ob_start();\Conquer\Admin\AdminController::items();$html=ob_get_clean();ck(str_contains($html,'item-catalog-grid')&&!str_contains($html,'Ansicht konnte nicht geladen'),'Render illustrated catalog');
    echo "ALL $checks REWARD CHECKS PASSED\n";
    if(in_array('--browser',$argv,true)){
        $routes= <<<'PHP'
        define('APP_BASE','');
        $path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
        if(str_starts_with($path,'/assets/')){
            $file=realpath(ROOT_DIR.$path);$assets=realpath(ROOT_DIR.'/assets').DIRECTORY_SEPARATOR;
            if(!$file||!str_starts_with($file,$assets)||!is_file($file)){http_response_code(404);exit;}
            $ext=pathinfo($file,PATHINFO_EXTENSION);header('Content-Type: '.(['css'=>'text/css','js'=>'application/javascript','svg'=>'image/svg+xml','png'=>'image/png','webp'=>'image/webp'][$ext]??'application/octet-stream'));readfile($file);exit;
        }
        session_name('conquer_reward_fixture');session_start();
        if($path==='/admin/login'){if($_SERVER['REQUEST_METHOD']==='POST')\Conquer\Admin\AdminController::loginPost();else \Conquer\Admin\AdminController::loginPage();}
        elseif($path==='/admin/logout')\Conquer\Admin\AdminController::logout();
        elseif(str_starts_with($path,'/admin/action/'))\Conquer\Admin\AdminController::handleAction();
        else{match($path){'/admin'=>\Conquer\Admin\AdminController::dashboard(),'/admin/rewards'=>\Conquer\Admin\AdminController::rewards(),'/admin/items'=>\Conquer\Admin\AdminController::items(),'/admin/world'=>\Conquer\Admin\AdminController::world(),'/admin/lands'=>\Conquer\Admin\AdminController::lands(),'/admin/players'=>\Conquer\Admin\AdminController::players(),'/admin/audit'=>\Conquer\Admin\AdminController::auditLog(),default=>http_response_code(404)};}
        PHP;
        $url=$fixture->serve($routes);
        $process=proc_open(['node',ROOT_DIR.'/tests/admin_backoffice.cjs',$url],[0=>['pipe','r'],1=>STDOUT,2=>STDERR],$pipes,ROOT_DIR,null,['bypass_shell'=>true]);
        if(!is_resource($process))throw new RuntimeException('Browser test did not start.');fclose($pipes[0]);
        if(proc_close($process)!==0)throw new RuntimeException('Browser checks failed.');
    }
}catch(Throwable $e){$exit=1;fwrite(STDERR,'FAIL '.$e->getMessage().' at '.$e->getFile().':'.$e->getLine()."\n");}
finally{if($fixture)$fixture->close();}
exit($exit);
