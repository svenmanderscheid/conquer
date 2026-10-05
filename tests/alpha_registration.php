<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));
require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';require __DIR__.'/Support/HttpApp.php';
use Conquer\Auth\AlphaAccess;
use Conquer\Db\Connection;
use ConquerTests\HttpApp;
function alphaAssert(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
$fixture=new \ConquerTests\FeatureDatabase();$jars=[];$mail=tempnam(sys_get_temp_dir(),'alpha-mail-');
try {
    $db=Connection::getInstance();$db->execute("UPDATE worlds SET status='running' WHERE id=1");
    $base=$fixture->serve(HttpApp::source([], $mail),['-d','disable_functions=mail','-d','display_errors=0']);
    for($i=0;$i<3;$i++)$jars[]=tempnam(sys_get_temp_dir(),'alpha-cookie-');
    $key=AlphaAccess::generate($db,'Isolated HTTP alpha',1);$name='AlphaIsolated';$password='Alpha-password-2026!';
    $csrf=static function(int $i)use($base,$jars):string{$r=HttpApp::request($base,'/','GET',[],null,$jars[$i]);preg_match('/name="csrf" value="([a-f0-9]+)"/',$r['body'],$m);return $m[1]??'';};
    $post=static fn(int $i,array $data):array=>HttpApp::request($base,'/auth/local','POST',['Content-Type: application/x-www-form-urlencoded'],http_build_query($data),$jars[$i]);
    $token=$csrf(0);alphaAssert($token!=='','public registration supplies CSRF');
    $form=['mode'=>'register','alpha_key'=>$key,'username'=>$name,'email'=>'alpha@example.invalid','password'=>$password,'csrf'=>$token];
    $created=$post(0,$form);alphaAssert($created['status']===302,'valid single-use key and email create isolated account');
    alphaAssert((bool)$db->query('SELECT beginner_shield_until > DATE_ADD(UTC_TIMESTAMP(),INTERVAL 6 DAY) FROM players WHERE username=?',[$name])->fetchColumn(),'new account receives seven-day beginner protection');
    alphaAssert((int)$db->query('SELECT uses_count FROM alpha_access_keys')->fetchColumn()===1,'key consumed exactly once');
    $mails=array_values(array_filter(explode("\n",(string)file_get_contents($mail))));alphaAssert(count($mails)===1,'verification message captured without external delivery');
    $message=json_decode($mails[0],true);preg_match('/token=([a-f0-9]{64})/',$message['body'],$tokenMatch);
    alphaAssert(isset($tokenMatch[1]),'captured verification includes token');
    $verified=HttpApp::request($base,'/auth/verify-email?token='.$tokenMatch[1]);alphaAssert($verified['status']<400&&(bool)$db->query('SELECT email_verified_at FROM players WHERE username=?',[$name])->fetchColumn(),'verification link confirms isolated mailbox');
    $reuse=$post(1,array_replace($form,['username'=>'AlphaSecond','email'=>'second@example.invalid','csrf'=>$csrf(1)]));
    alphaAssert($reuse['status']===200&&str_contains($reuse['body'],'ungültig oder nicht mehr verfügbar'),'used key cannot register twice');
    $login=$post(2,['mode'=>'login','identifier'=>'alpha@example.invalid','password'=>$password,'csrf'=>$csrf(2)]);
    alphaAssert($login['status']===302,'existing invited account signs in by email');
    $entry=HttpApp::request($base,'/','GET',[],null,$jars[2]);
    alphaAssert($entry['status']===302&&in_array('location: /city',$entry['headers'],true),'returning authenticated player enters the city directly');
    $db->execute('UPDATE players SET is_banned=1 WHERE username=?',[$name]);
    $me=HttpApp::request($base,'/api/auth/me','GET',[],null,$jars[2]);alphaAssert($me['status']===401,'banning player revokes API access immediately');
    $db->execute("INSERT INTO worlds(id,name,slug,status,map_size,map_seed)VALUES(2,'Second registration world','registration-two','running',256,42),(3,'Closed registration world','registration-three','paused',256,42)");
    $secondKey=AlphaAccess::generate($db,'Second village account',1);
    $second=['mode'=>'register','alpha_key'=>$secondKey,'username'=>'AlphaOtherWorld','email'=>'other-world@example.invalid','password'=>$password,'csrf'=>$csrf(1),'world_id'=>'3'];
    $closed=$post(1,$second);
    alphaAssert($closed['status']===200&&!(bool)$db->query("SELECT id FROM players WHERE username='AlphaOtherWorld'")->fetchColumn(),'paused world rolls registration back without creating an account');
    alphaAssert((int)$db->query("SELECT uses_count FROM alpha_access_keys WHERE label='Second village account'")->fetchColumn()===0,'failed world selection preserves the alpha key');
    \Conquer\Game\Vip\VipService::setPoints((int)$db->query('SELECT id FROM players WHERE username=?',[$name])->fetchColumn(),200000,1);
    $second['csrf']=$csrf(1);$second['world_id']='2';$other=$post(1,$second);
    alphaAssert($other['status']===302,'another account can register in the selected world');
    $otherId=(int)$db->query("SELECT id FROM players WHERE username='AlphaOtherWorld'")->fetchColumn();
    alphaAssert((int)$db->query('SELECT COUNT(*) FROM cities WHERE player_id=?',[$otherId])->fetchColumn()===1&&(int)$db->query('SELECT world_id FROM cities WHERE player_id=?',[$otherId])->fetchColumn()===2,'registration creates one village in the requested world');
    alphaAssert(\Conquer\Game\Vip\VipService::status($otherId,2)['points']===200,'second account begins with fresh world VIP progress');
    echo "ALL ISOLATED ALPHA REGISTRATION CHECKS PASSED\n";
}finally{$fixture->close();foreach([...$jars,$mail]as$f)if(is_file($f))unlink($f);}
