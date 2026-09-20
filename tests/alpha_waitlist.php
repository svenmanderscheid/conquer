<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));
require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';
use Conquer\Auth\AlphaWaitlist;
use Conquer\Db\Connection;
function checkWaitlist(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
$fixture=new \ConquerTests\FeatureDatabase();
try{
 $db=Connection::getInstance();
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
}finally{$fixture->close();}
