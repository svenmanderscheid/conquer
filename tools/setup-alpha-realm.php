<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(403); exit; }
define('ROOT_DIR',dirname(__DIR__));
require ROOT_DIR.'/src/Bootstrap.php'; \Conquer\Bootstrap::init(ROOT_DIR);
if (!in_array('--apply',$argv,true)) { echo "Creates a new Luxembourg 2x Alpha world, makes it the registration default, and keeps existing worlds intact. Run --apply after a verified database backup.\n"; exit; }
try {
    $db=\Conquer\Db\Connection::getInstance();
    $migration='0127_alpha_entry_and_missions.sql';
    \Conquer\Db\MigrationSql::apply($db->getPdo(),(string)file_get_contents(ROOT_DIR.'/migrations/'.$migration));
    $db->execute('INSERT IGNORE INTO migrations(filename) VALUES(?)',[$migration]);
    echo json_encode(\Conquer\Game\World\AlphaRealm::setup(),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
}
catch (Throwable $e) { fwrite(STDERR,$e->getMessage()."\n"); exit(1); }
