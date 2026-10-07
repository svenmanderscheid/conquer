<?php
declare(strict_types=1);
// Advance timers in a disposable browser fixture only; never available as a route.
if(PHP_SAPI!=='cli'||count($argv)!==3)exit(1);
$fixture=realpath($argv[1]);$runId=filter_var($argv[2],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if(!$fixture||!preg_match('/^conquer_feature_test_[a-f0-9]{12}$/D',basename($fixture))||!$runId)throw new RuntimeException('Disposable fixture required.');
define('ROOT_DIR',dirname(__DIR__,2));require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
\Conquer\Db\Connection::init($fixture);$db=\Conquer\Db\Connection::getInstance();
if($db->query('SELECT DATABASE()')->fetchColumn()!==basename($fixture))throw new RuntimeException('Fixture mismatch.');
\Conquer\Game\World\WorldContext::bind(1,1);
$db->execute("UPDATE dungeon_runs SET started_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 5 HOUR),decision_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 3 HOUR),decision_deadline=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 HOUR) WHERE id=? AND world_id=1 AND dungeon_code='melusina_well' AND status='running'",[$runId]);
\Conquer\Game\Dungeon\DungeonService::tick();
echo json_encode($db->query('SELECT status FROM dungeon_runs WHERE id=?',[$runId])->fetch());
