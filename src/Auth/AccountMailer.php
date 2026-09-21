<?php
declare(strict_types=1);
namespace Conquer\Auth;

use Conquer\Bootstrap;
use Conquer\Logger;

final class AccountMailer
{
    public static function passwordReset(string $email,string $token): bool
    {
        $url=self::url('/auth/reset?token='.rawurlencode($token));
        return self::send($email,'Passwort für Union of Kingdoms zurücksetzen',
            "Du hast das Zurücksetzen deines Passworts angefordert.\n\n{$url}\n\nDer Link ist 30 Minuten gültig und kann nur einmal verwendet werden. Wenn du das nicht warst, ignoriere diese Nachricht.");
    }

    public static function verification(string $email,string $token): bool
    {
        $url=self::url('/auth/verify-email?token='.rawurlencode($token));
        return self::send($email,'E-Mail-Adresse für Union of Kingdoms bestätigen',
            "Willkommen bei Union of Kingdoms. Bestätige deine E-Mail-Adresse über diesen Link:\n\n{$url}\n\nDer Link ist 24 Stunden gültig und kann nur einmal verwendet werden.");
    }

    private static function send(string $to,string $subject,string $body): bool
    {
        $cfg=Bootstrap::getConfig()['mail']??[];
        $from=(string)($cfg['from_address']??'noreply@unionofkingdoms.com');
        $name=(string)($cfg['from_name']??'Union of Kingdoms');
        if(!filter_var($to,FILTER_VALIDATE_EMAIL)||!filter_var($from,FILTER_VALIDATE_EMAIL))return false;
        $headers=['From: '.$name.' <'.$from.'>','Reply-To: '.$from,'Content-Type: text/plain; charset=UTF-8','X-Mailer: Conquer'];
        $sent=@mail($to,'=?UTF-8?B?'.base64_encode($subject).'?=',$body,implode("\r\n",$headers));
        if(!$sent)Logger::getInstance()->warn('Account email could not be handed to mail transport for domain '.substr(strrchr($to,'@')?:'',1));
        return $sent;
    }

    private static function url(string $path): string
    {
        $host=strtolower((string)parse_url('http://'.($_SERVER['HTTP_HOST']??''),PHP_URL_HOST));
        $known=in_array($host,['unionofkingdoms.com','www.unionofkingdoms.com','play.unionofkingdoms.com','localhost','127.0.0.1'],true);
        $root='';
        if($known){$scheme=(!empty($_SERVER['HTTPS'])&&strtolower((string)$_SERVER['HTTPS'])!=='off')?'https':'http';$root=$scheme.'://'.($_SERVER['HTTP_HOST']??'localhost').(defined('APP_BASE')?APP_BASE:'');}
        if($root==='')$root=rtrim((string)(Bootstrap::getConfig()['base_url']??''),'/');
        if(!filter_var($root,FILTER_VALIDATE_URL)){
            $scheme=(!empty($_SERVER['HTTPS'])&&strtolower((string)$_SERVER['HTTPS'])!=='off')?'https':'http';
            $root=$scheme.'://'.($_SERVER['HTTP_HOST']??'localhost').(defined('APP_BASE')?APP_BASE:'');
        }
        return $root.$path;
    }
}
