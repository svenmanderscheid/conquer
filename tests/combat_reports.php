<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));
require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require ROOT_DIR.'/tests/Support/FeatureDatabase.php';
use Conquer\Game\March\BattleReportService;
use Conquer\Game\Community\MailboxService;
function checkReport(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
$fixture=new \ConquerTests\FeatureDatabase();
try{
    $db=\Conquer\Db\Connection::getInstance();
    $db->execute("INSERT INTO players(id,username,email,password_hash) VALUES(1,'PreviewPlayer','combat@tests.invalid','unused')");
    $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y) VALUES(1,1,1,'Preview',65,65)");
    foreach(\Conquer\Game\City\CityState::BUILDING_CODES as$code)$db->execute('INSERT INTO city_buildings(city_id,building_code,level) VALUES(1,?,7)',[$code]);
    (require ROOT_DIR.'/tests/fixtures/combat_reports.php')($db);
    $row=$db->query('SELECT id,data_json FROM battle_reports WHERE attacker_id=1 ORDER BY id LIMIT 1')->fetch();
    $original=json_decode($row['data_json'],true);$c=$original['combat'];
    checkReport(count($c['defender']['armies'])===2,'garrison and reinforcement are recorded individually');
    checkReport($c['defender']['totals']['sent']===12500,'defense totals include 2000 reinforcements');
    $support=$c['defender']['armies'][1];$remaining=json_decode($db->query('SELECT troops_json FROM reinforcements WHERE sender_id=3')->fetchColumn(),true);
    checkReport($support['totals']['survived']===array_sum($remaining),'reinforcement report matches surviving stored support troops');
    foreach([$c['attacker'],$c['defender']]as$side)foreach($side['armies']as$army){
        $hospital=(int)$db->query('SELECT COALESCE(SUM(count),0) FROM hospital_wounded WHERE city_id=?',[$army['player_id']])->fetchColumn();
        checkReport($hospital===$army['totals']['injured'],'hospital agrees with report for '.$army['name']);
        checkReport(count($army['equipment'])===6&&$army['hunter_level']===30&&count($army['talents'])===1,'equipment, Hunter level and learned talents captured for '.$army['name']);
    }
    checkReport($c['attacker']['types']['infantry']['bonuses']['atk']>0,'active talent and equipment bonuses are included');
    $db->execute("UPDATE players SET username='LaterName' WHERE id=1");
    $db->execute('DELETE FROM player_treasure_loadouts WHERE player_id=1');
    $db->execute('DELETE FROM player_lord_talents WHERE player_id=1');
    $read=BattleReportService::get(1,(int)$row['id']);
    checkReport($read['details']===$original,'later equipment, talent and name changes cannot alter the saved battle');
    $inbox=MailboxService::state(1,1,['category'=>'war']);
    checkReport(count($inbox['entries'])===2,'attacker inbox contains its current and archive report without opponent copies');
    $defense=MailboxService::state(2,1,['category'=>'war']);
    checkReport(count($defense['entries'])===1&&$defense['entries'][0]['subject']==='Verteidigung: Niederlage','defender has one correctly labeled personal defeat');
    $defId=(int)$db->query('SELECT id FROM battle_reports WHERE attacker_id=2')->fetchColumn();
    checkReport(BattleReportService::get(1,$defId)===null,'opponent personal report is not readable');
    $mine=BattleReportService::get(2,$defId);
    checkReport($mine['details']['combat']===$c&&$mine['outcome']==='defender_wins','defender preserves the same snapshot with its own outcome');
    echo "ALL COMBAT REPORT CHECKS PASSED (disposable database).\n";
}finally{$fixture->close();}
