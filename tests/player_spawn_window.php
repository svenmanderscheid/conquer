<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit(1);
define('ROOT_DIR',dirname(__DIR__));
require ROOT_DIR.'/src/Bootstrap.php'; \Conquer\Bootstrap::init(ROOT_DIR);
require __DIR__.'/Support/FeatureDatabase.php';

use Conquer\Db\Connection;
use Conquer\Auth\OAuth;
use Conquer\Game\Map\WorldPlacement;
use Conquer\Game\World\{AlphaRealm,WorldEntry,LuxembourgGeography};

$fixture=new \ConquerTests\FeatureDatabase(); $db=Connection::getInstance(); $checks=0; $failed=false;
function spawnCheck(bool $ok,string $label): void {
    global $checks;
    if (!$ok) throw new RuntimeException($label);
    $checks++;
}
function spawnCity(Connection $db,int $id): array {
    $db->execute('INSERT INTO players(id,username,email,password_hash) VALUES(?,?,?,?)',[$id,'SpawnFixture'.$id,'spawn'.$id.'@tests.invalid','unused']);
    OAuth::createDefaultCity($db,$id,'SpawnFixture'.$id);
    return $db->query('SELECT * FROM cities WHERE player_id=?',[$id])->fetch();
}
try {
    $world=(int)AlphaRealm::setup()['world']['id'];
    $entry=WorldEntry::settings($world);
    $first=spawnCity($db,1);
    spawnCheck(LuxembourgGeography::at($first['coord_x'],$first['coord_y'])['canton_id']==='08','legacy permanent entry stays in Wiltz');
    $migration=(string)file_get_contents(ROOT_DIR.'/migrations/0139_temporary_player_spawn.sql');
    \Conquer\Db\MigrationSql::apply($db->getPdo(),$migration);
    \Conquer\Db\MigrationSql::apply($db->getPdo(),$migration);
    $after=WorldEntry::settings($world);
    spawnCheck($after['spawn_until']===null,'migration defaults to permanent entry');
    unset($after['spawn_until']);
    spawnCheck($after===$entry,'migration and replay preserve existing settings');
    spawnCheck($db->query('SELECT * FROM cities WHERE player_id=1')->fetch()===$first,'migration leaves player state intact');

    $end=(new DateTimeImmutable('2026-10-17 00:00:00',new DateTimeZone('Europe/Luxembourg')))->setTimezone(new DateTimeZone('UTC'));
    spawnCheck($end->format('Y-m-d H:i:s')==='2026-10-16 22:00:00','inclusive 16 October converts to the correct UTC deadline');
    $db->execute('UPDATE world_entry_settings SET spawn_until=? WHERE world_id=?',[$end->format('Y-m-d H:i:s'),$world]);
    date_default_timezone_set('Pacific/Honolulu');
    $db->transaction(static function(Connection $db) use ($world,$end): void {
        WorldPlacement::lockWorld($db,$world);
        $spot=WorldEntry::position($db,$world,null,$end->getTimestamp()-1);
        spawnCheck($spot!==null && LuxembourgGeography::at($spot['x'],$spot['y'])['canton_id']==='08','last second remains in Wiltz regardless of server timezone');
        spawnCheck(WorldEntry::position($db,$world,null,$end->getTimestamp())===null,'deadline immediately resumes normal placement');
        spawnCheck(WorldEntry::position($db,$world,null,$end->getTimestamp()+86400)===null,'expired window needs no cron or database writes');
    });
    date_default_timezone_set('UTC');

    $db->execute('UPDATE world_entry_settings SET spawn_until=? WHERE world_id=?',[gmdate('Y-m-d H:i:s',time()+3600),$world]);
    $positions=[[(int)$first['coord_x'],(int)$first['coord_y']]];
    for ($id=2;$id<=8;$id++) {
        $city=spawnCity($db,$id); $x=(int)$city['coord_x']; $y=(int)$city['coord_y'];
        spawnCheck((int)$city['world_id']===$world,'new account uses the default world');
        spawnCheck(LuxembourgGeography::isDryRectangle($x-1.5,$y-1.5,$x+2.5,$y+2.5,'08'),'entire new city is dry and inside Wiltz');
        foreach ($positions as $other) spawnCheck(!WorldPlacement::conflicts('city',$x,$y,'city',...$other),'new cities never overlap');
        $positions[]=[$x,$y];
    }
    $saved=$db->query('SELECT * FROM cities ORDER BY id')->fetchAll();
    $db->execute("UPDATE world_entry_settings SET spawn_until='2000-01-01 00:00:00' WHERE world_id=?",[$world]);
    spawnCheck(WorldEntry::defaultWorld()===$world,'expiration keeps the same registration world');
    $late=spawnCity($db,9);
    spawnCheck((int)$late['world_id']===$world,'registration after expiration succeeds in the same world');
    spawnCheck(LuxembourgGeography::isDryRectangle($late['coord_x']-1.5,$late['coord_y']-1.5,$late['coord_x']+2.5,$late['coord_y']+2.5,LuxembourgGeography::at($late['coord_x'],$late['coord_y'])['canton_id']),'ordinary placement keeps a valid city footprint');
    spawnCheck($db->query('SELECT * FROM cities WHERE player_id<9 ORDER BY id')->fetchAll()===$saved,'existing cities and balances survive expiration unchanged');

    $db->execute('UPDATE world_entry_settings SET spawn_x=0,spawn_y=0,spawn_radius=0,spawn_until=NULL WHERE world_id=?',[$world]);
    try {
        spawnCity($db,10);
        throw new RuntimeException('Expected full-region rejection.');
    } catch (DomainException) {
        spawnCheck(!$db->query('SELECT id FROM cities WHERE player_id=10')->fetchColumn(),'full active region rejects instead of scattering players');
    }
    echo "PASS $checks temporary player spawn checks\n";
} catch (Throwable $e) { $failed=true; fwrite(STDERR,'FAIL '.$e->getMessage().' at '.$e->getFile().':'.$e->getLine()."\n"); }
finally { date_default_timezone_set('UTC'); $fixture->close(); }
exit($failed?1:0);
