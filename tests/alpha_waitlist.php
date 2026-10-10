<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));
require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';
use Conquer\Auth\AlphaWaitlist;
use Conquer\Admin\AlphaWaitlistAdmin;
use Conquer\Db\Connection;
function checkWaitlist(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
$fixture=new \ConquerTests\FeatureDatabase();
try{
 $db=Connection::getInstance();
 checkWaitlist(AlphaWaitlistAdmin::emails($db)===[],'empty waitlist has no copy recipients');
 $input=['first_name'=>' Élise ','last_name'=>'Müller','email'=>' Tester@Tests.Invalid ','consent'=>'1'];
 AlphaWaitlist::join($input,'fr');
 $before=$db->query('SELECT * FROM alpha_waitlist')->fetch();
 checkWaitlist($before['first_name']==='Élise'&&$before['email']==='tester@tests.invalid'&&$before['locale']==='fr','normalized name, email and chosen language');
 checkWaitlist($before['consent_version']===AlphaWaitlist::CONSENT_VERSION&&$before['consent_at']!==null,'versioned consent saved');
 $db->execute('UPDATE alpha_waitlist SET invited_at=UTC_TIMESTAMP()');
 $before=$db->query('SELECT * FROM alpha_waitlist')->fetch();
 AlphaWaitlist::join(array_replace($input,['first_name'=>'Someone else','last_name'=>'Changed']),'de');
 checkWaitlist($db->query('SELECT * FROM alpha_waitlist')->fetch()===$before,'retry cannot overwrite identity, consent or invitation');
 foreach([
  ['first_name'=>[]],['first_name'=>"a\0b"],['last_name'=>str_repeat('ü',81)],
  ['last_name'=>'  '],['email'=>'bad@'],['email'=>[]],['consent'=>true],['consent'=>'0']
 ] as $change){
  $rejected=false;try{AlphaWaitlist::join(array_replace($input,$change),'de');}catch(InvalidArgumentException){$rejected=true;}
  checkWaitlist($rejected,'rejects invalid '.array_key_first($change));
 }
 checkWaitlist((int)$db->query('SELECT COUNT(*) FROM alpha_waitlist')->fetchColumn()===1,'invalid and repeated requests add no records');
 checkWaitlist((int)$db->query('SELECT COUNT(*) FROM players')->fetchColumn()===0,'waitlist does not create a player or grant game access');
 $list=AlphaWaitlistAdmin::listing($db,['q'=>'tester@tests.invalid','status'=>'invited']);
 checkWaitlist($list['total']===1&&$list['rows'][0]['email']==='tester@tests.invalid','admin listing exposes stored alpha email');
 checkWaitlist(is_file(ROOT_DIR.'/views/admin/alpha_waitlist.php'),'admin waitlist view exists');
 for($i=1;$i<=31;$i++){
  AlphaWaitlist::join(['first_name'=>'Copy','last_name'=>'Fixture '.sprintf('%02d',$i),'email'=>sprintf('copy%02d@tests.invalid',$i),'consent'=>'1'],'en');
 }
 $db->execute("UPDATE alpha_waitlist SET invited_at=UTC_TIMESTAMP() WHERE email='copy02@tests.invalid'");
 $filtered=AlphaWaitlistAdmin::listing($db,['q'=>'copy01','status'=>'waiting','page'=>2]);
 $pageTwo=AlphaWaitlistAdmin::listing($db,['page'=>2]);
 $emails=AlphaWaitlistAdmin::emails($db);
 checkWaitlist($filtered['total']===1&&count($pageTwo['rows'])===7&&count($emails)===32,'copy recipients include filtered-out registrations and every page');
 checkWaitlist(in_array('tester@tests.invalid',$emails,true)&&in_array('copy02@tests.invalid',$emails,true),'copy recipients include waiting and invited entries');
 $expected=$emails;sort($expected,SORT_STRING);
 checkWaitlist($emails===$expected&&count(array_unique($emails))===32,'copy recipients are sorted without repeated addresses');
 if(in_array('--browser',$argv,true)){
  $db->execute("INSERT INTO admin_users(username,password_hash,role) VALUES('CopyAdmin',?,'superadmin'),('CopyModerator',?,'moderator')",[password_hash('Waitlist-Fixture-2026!',PASSWORD_DEFAULT),password_hash('Waitlist-Fixture-2026!',PASSWORD_DEFAULT)]);
  $routes= <<<'PHP'
  define('APP_BASE','/conquer');$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
  if(!str_starts_with($path,APP_BASE.'/')){http_response_code(404);exit;}$path=substr($path,strlen(APP_BASE));
  if(str_starts_with($path,'/assets/')){$file=realpath(ROOT_DIR.$path);if(!$file||!str_starts_with($file,realpath(ROOT_DIR.'/assets').DIRECTORY_SEPARATOR)||!is_file($file)){http_response_code(404);exit;}header('Content-Type: '.(['css'=>'text/css','js'=>'application/javascript','svg'=>'image/svg+xml','png'=>'image/png','webp'=>'image/webp','woff2'=>'font/woff2'][pathinfo($file,PATHINFO_EXTENSION)]??'application/octet-stream'));readfile($file);exit;}
  session_name('conquer_waitlist_copy_fixture');session_start();
  if($path==='/admin/login'){if($_SERVER['REQUEST_METHOD']==='POST')\Conquer\Admin\AdminController::loginPost();else \Conquer\Admin\AdminController::loginPage();}
  elseif($path==='/admin/logout')\Conquer\Admin\AdminController::logout();
  elseif($path==='/admin/alpha-waitlist')\Conquer\Admin\AdminController::alphaWaitlist();
  elseif($path==='/admin')\Conquer\Admin\AdminController::dashboard();
  else http_response_code(404);
  PHP;
  $base=$fixture->serve($routes).'/conquer';
  $process=proc_open(['node',ROOT_DIR.'/tests/alpha_waitlist_copy.cjs',$base],[0=>['pipe','r'],1=>STDOUT,2=>STDERR],$pipes,ROOT_DIR,null,['bypass_shell'=>true]);
  if(!is_resource($process))throw new RuntimeException('Browser test did not start.');fclose($pipes[0]);
  if(proc_close($process)!==0)throw new RuntimeException('Waitlist copy browser checks failed.');
 }
}finally{$fixture->close();}
