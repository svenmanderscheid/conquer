<?php
declare(strict_types=1);
namespace Conquer\Auth;

use Conquer\Logger;

/** Authenticated account mail; configuration is the mail.hostinger subarray. */
final class HostingerMailTransport
{
    public static function send(array $config,string $to,string $subject,string $body,string $displayName): bool
    {
        $handle=false;
        try {
            $token=$config['api_token']??null;
            $mailbox=$config['mailbox_resource_id']??null;
            // Credentials belong in private server configuration. Neither setting
            // may alter the fixed destination or introduce an HTTP header.
            if(!is_string($token)||strlen($token)>4096||!preg_match('/^[\x21-\x7E]+$/D',$token)
                ||!is_string($mailbox)||!preg_match('/^[A-Za-z0-9_-]{1,128}$/D',$mailbox)) {
                return self::failed('configuration invalid');
            }
            if(strlen($to)>254||!filter_var($to,FILTER_VALIDATE_EMAIL)
                ||preg_match('/[\x00-\x1F\x7F]/',$subject.$displayName)) {
                return self::failed('message invalid');
            }
            $payload=json_encode(['to'=>[$to],'subject'=>$subject,'text'=>$body,'displayName'=>$displayName],JSON_THROW_ON_ERROR);
            if(!function_exists('curl_init'))return self::failed('curl unavailable');
            $handle=curl_init('https://api.mail.hostinger.com/api/v1/mailboxes/'.rawurlencode($mailbox).'/send');
            if($handle===false)return self::failed('initialization failed');
            $options=[
                CURLOPT_POST=>true,
                CURLOPT_POSTFIELDS=>$payload,
                CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token,'Content-Type: application/json','Accept: application/json'],
                CURLOPT_RETURNTRANSFER=>true,
                CURLOPT_HEADER=>false,
                CURLOPT_FOLLOWLOCATION=>false,
                CURLOPT_MAXREDIRS=>0,
                CURLOPT_CONNECTTIMEOUT=>5,
                CURLOPT_TIMEOUT=>15,
                CURLOPT_SSL_VERIFYPEER=>true,
                CURLOPT_SSL_VERIFYHOST=>2,
                // The API's successful response is empty. Discard all response
                // bodies, including errors that could echo account/reset data.
                CURLOPT_WRITEFUNCTION=>static fn($connection,string $chunk):int=>strlen($chunk),
            ];
            if(defined('CURLOPT_PROTOCOLS_STR')) {
                $options[CURLOPT_PROTOCOLS_STR]='https';
                $options[CURLOPT_REDIR_PROTOCOLS_STR]='https';
            } else {
                $options[CURLOPT_PROTOCOLS]=CURLPROTO_HTTPS;
                $options[CURLOPT_REDIR_PROTOCOLS]=CURLPROTO_HTTPS;
            }
            if(!curl_setopt_array($handle,$options))return self::failed('setup failed');
            $result=curl_exec($handle);
            $error=curl_errno($handle);
            if($result===false||$error!==0)return self::failed('connection failed (curl '.(int)$error.')');
            $status=(int)curl_getinfo($handle,CURLINFO_RESPONSE_CODE);
            // Hostinger's official SendApi returns void on successful HTTP
            // completion; there is no JSON success field to parse.
            if($status<200||$status>=300)return self::failed('request rejected (HTTP '.$status.')');
            return true;
        } catch(\Throwable) {
            // Exception messages, response bodies and curl_error can contain
            // credentials, recipients or reset links. Keep diagnostics coarse.
            return self::failed('transport exception');
        } finally {
            if($handle!==false)curl_close($handle);
        }
    }

    private static function failed(string $reason): bool
    {
        try {Logger::getInstance()->warn('Account email Hostinger transport: '.$reason.'.');}
        catch(\Throwable) {} // Logging cannot turn a failed handoff into a 500.
        return false;
    }
}
