<?php
declare(strict_types=1);
/** Integration of world reward rules, immutable versions and regional spawn snapshots. */
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));define('APP_BASE','/conquer');
require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';
date_default_timezone_set('UTC');\Conquer\Logger::init(sys_get_temp_dir().'/conquer-regional-rewards.log','ERROR');
use Conquer\Db\Connection;
use Conquer\Game\World\{WorldContext,LandProgressService,LandRules,RegionalSpawns};
use Conquer\Game\Rewards\RewardCatalog;
use Conquer\Game\Map\{MonsterData,WorldPlacement};
use Conquer\Admin\AdminService;
$fixture=new \ConquerTests\FeatureDatabase();$db=Connection::getInstance();$checks=0;
function checkR(bool $ok,string $message):void{global $checks;if(!$ok)throw new RuntimeException($message);$checks++;echo "PASS $message\n";}
function saveR(int $world,string $scope,int $revision,int $count,string $action='reward-save'):array{
    return AdminService::execute(1,$action,['world_id'=>$world,'player_id'=>0,'reason'=>'Isolated regional reward test','operation_id'=>bin2hex(random_bytes(16)),
        'source_type'=>'monster','source_key'=>'20209901','reward_scope'=>$scope,'revision'=>$revision,
        'config'=>['rows'=>[['target'=>'10103001','quantity'=>$count,'chance'=>100]],'resources'=>['food'=>10,'lumber'=>0,'stone'=>0,'gold'=>0],
            'gems_chance'=>0,'gems_amount'=>0,'charms'=>['chance'=>0,'normal'=>100,'epic'=>0,'legendary'=>0]]]);
}
try{
    $db->execute("INSERT INTO admin_users(id,username,password_hash,role)VALUES(1,'RegionalAdmin','unused','superadmin')");
    $db->execute("INSERT INTO worlds(id,name,slug,map_size,status)VALUES(2,'New land','new-land',256,'running')");
    LandProgressService::ensureWorld(1);LandProgressService::ensureWorld(2);
    saveR(1,'global',0,2);saveR(2,'world',0,7);
    checkR(RewardCatalog::effective('monster','20209901',1)['drops'][0]['count']===2,'world without override inherits global drops');
    checkR(RewardCatalog::effective('monster','20209901',2)['drops'][0]['count']===7,'world override wins without changing another world');
    $frozen=WorldContext::run(2,static fn()=>MonsterData::get(20209901));
    saveR(2,'world',1,9);
    checkR($frozen['drops'][0]['count']===7&&WorldContext::run(2,static fn()=>MonsterData::get(20209901))['drops'][0]['count']===9,'captured definition stays stable after a reward revision');
    checkR(RewardCatalog::effective('monster','20209901',2)['charms']['chance']===1,'admin cannot disable the guaranteed charm');
    try{saveR(2,'world',1,4);throw new RuntimeException('Stale edit accepted');}catch(InvalidArgumentException $e){checkR(true,'stale world reward revision is rejected');}
    saveR(2,'world',2,0,'reward-reset');
    checkR(RewardCatalog::effective('monster','20209901',2)['drops'][0]['count']===2,'world reset inherits the global rules');
    $history=$db->query("SELECT revision,config_json FROM reward_rule_revisions WHERE scope_world_id=2 AND source_type='monster' AND source_key='20209901' ORDER BY revision")->fetchAll();
    checkR(count($history)===3&&json_decode($history[0]['config_json'],true)['drops'][0]['count']===7&&$history[2]['config_json']===null,'history preserves old rules and the reset revision');
    $low=RegionalSpawns::candidates(2,'Orc',24,24);
    checkR($low&&max(array_column($low,'level'))<=2,'outer level-one land starts with low level monsters');
    checkR(RegionalSpawns::candidates(2,'Orc',120,120)===[],'closed central land cannot produce monster candidates');
    checkR(RegionalSpawns::candidates(1,'dragon',24,24)===[]&&RegionalSpawns::candidates(1,'Magdar',24,24)===[],'inactive dragons and Magdar never enter spawn candidates');
    $land=LandProgressService::at(2,24,24);
    $db->execute('UPDATE world_land_parts SET current_level=9 WHERE id=?',[$land['id']]);LandProgressService::invalidate(2);
    $high=RegionalSpawns::candidates(2,'Orc',24,24);
    checkR($high&&min(array_column($high,'level'))>=7,'fully developed outer land unlocks high level candidates');
    $db->execute("INSERT INTO field_monsters(world_id,monster_code,coord_x,coord_y,hp_current)VALUES(2,20209901,24,24,1)");$monster=$db->lastInsertId();
    RegionalSpawns::stamp('field_monsters',$monster,2,24,24);
    $spawn=$db->query('SELECT * FROM field_monsters WHERE id=?',[$monster])->fetch();
    checkR((int)$spawn['regional_level_at_spawn']===9&&(int)$spawn['effective_monster_level']===1&&(int)$spawn['regional_point_value']===100,'spawn stores distinct land level, monster level and point value');
    $rules=LandRules::get(2);
    $form=['world_id'=>2,'player_id'=>0,'operation_id'=>bin2hex(random_bytes(16)),'reason'=>'Regional rules integration','revision'=>$rules['revision'],'rules'=>['monster_points_per_level'=>200,'thresholds'=>[1=>1500]]];
    $saved=AdminService::execute(1,'land-rules-save',$form);$replayed=AdminService::execute(1,'land-rules-save',$form);
    checkR(!$saved['duplicate']&&$replayed['duplicate']&&LandRules::get(2)['thresholds'][1]===1500&&LandRules::get(1)['thresholds'][1]===1000,'admin land rules are world-scoped and replay exactly once');
    checkR((int)$db->query('SELECT regional_point_value FROM field_monsters WHERE id=?',[$monster])->fetchColumn()===100,'changing land rules preserves existing monster point snapshots');
    foreach(['0084_land_progression.sql','0085_monster_charms.sql','0086_reward_world_revisions.sql'] as $migration)\Conquer\Db\MigrationSql::apply($db->getPdo(),(string)file_get_contents(ROOT_DIR.'/migrations/'.$migration));
    checkR((int)$db->query("SELECT COUNT(*) FROM world_land_zones WHERE world_id=2 AND status='locked'")->fetchColumn()===2&&(int)$db->query('SELECT current_level FROM world_land_parts WHERE id=?',[$land['id']])->fetchColumn()===9,'repeated migrations preserve new-world locks and developed lands');
    $gateForm=$form;$gateForm['operation_id']=bin2hex(random_bytes(16));$gateForm['revision']=LandRules::get(2)['revision'];
    $gateForm['rules']=['gates'=>['middle'=>['minimum_count'=>1,'ratio'=>.001,'not_before_days'=>0]]];
    AdminService::execute(1,'land-rules-save',$gateForm);
    checkR($db->query("SELECT status FROM world_land_zones WHERE world_id=2 AND zone_key='middle'")->fetchColumn()==='open','saving fulfilled admin gate rules immediately opens the zone atomically');
    $db->execute("INSERT INTO map_charms(world_id,coord_x,coord_y,stat_category,grade,charm_code,expires_at)VALUES(1,30,30,'construction','normal',10700001,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR))");
    $blocked=$db->transaction(static function($db){WorldPlacement::lockWorld($db,1);return !WorldPlacement::canPlace($db,1,'monster',30,30);});
    checkR($blocked,'an active charm reserves its original tile against respawn');
    $_SESSION=['admin'=>['id'=>1,'username'=>'RegionalAdmin','role'=>'superadmin'],'admin_csrf'=>str_repeat('a',64)];$_GET=['world_id'=>2];
    ob_start();\Conquer\Admin\AdminController::lands();$html=ob_get_clean();
    checkR(str_contains($html,'land-rules-save')&&str_contains($html,'1024 Landteile')&&!str_contains($html,'Ansicht konnte nicht geladen'),'land administration renders the real regional state');
    echo "ALL $checks REGIONAL REWARD CHECKS PASSED\n";
}finally{$fixture->close();}
