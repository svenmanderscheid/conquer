<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR.'/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';
require __DIR__.'/Support/HttpApp.php';
use Conquer\Auth\AlphaAccess;
use Conquer\Db\Connection;
use ConquerTests\HttpApp;
function openAlphaAssert(bool $ok,string $label):void { if(!$ok)throw new RuntimeException($label);echo "PASS $label\n"; }
$fixture=new \ConquerTests\FeatureDatabase();$jars=[];$mail=tempnam(sys_get_temp_dir(),'open-alpha-mail-');
try {
    $db=Connection::getInstance();
    $db->execute("INSERT INTO worlds(id,name,slug,status,map_size,map_seed) VALUES(2,'Open second world','open-second','running',256,42),(3,'Paused world','paused-world','paused',256,42)");
    AlphaAccess::generate($db,'Unconsumed legacy invitation',1);
    $base=$fixture->serve(HttpApp::source(['open_alpha'=>true],$mail),['-d','disable_functions=mail','-d','display_errors=0']);
    for($i=0;$i<2;$i++)$jars[]=tempnam(sys_get_temp_dir(),'open-alpha-cookie-');
    $csrf=static function(int $i)use($base,$jars):string {
        $r=HttpApp::request($base,'/?mode=register','GET',[],null,$jars[$i]);
        openAlphaAssert($r['status']===200&&!str_contains($r['body'],'name="alpha_key"')&&str_contains($r['body'],'No key needed'),'production registration form offers access without an invitation field');
        preg_match('/name="csrf" value="([a-f0-9]+)"/',$r['body'],$m);return $m[1]??'';
    };
    $post=static fn(int $i,array $body):array=>HttpApp::request($base,'/auth/local','POST',['Content-Type: application/x-www-form-urlencoded'],http_build_query($body),$jars[$i]);
    $password='Open-alpha-password-2026!';
    $form=['mode'=>'register','username'=>'OpenAlphaFirst','email'=>'open-first@example.invalid','password'=>$password,'world_id'=>'2','csrf'=>$csrf(0)];
    $created=$post(0,$form);
    openAlphaAssert($created['status']===302,'production registration creates an account without any key');
    $player=$db->query('SELECT id,alpha_access_key_id,beginner_shield_until>DATE_ADD(UTC_TIMESTAMP(),INTERVAL 6 DAY) AS shield FROM players WHERE username=?',[$form['username']])->fetch();
    openAlphaAssert($player&&$player['alpha_access_key_id']===null&&(bool)$player['shield'],'new account has no invitation association and receives seven-day protection');
    openAlphaAssert((int)$db->query('SELECT world_id FROM cities WHERE player_id=?',[(int)$player['id']])->fetchColumn()===2,'registration respects the selected open world');
    $bad=$post(1,array_replace($form,['username'=>'NoCsrfPlayer','email'=>'no-csrf@example.invalid','csrf'=>'invalid']));
    openAlphaAssert($bad['status']===200&&(int)$db->query('SELECT COUNT(*) FROM players')->fetchColumn()===1,'missing or invalid CSRF cannot create an account');
    $duplicate=$post(1,array_replace($form,['username'=>'DuplicateMailbox','csrf'=>$csrf(1)]));
    openAlphaAssert($duplicate['status']===200&&(int)$db->query('SELECT COUNT(*) FROM players')->fetchColumn()===1,'a duplicate mailbox cannot create another account');
    $second=array_replace($form,['username'=>'OpenAlphaSecond','email'=>'open-second@example.invalid','alpha_key'=>'not-a-valid-key','world_id'=>'3','csrf'=>$csrf(1)]);
    $paused=$post(1,$second);
    openAlphaAssert($paused['status']===200&&(int)$db->query('SELECT COUNT(*) FROM players')->fetchColumn()===1,'paused world rolls back the new account');
    $second['world_id']='1';$second['csrf']=$csrf(1);$other=$post(1,$second);
    openAlphaAssert($other['status']===302,'an obsolete or invalid key field does not block Open Alpha registration');
    openAlphaAssert((int)$db->query('SELECT uses_count FROM alpha_access_keys')->fetchColumn()===0,'Open Alpha does not consume existing invitations');
    openAlphaAssert((int)$db->query('SELECT COUNT(*) FROM cities')->fetchColumn()===2,'each successful account has exactly one village');
    $messages=array_values(array_filter(explode("\n",(string)file_get_contents($mail))));
    openAlphaAssert(count($messages)===2,'both successful accounts receive verification through the isolated mail sink');
    $db->execute('UPDATE players SET is_banned=1 WHERE id=?',[(int)$player['id']]);
    $me=HttpApp::request($base,'/api/auth/me','GET',[],null,$jars[0]);
    openAlphaAssert($me['status']===401,'Open Alpha retains immediate API revocation for banned accounts');
    (new ReflectionProperty(\Conquer\Bootstrap::class,'config'))->setValue(null,['open_alpha'=>false]);
    openAlphaAssert(!AlphaAccess::isOpen(),'the server can return registration to invitation-only mode');
    echo "ALL OPEN ALPHA REGISTRATION CHECKS PASSED\n";
} finally { $fixture->close();foreach([...$jars,$mail]as$file)if(is_file($file))unlink($file); }
