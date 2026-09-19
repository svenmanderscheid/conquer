<?php
declare(strict_types=1);

/** Local, sequential service benchmark. No live-player fixtures or HTTP traffic. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }
define('ROOT_DIR', dirname(__DIR__));
define('APP_BASE', '/conquer');
require ROOT_DIR . '/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR . '/src'))->register();
require ROOT_DIR . '/tests/Support/FeatureDatabase.php';
date_default_timezone_set('UTC');
set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

use Conquer\Auth\OAuth;
use Conquer\Db\Connection;
use Conquer\Game\City\{CityState,TroopData};
use Conquer\Game\Community\CommunityService;
use Conquer\Game\Conquest\EventService;
use Conquer\Game\Kingdom\KingdomService;
use Conquer\Game\World\{WorldContext,WorldSettings,WorldSpawnService};

function benchmarkRequire(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function benchmarkStats(array $values): array
{
    sort($values, SORT_NUMERIC); $n=count($values);
    return ['samples'=>$n,'median_ms'=>round(($values[intdiv($n-1,2)]+$values[intdiv($n,2)])/2,3),
        'p95_ms'=>round($values[(int)ceil($n*.95)-1],3),'max_ms'=>round(max($values),3)];
}

/** Timings exclude assertion/JSON validation overhead, but include normal lazy service ticks. */
function benchmarkPackage(int $player, int $world): array
{
    return WorldContext::run($world, static function () use ($player,$world): array {
        $start=hrtime(true);$city=CityState::loadForPlayer($player);$cityEnd=hrtime(true);
        $kingdom=KingdomService::state($player);$kingdomEnd=hrtime(true);
        $community=CommunityService::state($player);$communityEnd=hrtime(true);
        $events=EventService::state($player);$end=hrtime(true);
        benchmarkRequire(is_array($city)&&(int)$city['city']['player_id']===$player&&(int)$city['city']['world_id']===$world,'City response crossed worlds.');
        benchmarkRequire(count($city['buildings'])===count(CityState::BUILDING_CODES),'Incomplete city buildings.');
        benchmarkRequire((int)$kingdom['profile']['id']===$player&&count($kingdom['rankings'])===100,'Incomplete kingdom snapshot.');
        $validPlayer=static fn(int $id):bool=>$id>=1+($world-1)*100&&$id<=$world*100;
        foreach($kingdom['rankings'] as $row)benchmarkRequire($validPlayer((int)$row['player_id']),'Ranking crossed worlds.');
        benchmarkRequire((int)$kingdom['alliance']['world_id']===$world&&count($kingdom['alliance']['members'])===10,'Alliance response crossed worlds.');
        foreach($kingdom['alliance']['members']as$row)benchmarkRequire($validPlayer((int)$row['player_id']),'Alliance member crossed worlds.');
        foreach($kingdom['alliances']as$row)benchmarkRequire((int)$row['id']>=1+($world-1)*10&&(int)$row['id']<=$world*10,'Alliance directory crossed worlds.');
        benchmarkRequire((int)$community['world_id']===$world&&count($community['players'])===99&&count($community['world_chat'])===10,'Incomplete community snapshot.');
        foreach($community['players']as$row)benchmarkRequire($validPlayer((int)$row['id']),'Community directory crossed worlds.');
        foreach(array_merge($community['world_chat'],$community['alliance_chat'],$community['members'])as$row)benchmarkRequire($validPlayer((int)$row['player_id']),'Community response crossed worlds.');
        benchmarkRequire(count($events['events'])===1&&count($events['invasions'])===1&&(int)$events['settings']['world_id']===$world,'Incomplete event snapshot.');
        foreach(array_merge($events['events'],$events['invasions'])as$row)benchmarkRequire((int)$row['world_id']===$world,'Event response crossed worlds.');
        json_encode([$city,$kingdom,$community,$events],JSON_THROW_ON_ERROR);
        return ['package'=>($end-$start)/1e6,'city'=>($cityEnd-$start)/1e6,'kingdom'=>($kingdomEnd-$cityEnd)/1e6,
            'community'=>($communityEnd-$kingdomEnd)/1e6,'events'=>($end-$communityEnd)/1e6];
    });
}

$fixture=null;$exit=0;
try {
    $fixture=new \ConquerTests\FeatureDatabase();$db=Connection::getInstance();
    benchmarkRequire(preg_match('/^conquer_feature_test_[a-f0-9]{12}$/D',(string)$db->query('SELECT DATABASE()')->fetchColumn())===1,'Refusing non-disposable database.');
    $db->execute("UPDATE worlds SET name='Benchmark One',slug='benchmark-one',status='running',map_size=256,map_seed=42,speed_factor=1,gather_factor=1,haul_factor=1 WHERE id=1");
    $db->execute("INSERT INTO worlds(id,name,slug,status,map_size,map_seed)VALUES(2,'Benchmark Two','benchmark-two','running',256,42)");
    $troop=(int)array_key_first(TroopData::all());
    $db->transaction(static function()use($db,$troop):void{
        for($player=1;$player<=200;$player++){
            $world=$player<=100?1:2;$name='Benchmark'.$player;
            $db->execute('INSERT INTO players(id,username,email,password_hash)VALUES(?,?,?,?)',[$player,$name,$name.'@example.invalid','unused']);
            OAuth::createDefaultCity($db,$player,$name,$world);
            $city=(int)$db->query('SELECT id FROM cities WHERE player_id=?',[$player])->fetchColumn();
            $db->execute('INSERT INTO city_troops(city_id,troop_code,count)VALUES(?,?,1000)',[$city,$troop]);
            $db->execute('INSERT INTO kingdom_profiles(player_id,display_name,welcome_claimed)VALUES(?,?,1)',[$player,$name]);
            $alliance=(int)ceil($player/10);$leader=($alliance-1)*10+1;
            if($player===$leader){
                $db->execute('INSERT INTO alliances(id,world_id,name,tag,leader_id,member_count)VALUES(?,?,?,?,?,10)',[$alliance,$world,'Benchmark Guild '.$alliance,'B'.$alliance,$leader]);
                $db->execute('INSERT INTO alliance_treasury(alliance_id)VALUES(?)',[$alliance]);
                $db->execute('INSERT INTO alliance_messages(alliance_id,player_id,username,message)VALUES(?,?,?,?)',[$alliance,$player,$name,'Synthetic alliance message']);
                $db->execute('INSERT INTO world_chat(world_id,player_id,username,message)VALUES(?,?,?,?)',[$world,$player,$name,'Synthetic world '.$world.' message']);
            }
            $db->execute('INSERT INTO alliance_members(alliance_id,player_id,world_id,role)VALUES(?,?,?,?)',[$alliance,$player,$world,$player===$leader?'leader':'member']);
        }
        for($world=1;$world<=2;$world++){
            $db->execute("INSERT INTO conquest_events(world_id,phase,starts_at,ends_at,state)VALUES(?,1,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 2 DAY),'upcoming')",[$world]);
            $db->execute("INSERT INTO world_invasions(world_id,target,starts_at,ends_at)VALUES(?,5000,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 2 DAY))",[$world]);
            $cfg=WorldSettings::defaults();$cfg['enabled']=false;
            $db->execute('INSERT INTO world_spawn_settings(world_id,settings_json)VALUES(?,?)',[$world,json_encode($cfg,JSON_THROW_ON_ERROR)]);
        }
    });
    benchmarkRequire((int)$db->query('SELECT COUNT(*) FROM players')->fetchColumn()===200,'Fixture player count mismatch.');
    foreach([1,2]as$world)benchmarkRequire((int)$db->query('SELECT COUNT(*) FROM cities WHERE world_id=?',[$world])->fetchColumn()===100,'Fixture world population mismatch.');
    $targets=[];for($i=0;$i<30;$i++)$targets[]=[intdiv($i,2)+1+($i%2)*100,1+$i%2];
    foreach($targets as[$player,$world])benchmarkPackage($player,$world);
    $samples=[];foreach($targets as[$player,$world])$samples[]=benchmarkPackage($player,$world);
    $statistics=[];foreach(['package','city','kingdom','community','events']as$key)$statistics[$key]=benchmarkStats(array_column($samples,$key));
    $cfg=WorldSettings::defaults();$cfg['batch_limit']=100;$cfg['resource_limit']=50;$cfg['monster_limit']=50;
    $cfg['monster_weights']=['Orc'=>100,'Skeleton'=>0,'Golem'=>0,'Treasure Goblin'=>0,'Deathkar'=>0,'dragon'=>0,'Magdar'=>0];
    $cfg=WorldSettings::validate($cfg);
    $db->execute('UPDATE world_spawn_settings SET settings_json=?,next_run_at=UTC_TIMESTAMP() WHERE world_id=1',[json_encode($cfg,JSON_THROW_ON_ERROR)]);
    benchmarkRequire((int)$db->query('SELECT COUNT(*) FROM field_objects')->fetchColumn()===0&&(int)$db->query('SELECT COUNT(*) FROM field_monsters')->fetchColumn()===0,'Spawn benchmark requires initially empty entity tables.');
    $start=hrtime(true);$spawn=WorldSpawnService::tick(1,false,'benchmark')[1]??[];$spawnMs=(hrtime(true)-$start)/1e6;
    benchmarkRequire(($spawn['status']??'')==='completed','Spawn benchmark did not complete.');
    benchmarkRequire($spawn['resources_spawned']+$spawn['monsters_spawned']+$spawn['placement_misses']+$spawn['chance_skipped']===100,'Spawn pass did not account for 100 attempts.');
    foreach(['field_objects'=>'resources_spawned','field_monsters'=>'monsters_spawned']as$table=>$key){
        $count=(int)$db->query('SELECT COUNT(*) FROM '.$table.' WHERE world_id=1')->fetchColumn();
        benchmarkRequire($count===$spawn[$key]&&$count<=50,'Spawn cap/count mismatch.');
        benchmarkRequire((int)$db->query('SELECT COUNT(*) FROM '.$table.' WHERE world_id<>1')->fetchColumn()===0,'Spawn pass crossed worlds.');
    }
    echo json_encode(['measured_at_utc'=>gmdate('c'),'environment'=>['php'=>PHP_VERSION,'os'=>PHP_OS_FAMILY,'database'=>$db->query('SELECT VERSION()')->fetchColumn(),'cli_opcache'=>(bool)ini_get('opcache.enable_cli')],
        'fixture'=>['players'=>200,'worlds'=>2,'players_per_world'=>100,'alliances'=>20,'garrison_per_city'=>1000,'warmup_packages'=>30,'measured_packages'=>30,'concurrency'=>1],
        'service_timings'=>$statistics,'spawn'=>['elapsed_ms'=>round($spawnMs,3),'attempt_budget'=>100,'resource_cap'=>50,'monster_cap'=>50,'result'=>$spawn],
        'validation'=>'All 60 read packages JSON-valid; city, rankings, alliances, community and event world checks passed; spawn attempts, caps and stored counts agree.',
        'limits'=>'Single local CLI process with warm PHP/catalog/database caches, empty battle/queue histories and no HTTP/browser/network or concurrent-player load. Not a production throughput estimate.'],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $e){$exit=1;fwrite(STDERR,'FAIL '.$e->getMessage().' at '.$e->getFile().':'.$e->getLine()."\n");}
finally{if($fixture)$fixture->close();}
exit($exit);
