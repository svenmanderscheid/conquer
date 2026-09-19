<?php
declare(strict_types=1);
/** Add the regional/charm schema to this installation; no gameplay reset. */
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
define('ROOT_DIR',dirname(__DIR__));require ROOT_DIR.'/src/Bootstrap.php';\Conquer\Bootstrap::init(ROOT_DIR);
$db=\Conquer\Db\Connection::getInstance();
$files=['0083_reward_overrides.sql','0084_land_progression.sql','0085_monster_charms.sql','0086_reward_world_revisions.sql','0087_charm_compatibility.sql'];
if(!in_array('--apply',$argv,true)){
    foreach($files as $file){$done=$db->query('SELECT filename FROM migrations WHERE filename=?',[$file])->fetchColumn();echo ($done?'APPLIED ':'PENDING ').$file."\n";}
    echo "Use --apply to install these migrations and initialize regional state.\n";exit;
}
$lock='conquer-migrate-land-charms';
if((int)$db->query('SELECT GET_LOCK(?,10)',[$lock])->fetchColumn()!==1)throw new RuntimeException('Another regional migration is running.');
try{
    foreach($files as $file){
        \Conquer\Db\MigrationSql::apply($db->getPdo(),(string)file_get_contents(ROOT_DIR.'/migrations/'.$file));
        $db->execute('INSERT IGNORE INTO migrations(filename)VALUES(?)',[$file]);
        echo 'READY '.$file."\n";
    }
    foreach($db->query('SELECT id FROM worlds ORDER BY id')->fetchAll(PDO::FETCH_COLUMN) as $worldId){
        \Conquer\Game\World\LandProgressService::ensureWorld((int)$worldId);
        $count=(int)$db->query('SELECT COUNT(*) FROM world_land_parts WHERE world_id=?',[$worldId])->fetchColumn();
        echo 'READY world '.(int)$worldId.': '.$count." lands\n";
    }
    echo "Land development, world reward versions and charm collection schema are ready.\n";
}finally{$db->query('SELECT RELEASE_LOCK(?)',[$lock]);}
