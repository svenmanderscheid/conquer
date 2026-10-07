<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));
require ROOT_DIR.'/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';
date_default_timezone_set('UTC');

use Conquer\Db\Connection;
use Conquer\Game\Dungeon\DungeonEntrance as E;
use Conquer\Game\Map\WorldPlacement as P;
use Conquer\Game\World\{WorldMapProfile as W,LuxembourgGeography as G};

if(($argv[1]??'')==='--allocate'){
    Connection::init($argv[2]);
    Connection::getInstance()->execute('SET SESSION innodb_lock_wait_timeout=1');
    echo json_encode(E::forWorld((int)$argv[3]),JSON_THROW_ON_ERROR);
    exit;
}
$checks=0;
function ck(bool $condition,string $label): void {
    global $checks;if(!$condition)throw new RuntimeException($label);$checks++;echo "PASS $label\n";
}
function allocateElsewhere(int $worldId): array {
    $root=sys_get_temp_dir().'/'.Connection::getInstance()->query('SELECT DATABASE()')->fetchColumn();
    $process=proc_open([PHP_BINARY,__FILE__,'--allocate',$root,(string)$worldId],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,ROOT_DIR,null,['bypass_shell'=>true]);
    if(!is_resource($process))throw new RuntimeException('Unable to allocate in worker.');
    fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    if(proc_close($process)!==0||$err!=='')throw new RuntimeException($err?:$out);
    return json_decode($out,true,32,JSON_THROW_ON_ERROR);
}
$fixture=null;$exit=0;
try{
    $fixture=new \ConquerTests\FeatureDatabase();$db=Connection::getInstance();
    W::configureEmptyWorld(1);
    foreach([2,3,4,5]as$world){
        $db->execute('INSERT INTO worlds(id,name,slug,status,map_size)VALUES(?,?,?,\'running\',256)',[$world,'Entrance fixture '.$world,'entrance-fixture-'.$world]);
        if($world!==2)W::configureEmptyWorld($world);
    }
    ck(E::forWorld(2)===null&&E::registered(2)===[],'legacy worlds never allocate regional entrances');
    $first=E::forWorld(1);
    ck($first!==null&&$first['canton_id']==='03'&&G::at($first['x'],$first['y'])['canton_id']==='03','entrance is inside canton Luxembourg');
    ck(G::isDryRectangle($first['x']-1.5,$first['y']-1.5,$first['x']+1.5,$first['y']+1.5,'03'),'entire entrance footprint stays on dry canton land');
    ck(E::forWorld(1)===$first&&count(E::registered(1))===1,'repeated map requests retain one persisted coordinate');
    $db->transaction(function()use($db,$first){
        E::registered(1,true);
        ck(allocateElsewhere(1)===$first,'status reads do not wait for entrance locks held by placement');
    });
    $db->transaction(function()use($db,$first){
        P::lockWorld($db,1);
        foreach(['city','resource','monster','boss','outpost','alliance_center']as$kind)ck(!P::canPlace($db,1,$kind,$first['x'],$first['y']),'entrance blocks overlapping '.$kind.' placement');
        ck(P::findNear($db,1,'resource',$first['x']+12,$first['y']+12,null,24)!==null,'nearby ordinary map placement still works');
    });
    $db->execute("INSERT INTO players(id,username,email,password_hash)VALUES(1,'EntranceFixture','entrance@tests.invalid','unused')");
    $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y)VALUES(1,1,3,'Existing city',?,?)",[$first['x'],$first['y']]);
    $occupied=E::forWorld(3);
    ck($occupied!==null&&!P::conflicts('outpost',$occupied['x'],$occupied['y'],'city',$first['x'],$first['y']),'new entrance searches around an existing city');
    $city=$db->query('SELECT coord_x,coord_y FROM cities WHERE id=1')->fetch();
    ck((int)$city['coord_x']===$first['x']&&(int)$city['coord_y']===$first['y'],'entrance allocation never relocates the existing city');
    ck(E::forWorld(3)===$occupied,'replacement coordinate stays fixed after allocation');
    $migration=(string)file_get_contents(ROOT_DIR.'/migrations/0132_dungeon_entrances.sql');
    \Conquer\Db\MigrationSql::apply($db->getPdo(),$migration);
    \Conquer\Db\MigrationSql::apply($db->getPdo(),$migration);
    ck(E::forWorld(1)===$first&&E::forWorld(3)===$occupied,'replayed additive migration retains both entrances');

    // A placement transaction may have read player data before obtaining the
    // world lock. Entrance collision reads must not use that old RR snapshot.
    $db->getPdo()->beginTransaction();
    $db->query('SELECT COUNT(*) FROM players')->fetchColumn();
    $concurrent=allocateElsewhere(4);
    P::lockWorld($db,4);
    ck(!P::canPlace($db,4,'resource',$concurrent['x'],$concurrent['y']),'placement sees an entrance committed after its earlier read snapshot');
    $db->getPdo()->commit();

    $db->transaction(function()use($db){
        $db->query('SELECT * FROM cities WHERE id=1 FOR UPDATE')->fetch();
        ck(E::forWorld(5)===null,'nested player transaction defers first entrance allocation');
    });
    ck(E::forWorld(5)!==null,'deferred entrance allocation succeeds outside player transaction');
    echo "OK: $checks entrance checks.\n";
}catch(Throwable $e){fwrite(STDERR,(string)$e."\n");$exit=1;}
finally{$fixture?->close();}
exit($exit);
