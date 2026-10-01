<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));date_default_timezone_set('UTC');require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';
use Conquer\Db\Connection;
use Conquer\Admin\AlphaPlaytestAnalytics;
use Conquer\Game\Territory\TerritoryContribution;
function alphaCheck(bool $value,string $label):void{if(!$value)throw new RuntimeException($label);echo 'PASS '.$label."\n";}
$fixture=new \ConquerTests\FeatureDatabase();
try{
    $db=Connection::getInstance();\Conquer\Db\MigrationSql::apply($db->getPdo(),file_get_contents(ROOT_DIR.'/migrations/0110_admin_analytics.sql'));
    $db->execute("INSERT INTO worlds(id,name,slug,status,map_size)VALUES(2,'Alpha fixture','alpha-fixture','running',256),(3,'Other fixture','alpha-other','running',256)");
    foreach(range(1,4) as $id){$db->execute('INSERT INTO players(id,username,email,password_hash)VALUES(?,?,?,?)',[$id,'Alpha'.$id,'alpha'.$id.'@invalid.test','unused']);$db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,castle_level,food)VALUES(?,?,?,'Fixture',?,20,?,?)",[$id,$id,$id===4?3:2,20+$id*5,$id===1?2:1,$id===4?999999:100]);}
    $db->execute("INSERT INTO alliances(id,world_id,name,tag,leader_id)VALUES(1,2,'Alpha','ALP',1),(2,3,'Other','OTH',4),(3,2,'Previous','PRE',3)");
    foreach([[2,1,1],[2,1,2],[2,3,3],[3,2,4]] as $membership)$db->execute("INSERT INTO alliance_members(world_id,alliance_id,player_id,role)VALUES(?,?,?,'member')",$membership);
    foreach([[1,2,'2026-10-10 10:00:00'],[1,2,'2026-10-11 11:00:00'],[1,2,'2026-10-11 11:01:00'],[1,2,'2026-10-17 11:00:00'],[2,2,'2026-10-12 10:00:00'],[3,2,'2026-10-19 10:00:00'],[3,2,'2026-10-20 10:00:00'],[4,3,'2026-10-10 10:00:00'],[4,3,'2026-10-11 10:00:00'],[1,3,'2026-09-01 10:00:00']] as $row)$db->execute('INSERT INTO player_activity_minutes(player_id,world_id,minute_slot)VALUES(?,?,?)',$row);
    foreach([[2,1,1,'supply','2026-10-19'],[2,1,1,'scout','2026-10-19'],[2,1,2,'supply','2026-10-19'],[2,3,1,'fortify','2026-10-19'],[3,2,4,'supply','2026-10-19'],[2,1,1,'supply','2026-09-01'],[2,1,1,'supply','2026-10-21']] as $row)$db->execute('INSERT INTO territory_support(world_id,alliance_id,player_id,target_id,kind,day_key,created_at)VALUES(?,?,?,\'commune:fixture\',?,?,?)',[$row[0],$row[1],$row[2],$row[3],$row[4],$row[4].' 10:00:00']);
    $db->execute("INSERT INTO territory_rewards(world_id,continent_id,player_id,alliance_id,target_id,event_key,reward_json,created_at)VALUES(2,'fixture',1,1,'commune:fixture','campaign:1','{}','2026-10-19 10:00:00'),(3,'fixture',4,2,'commune:fixture','campaign:1','{}','2026-10-19 10:00:00')");
    $now=strtotime('2026-10-20 12:00:00 UTC');
    $db->execute("INSERT INTO battle_reports(world_id,attacker_id,attacker_city_id,target_type,target_x,target_y,outcome,data_json,created_at)VALUES(2,2,2,3,50,50,'attacker_wins','{}','2026-10-19 12:00:00'),(2,3,3,3,50,50,'defender_wins','{}','2026-10-19 12:00:00'),(3,4,4,3,50,50,'attacker_wins','{}','2026-10-19 12:00:00')");
    $summary=TerritoryContribution::summary(1,2,1,$now);
    alphaCheck($summary['support']===['scout'=>1,'supply'=>1],'personal summary excludes other members, worlds, alliances and out-of-window events');
    alphaCheck($summary['victories']===1&&$summary['participating_members']===2&&$summary['members']===2,'team participation deduplicates identities across contributions');
    alphaCheck(TerritoryContribution::summary(1,2,3,$now)===[],'other alliance summary is unavailable');
    alphaCheck(TerritoryContribution::summary(1,3,2,$now)===[],'other world summary is unavailable');
    $before=(int)$db->query('SELECT COUNT(*) FROM territory_support')->fetchColumn();
    $stats=AlphaPlaytestAnalytics::snapshot(2,30,$now);
    alphaCheck($stats['retention'][1]===['eligible'=>2,'returned'=>1,'percent'=>50.0],'D1 uses complete UTC days and unique player returns');
    alphaCheck($stats['retention'][7]===['eligible'=>2,'returned'=>1,'percent'=>50.0],'D7 uses complete UTC days and world-specific first recorded activity');
    alphaCheck(AlphaPlaytestAnalytics::snapshot(2,7,$now)['retention'][7]['eligible']===2,'default seven-day period includes mature D7 cohorts');
    alphaCheck($stats['cities']===3&&$stats['milestones']['castle']===1&&$stats['milestones']['alliance']===3,'current milestones use selected world only');
    alphaCheck($stats['milestones']['hunt']===1,'successful rally members count as hunters; defeated and foreign players do not');
    alphaCheck($stats['average_resources']['food']===100.0,'resources from other worlds do not distort balance snapshot');
    alphaCheck($stats['territory_supporters']===2,'supporter period ignores future and foreign-world contributions');
    $empty=AlphaPlaytestAnalytics::snapshot(2,1,$now);
    alphaCheck($empty['retention'][1]['percent']===null,'incomplete cohorts display unknown rather than zero retention');
    alphaCheck(AlphaPlaytestAnalytics::snapshot(2,30,$now)===$stats&&TerritoryContribution::summary(1,2,1,$now)===$summary,'repeated reads are stable');
    alphaCheck((int)$db->query('SELECT COUNT(*) FROM territory_support')->fetchColumn()===$before,'analysis does not mutate gameplay records');
    echo "ALL ALPHA PLAYTEST CHECKS PASSED\n";
}finally{$fixture->close();}
