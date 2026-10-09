<?php
declare(strict_types=1);
/** Disposable database only: scouting, multi-member rallies and server window guards. */
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));date_default_timezone_set('UTC');require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';
use Conquer\Db\Connection;
use Conquer\Game\World\{WorldContext,WorldMapProfile};
use Conquer\Game\March\LandmarkScout;
use Conquer\Game\Rally\{ShrineRally,RallyService};
use Conquer\Game\Shrine\CongressService;
use Conquer\Game\Territory\TerritoryService;
function lc(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
function lr(callable $fn,string $label):void{try{$fn();}catch(DomainException|RuntimeException){lc(true,$label);return;}throw new RuntimeException($label);}
$fixture=new \ConquerTests\FeatureDatabase();
try{
 $db=Connection::getInstance();WorldContext::bind(1);\Conquer\Logger::init($fixture->sessionPath().'/http.log');
 $db->execute("INSERT INTO worlds(id,name,slug,status,map_size)VALUES(9,'Other fixture','other-landmark-fixture','running',256)");
 for($p=1;$p<=3;$p++){
  $db->execute("INSERT INTO players(id,username,email,password_hash)VALUES(?,?,?,'unused')",[$p,'Landmark'.$p,'landmark'.$p.'@invalid.test']);
  $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y)VALUES(?,?,1,'Fixture',?,40)",[$p,$p,30+$p]);
  $db->execute('INSERT INTO city_troops(city_id,troop_code,count)VALUES(?,50100101,10000)',[$p]);
 }
 $db->execute("INSERT INTO alliances(id,world_id,name,tag,leader_id)VALUES(1,1,'Fixture Dawn','DAWN',1)");
 $db->execute("INSERT INTO alliance_members(alliance_id,player_id,role)VALUES(1,1,'leader'),(1,2,'member')");
 $db->execute("INSERT INTO shrines(id,world_id,shrine_code,tier,coord_x,coord_y)VALUES(71,1,'CONGRESS','S',128,128),(72,1,'SHRINE_ICE','B',145,35)");
 $db->execute('INSERT INTO shrine_captures(shrine_id,garrison_troops_json)VALUES(71,?)',[json_encode([50100101=>10])]);
 $scout=LandmarkScout::dispatch(3,3,'shrine','71')['march_id'];
 lc((int)$db->query('SELECT COUNT(*) FROM battle_reports')->fetchColumn()===0,'scouting creates no intelligence before arrival');
 $db->execute('UPDATE shrine_captures SET garrison_troops_json=? WHERE shrine_id=71',[json_encode([50100101=>15])]);
 $db->execute('UPDATE marches SET arrival_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id=?',[$scout]);
 WorldContext::bind(9);LandmarkScout::resolve(['id'=>$scout]);LandmarkScout::resolve(['id'=>$scout]);lc(WorldContext::id()===9,'scout resolves its stored world and restores the caller context');WorldContext::bind(1);
 $data=json_decode($db->query('SELECT data_json FROM battle_reports WHERE march_id=?',[$scout])->fetchColumn(),true);
 lc($data['type']==='landmark_scout'&&$data['troops'][50100101]===15,'scouting records defenders at arrival');
 lc((int)$db->query('SELECT COUNT(*) FROM battle_reports WHERE march_id=?',[$scout])->fetchColumn()===1,'repeat scouting settlement creates one report');
 $mail=\Conquer\Game\Community\MailboxService::state(3,1)['entries'][0]??null;lc($mail&&$mail['category']==='war'&&str_contains($mail['subject'],'Kongress'),'landmark scouting appears in War mail with the target name');
 lr(fn()=>ShrineRally::start(3,3,71,[50100101=>10],5),'rally requires an alliance');
 if(!\Conquer\Game\Shrine\ShrineEvent::state()['active'])lr(fn()=>ShrineRally::start(1,1,72,[50100101=>10],5),'closed shrine event rejects rally on server');
 $rally=ShrineRally::start(1,1,71,[50100101=>100],5);RallyService::join(2,2,$rally,[50100101=>100]);
 lc((int)$db->query('SELECT count FROM city_troops WHERE city_id=1 AND troop_code=50100101')->fetchColumn()===9900,'leader troops reserved once');
 lc((int)$db->query('SELECT count FROM city_troops WHERE city_id=2 AND troop_code=50100101')->fetchColumn()===9900,'joining troops reserved once');
 $db->execute('UPDATE rally_participants SET arrival_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE rally_id=?',[$rally]);RallyService::launch($rally,1);
 $db->execute('UPDATE rallies SET arrival_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id=?',[$rally]);WorldContext::bind(9);RallyService::tick();RallyService::tick();lc(WorldContext::id()===9,'rally settlement uses its stored world and restores the caller context');WorldContext::bind(1);
 if(CongressService::state(1)['alliance_id']!==1)throw new RuntimeException(json_encode($db->query('SELECT status,result_json FROM rallies WHERE id=?',[$rally])->fetch()).' '.file_get_contents($fixture->sessionPath().'/http.log'));
 lc(CongressService::state(1)['alliance_id']===1,'combined rally captures Congress');
 lc((int)$db->query('SELECT COUNT(*) FROM shrine_garrisons WHERE shrine_id=71')->fetchColumn()===2,'both members hold separate real garrisons');
 lc((int)$db->query("SELECT COUNT(*) FROM battle_reports WHERE JSON_EXTRACT(data_json,'$.rally_id')=?",[$rally])->fetchColumn()===2,'each member receives exactly one combat report');
 $db->execute('UPDATE rallies SET return_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id=?',[$rally]);RallyService::tick();RallyService::tick();
 lc((int)$db->query('SELECT count FROM city_troops WHERE city_id=1 AND troop_code=50100101')->fetchColumn()===9900,'garrisoned survivors are not also refunded to the city');
 lr(fn()=>ShrineRally::start(1,1,71,[50100101=>10],5),'owned Congress rejects a new rally');
 $db->execute('UPDATE shrine_captures SET alliance_id=NULL,garrison_troops_json=? WHERE shrine_id=71',[json_encode([50100101=>1000000])]);$db->execute('DELETE FROM shrine_garrisons');
 $losing=ShrineRally::start(1,1,71,[50100101=>10],5);RallyService::launch($losing,1);$db->execute('UPDATE rallies SET arrival_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id=?',[$losing]);RallyService::tick();
 lc($db->query('SELECT status FROM rallies WHERE id=?',[$losing])->fetchColumn()==='returning','losing rally returns survivors');
 $db->execute('UPDATE rallies SET return_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id=?',[$losing]);$before=(int)$db->query('SELECT count FROM city_troops WHERE city_id=1 AND troop_code=50100101')->fetchColumn();RallyService::tick();RallyService::tick();
 lc((int)$db->query('SELECT count FROM city_troops WHERE city_id=1 AND troop_code=50100101')->fetchColumn()===$before+2,'defeated survivors return exactly once');
 $token=bin2hex(random_bytes(32));$csrf=str_repeat('b',64);
 $db->execute("INSERT INTO sessions(player_id,token,csrf_token,ip_address,user_agent,expires_at,active_world_id)VALUES(1,?,?,'127.0.0.1','landmark test',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),1)",[$token,$csrf]);
 $source=str_replace(['declare(strict_types=1);',"define('ROOT_DIR', __DIR__);","require_once ROOT_DIR . '/src/Bootstrap.php';",'\\Conquer\\Bootstrap::init(ROOT_DIR);'],'',file_get_contents(ROOT_DIR.'/index.php'));$source=preg_replace('/^<\?php\s*/','',$source);
 $url=$fixture->serve("\$_SERVER['SCRIPT_NAME']='/index.php'; ".$source,['-d','display_errors=0']);
 $post=static function(string $path,array $body,string $csrfValue)use($url,$token,$db):array{
  $db->execute('DELETE FROM security_rate_limits');$h=curl_init($url.'/api/'.$path);curl_setopt_array($h,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_TIMEOUT=>15,CURLOPT_POSTFIELDS=>json_encode($body),CURLOPT_HTTPHEADER=>['Content-Type: application/json','Cookie: conquer_session='.$token,'X-CSRF-Token: '.$csrfValue]]);$raw=curl_exec($h);$status=curl_getinfo($h,CURLINFO_HTTP_CODE);curl_close($h);return [$status,json_decode((string)$raw,true)];
 };
 $body=['target_id'=>71,'rally_minutes'=>5,'troops'=>[50100101=>10],'expected_world_id'=>1,'operation_key'=>'landmark_rally_receipt_001'];
 lc($post('rally/start-shrine',$body,'invalid')[0]===403,'shrine rally rejects invalid CSRF');
 $first=$post('rally/start-shrine',$body,$csrf);lc($first[0]===200,'shrine rally HTTP accepted '.json_encode($first));
 $stock=(int)$db->query('SELECT count FROM city_troops WHERE city_id=1 AND troop_code=50100101')->fetchColumn();
 lc($post('rally/start-shrine',$body,$csrf)[1]===$first[1]&&(int)$db->query('SELECT count FROM city_troops WHERE city_id=1 AND troop_code=50100101')->fetchColumn()===$stock,'HTTP rally retry returns one reservation and original rally');
 $body['troops']=[50100101=>11];lc($post('rally/start-shrine',$body,$csrf)[0]===409,'changed rally payload cannot reuse the receipt');
 RallyService::cancel((int)$first[1]['data']['rally_id'],1);
 // A lazy tick after event closure still honors a valid scheduled arrival.
 $event=\Conquer\Game\Shrine\ShrineEvent::state();$arrival=strtotime($event['starts_at'].' UTC')-604800+600;
 $delayed=ShrineRally::start(1,1,71,[50100101=>100],5);$meta=json_decode($db->query('SELECT result_json FROM rallies WHERE id=?',[$delayed])->fetchColumn(),true);$meta['shrine_id']=72;$meta['event_instance']=\Conquer\Game\Shrine\ShrineEvent::state($arrival)['instance_id'];
 $db->execute('INSERT INTO shrine_captures(shrine_id,garrison_troops_json)VALUES(72,?)',[json_encode([50100101=>10])]);
 $db->execute("UPDATE rallies SET target_x=145,target_y=35,status='marching',arrival_time=?,return_time=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),result_json=? WHERE id=?",[gmdate('Y-m-d H:i:s',$arrival),json_encode($meta),$delayed]);RallyService::tick();
 lc(CongressService::detail(72,1)['alliance_id']===1,'late event processing honors arrival inside the original attack window');
 $db->execute("INSERT INTO worlds(id,name,slug,status,map_size)VALUES(2,'Territory fixture','landmark-fixture','running',768)");WorldMapProfile::configureEmptyWorld(2);WorldContext::bind(2);TerritoryService::ensureWorld(2);
 $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y)VALUES(4,1,2,'Lux fixture',100,350)");$target=TerritoryService::state(1,2)['targets'][0];
 $scout=LandmarkScout::dispatch(1,4,'territory',$target['id'])['march_id'];$db->execute('UPDATE marches SET arrival_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id=?',[$scout]);LandmarkScout::resolve(['id'=>$scout]);
 $data=json_decode($db->query('SELECT data_json FROM battle_reports WHERE march_id=?',[$scout])->fetchColumn(),true);
 lc($data['landmark_id']===$target['id']&&$data['npc_troops']>0,'Commune scouting supports string IDs and actual NPC defense');
 lc(\Conquer\Security\ApiOperation::protects('/api/march/scout-landmark',[])&&\Conquer\Security\ApiOperation::protects('/api/rally/start-shrine',[]),'both new write endpoints require replay receipts');
 echo "Landmark server actions passed.\n";
}finally{$fixture->close();}
