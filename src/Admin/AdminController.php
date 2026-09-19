<?php
declare(strict_types=1);
namespace Conquer\Admin;
use Conquer\Auth\AdminAuth;
use Conquer\Db\Connection;

final class AdminController
{
    public static function loginPage(): void
    {
        if(AdminAuth::isLoggedIn()){header('Location: '.APP_BASE.'/admin');exit;}
        $csrf=self::getCsrfToken();$error='';require ROOT_DIR.'/views/admin/login.php';
    }
    public static function loginPost(): void
    {
        $csrf=self::getCsrfToken();$error='';
        $token=$_POST['csrf_token']??'';
        if(!is_string($token)||!hash_equals($csrf,$token))$error='Ungültige Anfrage. Bitte lade die Seite neu.';
        else try {
            $user=$_POST['username']??'';$password=$_POST['password']??'';
            if(is_string($user)&&is_string($password)&&AdminAuth::login(trim($user),$password)){header('Location: '.APP_BASE.'/admin');exit;}
            $error='Ungültiger Benutzername oder Passwort.';
        }catch(\RuntimeException $e){$error='Anmeldung nicht möglich. Bitte versuche es später erneut.';}
        require ROOT_DIR.'/views/admin/login.php';
    }
    public static function logout(): void {AdminAuth::logout();}
    public static function dashboard(): void {self::render('dashboard','Übersicht');}
    public static function rewards(): void {self::render('rewards','Beute & Drops');}
    public static function lands(): void {self::render('lands','Länder & Entwicklung');}
    public static function items(): void {self::render('items','Gegenstandskatalog');}
    public static function players(): void {self::render('players','Spielerverwaltung');}
    public static function playerDetail(int $id): void {self::render('player_detail','Spielerprofil',['playerId'=>$id]);}
    public static function world(): void {self::render('world','Weltsteuerung');}
    public static function alliances(): void {self::render('alliances','Allianzen');}
    public static function chat(): void {self::render('chat','Chatprotokoll');}
    public static function bugReports(): void {self::render('bug_reports','Bugmeldungen');}
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
        if($action==='land-rules-save')$return='/admin/lands';
        if($action==='bug-report-update')$return='/admin/bug-reports';
        $rewardAction=in_array($action,['reward-save','reward-reset'],true);
        if($rewardAction){$return='/admin/rewards';$sourceType=is_string($_POST['source_type']??null)?$_POST['source_type']:'monster';$sourceKey=is_string($_POST['source_key']??null)?$_POST['source_key']:'';}
        try {
            $result=AdminService::execute((int)$admin['id'],$action,$_POST);
            $worldId=(int)($result['world_id']??$worldId);
            $_SESSION['admin_flash']=($result['duplicate']?'Bereits ausgeführt: ':'').$result['message'];
            $_SESSION['admin_flash_kind']='success';
            if($rewardAction)unset($_SESSION['admin_reward_draft']);
        } catch(\InvalidArgumentException|\DomainException|\RuntimeException $e) {
            if($e instanceof \PDOException){error_log('Admin action failed: '.$e->getMessage());$message='Datenbankfehler. Der Vorgang wurde zurückgerollt.';}else $message=$e->getMessage();
            $_SESSION['admin_flash']=$message;$_SESSION['admin_flash_kind']='error';
        } catch(\Throwable $e){error_log('Admin action failed: '.$e->getMessage());$_SESSION['admin_flash']='Der Vorgang konnte nicht gespeichert werden. Alle Änderungen wurden zurückgerollt.';$_SESSION['admin_flash_kind']='error';}
        if($rewardAction&&($_SESSION['admin_flash_kind']??'')==='error'&&$action==='reward-save')$_SESSION['admin_reward_draft']=$_POST;
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
