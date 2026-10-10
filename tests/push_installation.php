<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));
require ROOT_DIR.'/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';
use Conquer\Db\Connection;
use Conquer\Game\Notification\PushInstallation as Install;
function installCheck(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
function installReject(callable $call,string $label):void{try{$call();}catch(RuntimeException){installCheck(true,$label);return;}throw new RuntimeException($label);}
$fixture=new \ConquerTests\FeatureDatabase();
try{
    $db=Connection::getInstance();
    // Everything below addresses only this synthetic schema.
    $db->execute('DROP TABLE IF EXISTS push_deliveries');$db->execute('DROP TABLE IF EXISTS push_subscriptions');
    $db->execute('DELETE FROM migrations WHERE filename=?',[Install::MIGRATION]);
    $db->execute("INSERT INTO players(id,username,email,password_hash,gems)VALUES(1,'PushInstall','install@tests.invalid','unused',12345)");
    $tablesBefore=$db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $markersBefore=$db->query('SELECT filename FROM migrations ORDER BY filename')->fetchAll(PDO::FETCH_COLUMN);
    $before=Install::inspect();
    installCheck(!$before['ready']&&$before['can_apply']&&!$before['recorded']&&$before['schema_drift']===[],'read-only preflight reports clean pending installation');
    installCheck($tablesBefore===$db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN)&&$markersBefore===$db->query('SELECT filename FROM migrations ORDER BY filename')->fetchAll(PDO::FETCH_COLUMN),'preflight changes no schema or migration markers');
    $after=Install::apply();
    installCheck($after['ready']&&$after['recorded']&&$after['schema_drift']===[],'targeted migration creates and validates both complete tables');
    $added=array_values(array_diff($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN),$tablesBefore));sort($added);
    installCheck($added===['push_deliveries','push_subscriptions'],'only two push tables are added');
    $newMarkers=array_values(array_diff($db->query('SELECT filename FROM migrations ORDER BY filename')->fetchAll(PDO::FETCH_COLUMN),$markersBefore));
    installCheck($newMarkers===[Install::MIGRATION]&&(int)$db->query('SELECT gems FROM players WHERE id=1')->fetchColumn()===12345,'only 0145 is recorded and existing player state remains unchanged');
    installCheck(Install::apply()['previously_recorded']&&Install::inspect()['ready'],'repeated apply is idempotent');
    $db->execute('DELETE FROM migrations WHERE filename=?',[Install::MIGRATION]);$db->execute('DROP TABLE push_deliveries');
    installCheck(Install::inspect()['can_apply']&&!Install::inspect()['tables']['push_deliveries'],'compatible partially created schema is resumable');
    installCheck(Install::apply()['ready'],'retry finishes partially created schema and records only verified result');
    $db->execute('DELETE FROM migrations WHERE filename=?',[Install::MIGRATION]);
    $db->execute('ALTER TABLE push_subscriptions MODIFY endpoint VARCHAR(2000) NOT NULL');
    installCheck(!Install::inspect()['can_apply']&&in_array('push_subscriptions.endpoint:definition',Install::inspect()['schema_drift'],true),'preflight detects existing incompatible column');
    installReject(fn()=>Install::apply(),'apply refuses incompatible schema instead of overwriting it');
    installCheck(!$db->query('SELECT 1 FROM migrations WHERE filename=?',[Install::MIGRATION])->fetchColumn(),'failed validation never records migration');
    $db->execute('ALTER TABLE push_subscriptions MODIFY endpoint VARCHAR(2048) NOT NULL');
    $fk=$db->query("SELECT CONSTRAINT_NAME FROM information_schema.key_column_usage WHERE table_schema=DATABASE() AND table_name='push_subscriptions' AND column_name='session_id' AND referenced_table_name='sessions'")->fetchColumn();
    if(!is_string($fk)||!preg_match('/^[A-Za-z0-9_]+$/D',$fk))throw new RuntimeException('Unexpected fixture constraint name.');
    $db->execute('ALTER TABLE push_subscriptions DROP FOREIGN KEY `'.$fk.'`');
    installReject(fn()=>Install::apply(),'missing session revocation cascade prevents installation approval');
    $db->execute('ALTER TABLE push_subscriptions ADD FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE');
    $db->execute('ALTER TABLE kingdom_profiles DROP COLUMN report_heal_complete');
    installCheck(in_array('kingdom_profiles.report_heal_complete',Install::inspect()['missing_prerequisites'],true),'missing producer prerequisites are named without applying other migrations');
    installReject(fn()=>Install::apply(),'missing prerequisite blocks targeted install');
    $db->execute('ALTER TABLE kingdom_profiles ADD COLUMN report_heal_complete TINYINT(1) NOT NULL DEFAULT 1');
    $db->getPdo()->beginTransaction();
    installReject(fn()=>Install::apply(),'installer refuses implicit DDL commit of an existing transaction');
    $db->getPdo()->rollBack();
    installCheck(Install::apply()['ready'],'verified fixture returns to ready after explicitly repaired test drift');
    echo "ALL TARGETED PUSH INSTALLATION CHECKS PASSED\n";
}finally{$fixture->close();}
