<?php
declare(strict_types=1);
/** Real front-controller admission checks using an isolated schema-only fixture. */
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));date_default_timezone_set('UTC');
require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';require __DIR__.'/Support/HttpApp.php';
use Conquer\Db\Connection;
use ConquerTests\HttpApp;
function admissionCheck(bool $ok,string $label,?array $response=null):void{if(!$ok)throw new RuntimeException($label.($response?' | '.json_encode($response,JSON_UNESCAPED_UNICODE):''));echo "PASS $label\n";}
$fixture=new \ConquerTests\FeatureDatabase();
try{
    $db=Connection::getInstance();$db->execute("INSERT INTO worlds(id,name,slug,status)VALUES(2,'Second world','admission-http','running')");
    foreach([1=>1,2=>2,3=>1,4=>2,5=>1,6=>1]as$pid=>$world){
        $db->execute('INSERT INTO players(id,username,email,password_hash)VALUES(?,?,?,?)',[$pid,'Admission'.$pid,'admission'.$pid.'@tests.invalid','unused']);
        $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y)VALUES(?,?,?,'Fixture',?,90)",[$pid,$pid,$world,50+$pid*3]);
        $db->execute("INSERT INTO city_buildings(city_id,building_code,level)VALUES(?,'castle',1)",[$pid]);
    }
    $db->execute("INSERT INTO alliances(id,world_id,name,tag,leader_id,recruitment_mode)VALUES(1,1,'Application Alliance','APP',1,'application'),(2,1,'Open Alliance','OPEN',1,'open'),(3,2,'Second World Alliance','OTHER',2,'open')");
    $db->execute("INSERT INTO alliance_members(alliance_id,player_id,world_id,role)VALUES(1,1,1,'leader'),(3,2,2,'leader')");
    $tokens=[];$csrf=str_repeat('d',64);
    foreach([1=>1,3=>1,4=>2,5=>1,6=>1]as$pid=>$world){$tokens[$pid]=str_pad((string)$pid,64,'0');$db->execute("INSERT INTO sessions(player_id,token,csrf_token,ip_address,user_agent,expires_at,active_world_id)VALUES(?,?,?,'127.0.0.1','admission-http',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),?)",[$pid,$tokens[$pid],$csrf,$world]);}
    $base=$fixture->serve(HttpApp::source(),['-d','display_errors=0']);
    $call=static function(string $path,array $body,int $pid=3,bool $sendCsrf=true)use($db,$base,$tokens,$csrf):array{
        $db->execute('DELETE FROM security_rate_limits');$headers=['Content-Type: application/json'];
        if($pid)$headers[]='Cookie: conquer_session='.$tokens[$pid];if($sendCsrf)$headers[]='X-CSRF-Token: '.$csrf;
        return HttpApp::request($base,$path,'POST',$headers,json_encode($body,JSON_THROW_ON_ERROR));
    };
    $legacy='/api/alliance/join';$community='/api/community/alliance-action';
    $joinBody=['action'=>'alliance.join','request_id'=>'admission_http_join_0001'];
    $r=$call($legacy,['alliance_id'=>2],0);admissionCheck($r['status']===401,'legacy join requires authentication',$r);
    $r=$call($legacy,['alliance_id'=>2],3,false);admissionCheck($r['status']===403,'legacy join requires CSRF',$r);
    $r=$call($legacy,['alliance_id'=>1]);admissionCheck($r['status']===410&&$r['json']['error']['code']==='ACTION_RETIRED','retired legacy route cannot bypass current admission rules',$r);
    $r=$call($community,$joinBody+['alliance_id'=>1]);admissionCheck($r['status']===422,'active join cannot bypass application mode',$r);
    $db->execute('UPDATE alliances SET minimum_power=99999999 WHERE id=2');
    $r=$call($community,$joinBody+['alliance_id'=>2]);admissionCheck($r['status']===422,'active join enforces minimum power',$r);
    $db->execute('UPDATE alliances SET minimum_power=0,max_members=0 WHERE id=2');
    $r=$call($community,$joinBody+['alliance_id'=>2]);admissionCheck($r['status']===422,'active join enforces current capacity',$r);
    $db->execute('UPDATE alliances SET max_members=30 WHERE id=2');
    $r=$call($community,$joinBody+['alliance_id'=>3]);admissionCheck($r['status']===404,'active join cannot join another world',$r);
    $r=$call($community,$joinBody+['alliance_id'=>3,'world_id'=>2]);admissionCheck($r['status']===409,'body cannot switch the active world',$r);
    $r=$call($community,$joinBody+['alliance_id'=>3,'world_id'=>2],4);admissionCheck($r['status']===200&&(int)$r['json']['data']['result']['alliance_id']===3,'player joins the active second world',$r);
    admissionCheck((int)$db->query('SELECT world_id FROM alliance_members WHERE player_id=4')->fetchColumn()===2,'membership retains the actual world');
    $send=['action'=>'invitation.send','player_id'=>5,'world_id'=>1,'request_id'=>'invitation_http_send_0001'];
    $r=$call($community,$send,0);admissionCheck($r['status']===401,'invitation endpoint requires authentication',$r);
    $r=$call($community,$send,1,false);admissionCheck($r['status']===403,'invitation endpoint requires CSRF',$r);
    $sent=$call($community,$send,1);admissionCheck($sent['status']===200,'leadership sends invitation over the real API',$sent);
    $replay=$call($community,$send,1);admissionCheck($replay['status']===200&&$replay['json']['data']===$sent['json']['data'],'API send replay is stable',$replay);
    $inviteId=$sent['json']['data']['result']['invitation_id'];$accept=['action'=>'invitation.accept','invitation_id'=>$inviteId,'world_id'=>1,'request_id'=>'invitation_http_accept_0001'];
    $r=$call($community,$accept,3);admissionCheck($r['status']===404,'API cannot accept another recipient invitation',$r);
    $r=$call($community,$accept,5);admissionCheck($r['status']===200&&(int)$db->query('SELECT alliance_id FROM alliance_members WHERE player_id=5 AND world_id=1')->fetchColumn()===1,'recipient accepts the invitation over the real API',$r);
    $r=$call($community,array_replace($send,['player_id'=>3,'request_id'=>'invitation_http_send_0002']),1);admissionCheck($r['status']===200,'unaffiliated player can receive another invitation',$r);
    $join=$joinBody+['alliance_id'=>2,'world_id'=>1];$joined=$call($community,$join);
    admissionCheck($joined['status']===200&&(int)$joined['json']['data']['result']['alliance_id']===2,'successful join response identifies the alliance',$joined);
    $replay=$call($community,$join);admissionCheck($replay['status']===200&&$replay['json']['data']===$joined['json']['data'],'supplied join request ID is replay safe',$replay);
    $r=$call($community,array_replace($join,['alliance_id'=>1]));admissionCheck($r['status']===422,'changed join replay is rejected',$r);
    admissionCheck((int)$db->query("SELECT COUNT(*) FROM alliance_invitations WHERE player_id=3 AND status='pending'")->fetchColumn()===0,'join closes competing invitations');
    $db->execute("UPDATE worlds SET status='closed' WHERE id=1");
    $r=$call($community,$joinBody+['alliance_id'=>2],6);admissionCheck($r['status']===409,'closed worlds reject join',$r);
    echo "PASS alliance admission HTTP\n";
}finally{$fixture->close();}
