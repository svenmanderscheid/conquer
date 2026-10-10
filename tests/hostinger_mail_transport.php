<?php
declare(strict_types=1);

namespace Conquer\Auth {
    // This standalone CLI suite replaces curl only inside the transport's
    // namespace. No network request or production mailbox is involved.
    function curl_init(string $url): object|false
    {
        $state=&$GLOBALS['HOSTINGER_MAIL_TEST'];
        $state['urls'][]=$url;
        if($state['throw']==='init')throw new \RuntimeException($state['sensitive']);
        return $state['init']?(object)['test'=>true]:false;
    }
    function curl_setopt_array(object $handle,array $options): bool
    {
        $GLOBALS['HOSTINGER_MAIL_TEST']['options']=$options;
        return $GLOBALS['HOSTINGER_MAIL_TEST']['setup'];
    }
    function curl_exec(object $handle): bool|string
    {
        $state=&$GLOBALS['HOSTINGER_MAIL_TEST'];
        $state['requests']++;
        if($state['throw']==='exec')throw new \RuntimeException($state['sensitive']);
        $state['discarded']=$state['options'][CURLOPT_WRITEFUNCTION]($handle,$state['sensitive']);
        return $state['result'];
    }
    function curl_errno(object $handle): int {return $GLOBALS['HOSTINGER_MAIL_TEST']['errno'];}
    function curl_getinfo(object $handle,int $option): int
    {
        if($option!==CURLINFO_RESPONSE_CODE)throw new \LogicException('Unexpected curl info.');
        return $GLOBALS['HOSTINGER_MAIL_TEST']['status'];
    }
    function curl_close(object $handle): void {$GLOBALS['HOSTINGER_MAIL_TEST']['closed']++;}
    function mail(string $to,string $subject,string $body,array|string $headers=[],string $parameters=''): bool
    {
        $GLOBALS['HOSTINGER_MAIL_TEST']['php_mail_calls']++;
        throw new \LogicException('The Hostinger transport must never fall back to PHP mail.');
    }
}

namespace {
    if(PHP_SAPI!=='cli')exit(1);
    if(!extension_loaded('curl'))throw new \RuntimeException('The transport test needs curl constants; no network will be used.');
    require dirname(__DIR__).'/src/Logger.php';
    require dirname(__DIR__).'/src/Bootstrap.php';
    require dirname(__DIR__).'/src/Auth/HostingerMailTransport.php';
    require dirname(__DIR__).'/src/Auth/AccountMailer.php';

    use Conquer\Auth\AccountMailer;
    use Conquer\Auth\HostingerMailTransport;

    function hostingerCheck(bool $ok,string $label): void
    {
        if(!$ok)throw new \RuntimeException($label);
        echo "PASS $label\n";
    }
    function hostingerFixture(array $overrides=[]): void
    {
        $GLOBALS['HOSTINGER_MAIL_TEST']=array_replace([
            'urls'=>[],'options'=>[],'requests'=>0,'closed'=>0,'discarded'=>0,'php_mail_calls'=>0,
            'status'=>204,'errno'=>0,'result'=>'','init'=>true,'setup'=>true,'throw'=>null,
            'sensitive'=>'secret-api-token recipient@example.invalid https://game.example.invalid/auth/reset?token=private-reset-token',
        ],$overrides);
    }

    $log=tempnam(sys_get_temp_dir(),'uok-hostinger-transport-');
    Conquer\Logger::init($log);
    $config=['api_token'=>'secret-api-token','mailbox_resource_id'=>'AC1a2b3c4d5e6f7g'];
    $to='recipient@example.invalid';
    $subject='Reset your Union of Kingdoms password';
    $body="Use this link:\nhttps://game.example.invalid/auth/reset?token=private-reset-token";
    $name='Union of Kingdoms';
    $send=static fn(array $cfg):bool=>HostingerMailTransport::send($cfg,$to,$subject,$body,$name);
    $bootstrapConfig=new ReflectionProperty(Conquer\Bootstrap::class,'config');
    $previousConfig=$bootstrapConfig->getValue();
    try {
        hostingerFixture();
        hostingerCheck($send($config),'empty successful API response is accepted');
        $state=$GLOBALS['HOSTINGER_MAIL_TEST'];
        $options=$state['options'];
        hostingerCheck($state['urls']===['https://api.mail.hostinger.com/api/v1/mailboxes/AC1a2b3c4d5e6f7g/send'],'request targets only the fixed mailbox send endpoint');
        hostingerCheck($options[CURLOPT_POST]===true&&json_decode($options[CURLOPT_POSTFIELDS],true,512,JSON_THROW_ON_ERROR)===['to'=>[$to],'subject'=>$subject,'text'=>$body,'displayName'=>$name],'request preserves the recipient, plain message and sender display name');
        hostingerCheck(in_array('Authorization: Bearer secret-api-token',$options[CURLOPT_HTTPHEADER],true)&&in_array('Content-Type: application/json',$options[CURLOPT_HTTPHEADER],true),'request uses authenticated JSON');
        hostingerCheck($options[CURLOPT_SSL_VERIFYPEER]===true&&$options[CURLOPT_SSL_VERIFYHOST]===2,'TLS verifies both the certificate and hostname');
        $httpsOnly=defined('CURLOPT_PROTOCOLS_STR')
            ?$options[CURLOPT_PROTOCOLS_STR]==='https'&&$options[CURLOPT_REDIR_PROTOCOLS_STR]==='https'
            :$options[CURLOPT_PROTOCOLS]===CURLPROTO_HTTPS&&$options[CURLOPT_REDIR_PROTOCOLS]===CURLPROTO_HTTPS;
        hostingerCheck($httpsOnly&&$options[CURLOPT_FOLLOWLOCATION]===false&&$options[CURLOPT_MAXREDIRS]===0,'only HTTPS is allowed and redirects cannot forward the credential');
        hostingerCheck($options[CURLOPT_CONNECTTIMEOUT]===5&&$options[CURLOPT_TIMEOUT]===15,'transport has bounded connect and request times');
        hostingerCheck($state['discarded']===strlen($state['sensitive'])&&$state['closed']===1,'response content is discarded and the handle is closed');

        foreach([200,201,202,204] as $status) {
            hostingerFixture(['status'=>$status]);
            hostingerCheck($send($config),"successful HTTP $status is accepted without parsing a body");
        }
        foreach([0,301,302,307,400,401,403,422,429,500,503] as $status) {
            hostingerFixture(['status'=>$status]);
            hostingerCheck(!$send($config)&&$GLOBALS['HOSTINGER_MAIL_TEST']['requests']===1&&$GLOBALS['HOSTINGER_MAIL_TEST']['closed']===1,"HTTP $status fails without retries or fallback");
        }
        foreach([
            ['result'=>false,'errno'=>28],
            ['result'=>false,'errno'=>60],
            ['result'=>'','errno'=>7],
            ['throw'=>'exec'],
            ['setup'=>false],
            ['init'=>false],
            ['throw'=>'init'],
        ] as $index=>$failure) {
            hostingerFixture($failure);
            $expectedClosed=($failure['init']??true)&&($failure['throw']??null)!=='init'?1:0;
            hostingerCheck(!$send($config)&&$GLOBALS['HOSTINGER_MAIL_TEST']['closed']===$expectedClosed,'connection/setup failure '.($index+1).' returns false and releases its handle');
        }
        foreach([
            [],
            ['api_token'=>''],
            ['api_token'=>[]],
            ['api_token'=>"token\r\nX-Injected: yes"],
            ['api_token'=>str_repeat('a',4097)],
            ['mailbox_resource_id'=>''],
            ['mailbox_resource_id'=>[]],
            ['mailbox_resource_id'=>'../other/send'],
            ['mailbox_resource_id'=>'AC123?redirect=https://attacker.invalid'],
        ] as $index=>$invalid) {
            hostingerFixture();
            $invalidConfig=$index===0?[]:array_replace($config,$invalid);
            hostingerCheck(!$send($invalidConfig)&&$GLOBALS['HOSTINGER_MAIL_TEST']['urls']===[],'invalid configuration '.($index+1).' is rejected before any connection');
        }
        foreach([
            ["recipient@example.invalid\r\nBcc: other@example.invalid",$subject,$body,$name],
            [$to,"Reset\nInjected",$body,$name],
            [$to,$subject,$body,"Name\rInjected"],
            [$to,$subject,"Invalid UTF-8 \xFF",$name],
        ] as $index=>$message) {
            hostingerFixture();
            hostingerCheck(!HostingerMailTransport::send($config,...$message)&&$GLOBALS['HOSTINGER_MAIL_TEST']['urls']===[],'invalid message '.($index+1).' is rejected before any connection');
        }

        // Exercise the real AccountMailer dispatch, with the same private
        // configuration shape used by the application, still using curl fakes.
        $bootstrapConfig->setValue(null,[
            'base_url'=>'https://game.example.invalid/realm',
            'mail'=>[
                'transport'=>'hostinger',
                'from_address'=>'hello@example.invalid',
                'from_name'=>$name,
                'hostinger'=>$config,
            ],
        ]);
        $accountToken=str_repeat('a',64);
        $flows=[
            'passwordReset'=>['/auth/reset?token=','Reset your Union of Kingdoms password'],
            'verification'=>['/auth/verify-email?token=','Verify your Union of Kingdoms email address'],
        ];
        foreach($flows as $method=>[$path,$expectedSubject]) {
            hostingerFixture();
            hostingerCheck(AccountMailer::$method($to,$accountToken)
                &&$GLOBALS['HOSTINGER_MAIL_TEST']['requests']===1
                &&$GLOBALS['HOSTINGER_MAIL_TEST']['php_mail_calls']===0,
                "$method dispatches once through the configured Hostinger API");
            $payload=json_decode($GLOBALS['HOSTINGER_MAIL_TEST']['options'][CURLOPT_POSTFIELDS],true,512,JSON_THROW_ON_ERROR);
            $expectedLink='https://game.example.invalid/realm'.$path.$accountToken;
            hostingerCheck($payload['to']===[$to]&&$payload['subject']===$expectedSubject
                &&$payload['displayName']===$name&&str_contains($payload['text'],"\n\n".$expectedLink."\n\n"),
                "$method preserves the configured origin, link, subject and recipient");
            foreach([401,429] as $status) {
                hostingerFixture(['status'=>$status]);
                hostingerCheck(!AccountMailer::$method($to,$accountToken)
                    &&$GLOBALS['HOSTINGER_MAIL_TEST']['requests']===1
                    &&$GLOBALS['HOSTINGER_MAIL_TEST']['php_mail_calls']===0
                    &&$GLOBALS['HOSTINGER_MAIL_TEST']['closed']===1,
                    "$method returns false on HTTP $status without PHP mail fallback");
            }
        }
        $logs=(string)file_get_contents($log);
        foreach(['secret-api-token',$to,'private-reset-token','https://game.example.invalid',$body,$accountToken] as $secret) {
            hostingerCheck(!str_contains($logs,$secret),'logs omit private data marker '.hash('crc32b',$secret));
        }
        hostingerCheck(str_contains($logs,'HTTP 429')&&str_contains($logs,'curl 60'),'logs retain safe status codes for diagnosis');
        echo "ALL HOSTINGER MAIL TRANSPORT CHECKS PASSED\n";
    } finally {
        $bootstrapConfig->setValue(null,$previousConfig);
        unset($GLOBALS['HOSTINGER_MAIL_TEST']);
        if(is_file($log))unlink($log);
    }
}
