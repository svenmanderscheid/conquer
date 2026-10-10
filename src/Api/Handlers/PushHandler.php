<?php
declare(strict_types=1);
namespace Conquer\Api\Handlers;

use Conquer\Api\Response;
use Conquer\Auth\Session;
use Conquer\Game\Locale;
use Conquer\Game\Notification\PushService;

final class PushHandler
{
    public static function status(array $params): void
    {
        $post = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
        $session = self::session($post);
        try {
            $input = $post ? self::input($session) : $_GET;
            $platform = $input['platform'] ?? 'web';
            if (!in_array($platform,['web','android'],true)) throw new \InvalidArgumentException();
            if (!$post && (isset($input['endpoint']) || isset($input['token']))) throw new \InvalidArgumentException();
            $device = $post ? PushService::device($input) : null;
            Response::ok(PushService::status($session,$device['endpoint'] ?? null,$platform));
        } catch (\InvalidArgumentException|\JsonException) { Response::error(400,'INVALID_INPUT',Locale::t('push.error.request')); }
    }

    public static function subscribe(array $params): void { self::write('subscribe'); }
    public static function unsubscribe(array $params): void { self::write('unsubscribe'); }
    public static function preferences(array $params): void { self::write('preferences'); }
    public static function test(array $params): void { self::write('test'); }

    private static function session(bool $write): array
    {
        $session = Session::current();
        if (!$session) Response::error(401,'UNAUTHENTICATED','Not logged in.');
        if ($write) {
            $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
            if (!is_string($csrf) || !hash_equals((string)$session['csrf_token'],$csrf)) Response::error(403,'CSRF_INVALID','CSRF token missing or invalid.');
        }
        return $session;
    }

    private static function write(string $action): void
    {
        $session = self::session(true);
        try {
            $input = self::input($session);
            $device = PushService::device($input);
            $status = PushService::status($session,$device['endpoint'],$device['platform']);
            if ($action !== 'unsubscribe' && !$status['available']) Response::error(503,'PUSH_UNAVAILABLE',Locale::t('push.error.unavailable'));
            if ($action === 'subscribe') Response::ok(PushService::subscribe($session,$input));
            if (PushService::schemaReady()) match ($action) {
                'unsubscribe' => PushService::unsubscribe($session,$device['endpoint']),
                'preferences' => PushService::updatePreferences($session,$device['endpoint'],$input['preferences'] ?? null),
                'test' => PushService::test($session,$device['endpoint']),
            };
            Response::ok(PushService::status($session,$device['endpoint'],$device['platform']) + ['queued'=>$action==='test']);
        } catch (\InvalidArgumentException|\JsonException) {
            Response::error(400,'INVALID_INPUT',Locale::t('push.error.request'));
        } catch (\DomainException $error) {
            Response::error(in_array($error->getCode(),[404,409],true)?$error->getCode():400,'PUSH_REQUEST_FAILED',Locale::t('push.error.request'));
        }
    }

    private static function input(array $session): array
    {
        $raw = file_get_contents('php://input',false,null,0,8193);
        if ($raw === false || strlen($raw)>8192) throw new \InvalidArgumentException();
        $input = json_decode($raw,true,16,JSON_THROW_ON_ERROR);
        if (!is_array($input)) throw new \InvalidArgumentException();
        if (isset($input['expected_player_id']) && (!is_int($input['expected_player_id']) || $input['expected_player_id'] !== (int)$session['player_id'])) Response::error(409,'ACCOUNT_CHANGED',Locale::t('push.error.account_changed'));
        return $input;
    }
}
