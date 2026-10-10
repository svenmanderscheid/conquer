<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));
require ROOT_DIR.'/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
use Conquer\Game\Notification\{PushConfig,PushTransport};
function transportCheck(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
function transportClient(PushTransport $transport,array $responses,array &$history):void{
    $mock=new \GuzzleHttp\Handler\MockHandler($responses);$stack=\GuzzleHttp\HandlerStack::create($mock);$stack->push(\GuzzleHttp\Middleware::history($history));
    (new ReflectionProperty(PushTransport::class,'client'))->setValue($transport,new \GuzzleHttp\Client(['handler'=>$stack,'allow_redirects'=>false,'http_errors'=>false]));
}
$oldConf=getenv('OPENSSL_CONF');
if(!$oldConf&&is_file('C:/xampp/apache/conf/openssl.cnf'))putenv('OPENSSL_CONF=C:/xampp/apache/conf/openssl.cnf');
$file=tempnam(sys_get_temp_dir(),'uok-push-fixture-');
try{
    transportCheck(PushConfig::dependencies(),'locked dependencies load');
    $keys=\Minishlink\WebPush\VAPID::createVapidKeys();$recipient=\Minishlink\WebPush\VAPID::createVapidKeys();
    $config=['enabled'=>true,'subject'=>'mailto:fixture@tests.invalid','public_key'=>$keys['publicKey'],'private_key'=>$keys['privateKey']];
    transportCheck(PushConfig::configured($config),'valid matching VAPID keypair reports ready');
    transportCheck(!PushConfig::configured(array_replace($config,['private_key'=>$recipient['privateKey']])),'mismatched VAPID keys report unavailable');
    $sub=['platform'=>'web','endpoint'=>'https://fcm.googleapis.com/fcm/send/fixture-no-network','public_key'=>$recipient['publicKey'],'auth_token'=>rtrim(strtr(base64_encode(random_bytes(16)),'+/','-_'),'=')];
    $payload=['title'=>'Union of Kingdoms','body'=>'An activity in your kingdom is complete.','tag'=>'uok-'.str_repeat('a',32),'url'=>'city#city','player_id'=>123,'world_id'=>456];
    $history=[];$transport=new PushTransport($config);transportClient($transport,[new \GuzzleHttp\Psr7\Response(201)],$history);
    transportCheck($transport($sub,$payload)==='sent'&&count($history)===1,'real Web Push library creates an accepted request against mock provider');
    $request=$history[0]['request'];
    transportCheck($request->getHeaderLine('Content-Encoding')==='aes128gcm'&&str_starts_with($request->getHeaderLine('Authorization'),'vapid '),'Web Push uses encrypted payload and VAPID authorization');
    transportCheck(!str_contains((string)$request->getBody(),'Union of Kingdoms')&&!str_contains((string)$request->getBody(),'player_id'),'Web Push request never contains plaintext game message or metadata');
    $history=[];$transport=new PushTransport($config);transportClient($transport,[new \GuzzleHttp\Psr7\Response(410)],$history);
    transportCheck($transport($sub,$payload)==='expired','Web Push 410 revokes expired subscription');
    $history=[];$transport=new PushTransport($config);transportClient($transport,[new \GuzzleHttp\Psr7\Response(302,['Location'=>'http://127.0.0.1/private'])],$history);
    transportCheck($transport($sub,$payload)==='failed'&&count($history)===1,'provider redirects cannot trigger an internal request');
    $rsa=openssl_pkey_new(['private_key_type'=>OPENSSL_KEYTYPE_RSA,'private_key_bits'=>2048]);openssl_pkey_export($rsa,$pem);
    $credentials=['type'=>'service_account','project_id'=>'uok-fixture','client_email'=>'fixture@uok-fixture.iam.gserviceaccount.com','private_key'=>$pem,'token_uri'=>'https://oauth2.googleapis.com/token'];
    file_put_contents($file,json_encode($credentials));chmod($file,0600);
    $nativeConfig=['native_enabled'=>true,'firebase_project_id'=>'uok-fixture','firebase_credentials_file'=>$file];
    transportCheck(PushConfig::firebaseCredentials($nativeConfig)!==null,'private fixture service account validates outside webroot');
    file_put_contents($file,json_encode(array_replace($credentials,['token_uri'=>'http://127.0.0.1/private'])));
    transportCheck(PushConfig::firebaseCredentials($nativeConfig)===null,'service account cannot redirect OAuth to arbitrary host');
    file_put_contents($file,json_encode(array_replace($credentials,['private_key'=>'not a key'])));
    transportCheck(PushConfig::firebaseCredentials($nativeConfig)===null,'malformed native private key reports unavailable');
    file_put_contents($file,json_encode($credentials));
    $history=[];$transport=new PushTransport($nativeConfig);transportClient($transport,[new \GuzzleHttp\Psr7\Response(200,[],json_encode(['access_token'=>'fixture-token','expires_in'=>3600,'token_type'=>'Bearer'])),new \GuzzleHttp\Psr7\Response(200,[],'{"name":"projects/uok-fixture/messages/fixture"}')],$history);
    $native=['platform'=>'android','endpoint'=>'fcm:'.str_repeat('fixture-token-',6)];
    transportCheck($transport($native,$payload)==='sent'&&count($history)===2,'Google auth and FCM v1 send execute entirely through mock responses');
    transportCheck((string)$history[0]['request']->getUri()==='https://oauth2.googleapis.com/token'&&(string)$history[1]['request']->getUri()==='https://fcm.googleapis.com/v1/projects/uok-fixture/messages:send','native transport uses fixed Google HTTPS endpoints');
    $body=json_decode((string)$history[1]['request']->getBody(),true)['message'];
    transportCheck(array_keys($body['data'])===['url','tag']&&$body['android']['notification']['channel_id']==='uok_game'&&!str_contains(json_encode($body),'player_id')&&!str_contains(json_encode($body),'world_id'),'FCM sends only generic notification and opaque route/tag data on expected native channel');
    $history=[];$transport=new PushTransport($nativeConfig);transportClient($transport,[new \GuzzleHttp\Psr7\Response(200,[],json_encode(['access_token'=>'fixture-token','expires_in'=>3600])),new \GuzzleHttp\Psr7\Response(404,[],'{"error":{"details":[{"errorCode":"UNREGISTERED"}]}}')],$history);
    transportCheck($transport($native,$payload)==='expired','unregistered native token is removed');
    echo "ALL PUSH TRANSPORT CHECKS PASSED (MOCK NETWORK ONLY)\n";
}finally{
    if(is_file($file))unlink($file);
    putenv('OPENSSL_CONF'.($oldConf===false?'':'='.$oldConf));
}
