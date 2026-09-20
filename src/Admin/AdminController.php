<?php
declare(strict_types=1);
namespace Conquer\Admin;
use Conquer\Auth\AdminAuth;
use Conquer\Db\Connection;

final class AdminController
{
    public static function loginPage(): void
    {
        if(AdminAuth::isLoggedIn()){header('Location: '.APP_BASE.(AdminAuth::mustChangePassword()?'/admin/change-password':'/admin'));exit;}
        $csrf=self::getCsrfToken();$error='';require ROOT_DIR.'/views/admin/login.php';
    }
    public static function loginPost(): void
    {
        $csrf=self::getCsrfToken();$error='';
        $token=$_POST['csrf_token']??'';
        if(!is_string($token)||!hash_equals($csrf,$token))$error='Ungültige Anfrage. Bitte lade die Seite neu.';
        else try {
            $user=$_POST['username']??'';$password=$_POST['password']??'';
            if(is_string($user)&&is_string($password)&&AdminAuth::login(trim($user),$password)){header('Location: '.APP_BASE.(AdminAuth::mustChangePassword()?'/admin/change-password':'/admin'));exit;}
            $error='Ungültiger Benutzername oder Passwort.';
        }catch(\RuntimeException $e){$error='Anmeldung nicht möglich. Bitte versuche es später erneut.';}
        require ROOT_DIR.'/views/admin/login.php';
    }
    public static function logout(): void {AdminAuth::logout();}
    public static function changePasswordPage(): void
    {
        $admin=AdminAuth::current();
        if($admin===null){header('Location: '.APP_BASE.'/admin/login');exit;}
        if(!AdminAuth::mustChangePassword()){header('Location: '.APP_BASE.'/admin');exit;}
        $csrf=self::getCsrfToken();$error='';require ROOT_DIR.'/views/admin/change_password.php';
    }
    public static function changePasswordPost(): void
    {
        $admin=AdminAuth::current();
        if($admin===null){header('Location: '.APP_BASE.'/admin/login');exit;}
        $csrf=self::getCsrfToken();$error='';$token=$_POST['csrf_token']??'';
        if(!is_string($token)||!hash_equals($csrf,$token))$error='Ungültige Anfrage. Bitte lade die Seite neu.';
        else {
            $current=$_POST['current_password']??'';$new=$_POST['new_password']??'';$confirm=$_POST['confirm_password']??'';
            if(!is_string($current)||!is_string($new)||!is_string($confirm))$error='Ungültige Passwortangaben.';
            elseif($new!==$confirm)$error='Die beiden neuen Passwörter stimmen nicht überein.';
            else try{AdminAuth::changeRequiredPassword($current,$new);$_SESSION['admin_flash']='Dein Passwort wurde geändert.';$_SESSION['admin_flash_kind']='success';header('Location: '.APP_BASE.'/admin',true,303);exit;}
            catch(\DomainException $e){$error=$e->getMessage();}
        }
        require ROOT_DIR.'/views/admin/change_password.php';
    }
    public static function dashboard(): void {self::render('dashboard','Übersicht');}
    public static function rewards(): void {self::render('rewards','Beute & Drops');}
    public static function lands(): void {self::render('lands','Länder & Entwicklung');}
    public static function items(): void {self::render('items','Gegenstandskatalog');}
    public static function players(): void {self::render('players','Spielerverwaltung');}
    public static function alphaKeys(): void
    {
        header('Cache-Control: private, no-store');
        header('Referrer-Policy: same-origin');
        self::render('alpha_keys','Alpha-Keys');
    }
    public static function playerDetail(int $id): void {self::render('player_detail','Spielerprofil',['playerId'=>$id]);}
    public static function alphaWaitlist(): void
    {
        header('Cache-Control: private, no-store');
        header('Referrer-Policy: same-origin');
        $admin=AdminAuth::requireAuth();
        if($admin['role']!=='superadmin'){http_response_code(403);echo 'Superadmin erforderlich.';return;}
        if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET'){http_response_code(405);header('Allow: GET');return;}
        if(($_GET['export']??null)==='csv'){AlphaWaitlistAdmin::export(Connection::getInstance());return;}
        self::render('alpha_waitlist','Alpha-Warteliste');
    }
    public static function world(): void {self::render('world','Weltsteuerung');}
    public static function worldCreate(): void {self::render('world_create','Welt erstellen');}
    public static function alliances(): void {self::render('alliances','Allianzen');}
    public static function chat(): void {self::render('chat','Chatprotokoll');}
    public static function bugReports(): void {self::render('bug_reports','Bugmeldungen');}
    public static function bugReportScreenshot(int $id): void
    {
        AdminAuth::requireAuth();
        $row=Connection::getInstance()->query('SELECT screenshot,screenshot_mime FROM bug_reports WHERE id=?',[$id])->fetch();
        if(!$row||!is_string($row['screenshot'])||$row['screenshot']===''){http_response_code(404);return;}
        $mime=in_array($row['screenshot_mime'],['image/jpeg','image/png'],true)?$row['screenshot_mime']:'image/jpeg';
        header('Content-Type: '.$mime);header('Content-Length: '.strlen($row['screenshot']));header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');
        echo $row['screenshot'];
    }
    public static function auditLog(): void {self::render('audit','Änderungsprotokoll');}
    public static function handleAction(): void
    {
        $admin=AdminAuth::requireAuth();
        if($admin['role']!=='superadmin'){http_response_code(403);echo 'Superadmin erforderlich.';return;}
        if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);header('Allow: POST');return;}
        $token=$_POST['csrf_token']??'';
        if(!is_string($token)||!hash_equals(self::getCsrfToken(),$token)){http_response_code(403);echo 'Ungültiger CSRF-Token.';return;}
        $action=basename((string)parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH));
        $worldId=max(0,(int)($_POST['world_id']??0));$playerId=max(0,(int)($_POST['player_id']??0));
        $return=$playerId?'/admin/players/'.$playerId:'/admin/world';
        if($action==='world-create')$return='/admin/world-create';
        if($action==='land-rules-save')$return='/admin/lands';
        if($action==='bug-report-update')$return='/admin/bug-reports';
        if($action==='alpha-waitlist-update')$return='/admin/alpha-waitlist';
        $alphaAction=in_array($action,['alpha-key-create','alpha-key-revoke'],true);
        if($alphaAction)$return='/admin/alpha-keys';
        $rewardAction=in_array($action,['reward-save','reward-reset'],true);
        if($rewardAction){$return='/admin/rewards';$sourceType=is_string($_POST['source_type']??null)?$_POST['source_type']:'monster';$sourceKey=is_string($_POST['source_key']??null)?$_POST['source_key']:'';}
        try {
            $result=AdminService::execute((int)$admin['id'],$action,$_POST);
            $worldId=(int)($result['world_id']??$worldId);
            if($action==='world-create'){$return='/admin/world';unset($_SESSION['admin_world_create_draft']);}
            $_SESSION['admin_flash']=($result['duplicate']?'Bereits ausgeführt: ':'').$result['message'];
            $_SESSION['admin_flash_kind']='success';
            if($rewardAction)unset($_SESSION['admin_reward_draft']);
            if($action==='alpha-key-create'){
                unset($_SESSION['admin_alpha_draft']);
                if(!empty($result['issued_keys']))$_SESSION['admin_alpha_issued']=['admin_id'=>(int)$admin['id'],'expires'=>time()+300,'keys'=>$result['issued_keys']];
                elseif($result['duplicate'])$_SESSION['admin_flash'].=' Es wurden keine weiteren Keys erstellt. Falls du die einmalige Anzeige verpasst hast, sperre die betroffenen Keys und erstelle neue.';
            }
        } catch(\InvalidArgumentException|\DomainException|\RuntimeException $e) {
            if($e instanceof \PDOException){error_log('Admin action failed: '.$e->getMessage());$message='Datenbankfehler. Der Vorgang wurde zurückgerollt.';}else $message=$e->getMessage();
            $_SESSION['admin_flash']=$message;$_SESSION['admin_flash_kind']='error';
        } catch(\Throwable $e){error_log('Admin action failed: '.$e->getMessage());$_SESSION['admin_flash']='Der Vorgang konnte nicht gespeichert werden. Alle Änderungen wurden zurückgerollt.';$_SESSION['admin_flash_kind']='error';}
        if($rewardAction&&($_SESSION['admin_flash_kind']??'')==='error'&&$action==='reward-save')$_SESSION['admin_reward_draft']=$_POST;
        if($action==='alpha-key-create'&&($_SESSION['admin_flash_kind']??'')==='error'){
            $_SESSION['admin_alpha_draft']=[];
            foreach(['label','quantity','max_uses','expires_at','reason','operation_id'] as $field)if(is_string($_POST[$field]??null))$_SESSION['admin_alpha_draft'][$field]=mb_substr($_POST[$field],0,500);
        }
        if($action==='world-create'&&($_SESSION['admin_flash_kind']??'')==='error'){
            $_SESSION['admin_world_create_draft']=$_POST;
            unset($_SESSION['admin_world_create_draft']['csrf_token'],$_SESSION['admin_world_create_draft']['operation_id'],$_SESSION['admin_world_create_draft']['reason']);
            if(is_array($_SESSION['admin_world_create_draft']['settings']??null)&&!array_key_exists('enabled',$_SESSION['admin_world_create_draft']['settings']))$_SESSION['admin_world_create_draft']['settings']['enabled']=0;
        }
        if($alphaAction||$action==='alpha-waitlist-update'){header('Location: '.APP_BASE.$return,true,303);exit;}
        header('Location: '.APP_BASE.$return.'?world_id='.$worldId.($rewardAction?'&type='.rawurlencode($sourceType).'&source='.rawurlencode($sourceKey).'&scope='.(($_POST['reward_scope']??'global')==='world'?'world':'global'):''), true,303);exit;
    }
    private static function render(string $view,string $title,array $vars=[]): void
    {
        $adminSession=AdminAuth::requireAuth();$db=Connection::getInstance();$csrf=self::getCsrfToken();
        $worlds=$db->query('SELECT * FROM worlds ORDER BY id')->fetchAll();
        $selectedWorld=(int)($_GET['world_id']??$_SESSION['admin_world_id']??($worlds[0]['id']??0));
        if(!in_array($selectedWorld,array_map(static fn(array $w):int=>(int)$w['id'],$worlds),true))$selectedWorld=(int)($worlds[0]['id']??0);
        $_SESSION['admin_world_id']=$selectedWorld;
        $world=null;foreach($worlds as $candidate)if((int)$candidate['id']===$selectedWorld)$world=$candidate;
        $pageTitle=$title;$activePage=$view==='player_detail'?'players':$view;$canEdit=$adminSession['role']==='superadmin';
        extract($vars,EXTR_SKIP);
        require_once ROOT_DIR.'/views/admin/helpers.php';
        ob_start();
        try{require ROOT_DIR.'/views/admin/'.$view.'.php';}
        catch(\Throwable $e){ob_end_clean();ob_start();error_log('Admin render failed: '.$e->getMessage());echo '<div class="notice error">Die Ansicht konnte nicht geladen werden. Prüfe den Migrationsstand und das Serverprotokoll.</div>';}
        $content=ob_get_clean();require ROOT_DIR.'/views/admin/layout.php';
    }
    private static function getCsrfToken(): string
    {
        if(empty($_SESSION['admin_csrf']))$_SESSION['admin_csrf']=bin2hex(random_bytes(32));return $_SESSION['admin_csrf'];
    }
}
