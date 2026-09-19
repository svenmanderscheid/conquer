<?php
declare(strict_types=1);
/** Local HTTP acceptance checks. Only the freshly registered test city is edited. */
if (PHP_SAPI!=='cli') { http_response_code(403); exit; }
$base=rtrim($argv[1]??'http://localhost/conquer','/');
if (!in_array(parse_url($base,PHP_URL_HOST),['localhost','127.0.0.1'],true)) { exit("Local host only.\n"); }
define('ROOT_DIR',dirname(__DIR__));require ROOT_DIR.'/src/Bootstrap.php';\Conquer\Bootstrap::init(ROOT_DIR);
use Conquer\Db\Connection;
use Conquer\Game\City\{CityState,BuildingData};
use Conquer\Game\Treasure\TreasureService;
use Conquer\Game\Hospital\HospitalService;
$db=Connection::getInstance();$jar=tempnam(sys_get_temp_dir(),'pve-integration-');$csrf='';$checks=0;
function verifyPve(bool $ok,string $label): void { global $checks; if (!$ok) { throw new RuntimeException($label); } ++$checks; echo "PASS $label\n"; }
function callPve(string $path,?array $payload=null,bool $form=false,bool $token=true): array {
    global $base,$jar,$csrf;
    $ch=curl_init($base.$path);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEJAR=>$jar,CURLOPT_COOKIEFILE=>$jar,CURLOPT_TIMEOUT=>20]);
    if ($payload!==null) { curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$form?http_build_query($payload):json_encode($payload),CURLOPT_HTTPHEADER=>$form?[]:array_filter(['Content-Type: application/json',$token?'X-CSRF-Token: '.$csrf:null])]); }
    $text=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);if ($text===false) { throw new RuntimeException(curl_error($ch)); } curl_close($ch);
    return ['status'=>$status,'body'=>json_decode($text,true),'text'=>$text];
}
function pveData(string $path): array { $r=callPve($path);verifyPve($r['status']===200&&($r['body']['ok']??false),'GET '.$path);return $r['body']['data']; }
try {
    $name='PveReview'.date('His').random_int(10,99);$password=bin2hex(random_bytes(14));
    $page=callPve('/');preg_match('/name="csrf" value="([a-f0-9]+)"/',$page['text'],$m);
    verifyPve(isset($m[1]),'real registration form token');
    verifyPve(callPve('/auth/local',['mode'=>'register','username'=>$name,'password'=>$password,'alpha_key'=>'LOCAL-ALPHA-ACCESS-2026-KEY1','csrf'=>$m[1]],true)['status']===302,'fresh isolated test player registered');
    $game=pveData('/api/game/state');$csrf=$game['player']['csrf'];$cityId=(int)$game['city']['id'];
    $pid=(int)$db->query('SELECT player_id FROM cities WHERE id=?',[$cityId])->fetchColumn();
    $kingdom=pveData('/api/kingdom/state');$expeditions=pveData('/api/expeditions/state');
    verifyPve(isset($kingdom['profile']) && isset($expeditions['expeditions']),'both new systems return structured live state');
    $market=pveData('/api/market/state');verifyPve(count($market['offers'])===8,'market offers have explicit server-owned rates');
    verifyPve(callPve('/api/market/action',['offer_id'=>'food_lumber'],false,false)['status']===403,'trade requires CSRF');
    verifyPve(callPve('/api/market/action',['offer_id'=>'food;DROP TABLE cities'])['status']===400,'forged offer rejected');
    $before=CityState::loadForPlayer($pid)['city'];
    verifyPve(callPve('/api/market/action',['offer_id'=>'food_lumber'])['status']===200,'trade spends real resources');
    $after=CityState::loadForPlayer($pid)['city'];
    verifyPve(abs($after['food']-($before['food']-1000))<=2&&abs($after['lumber']-($before['lumber']+1000))<=2,'1:1 trade charges and delivers advertised amounts');
    $db->execute('UPDATE players SET gems=100 WHERE id=?',[$pid]);
    $gemsBefore=(int)$db->query('SELECT gems FROM players WHERE id=?',[$pid])->fetchColumn();
    $lumberBefore=(int)CityState::loadForPlayer($pid)['city']['lumber'];
    verifyPve(callPve('/api/market/action',['offer_id'=>'gems_lumber'])['status']===200,'crystal-priced merchant offer succeeds');
    verifyPve((int)$db->query('SELECT gems FROM players WHERE id=?',[$pid])->fetchColumn()===$gemsBefore-20&&(int)CityState::loadForPlayer($pid)['city']['lumber']>=$lumberBefore+1100,'crystal trade charges and delivers atomically');
    verifyPve(count(pveData('/api/market/state')['history'])===2,'trade history persists');
    $db->execute('UPDATE cities SET food=0,last_resource_update=UTC_TIMESTAMP() WHERE id=?',[$cityId]);
    verifyPve(callPve('/api/market/action',['offer_id'=>'food_lumber'])['status']===422,'unaffordable trade rejected');
    verifyPve((int)$db->query('SELECT COUNT(*) FROM market_exchanges WHERE player_id=?',[$pid])->fetchColumn()===2,'failed trade creates no receipt');
    $cap=BuildingData::getStorageCaps($game['buildings'])['food'];
    $db->execute('UPDATE cities SET food=?,last_resource_update=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 HOUR) WHERE id=?',[$cap+2000,$cityId]);
    verifyPve(pveData('/api/game/state')['city']['food']===$cap+2000,'earned over-cap balance survives server refresh');
    $db->execute('INSERT INTO player_treasures (player_id,treasure_code,fragments) VALUES (?,60200001,10)',[$pid]);
    verifyPve(!TreasureService::equipTreasure($pid,60200001,1,1),'rare relic cannot equip below its own fragment threshold');
    $db->execute('UPDATE player_treasures SET fragments=20 WHERE player_id=? AND treasure_code=60200001',[$pid]);
    verifyPve(TreasureService::equipTreasure($pid,60200001,1,1),'rare relic unlocks at its configured fragment threshold');
    $rare=array_values(array_filter(TreasureService::getPlayerTreasures($pid),fn($t)=>$t['treasure_code']===60200001))[0];
    verifyPve($rare['level']===1,'rare relic uses same level for UI and equipment');
    TreasureService::unequipTreasure($pid,60200001);
    verifyPve(TreasureService::equipTreasure($pid,60100002,1,1),'earned starter relic can be equipped');
    $rates=CityState::loadForPlayer($pid)['production_rates'];
    verifyPve(abs($rates['lumber_camp']-306)<0.01,'equipped two-percent production relic has actual effect');
    $db->execute('INSERT INTO city_troops (city_id,troop_code,count) VALUES (?,50100101,20) ON DUPLICATE KEY UPDATE count=20',[$cityId]);
    $db->execute('INSERT INTO hospital_wounded (city_id,troop_code,count,healing_count,healing_ends_at) VALUES (?,50100101,7,7,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND))',[$cityId]);
    HospitalService::processHealed($cityId);HospitalService::processHealed($cityId);
    verifyPve((int)$db->query('SELECT count FROM city_troops WHERE city_id=? AND troop_code=50100101',[$cityId])->fetchColumn()===27,'healed troops return exactly once');
    $blocked=false;try { \Conquer\Game\March\MarchDispatcher::dispatchPlayerAttack($pid,$cityId,0,0,(int)$game['city']['coord_x'],(int)$game['city']['coord_y'],[50100101=>1]); } catch (RuntimeException $e) { $blocked=str_contains($e->getMessage(),'eigene Stadt'); }
    verifyPve($blocked,'direct city attacks cannot target the own city');
    verifyPve(callPve('/api/alliance/leave',[])['status']===410,'retired alliance mutation cannot bypass expedition ownership rules');
    verifyPve(callPve('/api/inventory/use',['item_code'=>1])['status']===410,'retired inventory mutation cannot bypass audited inventory rules');
    $latest=pveData('/api/game/state');
    $scouts=array_values(array_filter($latest['monsters'],fn($m)=>(int)$m['monster_code']===20209901));
    $node=$latest['nodes'][0];$scout=$scouts[0];
    verifyPve(callPve('/api/march/dispatch',['target_x'=>$scout['coord_x'],'target_y'=>$scout['coord_y'],'troops'=>[50100101=>10]])['status']===200,'own fixture army dispatches to solo target');
    verifyPve(callPve('/api/march/dispatch-gather',['target_x'=>$node['coord_x'],'target_y'=>$node['coord_y'],'troop_count'=>10])['status']===200,'own fixture army dispatches to gather target');
    // Simulate returning after a long absence using only this new test player's timers.
    $db->execute("UPDATE marches SET departure_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 120 SECOND),arrival_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 60 SECOND) WHERE player_id=? AND state='marching'",[$pid]);
    $caughtUp=pveData('/api/game/state');
    verifyPve(count($caughtUp['marches'])===0&&($caughtUp['troops'][50100101]??0)===27,'offline solo and gather trips catch up through return without extra online waiting');
    verifyPve(str_contains(callPve('/city/3d?embed=1')['text'],'embedded: true'),'3D scene exposes the verified parent bridge');
    $db->execute('UPDATE cities SET food=5000,lumber=5000,stone=5000,gold=3000,last_resource_update=UTC_TIMESTAMP() WHERE id=?',[$cityId]);
    file_put_contents(sys_get_temp_dir().'/conquer-pve-review.json',json_encode(['username'=>$name,'password'=>$password,'base'=>$base]));
    echo "ALL $checks INTEGRATION CHECKS PASSED for $name. Local browser credentials saved in temporary conquer-pve-review.json.\n";
} catch(Throwable $e) { fwrite(STDERR,'FAIL '.$e->getMessage()."\n");exit(1); }
finally { if(is_file($jar))unlink($jar); }
