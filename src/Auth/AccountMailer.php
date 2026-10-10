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
        return self::send($email,'Reset your Union of Kingdoms password',
            "You requested a password reset.\n\n{$url}\n\nThis link is valid for 30 minutes and can only be used once. If you did not request this, ignore this message.");
    }

    public static function verification(string $email,string $token): bool
    {
        $url=self::url('/auth/verify-email?token='.rawurlencode($token));
        return self::send($email,'Verify your Union of Kingdoms email address',
            "Welcome to Union of Kingdoms. Verify your email address using this link:\n\n{$url}\n\nThis link is valid for 24 hours and can only be used once.");
    }

    private static function send(string $to,string $subject,string $body): bool
    {
        $cfg=Bootstrap::getConfig()['mail']??[];
        $from=(string)($cfg['from_address']??'noreply@unionofkingdoms.com');
        $name=(string)($cfg['from_name']??'Union of Kingdoms');
        if(!filter_var($to,FILTER_VALIDATE_EMAIL)||!filter_var($from,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$name))return false;
        $transport=$cfg['transport']??'php';
        try{
            if($transport==='hostinger'){
                // The API sends as its authenticated mailbox; no PHP fallback.
                $sent=HostingerMailTransport::send($cfg['hostinger']??[],$to,$subject,$body,$name);
            }elseif($transport==='php'){
                $headers=['From: '.$name.' <'.$from.'>','Reply-To: '.$from,'MIME-Version: 1.0','Content-Type: text/plain; charset=UTF-8','X-Mailer: Conquer'];
                $sent=@mail($to,'=?UTF-8?B?'.base64_encode($subject).'?=',$body,implode("\r\n",$headers));
            }else{
                Logger::getInstance()->warn('Account email transport configuration is invalid.');
                return false;
            }
        }catch(\Throwable $e){
            // Transport errors must not expose mail contents or break registration.
            Logger::getInstance()->warn('Account email transport failed ('.$e::class.').');
            return false;
        }
        if(!$sent)Logger::getInstance()->warn('Account email could not be handed to the configured mail transport.');
        return $sent;
    }

    private static function url(string $path): string
    {
        // Recovery destinations are operator configuration, never request Host data.
        $root=rtrim((string)(Bootstrap::getConfig()['base_url']??''),'/');
        $parts=parse_url($root);
        $local=is_array($parts)&&in_array(strtolower($parts['host']??''),['localhost','127.0.0.1','[::1]'],true);
        if(!is_array($parts)||!filter_var($root,FILTER_VALIDATE_URL)||empty($parts['host'])
            ||isset($parts['user'])||isset($parts['pass'])||isset($parts['query'])||isset($parts['fragment'])
            ||!in_array($parts['scheme']??'',$local?['http','https']:['https'],true)) {
            throw new \RuntimeException('Die öffentliche HTTPS-Adresse für Konto-E-Mails ist nicht eingerichtet.');
        }
        return $root.$path;
    }
}
