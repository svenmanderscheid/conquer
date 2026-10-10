<?php
declare(strict_types=1);
namespace Conquer\Admin;

use Conquer\Auth\AdminAuth;

final class CaseController
{
    public static function action():void
    {
        $admin=AdminAuth::requireAuth();header('Cache-Control: private, no-store');
        if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);header('Allow: POST');return;}
        $token=$_POST['csrf_token']??null;
        if(!is_string($token)||!is_string($_SESSION['admin_csrf']??null)||!hash_equals($_SESSION['admin_csrf'],$token)){http_response_code(403);echo CaseService::t('csrf');return;}
        $source=in_array($_POST['source']??'', ['bug','content'],true)?$_POST['source']:'bug';$id=max(0,(int)($_POST['case_id']??0));
        try {
            $result=CaseService::execute((int)$admin['id'],$_POST);
            $_SESSION['admin_flash']=$result['message'];$_SESSION['admin_flash_kind']='success';unset($_SESSION['admin_case_draft']);
        } catch(\DomainException $e){
            $_SESSION['admin_flash']=$e->getMessage();$_SESSION['admin_flash_kind']='error';
            // Keep the text and operation receipt locally in the session after a stale/error response.
            self::retainDraft();
        } catch(\Throwable $e){
            \Conquer\Observability\EventLog::exception($e,'case.update','CASE_UPDATE_FAILED');$_SESSION['admin_flash']=CaseService::t('failed');$_SESSION['admin_flash_kind']='error';self::retainDraft();
        }
        header('Location: '.APP_BASE.'/admin/cases?source='.rawurlencode($source).'&case_id='.$id,true,303);exit;
    }
    private static function retainDraft():void
    {
        $_SESSION['admin_case_draft']=[];
        foreach(['source'=>10,'case_id'=>20,'case_action'=>20,'body'=>4000,'reason'=>500,'operation_id'=>64] as $field=>$limit)if(is_string($_POST[$field]??null))$_SESSION['admin_case_draft'][$field]=mb_substr($_POST[$field],0,$limit);
    }
}
