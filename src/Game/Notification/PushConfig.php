<?php
declare(strict_types=1);
namespace Conquer\Game\Notification;

final class PushConfig
{
    public static function load(): array
    {
        $root = dirname(__DIR__, 3);
        $file = $root . '/config/push.php';
        $config = is_file($file) ? require $file : [];
        $config = is_array($config) ? $config : [];
        foreach (['enabled', 'subject', 'public_key', 'private_key', 'native_enabled', 'firebase_project_id', 'firebase_credentials_file'] as $key) {
            $env = getenv('UOK_PUSH_' . strtoupper($key));
            if ($env !== false) $config[$key] = $env;
        }
        $config['enabled'] = filter_var($config['enabled'] ?? false, FILTER_VALIDATE_BOOL);
        $config['native_enabled'] = filter_var($config['native_enabled'] ?? false, FILTER_VALIDATE_BOOL);
        return $config;
    }

    public static function dependencies(): bool
    {
        $autoload = dirname(__DIR__, 3) . '/vendor/autoload.php';
        if (is_file($autoload)) require_once $autoload;
        return class_exists(\Minishlink\WebPush\WebPush::class)
            && class_exists(\GuzzleHttp\Client::class)
            && extension_loaded('curl') && extension_loaded('openssl') && extension_loaded('mbstring');
    }

    public static function configured(array $config): bool
    {
        if (!($config['enabled'] ?? false) || !self::dependencies()) return false;
        $subject = $config['subject'] ?? '';
        if (!is_string($subject) || !(preg_match('~^mailto:[^\s@]+@[^\s@]+$~D', $subject)
            || (filter_var($subject, FILTER_VALIDATE_URL) && str_starts_with($subject, 'https://')))) return false;
        try {
            $public = PushService::decodeKey($config['public_key'] ?? '', 65);
            $private = PushService::decodeKey($config['private_key'] ?? '', 32);
            if ($public[0] !== "\x04") return false;
            // Import the private scalar without a supplied public point so OpenSSL
            // derives it; this rejects mismatched/corrupt pairs before opt-in.
            $der = "\x30\x31\x02\x01\x01\x04\x20".$private."\xA0\x0A\x06\x08\x2A\x86\x48\xCE\x3D\x03\x01\x07";
            $key = @openssl_pkey_get_private("-----BEGIN EC PRIVATE KEY-----\n".chunk_split(base64_encode($der),64,"\n")."-----END EC PRIVATE KEY-----\n");
            if (!$key) return false;
            $details = openssl_pkey_get_details($key);
            return isset($details['ec']['x'],$details['ec']['y'])
                && hash_equals($public,"\x04".str_pad($details['ec']['x'],32,"\x00",STR_PAD_LEFT).str_pad($details['ec']['y'],32,"\x00",STR_PAD_LEFT));
        } catch (\InvalidArgumentException) { return false; }
    }

    public static function firebaseCredentials(array $config): ?array
    {
        if (!($config['native_enabled'] ?? false) || !self::dependencies() || !class_exists(\Google\Auth\Credentials\ServiceAccountCredentials::class)) return null;
        $project = $config['firebase_project_id'] ?? '';
        if (!is_string($project) || !preg_match('/^[a-z][a-z0-9-]{4,61}[a-z0-9]$/D', $project)) return null;
        $file = realpath((string)($config['firebase_credentials_file'] ?? ''));
        $root = realpath(dirname(__DIR__,3));
        if (!$file || !is_file($file) || !is_readable($file) || !$root) return null;
        // The service account must not be bundled with public application files.
        $normalized = strtolower(str_replace('\\','/',$file));
        $roots = [$root];
        if (!empty($_SERVER['DOCUMENT_ROOT']) && realpath($_SERVER['DOCUMENT_ROOT'])) $roots[] = realpath($_SERVER['DOCUMENT_ROOT']);
        for ($ancestor=dirname($root);$ancestor!==dirname($ancestor);$ancestor=dirname($ancestor)) {
            if (in_array(strtolower(basename($ancestor)),['htdocs','public_html','www','wwwroot','html'],true)) $roots[]=$ancestor;
        }
        foreach ($roots as $webRoot) if (str_starts_with($normalized,strtolower(str_replace('\\','/',$webRoot)).'/')) return null;
        $raw = file_get_contents($file);
        if ($raw === false || strlen($raw) > 32768) return null;
        $credentials = json_decode($raw,true);
        if (!is_array($credentials) || ($credentials['type'] ?? '') !== 'service_account'
            || ($credentials['project_id'] ?? '') !== $project
            || ($credentials['token_uri'] ?? '') !== 'https://oauth2.googleapis.com/token'
            || !filter_var($credentials['client_email'] ?? '',FILTER_VALIDATE_EMAIL)
            || !is_string($credentials['private_key'] ?? null)
            || @openssl_pkey_get_private($credentials['private_key']) === false) return null;
        return $credentials;
    }
}
