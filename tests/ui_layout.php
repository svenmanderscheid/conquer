<?php
declare(strict_types=1);
define('ROOT_DIR',dirname(__DIR__));
require ROOT_DIR.'/src/Bootstrap.php';\Conquer\Bootstrap::init(ROOT_DIR);
require __DIR__.'/Support/FeatureDatabase.php';
use Conquer\Game\Ui\LayoutSettings;
use Conquer\Admin\AdminService;
$fixture=new \ConquerTests\FeatureDatabase();$db=\Conquer\Db\Connection::getInstance();
function checkLayout(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);echo "PASS $message\n";}
try {
    $admin=\Conquer\Auth\AdminAuth::createAdmin('LayoutAdmin','Layout-test-2026!','superadmin',false);
    $moderator=\Conquer\Auth\AdminAuth::createAdmin('LayoutReader','Layout-test-2026!','moderator',false);
    $profiles=LayoutSettings::defaults();$profiles['portrait']['navigation']=['x'=>10,'y'=>-20,'width'=>110,'height'=>120];
    $input=['revision'=>'0','operation_id'=>bin2hex(random_bytes(16)),'reason'=>'Layout test','layout'=>json_encode($profiles)];
    $result=AdminService::execute($admin,'layout-save',$input);
    checkLayout(LayoutSettings::read()['profiles']===$profiles,'global layout round trip');
    checkLayout(AdminService::execute($admin,'layout-save',$input)['duplicate'],'same receipt cannot save twice');
    checkLayout(LayoutSettings::read()['revision']===1,'duplicate keeps revision');
    checkLayout((int)$db->query("SELECT COUNT(*) FROM admin_audit_log WHERE action='admin.layout-save'")->fetchColumn()===1,'one audit entry');
    try{AdminService::execute($admin,'layout-save',array_replace($input,['operation_id'=>bin2hex(random_bytes(16))]));throw new RuntimeException('stale version accepted');}catch(DomainException){echo "PASS concurrent stale edit rejected\n";}
    try{AdminService::execute($moderator,'layout-save',array_replace($input,['revision'=>'1','operation_id'=>bin2hex(random_bytes(16))]));throw new RuntimeException('moderator accepted');}catch(InvalidArgumentException){echo "PASS moderator cannot write\n";}
    checkLayout(count(LayoutSettings::catalog())>60,'complete component catalog');
    checkLayout(LayoutSettings::validate(['portrait'=>['gems'=>['x'=>300,'y'=>200,'width'=>150,'height'=>150,'anchor'=>'bottom-right']]])['portrait']['gems']['anchor']==='bottom-right','extended controls and anchors');
    foreach([['x'=>4097,'y'=>0,'width'=>100,'height'=>100],['x'=>0,'y'=>0,'width'=>100,'height'=>100,'css'=>'display:none'],['x'=>'0','y'=>0,'width'=>100,'height'=>100],['x'=>0,'y'=>0,'width'=>100,'height'=>100,'anchor'=>'invalid']] as $invalid){
        try{LayoutSettings::validate(['portrait'=>['navigation'=>$invalid]]);throw new RuntimeException('invalid setting accepted');}catch(InvalidArgumentException){echo "PASS invalid setting rejected\n";}
    }
    AdminService::execute($admin,'layout-save',array_replace($input,['revision'=>'1','operation_id'=>bin2hex(random_bytes(16)),'layout'=>json_encode(LayoutSettings::defaults())]));
    checkLayout(LayoutSettings::read()['profiles']===LayoutSettings::defaults(),'reset persists defaults');
}finally{$fixture->close();}
