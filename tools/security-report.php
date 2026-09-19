<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
define('ROOT_DIR',dirname(__DIR__));require ROOT_DIR.'/src/Bootstrap.php';\Conquer\Bootstrap::init(ROOT_DIR);
$db=\Conquer\Db\Connection::getInstance();
$rows=$db->query('SELECT f.*,p.username FROM security_activity_flags f JOIN players p ON p.id=f.player_id WHERE flag_day>=DATE_SUB(UTC_DATE(),INTERVAL 7 DAY) ORDER BY last_seen DESC LIMIT 200')->fetchAll();
echo "Aktivitätshinweise der letzten 7 Tage (höchstens 200). Kein Nachweis für einen Bot, keine automatische Sperre.\n";
foreach($rows as $r)echo json_encode($r,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
if(!$rows)echo "Keine Hinweise vorhanden.\n";
