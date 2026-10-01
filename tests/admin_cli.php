<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require dirname(__DIR__).'/src/Auth/AdminCli.php';
use Conquer\Auth\AdminCli;
// Manual/PTY smoke mode: exercises the real terminal transport without a DB.
if(($argv[1]??'')==='--terminal'){
    exit(AdminCli::run(['TerminalFixture'],static function($u,$p,$r,$m):int{
        if($p!=="CLI-ÄÖ-Terminal-2026!")throw new RuntimeException('Terminal input mismatch');return 73;
    },STDIN,STDOUT,STDERR));
}
$checks=0;
function cliCheck(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException($label);$checks++;echo "PASS $label\n";}
$secret='  Sicheres-ÄÖ-Schlüssel-2026!  ';
$root=sys_get_temp_dir().'/conquer_admin_cli_'.bin2hex(random_bytes(6));
mkdir($root.'/bin',0700,true);mkdir($root.'/cron',0700,true);mkdir($root.'/src/Auth',0700,true);
copy(dirname(__DIR__).'/bin/create_admin.php',$root.'/bin/create_admin.php');
copy(dirname(__DIR__).'/cron/create_admin.php',$root.'/cron/create_admin.php');
copy(dirname(__DIR__).'/src/Auth/AdminCli.php',$root.'/src/Auth/AdminCli.php');
file_put_contents($root.'/src/Bootstrap.php', <<<'PHP'
<?php
namespace Conquer {final class Bootstrap{public static function init(string $root):void{file_put_contents($root.'/booted','1');}}}
namespace Conquer\Auth {final class AdminAuth{public static function createAdmin(string $user,string $password,string $role,bool $must=false):int{
$expected=json_decode(getenv('CLI_TEST_EXPECTED'),true);
if(getenv('CLI_TEST_THROW'))throw new \RuntimeException($password);
if([$user,hash('sha256',$password),$role,$must]!==$expected)throw new \RuntimeException('Fixture mismatch');
file_put_contents(ROOT_DIR.'/created','1');return 71;
}}}
PHP);
try{
    $run=static function(string $entry,array $args,string $input,?array $expected=null,bool $throws=false)use($root,$secret):array{
        foreach(['booted','created']as$f)if(is_file($root.'/'.$f))unlink($root.'/'.$f);
        $env=getenv();$env['CLI_TEST_EXPECTED']=json_encode($expected);$env['CLI_TEST_THROW']=$throws?'1':'';
        $p=proc_open([PHP_BINARY,$root.'/'.$entry.'/create_admin.php',...$args],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$root,$env,['bypass_shell'=>true]);
        if(!is_resource($p))throw new RuntimeException('CLI process unavailable');
        fwrite($pipes[0],$input);fclose($pipes[0]);$out=stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($p);
        cliCheck(!str_contains($out,$secret),'credential sentinel absent from process output');
        return [$code,is_file($root.'/booted'),is_file($root.'/created')];
    };
    foreach(['bin'=>'moderator','cron'=>'superadmin']as$entry=>$default){
        cliCheck($run($entry,['FixtureAdmin','--password-stdin'],$secret."\r\n",['FixtureAdmin',hash('sha256',$secret),$default,false])===[0,true,true],"$entry exact UTF-8 and whitespace, CRLF, default role");
        cliCheck($run($entry,['FixtureAdmin','--must-change','moderator','--password-stdin'],$secret."\n",['FixtureAdmin',hash('sha256',$secret),'moderator',true])===[0,true,true],"$entry flags independent of role position");
        cliCheck($run($entry,['FixtureAdmin',$secret,'superadmin'],'')===[1,false,false],"$entry rejects legacy secret argument before bootstrap");
        cliCheck($run($entry,['FixtureAdmin'],$secret."\n")===[1,false,false],"$entry refuses implicit redirected password");
        cliCheck($run($entry,['--help'],'')===[0,false,false],"$entry help has no application side effects");
    }
    foreach(['',"short\n",$secret."\nsecond",$secret."\n\n",$secret."\0",str_repeat('A',201)]as$input){cliCheck($run('bin',['FixtureAdmin','--password-stdin'],$input)===[1,false,false],'malformed, empty or oversized stdin rejected before bootstrap');}
    foreach([[],['FixtureAdmin','--unknown'],['FixtureAdmin','--password-stdin','--password-stdin'],['FixtureAdmin','moderator','superadmin']]as$args){cliCheck($run('bin',$args,'')===[1,false,false],'invalid arguments rejected without application initialization');}
    foreach([14,200]as$length){$value=str_repeat('Z',$length);cliCheck($run('bin',['FixtureAdmin','--password-stdin'],$value,['FixtureAdmin',hash('sha256',$value),'moderator',false])===[0,true,true],'documented password length accepted without terminator');}
    cliCheck($run('bin',['FixtureAdmin','--password-stdin'],$secret,null,true)===[1,true,false],'driver error text cannot echo password');
    $mock=static function(array $values,bool $throw=false)use($secret):array{
        $in=fopen('php://temp','w+');$out=fopen('php://temp','w+');$err=fopen('php://temp','w+');$calls=0;
        $reader=static function()use(&$values,$throw,$secret):string{if($throw)throw new RuntimeException($secret);return array_shift($values);};
        $status=AdminCli::run(['Mock','--must-change'],static function($u,$p,$r,$m)use(&$calls,$secret):int{if([$u,$p,$r,$m]!==['Mock',$secret,'moderator',true])throw new RuntimeException('Wrong interactive values');$calls++;return 72;},$in,$out,$err,'moderator',$reader);
        rewind($out);rewind($err);$text=stream_get_contents($out).stream_get_contents($err);fclose($in);fclose($out);fclose($err);cliCheck(!str_contains($text,$secret),'hidden-reader output never contains password');return[$status,$calls];
    };
    cliCheck($mock([$secret,$secret])===[0,1],'interactive confirmation preserves exact password and invokes creation once');
    cliCheck($mock([$secret,'different-secret-value'])===[1,0],'confirmation mismatch prevents creation');
    cliCheck($mock([],true)===[1,0],'hidden helper failure prevents creation without unsafe fallback');
    echo "ALL $checks ADMIN CLI CHECKS PASSED (isolated stubs; no database access)\n";
}finally{
    foreach(['bin/create_admin.php','cron/create_admin.php','src/Auth/AdminCli.php','src/Bootstrap.php','booted','created']as$f)if(is_file($root.'/'.$f))unlink($root.'/'.$f);
    foreach(['bin','cron','src/Auth','src','']as$d)rmdir($root.($d!==''?'/'.$d:''));
}
