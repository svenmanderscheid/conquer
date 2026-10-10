<?php
declare(strict_types=1);
namespace Conquer\Game\Notification;

use Conquer\Db\Connection;
use Conquer\Game\Locale;

/** Persist opt-in and a durable outbox; only the CLI worker contacts push services. */
final class PushService
{
    public const DEFAULT_PREFERENCES = ['completions' => true, 'security' => true];

    public static function schemaReady(): bool
    {
        $count = Connection::getInstance()->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('push_subscriptions','push_deliveries')")->fetchColumn();
        return (int) $count === 2;
    }

    public static function status(array $session, ?string $endpoint = null, string $platform = 'web'): array
    {
        $config = PushConfig::load();
        $schema = self::schemaReady();
        $ready = $schema && ($platform === 'android' ? PushConfig::firebaseCredentials($config) !== null : PushConfig::configured($config));
        $row = $schema && $endpoint !== null ? self::owned($session, $endpoint) : false;
        return [
            'available' => $ready,
            'public_key' => $ready && $platform === 'web' ? (string) $config['public_key'] : null,
            'platform' => $platform,
            'enabled' => (bool) $row,
            'player_id' => (int) $session['player_id'],
            'preferences' => $row ? ['completions' => (bool) $row['completions'], 'security' => (bool) $row['security']] : self::DEFAULT_PREFERENCES,
        ];
    }

    public static function decodeKey(mixed $key, int $length): string
    {
        if (!is_string($key) || !preg_match('/^[A-Za-z0-9_-]+$/D', $key)) throw new \InvalidArgumentException('Invalid push key.');
        $raw = base64_decode(strtr($key, '-_', '+/'), true);
        if ($raw === false || strlen($raw) !== $length) throw new \InvalidArgumentException('Invalid push key.');
        return $raw;
    }

    /** Fixed provider hosts, HTTPS only, no credentials/alternate ports/redirects. */
    public static function validateEndpoint(mixed $endpoint): string
    {
        if (!is_string($endpoint) || strlen($endpoint) > 2048 || preg_match('/[\x00-\x20\x7f\\\\]/', $endpoint)) throw new \InvalidArgumentException('Invalid push endpoint.');
        $url = parse_url($endpoint);
        if (!$url || ($url['scheme'] ?? '') !== 'https' || isset($url['user'], $url['pass'])
            || isset($url['user']) || isset($url['pass']) || isset($url['fragment'])
            || (isset($url['port']) && $url['port'] !== 443) || empty($url['path'])) throw new \InvalidArgumentException('Invalid push endpoint.');
        $host = strtolower((string) ($url['host'] ?? ''));
        $allowed = in_array($host, ['fcm.googleapis.com', 'updates.push.services.mozilla.com', 'web.push.apple.com'], true)
            || preg_match('/^[a-z0-9-]+\.notify\.windows\.com$/D', $host)
            || preg_match('/^[a-z0-9-]+\.push\.apple\.com$/D', $host);
        if (!$allowed) throw new \InvalidArgumentException('Unsupported push service.');
        return $endpoint;
    }

    public static function preferences(mixed $value): array
    {
        if (!is_array($value)) throw new \InvalidArgumentException('Invalid notification preferences.');
        $result = self::DEFAULT_PREFERENCES;
        foreach ($value as $key => $enabled) {
            if (!array_key_exists($key, $result) || !is_bool($enabled)) throw new \InvalidArgumentException('Invalid notification preferences.');
            $result[$key] = $enabled;
        }
        return $result;
    }

    public static function device(array $input): array
    {
        $platform = $input['platform'] ?? 'web';
        if ($platform === 'android') {
            $token = $input['token'] ?? null;
            if (!is_string($token) || !preg_match('/^[A-Za-z0-9_:.-]{20,2044}$/D',$token)) throw new \InvalidArgumentException('Invalid device token.');
            return ['platform'=>'android','endpoint'=>'fcm:'.$token];
        }
        if ($platform !== 'web') throw new \InvalidArgumentException('Invalid notification platform.');
        return ['platform'=>'web','endpoint'=>self::validateEndpoint($input['endpoint'] ?? $input['subscription']['endpoint'] ?? null)];
    }

    public static function subscribe(array $session, array $input): array
    {
        $device = self::device($input);
        $endpoint = $device['endpoint'];
        $platform = $device['platform'];
        $public = $auth = '';
        if ($platform === 'web') {
            $subscription = $input['subscription'] ?? null;
            if (!is_array($subscription)) throw new \InvalidArgumentException('Invalid push subscription.');
            $public = $subscription['keys']['p256dh'] ?? null;
            $auth = $subscription['keys']['auth'] ?? null;
            if (self::decodeKey($public, 65)[0] !== "\x04") throw new \InvalidArgumentException('Invalid push key.');
            self::decodeKey($auth, 16);
        }
        $prefs = self::preferences($input['preferences'] ?? self::DEFAULT_PREFERENCES);
        $db = Connection::getInstance();
        $db->transaction(static function () use ($db, $session, $endpoint, $public, $auth, $prefs, $input, $platform): void {
            // An explicit subscription transfers this browser from its previous account.
            // Delete the old outbox through the FK before assigning a new owner.
            $hash = hash('sha256', $endpoint);
            $old = $db->query('SELECT id,player_id,session_id FROM push_subscriptions WHERE endpoint_hash=? FOR UPDATE', [$hash])->fetch();
            if ($platform === 'android' && array_key_exists('previous_token',$input)) {
                $previous = self::device(['platform'=>'android','token'=>$input['previous_token']]);
                $previousRow = $db->query('SELECT id,player_id,session_id FROM push_subscriptions WHERE endpoint_hash=? FOR UPDATE',[hash('sha256',$previous['endpoint'])])->fetch();
                if ($previousRow && ((int)$previousRow['player_id'] !== (int)$session['player_id'] || (int)$previousRow['session_id'] !== (int)$session['id'])) throw new \DomainException('Notification device not found.',404);
                if ($old && ((int)$old['player_id'] !== (int)$session['player_id'] || (int)$old['session_id'] !== (int)$session['id'])) throw new \DomainException('Notification device conflict.',409);
                // A lost response may replay OLD -> NEW after OLD already disappeared.
                if (!$previousRow && !$old) throw new \DomainException('Notification device not found.',404);
                if ($previousRow && $old && (int)$previousRow['id'] !== (int)$old['id']) {
                    // Both are owned by this exact session; merge queued work before cleanup.
                    $db->execute('UPDATE IGNORE push_deliveries SET subscription_id=? WHERE subscription_id=?',[$previousRow['id'],$old['id']]);
                    $db->execute('DELETE FROM push_subscriptions WHERE id=?',[$old['id']]);
                }
                if ($previousRow) $db->execute('UPDATE push_subscriptions SET endpoint_hash=?,endpoint=?,locale=?,updated_at=UTC_TIMESTAMP() WHERE id=?',[$hash,$endpoint,Locale::normalize($input['locale']??'en'),$previousRow['id']]);
                return; // Keep its ID, preferences, opt-in floor, device slot and outbox.
            }
            if ($old && (int) $old['player_id'] === (int) $session['player_id'] && (int) $old['session_id'] === (int) $session['id']) {
                $db->execute('UPDATE push_subscriptions SET public_key=?,auth_token=?,locale=?,completions=?,security=?,updated_at=UTC_TIMESTAMP() WHERE id=?', [$public,$auth,Locale::normalize($input['locale'] ?? 'en'),(int)$prefs['completions'],(int)$prefs['security'],$old['id']]);
                return;
            }
            if ($old) $db->execute('DELETE FROM push_subscriptions WHERE id=?', [$old['id']]);
            if ((int)$db->query('SELECT COUNT(*) FROM push_subscriptions WHERE player_id=?', [$session['player_id']])->fetchColumn() >= 10) throw new \DomainException('Too many notification devices.', 409);
            $cursor = (int) $db->query('SELECT COALESCE(MAX(id),0) FROM notifications')->fetchColumn();
            $db->execute('INSERT INTO push_subscriptions(player_id,session_id,platform,endpoint_hash,endpoint,public_key,auth_token,locale,completions,security,last_notification_id) VALUES(?,?,?,?,?,?,?,?,?,?,?)',
                [$session['player_id'],$session['id'],$platform,$hash,$endpoint,$public,$auth,Locale::normalize($input['locale'] ?? 'en'),(int)$prefs['completions'],(int)$prefs['security'],$cursor]);
        });
        return self::status($session, $endpoint, $platform);
    }

    public static function owned(array $session, string $endpoint): array|false
    {
        return Connection::getInstance()->query('SELECT * FROM push_subscriptions WHERE endpoint_hash=? AND player_id=? AND session_id=?', [hash('sha256',$endpoint),$session['player_id'],$session['id']])->fetch();
    }

    public static function unsubscribe(array $session, string $endpoint): void
    {
        Connection::getInstance()->execute('DELETE FROM push_subscriptions WHERE endpoint_hash=? AND player_id=? AND session_id=?', [hash('sha256',$endpoint),$session['player_id'],$session['id']]);
    }

    public static function updatePreferences(array $session, string $endpoint, mixed $input): void
    {
        $prefs = self::preferences($input);
        $db = Connection::getInstance();
        $db->transaction(static function () use ($db,$session,$endpoint,$prefs): void {
            $row = self::owned($session,$endpoint);
            if (!$row) throw new \DomainException('Notification device not found.',404);
            $cursor = (int)$db->query('SELECT COALESCE(MAX(id),0) FROM notifications')->fetchColumn();
            $db->execute('UPDATE push_subscriptions SET completions=?,security=?,last_notification_id=?,updated_at=UTC_TIMESTAMP() WHERE id=?', [(int)$prefs['completions'],(int)$prefs['security'],$cursor,$row['id']]);
            foreach ($prefs as $category=>$enabled) if (!$enabled) $db->execute('DELETE FROM push_deliveries WHERE subscription_id=? AND category=? AND completed_at IS NULL', [$row['id'],$category]);
            if(!$prefs['security'])$db->execute("DELETE FROM push_deliveries WHERE subscription_id=? AND category IN ('alliance','messages') AND completed_at IS NULL",[$row['id']]);
        });
    }

    public static function test(array $session, string $endpoint): void
    {
        $db = Connection::getInstance();
        $db->transaction(static function () use ($db,$session,$endpoint): void {
            $row = $db->query('SELECT * FROM push_subscriptions WHERE endpoint_hash=? AND player_id=? AND session_id=? FOR UPDATE', [hash('sha256',$endpoint),$session['player_id'],$session['id']])->fetch();
            if (!$row) throw new \DomainException('Notification device not found.',404);
            // Repeated taps/replayed network requests produce at most one test per minute.
            if ($row['last_test_at'] && strtotime($row['last_test_at'].' UTC') > time()-60) return;
            $db->execute('INSERT INTO push_deliveries(subscription_id,category,world_id,delivery_tag) VALUES(?,\'test\',?,?)', [$row['id'],(int)($session['active_world_id'] ?? 1),bin2hex(random_bytes(16))]);
            $db->execute('UPDATE push_subscriptions SET last_test_at=UTC_TIMESTAMP() WHERE id=?', [$row['id']]);
        });
    }

    /** Also used before replacing a login on a shared browser. Never prevents logout. */
    public static function revokeCookie(mixed $token): void
    {
        if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/Di', $token)) return;
        try {
            if (self::schemaReady()) Connection::getInstance()->execute('DELETE p FROM push_subscriptions p JOIN sessions s ON s.id=p.session_id WHERE s.token=?', [$token]);
        } catch (\Throwable $error) { \Conquer\Observability\EventLog::exception($error,'push.revoke'); }
    }

    public static function category(string $type): ?string
    {
        return match ($type) {
            'build_complete','research_complete','train_complete','heal_complete','farm_returned' => 'completions',
            'battle_incoming','scout_incoming','rally_incoming','wall_destroyed' => 'security',
            'alliance_rally_player','alliance_rally_monster' => 'alliance',
            'private_message' => 'messages',
            default => null,
        };
    }

    /** Keep existing device preferences compatible; community channel mutes also apply. */
    public static function enabled(array $subscription,string $category): bool
    {
        return $category==='test'||!empty($subscription[$category==='completions'?'completions':'security']);
    }
}
