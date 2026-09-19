<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();date_default_timezone_set('UTC');
use Conquer\Db\Connection;
use Conquer\Game\Support\BugReportService;

function checkBug(bool $condition,string $message):void{if(!$condition)throw new RuntimeException($message);echo 'PASS '.$message.PHP_EOL;}
function rejectBug(callable $call,string $message):void{try{$call();}catch(DomainException){checkBug(true,$message);return;}throw new RuntimeException('Expected rejection: '.$message);}

$cfg=require ROOT_DIR.'/config/database.php';$schema='conquer_bug_test_'.bin2hex(random_bytes(6));$server=null;$created=false;$exit=0;
try{
    if(!in_array($cfg['host']??'', ['127.0.0.1','localhost'],true))throw new RuntimeException('Local database required.');
    $server=new PDO('mysql:host='.$cfg['host'].';port='.($cfg['port']??3306).';charset=utf8mb4',$cfg['username'],$cfg['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $server->exec('CREATE DATABASE `'.$schema.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');$created=true;
    $pdo=new PDO('mysql:host='.$cfg['host'].';port='.($cfg['port']??3306).';dbname='.$schema.';charset=utf8mb4',$cfg['username'],$cfg['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    $pdo->exec('CREATE TABLE players(id INT NOT NULL PRIMARY KEY) ENGINE=InnoDB');
    $pdo->exec('INSERT INTO players(id) VALUES(1)');
    \Conquer\Db\MigrationSql::apply($pdo,(string)file_get_contents(ROOT_DIR.'/migrations/0099_bug_reports.sql'));
    $reflection=new ReflectionClass(Connection::class);$db=$reflection->newInstanceWithoutConstructor();$reflection->getProperty('pdo')->setValue($db,$pdo);$reflection->getProperty('instance')->setValue(null,$db);
    $payload=['operation_key'=>'bug_test_operation_0001','category'=>'interface','severity'=>'blocking','title'=>'Button bleibt gesperrt','description'=>'Nach der Auswahl bleibt der Hauptknopf weiterhin gesperrt.','reproduction_steps'=>'Fenster öffnen und Eintrag auswählen.','expected_result'=>'Der Knopf wird aktiv.','page_path'=>'/city#inventory','client_context'=>['viewport'=>'390x844','language'=>'de-LU']];
    $first=BugReportService::submit(1,1,$payload,'Test Browser','127.0.0.1');$again=BugReportService::submit(1,1,$payload,'Test Browser','127.0.0.1');
    checkBug(!$first['duplicate']&&$again['duplicate']&&$first['id']===$again['id']&&(int)$db->query('SELECT COUNT(*) FROM bug_reports')->fetchColumn()===1,'submission retry creates exactly one report');
    $saved=$db->query('SELECT category,severity,page_path,client_context FROM bug_reports WHERE id=?',[$first['id']])->fetch();$context=json_decode($saved['client_context'],true);
    checkBug($saved['category']==='interface'&&$saved['severity']==='blocking'&&$saved['page_path']==='/city#inventory'&&$context['viewport']==='390x844','report stores player details and safe diagnostics');
    rejectBug(fn()=>BugReportService::submit(1,1,array_replace($payload,['operation_key'=>'bug_test_operation_0002','description'=>'Too short']),'',''),'short description rejected');
    rejectBug(fn()=>BugReportService::submit(1,1,array_replace($payload,['operation_key'=>'bug_test_operation_0003','category'=>'forged']),'',''),'unknown category rejected');
    for($i=2;$i<=5;$i++)BugReportService::submit(1,1,array_replace($payload,['operation_key'=>'bug_test_operation_000'.$i,'title'=>'Valid report '.$i]),'','');
    rejectBug(fn()=>BugReportService::submit(1,1,array_replace($payload,['operation_key'=>'bug_test_operation_0006','title'=>'Sixth valid report']),'',''),'hourly abuse limit enforced');
    echo "ALL BUG REPORT CHECKS PASSED\n";
}catch(Throwable $e){$exit=1;fwrite(STDERR,'FAIL '.$e->getMessage().' at '.$e->getFile().':'.$e->getLine().PHP_EOL);}
finally{if($created&&$server&&preg_match('/^conquer_bug_test_[a-f0-9]{12}$/D',$schema))$server->exec('DROP DATABASE `'.$schema.'`');}
exit($exit);
