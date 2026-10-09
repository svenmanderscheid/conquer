<?php
declare(strict_types=1);
/** Invitations and admission use only disposable local database fixtures. */
if(PHP_SAPI!=='cli')exit(1);
date_default_timezone_set('UTC');define('ROOT_DIR',dirname(__DIR__));
require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
use Conquer\Db\Connection;
use Conquer\Game\Community\AllianceCommunityService as Alliance;
use Conquer\Game\Kingdom\KingdomService as Kingdom;
use Conquer\Game\World\WorldContext as World;
if(($argv[1]??'')==='--worker'){
    $dir=realpath($argv[2]??'');
    if(!$dir||dirname($dir)!==realpath(sys_get_temp_dir())||!preg_match('/^conquer_feature_test_[a-f0-9]{12}$/D',basename($dir)))exit(2);
    Connection::init($dir);World::bind(1);
    try{echo json_encode(['ok'=>true,'result'=>Alliance::action((int)$argv[3],json_decode($argv[4],true,16,JSON_THROW_ON_ERROR))]);}
    catch(DomainException $e){echo json_encode(['ok'=>false]);}exit;
}
require __DIR__.'/Support/FeatureDatabase.php';$fixture=new \ConquerTests\FeatureDatabase();$db=Connection::getInstance();$serial=0;
function inviteCheck(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
function inviteAct(int $player,string $action,array $body=[],?string $receipt=null):array{global $serial;return Alliance::action($player,['action'=>$action,'request_id'=>$receipt??'invite_fixture_'.str_pad((string)++$serial,8,'0',STR_PAD_LEFT),'world_id'=>World::id()]+$body)['result'];}
function inviteDeny(callable $call,string $label):void{try{$call();}catch(DomainException){inviteCheck(true,$label);return;}throw new RuntimeException('Unexpected acceptance: '.$label);}
function inviteMember(int $player):array|false{return Connection::getInstance()->query('SELECT * FROM alliance_members WHERE player_id=? AND world_id=1',[$player])->fetch();}
try{
    \Conquer\Db\MigrationSql::apply($db->getPdo(),(string)file_get_contents(ROOT_DIR.'/migrations/0141_alliance_invitations.sql'));
    $db->execute("INSERT INTO worlds(id,name,slug,status)VALUES(2,'Other world','invitation-other','running')");
    for($id=1;$id<=16;$id++){
        $world=$id===10?2:1;
        $db->execute('INSERT INTO players(id,username,email,password_hash)VALUES(?,?,?,?)',[$id,'InviteFixture'.$id,'invite'.$id.'@test.invalid','unused']);
        $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y)VALUES(?,?,?,'Fixture',?,90)",[$id,$id,$world,50+$id*3]);
        $db->execute("INSERT INTO city_buildings(city_id,building_code,level)VALUES(?,'castle',1)",[$id]);
    }
    $db->execute("INSERT INTO alliances(id,world_id,name,tag,leader_id,max_members,recruitment_mode)VALUES(1,1,'First alliance','ONE',1,30,'application'),(2,1,'Second alliance','TWO',4,30,'open'),(3,2,'Other world','OTH',10,30,'open')");
    foreach([[1,1,1,'leader'],[1,2,1,'vice_leader'],[1,3,1,'officer'],[2,4,1,'leader'],[3,10,2,'leader']]as$row)$db->execute('INSERT INTO alliance_members(alliance_id,player_id,world_id,role)VALUES(?,?,?,?)',$row);
    World::bind(1);
    inviteDeny(fn()=>inviteAct(3,'invitation.send',['player_id'=>5]),'R3 cannot send invitations');
    inviteDeny(fn()=>inviteAct(5,'invitation.send',['player_id'=>6]),'players without an alliance cannot invite');
    inviteDeny(fn()=>inviteAct(1,'invitation.send',['player_id'=>1]),'self invitations are rejected');
    inviteDeny(fn()=>inviteAct(1,'invitation.send',['player_id'=>4]),'existing membership cannot be overwritten');
    inviteDeny(fn()=>inviteAct(1,'invitation.send',['player_id'=>10]),'cross-world invitation target is rejected');
    $db->execute('UPDATE players SET is_banned=1 WHERE id=16');
    inviteDeny(fn()=>inviteAct(1,'invitation.send',['player_id'=>16]),'banned recipients are rejected');
    $a=inviteAct(2,'invitation.send',['player_id'=>5],'invitation_send_receipt_0001');
    inviteCheck(!inviteMember(5),'sending an invitation does not create membership');
    inviteCheck(inviteAct(2,'invitation.send',['player_id'=>5],'invitation_send_receipt_0001')===$a,'send replay returns the original result');
    inviteCheck(inviteAct(1,'invitation.send',['player_id'=>5])['invitation_id']===$a['invitation_id']&&(int)$db->query('SELECT COUNT(*) FROM alliance_invitations')->fetchColumn()===1,'a second leader cannot create a duplicate pending invitation');
    inviteDeny(fn()=>inviteAct(2,'invitation.send',['player_id'=>6],'invitation_send_receipt_0001'),'changed request replay is rejected');
    $incoming=Alliance::state(5,1)['invitations'];
    inviteCheck(count($incoming)===1&&$incoming[0]['tag']==='ONE'&&$incoming[0]['sender_name']==='InviteFixture2','recipient sees alliance, sender and expiration');
    inviteCheck(count(Alliance::state(1,1)['sent_invitations'])===1&&Alliance::state(3,1)['sent_invitations']===[]&&Alliance::state(6,1)['invitations']===[],'inbox and outbox are private to recipient and leadership');
    $candidates=Alliance::state(1,1,['invite_search'=>'InviteFixture'])['invite_candidates'];
    $candidateIds=array_map('intval',array_column($candidates,'player_id'));
    inviteCheck(in_array(5,$candidateIds,true)&&!array_intersect([1,2,3,4,10,16],$candidateIds)&&isset($candidates[0]['power']),'candidate search shows only unallied players in the same world with power');
    inviteCheck(Alliance::state(3,1,['invite_search'=>'InviteFixture'])['invite_candidates']===[],'ordinary members cannot use recruitment search');
    $db->execute("UPDATE players SET username='ExactTarget' WHERE id=15");
    $byId=Alliance::state(1,1,['invite_search'=>'15'])['invite_candidates'];
    inviteCheck(count($byId)===1&&(int)$byId[0]['player_id']===15&&$byId[0]['username']==='ExactTarget','numeric search finds exact player ID even without a matching name');
    inviteCheck(Alliance::state(1,1,['invite_search'=>'10'])['invite_candidates']===[],'numeric lookup still respects world membership');
    inviteDeny(fn()=>inviteAct(1,'invitation.accept',['invitation_id'=>$a['invitation_id']]),'sender cannot accept for the recipient');
    inviteDeny(fn()=>inviteAct(6,'invitation.decline',['invitation_id'=>$a['invitation_id']]),'another player cannot decline the invitation');
    inviteDeny(fn()=>inviteAct(4,'invitation.revoke',['invitation_id'=>$a['invitation_id']]),'another alliance cannot revoke the invitation');
    $other=inviteAct(4,'invitation.send',['player_id'=>5]);
    $accepted=inviteAct(5,'invitation.accept',['invitation_id'=>$a['invitation_id']],'invitation_accept_receipt_0001');
    inviteCheck(inviteAct(5,'invitation.accept',['invitation_id'=>$a['invitation_id']],'invitation_accept_receipt_0001')===$accepted,'acceptance is replay safe');
    $member=inviteMember(5);inviteCheck((int)$member['alliance_id']===1&&$member['role']==='member'&&(int)$member['role_level']===1,'accepting invitation joins an application alliance as R1');
    inviteCheck(Alliance::state(5,1)['invitations']===[]&&$db->query('SELECT status FROM alliance_invitations WHERE id=?',[$other['invitation_id']])->fetchColumn()==='revoked','joining closes competing pending invitations');
    inviteDeny(fn()=>inviteAct(5,'invitation.accept',['invitation_id'=>$a['invitation_id']]),'new receipt cannot accept a completed invitation again');
    $declined=inviteAct(1,'invitation.send',['player_id'=>6]);inviteAct(6,'invitation.decline',['invitation_id'=>$declined['invitation_id']]);
    inviteCheck(!inviteMember(6)&&Alliance::state(6,1)['invitations']===[],'declining leaves the recipient unallied');
    inviteDeny(fn()=>inviteAct(1,'invitation.send',['player_id'=>6]),'declined invitations cannot be resent immediately');
    $revoked=inviteAct(2,'invitation.send',['player_id'=>7]);inviteAct(1,'invitation.revoke',['invitation_id'=>$revoked['invitation_id']]);
    inviteDeny(fn()=>inviteAct(7,'invitation.accept',['invitation_id'=>$revoked['invitation_id']]),'revoked invitations cannot be accepted');
    $expired=inviteAct(1,'invitation.send',['player_id'=>8]);$db->execute('UPDATE alliance_invitations SET expires_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id=?',[$expired['invitation_id']]);
    inviteCheck(Alliance::state(8,1)['invitations']===[],'expired invitations disappear from inbox');
    inviteDeny(fn()=>inviteAct(8,'invitation.accept',['invitation_id'=>$expired['invitation_id']]),'expired invitations cannot be accepted');
    $power=inviteAct(1,'invitation.send',['player_id'=>9]);$db->execute('UPDATE alliances SET minimum_power=99999999 WHERE id=1');
    inviteDeny(fn()=>inviteAct(9,'invitation.accept',['invitation_id'=>$power['invitation_id']]),'acceptance rechecks minimum power');
    inviteDeny(fn()=>inviteAct(1,'invitation.send',['player_id'=>11]),'sending respects minimum power');
    $db->execute('UPDATE alliances SET minimum_power=0 WHERE id=1');
    $full=inviteAct(1,'invitation.send',['player_id'=>11]);$db->execute('UPDATE alliances SET max_members=4 WHERE id=1');
    inviteDeny(fn()=>inviteAct(11,'invitation.accept',['invitation_id'=>$full['invitation_id']]),'acceptance rechecks capacity');
    inviteDeny(fn()=>inviteAct(1,'invitation.send',['player_id'=>12]),'full alliances cannot issue invitations');
    $db->execute('UPDATE alliances SET max_members=30 WHERE id=1');
    inviteAct(1,'invitation.send',['player_id'=>12]);inviteAct(12,'alliance.join',['alliance_id'=>2]);
    inviteCheck(Alliance::state(12,1)['invitations']===[],'direct joining closes other invitations');
    inviteAct(1,'invitation.send',['player_id'=>13]);
    $originalJoin=new ReflectionMethod(Kingdom::class,'joinAlliance');$db->transaction(fn()=>$originalJoin->invoke(null,13,['alliance_id'=>2]));
    inviteCheck(Alliance::state(13,1)['invitations']===[],'original Kingdom join route also closes invitations');
    $ap=inviteAct(14,'application.submit',['alliance_id'=>1]);inviteAct(1,'invitation.send',['player_id'=>14]);inviteAct(1,'application.accept',['application_id'=>$ap['application_id']]);
    inviteCheck(Alliance::state(14,1)['invitations']===[]&&(int)inviteMember(14)['alliance_id']===1,'application approval preserves admission and clears invitations');
    // Two genuine PHP processes compete for the last seat.
    $raceA=inviteAct(1,'invitation.send',['player_id'=>11]);$raceB=inviteAct(1,'invitation.send',['player_id'=>15]);
    $db->execute('UPDATE alliances SET max_members=6 WHERE id=1');$directory=$fixture->sessionPath();$workers=[];
    foreach([[11,$raceA['invitation_id']],[15,$raceB['invitation_id']]]as[$pid,$inviteId]){
        $payload=['action'=>'invitation.accept','invitation_id'=>$inviteId,'request_id'=>'invitation_race_'.$pid.'_000000','world_id'=>1];
        $process=proc_open([PHP_BINARY,__FILE__,'--worker',$directory,(string)$pid,json_encode($payload)],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,ROOT_DIR,null,['bypass_shell'=>true]);fclose($pipes[0]);$workers[]=[$process,$pipes];
    }
    $wins=0;foreach($workers as[$process,$pipes]){$output=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($process);$result=json_decode($output,true);inviteCheck($code===0&&is_array($result),'concurrent invitation worker completed: '.$errors);$wins+=(int)$result['ok'];}
    inviteCheck($wins===1&&(int)$db->query('SELECT COUNT(*) FROM alliance_members WHERE alliance_id=1')->fetchColumn()===6,'competing acceptances cannot exceed capacity');
    inviteAct(4,'invitation.send',['player_id'=>6]);
    $originalCreate=new ReflectionMethod(Kingdom::class,'createAlliance');$db->transaction(fn()=>$originalCreate->invoke(null,6,['name'=>'New alliance','tag'=>'NEW','description'=>'']));
    inviteCheck(Alliance::state(6,1)['invitations']===[]&&inviteMember(6)['role']==='leader','creating an alliance also closes pending invitations');
    $db->execute("UPDATE worlds SET status='closed' WHERE id=1");inviteDeny(fn()=>inviteAct(1,'invitation.send',['player_id'=>9]),'closed world blocks invitation writes');
    echo "PASS alliance invitations service\n";
}finally{$fixture->close();}
