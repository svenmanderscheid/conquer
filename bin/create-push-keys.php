<?php
declare(strict_types=1);
// Explicit operator command. Never prints private key material.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$root = dirname(__DIR__);
require $root.'/src/Autoloader.php';
(new \Conquer\Autoloader($root.'/src'))->register();
$subject = $argv[1] ?? '';
if (!preg_match('~^mailto:[^\s@]+@[^\s@]+$~D',$subject)) {
    fwrite(STDERR,"Usage: php bin/create-push-keys.php mailto:operator@example.com\n"); exit(1);
}
if (!\Conquer\Game\Notification\PushConfig::dependencies()) {
    fwrite(STDERR,"Install locked Composer dependencies first.\n"); exit(1);
}
$file=$root.'/config/push.php';
$handle=fopen($file,'x');
if ($handle===false) { fwrite(STDERR,"Private configuration already exists; it was not changed.\n"); exit(1); }
try {
    chmod($file,0600);
    $keys=\Minishlink\WebPush\VAPID::createVapidKeys();
    $config=['enabled'=>false,'subject'=>$subject,'public_key'=>$keys['publicKey'],'private_key'=>$keys['privateKey'],
        'native_enabled'=>false,'firebase_project_id'=>'','firebase_credentials_file'=>''];
    fwrite($handle,"<?php\ndeclare(strict_types=1);\nreturn ".var_export($config,true).";\n");
    fclose($handle);
    echo "Private Web Push configuration created. Enable it after migration and worker setup.\n";
} catch (\Throwable) {
    fclose($handle); unlink($file);
    fwrite(STDERR,"Key generation failed. Check the local OpenSSL configuration.\n"); exit(1);
}
