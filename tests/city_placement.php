<?php
declare(strict_types=1);
/** City spawn/return/teleport regressions in a disposable MySQL database. */
if (PHP_SAPI !== 'cli') { exit(1); }
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR . '/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR . '/src'))->register();
date_default_timezone_set('UTC');
\Conquer\Logger::init(sys_get_temp_dir() . '/conquer-city-placement.log', 'ERROR');
use Conquer\Db\Connection;
use Conquer\Auth\OAuth;
use Conquer\Game\Map\WorldPlacement;
use Conquer\Game\Map\WorldTerrain;
use Conquer\Game\March\MarchTick;
function verify(bool $ok, string $label): void { if (!$ok) throw new RuntimeException($label); echo "PASS $label\n"; }
function rejected(callable $fn, string $label): void { try { $fn(); } catch (RuntimeException) { verify(true,$label); return; } throw new RuntimeException('Expected rejection: '.$label); }
function city(Connection $db, int $id): array { return $db->query('SELECT * FROM cities WHERE id=?',[$id])->fetch(); }
function validCity(Connection $db, array $row): bool {
    return $db->transaction(static function(Connection $db)use($row):bool {
        WorldPlacement::lockWorld($db,1);
        return WorldPlacement::canPlace($db,1,'city',(int)$row['coord_x'],(int)$row['coord_y'],(int)$row['id']);
    });
}
$cfg=require ROOT_DIR.'/config/database.php';
if(!in_array($cfg['host']??'', ['127.0.0.1','localhost'],true))exit("Local MySQL only.\n");
$name='conquer_placement_test_'.bin2hex(random_bytes(6));
$root=sys_get_temp_dir().DIRECTORY_SEPARATOR.$name;
$admin=null;$exit=0;
try {
    $source=$cfg['database'];if(!preg_match('/^[a-zA-Z0-9_]+$/D',$source))throw new RuntimeException('Invalid source database name.');
    $admin=new PDO('mysql:host='.$cfg['host'].';port='.($cfg['port']??3306).';charset=utf8mb4',$cfg['username'],$cfg['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $admin->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    foreach($admin->query('SHOW TABLES FROM `'.$source.'`')->fetchAll(PDO::FETCH_COLUMN)as$table){
        if(!preg_match('/^[a-zA-Z0-9_]+$/D',$table))throw new RuntimeException('Invalid source table name.');
        $admin->exec('CREATE TABLE `'.$name.'`.`'.$table.'` LIKE `'.$source.'`.`'.$table.'`');
    }
    $admin->exec('INSERT INTO `'.$name.'`.worlds SELECT * FROM `'.$source.'`.worlds WHERE id=1');
    mkdir($root.'/config',0700,true);$cfg['database']=$name;
    file_put_contents($root.'/config/database.php',"<?php\nreturn ".var_export($cfg,true).";\n");
    $db=Connection::init($root);
    foreach([1,2]as$id)$db->execute('INSERT INTO players(id,username,email,password_hash)VALUES(?,?,?,?)',[$id,'PlacementFixture'.$id,'placement'.$id.'@invalid.test','unused']);

    // Standard creation also supports callers that have not opened a transaction.
    OAuth::createDefaultCity($db,1,'PlacementFixture1');
    $cityId=(int)$db->query('SELECT id FROM cities WHERE player_id=1')->fetchColumn();
    verify(!$db->getPdo()->inTransaction()&&validCity($db,city($db,$cityId)),'new city occupies dry, unoccupied 4x4 inside world bounds');
    verify((int)$db->query('SELECT COUNT(*) FROM city_buildings WHERE city_id=?',[$cityId])->fetchColumn()===count(\Conquer\Game\City\CityState::BUILDING_CODES),'new city includes every starting building');

    $shore=null;
    for($y=60;$y<70&&$shore===null;$y++)for($x=69;$x<82;$x++){
        if(!WorldTerrain::isWater($x,$y)&&!WorldTerrain::isDryRectangle(...WorldPlacement::footprint('city',$x,$y))){$shore=[$x,$y];break;}
    }
    verify($shore!==null,'shore fixture has dry center with wet footprint edge');
    $db->transaction(static function(Connection $db)use($shore):void {
        WorldPlacement::lockWorld($db,1);
        verify(!WorldPlacement::canPlace($db,1,'city',$shore[0],$shore[1]),'city placement rejects dry-center shoreline intersection');
        verify(!WorldPlacement::canPlace($db,1,'city',75,65),'city placement rejects lake center');
    });

    $restore=new ReflectionMethod(OAuth::class,'restoreHiddenCityOnLogin');
    $db->execute('UPDATE players SET is_hidden=1 WHERE id=1');
    $db->execute('UPDATE cities SET is_hidden=1,coord_x=75,coord_y=65 WHERE id=?',[$cityId]);
    $restore->invoke(new OAuth([]),1);
    verify(validCity($db,city($db,$cityId))&&(int)city($db,$cityId)['is_hidden']===0&&(int)$db->query('SELECT is_hidden FROM players WHERE id=1')->fetchColumn()===0,'hidden city returns visibly only at a valid dry destination');

    $wall=new ReflectionMethod(MarchTick::class,'checkWallDestroyed');
    $db->execute('UPDATE cities SET coord_x=75,coord_y=65,wall_hp_current=0 WHERE id=?',[$cityId]);
    $wall->invoke(null,$db,$cityId,1);
    $row=city($db,$cityId);
    verify(validCity($db,$row)&&(int)$row['wall_hp_current']===(int)$row['wall_hp_max'],'wall teleport reserves valid destination within current world and restores wall');

    // A 4x4 world has one possible city anchor. Its monster blocks that footprint.
    $db->execute('UPDATE worlds SET map_size=4 WHERE id=1');
    $db->execute('INSERT INTO field_monsters(world_id,monster_code,coord_x,coord_y,hp_current)VALUES(1,20209901,1,1,1)');
    rejected(fn()=>OAuth::createDefaultCity($db,2,'PlacementFixture2'),'registration fails closed when no complete city footprint remains');
    verify((int)$db->query('SELECT COUNT(*) FROM cities WHERE player_id=2')->fetchColumn()===0,'failed registration leaves no partial city');
    $db->execute('UPDATE players SET is_hidden=1 WHERE id=1');
    $db->execute('UPDATE cities SET is_hidden=1 WHERE id=?',[$cityId]);
    $before=city($db,$cityId);
    rejected(fn()=>$restore->invoke(new OAuth([]),1),'hidden return fails closed on full map');
    verify(city($db,$cityId)===$before&&(int)$db->query('SELECT is_hidden FROM players WHERE id=1')->fetchColumn()===1,'failed hidden return preserves location and visibility');
    $db->execute('UPDATE cities SET wall_hp_current=0 WHERE id=?',[$cityId]);$before=city($db,$cityId);
    $notifications=(int)$db->query('SELECT COUNT(*) FROM notifications')->fetchColumn();
    $wall->invoke(null,$db,$cityId,1);
    verify(city($db,$cityId)===$before,'failed wall teleport leaves coordinates and wall unchanged');
    verify((int)$db->query('SELECT COUNT(*) FROM notifications')->fetchColumn()===$notifications,'failed wall teleport creates no misleading success notification');
} catch(Throwable $e) { fwrite(STDERR,'FAIL '.$e->getMessage()."\n");$exit=1; }
finally {
    if($admin!==null)$admin->exec('DROP DATABASE IF EXISTS `'.$name.'`');
    // Only this exact generated temporary config is removed; no workspace traversal.
    if(is_file($root.'/config/database.php'))unlink($root.'/config/database.php');
    if(is_dir($root.'/config'))rmdir($root.'/config');
    if(is_dir($root))rmdir($root);
}
exit($exit);
