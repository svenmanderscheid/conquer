<?php
declare(strict_types=1);
define('ROOT_DIR',dirname(__DIR__));
require ROOT_DIR.'/src/Bootstrap.php';\Conquer\Bootstrap::init(ROOT_DIR);
require __DIR__.'/Support/FeatureDatabase.php';
use Conquer\Game\Ui\ExtraEventButton;
use Conquer\Admin\AdminService;
$fixture=new \ConquerTests\FeatureDatabase();
function eventCheck(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);echo "PASS $message\n";}
try{
 $admin=\Conquer\Auth\AdminAuth::createAdmin('EventAdmin','Event-test-2026!','superadmin',false);
 $reader=\Conquer\Auth\AdminAuth::createAdmin('EventReader','Event-test-2026!','moderator',false);
 $start=strtotime('2026-10-03 10:00:00 UTC');
 $input=['world_id'=>'1','operation_id'=>bin2hex(random_bytes(16)),'reason'=>'Event button test','enabled'=>'1','name_en'=>'Harvest Festival','name_de'=>'Erntefest','name_fr'=>'','starts_at'=>'2026-10-03T10:00','ends_at'=>'2026-10-03T11:00','icon'=>'events','target'=>'events'];
 eventCheck(ExtraEventButton::active(1,$start)===null,'No event is visible by default');
 AdminService::execute($admin,'extra-event-save',$input);
 eventCheck(AdminService::execute($admin,'extra-event-save',$input)['duplicate'],'Duplicate save uses receipt');
 eventCheck(ExtraEventButton::active(1,$start-1)===null,'Hidden before start');
 eventCheck(ExtraEventButton::active(1,$start)['name']==='Harvest Festival','Visible exactly at start');
 eventCheck(ExtraEventButton::active(1,$start+3600)===null,'Hidden exactly at end');
 eventCheck(ExtraEventButton::active(2,$start)===null,'World scoping');
 $_COOKIE['conquer_locale']='de';eventCheck(ExtraEventButton::active(1,$start)['name']==='Erntefest','German name');
 $_COOKIE['conquer_locale']='fr';eventCheck(ExtraEventButton::active(1,$start)['name']==='Harvest Festival','English fallback');unset($_COOKIE['conquer_locale']);
 foreach([['target'=>'https://example.test'],['icon'=>'../../secret'],['ends_at'=>'2026-10-03T09:00'],['starts_at'=>'2026-02-30T10:00'],['name_en'=>'']] as $bad){
  try{ExtraEventButton::validate(array_replace($input,$bad));throw new RuntimeException('Invalid event accepted');}catch(InvalidArgumentException $e){eventCheck(true,'Invalid configuration rejected');}
 }
 try{AdminService::execute($reader,'extra-event-save',array_replace($input,['operation_id'=>bin2hex(random_bytes(16))]));throw new RuntimeException('Moderator saved event');}catch(InvalidArgumentException $e){eventCheck(true,'Only superadmins can save');}
 $input['enabled']='0';$input['operation_id']=bin2hex(random_bytes(16));AdminService::execute($admin,'extra-event-save',$input);
 eventCheck(ExtraEventButton::active(1,$start)===null,'Manual disable hides button');
 eventCheck((int)\Conquer\Db\Connection::getInstance()->query("SELECT COUNT(*) FROM admin_audit_log WHERE action='admin.extra-event-save'")->fetchColumn()===2,'Changes audited once each');
 $input['enabled']='1';$input['event_id']='1';$input['operation_id']=bin2hex(random_bytes(16));AdminService::execute($admin,'extra-event-save',$input);
 $second=array_replace($input,['event_id'=>'0','name_en'=>'Summer Siege','description_en'=>'Gather your alliance.','target'=>'alliance','operation_id'=>bin2hex(random_bytes(16))]);
 $saved=AdminService::execute($admin,'extra-event-save',$second);
 eventCheck($saved['event_id']===2&&count(ExtraEventButton::activeEvents(1,$start))===2,'Overlapping events have stable separate IDs');
 eventCheck(AdminService::execute($admin,'extra-event-save',$second)['duplicate']&&count(ExtraEventButton::all(1))===2,'Repeated creation cannot duplicate an event');
 $second['event_id']='2';$second['enabled']='0';$second['operation_id']=bin2hex(random_bytes(16));AdminService::execute($admin,'extra-event-save',$second);
 eventCheck(count(ExtraEventButton::activeEvents(1,$start))===1&&ExtraEventButton::active(1,$start)['id']===1,'Disabling one event preserves the other');
 $legacy=ExtraEventButton::validate($input);\Conquer\Db\Connection::getInstance()->execute('UPDATE world_extra_event_buttons SET settings_json=? WHERE world_id=1',[json_encode($legacy)]);
 eventCheck(ExtraEventButton::active(1,$start)['id']===1,'Original single-event records remain readable');
 \Conquer\Db\Connection::getInstance()->execute('DELETE FROM world_extra_event_buttons WHERE world_id=1');$second['event_id']='0';$second['enabled']='1';$second['operation_id']=bin2hex(random_bytes(16));
 eventCheck(AdminService::execute($admin,'extra-event-save',$second)['event_id']===1,'First new event receives ID one');
}finally{$fixture->close();}
