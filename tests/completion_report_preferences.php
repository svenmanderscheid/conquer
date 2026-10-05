<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR.'/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
date_default_timezone_set('UTC');
require __DIR__.'/Support/FeatureDatabase.php';
require __DIR__.'/Support/HttpApp.php';

use Conquer\Db\Connection;
use Conquer\Game\City\CityState;
use Conquer\Game\Community\MailboxService as Mail;
use Conquer\Game\Kingdom\KingdomService as Kingdom;
use Conquer\Game\Notification\NotificationService as Notices;
use Conquer\Game\Research\ResearchProcessor;
use Conquer\Game\World\WorldContext;

function reportCheck(bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException($label);
    echo "PASS $label\n";
}

$fixture = new \ConquerTests\FeatureDatabase();
try {
    $db = Connection::getInstance();
    foreach ([1,2] as $player) {
        $db->execute('INSERT INTO players(id,username,email,password_hash) VALUES(?,?,?,?)', [$player,'Reports'.$player,'reports'.$player.'@tests.invalid','unused']);
        $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,castle_level) VALUES(?,?,1,'Reports',?,65,5)", [$player,$player,65+$player*8]);
        foreach (CityState::BUILDING_CODES as $code) $db->execute('INSERT INTO city_buildings(city_id,building_code,level) VALUES(?,?,5)', [$player,$code]);
    }
    WorldContext::bind(1,1);
    $types = ['build_complete','research_complete','train_complete'];
    $keys = array_map(static fn(string $type): string => 'report_'.$type, $types);
    $settings = Kingdom::state(1)['settings'];
    reportCheck(array_intersect_key($settings,array_flip($keys))===array_fill_keys($keys,true), 'all completion reports enabled by default');
    $save = static fn(array $choices): array => Kingdom::action(1,['action'=>'settings.save','reduced_motion'=>false,'compact_numbers'=>false,'confirm_actions'=>true]+$choices);
    $send = static function(int $city=1) use($db,$types): array {
        $ids=[];
        foreach ($types as $type) { Notices::pushCityCompletion($city,$type,['building_code'=>'farm']); $ids[$type]=$db->lastInsertId(); }
        return $ids;
    };
    $inboxIds = static fn(): array => array_map('intval', $db->query("SELECT source_id FROM mailbox_entries WHERE player_id=1 AND source='notification'")->fetchAll(PDO::FETCH_COLUMN));
    $original=$send(); Mail::sync(1,1);
    reportCheck(count(array_intersect($original,$inboxIds()))===3, 'default choices deliver all three reports');
    foreach ($keys as $disabled) {
        $choices=array_fill_keys($keys,true); $choices[$disabled]=false;
        reportCheck(array_intersect_key($save($choices)['state']['settings'],$choices)===$choices, 'save and read back independent choice '.$disabled);
        $batch=$send(); Mail::sync(1,1);
        foreach ($batch as $type=>$id) reportCheck(in_array($id,$inboxIds(),true)===$choices['report_'.$type], 'delivery follows only its own switch: '.$disabled.' / '.$type);
    }
    $save(array_fill_keys($keys,false));
    $save([]);
    reportCheck(array_intersect_key(Kingdom::state(1)['settings'],array_flip($keys))===array_fill_keys($keys,false), 'older clients preserve preferences when omitting new fields');
    foreach ([null,0,1,'false',[],new stdClass()] as $invalid) {
        try { $save(['report_build_complete'=>true,'report_train_complete'=>$invalid]); throw new RuntimeException('Invalid preference accepted'); }
        catch (DomainException) {}
    }
    reportCheck(array_intersect_key(Kingdom::state(1)['settings'],array_flip($keys))===array_fill_keys($keys,false), 'invalid boolean values rejected without partial updates');

    $db->execute("INSERT INTO building_queue(city_id,building_code,level_to,started_at,finishes_at) VALUES(1,'farm',6,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 MINUTE),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND))");
    $db->execute("INSERT INTO troop_queue(city_id,troop_code,count,barrack_slot,started_at,finishes_at) VALUES(1,50100101,10,1,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 MINUTE),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND))");
    $db->execute("INSERT INTO research_queue(player_id,world_id,research_code,level_to,finishes_at) VALUES(1,1,'food_production',1,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND))");
    $last=(int)$db->query('SELECT MAX(id) FROM notifications')->fetchColumn();
    ResearchProcessor::processQueue(1,1); CityState::loadForPlayer(1,1);
    $muted=array_values(array_filter(Notices::buildingCompletions(1,1,1),static fn(array $n): bool => $n['id']>$last));
    reportCheck(count($muted)===3 && count(array_filter($muted,static fn(array $n): bool => $n['data']['report_enabled']===false))===3, 'muted actual completions retain all building readiness markers');
    reportCheck((int)$db->query("SELECT level FROM city_buildings WHERE city_id=1 AND building_code='farm'")->fetchColumn()===6 && (int)$db->query('SELECT count FROM city_troops WHERE city_id=1 AND troop_code=50100101')->fetchColumn()===10 && (int)$db->query("SELECT level FROM player_research WHERE player_id=1 AND world_id=1 AND research_code='food_production'")->fetchColumn()===1, 'buildings research and troops credited while reports are disabled');
    Mail::sync(1,1);
    reportCheck(!array_intersect(array_column($muted,'id'),$inboxIds()), 'muted actual completions create no mail');
    reportCheck(!array_intersect(array_column($muted,'id'),array_column(Notices::getPending(1),'id')) && Notices::getUnreadCount(1)===count(Notices::getPending(1)), 'muted completions excluded from notification delivery and unread count');
    Notices::pushCityCompletion(1,Notices::TYPE_HEAL_COMPLETE,['count'=>2]); $heal=$db->lastInsertId();
    Mail::sync(1,1);
    reportCheck(in_array($heal,$inboxIds(),true), 'healing and unrelated reports remain enabled');
    $others=$send(2); Mail::sync(2,1);
    reportCheck((int)$db->query("SELECT COUNT(*) FROM mailbox_entries WHERE player_id=2 AND source='notification'")->fetchColumn()===3, 'preferences do not change another player or defaults before profile creation');

    $save(array_fill_keys($keys,true)); $enabled=$send(); Mail::sync(1,1);
    reportCheck(count(array_intersect($enabled,$inboxIds()))===3 && !array_intersect(array_column($muted,'id'),$inboxIds()) && count(array_intersect($original,$inboxIds()))===3, 're-enabling delivers future reports without backfilling muted reports or removing old mail');
    Notices::markRead(1,array_column($muted,'id'));
    reportCheck(!array_intersect(array_column($muted,'id'),array_column(Notices::buildingCompletions(1,1,1),'id')), 'muted readiness markers can still be acknowledged');
    $db->execute("INSERT INTO worlds(id,name,slug,status) VALUES(2,'Other Realm','reports-other','running')");
    $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y) VALUES(3,1,2,'Other City',80,80)");
    $save(['report_build_complete'=>false]);
    Notices::pushCityCompletion(3,Notices::TYPE_BUILD_COMPLETE,[]);
    WorldContext::run(2,static function(): void { Mail::sync(1,2); reportCheck(Mail::state(1,2,['category'=>'system'])['entries']===[] && count(Notices::buildingCompletions(1,3,2))===1, 'account preference also applies to background completions in another world'); });

    $token=bin2hex(random_bytes(32)); $csrf=bin2hex(random_bytes(32));
    $db->execute("INSERT INTO sessions(player_id,token,csrf_token,ip_address,user_agent,expires_at,active_world_id) VALUES(1,?,?,'127.0.0.1','report fixture',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),1)",[$token,$csrf]);
    $url=$fixture->serve('\\Conquer\\Api\\Handlers\\KingdomHandler::action([]);');
    $payload=json_encode(['action'=>'settings.save','reduced_motion'=>false,'compact_numbers'=>false,'confirm_actions'=>true,'report_train_complete'=>false]);
    $request=static fn(array $headers): array => \ConquerTests\HttpApp::request($url,'/action','POST',['Content-Type: application/json',...$headers],$payload);
    reportCheck($request([])['status']===401 && $request(['Cookie: conquer_session='.$token])['status']===403, 'settings endpoint requires login and CSRF');
    $response=$request(['Cookie: conquer_session='.$token,'X-CSRF-Token: '.$csrf]);
    reportCheck($response['status']===200 && $response['json']['data']['state']['settings']['report_train_complete']===false, 'authenticated settings request persists and returns report choice');
    echo "ALL COMPLETION REPORT PREFERENCE CHECKS PASSED\n";
} finally { $fixture->close(); }
