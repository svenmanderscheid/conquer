<?php
declare(strict_types=1);

namespace Conquer\Auth;

use Conquer\Db\Connection;
use Conquer\Game\City\CityState;
use Conquer\Game\Map\WorldPlacement;
use Conquer\Logger;

/**
 * OAuth 2.0 login for Google and Discord.
 *
 * Flow:
 *   1. redirectUrl($provider) — build the provider auth URL, set state cookie
 *   2. handleCallback($provider, $code, $state) — verify state, exchange code,
 *      fetch user info, find or create player, return player_id
 *
 * No Composer: all HTTP via curl (always available in XAMPP / Hostinger).
 */
final class OAuth
{
    private const PROVIDERS = [
        'google' => [
            'auth_url'     => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token_url'    => 'https://oauth2.googleapis.com/token',
            'userinfo_url' => 'https://www.googleapis.com/oauth2/v2/userinfo',
            'scope'        => 'openid email profile',
        ],
        'discord' => [
            'auth_url'     => 'https://discord.com/api/oauth2/authorize',
            'token_url'    => 'https://discord.com/api/oauth2/token',
            'userinfo_url' => 'https://discord.com/api/users/@me',
            'scope'        => 'identify email',
        ],
    ];

    private const STATE_COOKIE     = 'oauth_state';
    private const STATE_COOKIE_TTL = 600; // 10 minutes

    public function __construct(
        private readonly array $oauthConfig, // $appConfig['oauth']
    ) {}

    // -------------------------------------------------------------------------
    // Step 1 — build provider redirect URL
    // -------------------------------------------------------------------------

    public function redirectUrl(string $provider): string
    {
        $cfg = $this->providerCfg($provider);
        $p   = self::PROVIDERS[$provider];

        $state = bin2hex(random_bytes(16));
        $this->saveState($state, $provider);

        $params = [
            'client_id'     => $cfg['client_id'],
            'redirect_uri'  => $cfg['redirect_uri'],
            'response_type' => 'code',
            'scope'         => $p['scope'],
            'state'         => $state,
        ];

        // Google requires access_type for the offline/online hint
        if ($provider === 'google') {
            $params['access_type'] = 'online';
        }

        return $p['auth_url'] . '?' . http_build_query($params);
    }

    // -------------------------------------------------------------------------
    // Step 2 — handle provider callback
    // -------------------------------------------------------------------------

    /**
     * @return int  player_id of the authenticated (or newly registered) player
     */
    public function handleCallback(string $provider, string $code, string $incomingState): int
    {
        $this->verifyState($incomingState, $provider);
        $this->clearState();

        $cfg         = $this->providerCfg($provider);
        $p           = self::PROVIDERS[$provider];

        $accessToken = $this->exchangeCode($provider, $code, $cfg, $p['token_url']);
        $raw         = $this->fetchUserInfo($p['userinfo_url'], $accessToken);
        $user        = $this->normalizeUser($provider, $raw);

        $playerId = $this->findOrCreatePlayer($provider, $user);

        // VIP daily points are claimed from the same panel for all login methods.

        // Inactivity system: restore hidden city on login.
        try {
            $this->restoreHiddenCityOnLogin($playerId);
        } catch (\Throwable) {}

        // Initialize tutorial progress (INSERT IGNORE — safe to call every login).
        try {
            \Conquer\Game\Tutorial\TutorialService::ensureInitialized($playerId);
        } catch (\Throwable) {}

        // Ensure daily quests exist for today (idempotent).
        try {
            \Conquer\Game\Quest\DailyQuestService::ensureDailyQuests($playerId);
        } catch (\Throwable) {}

        // Update last_active_at
        try {
            \Conquer\Db\Connection::getInstance()->execute(
                'UPDATE players SET last_active_at = UTC_TIMESTAMP() WHERE id = ?',
                [$playerId],
            );
        } catch (\Throwable) {}

        return $playerId;
    }

    // -------------------------------------------------------------------------
    // OAuth state (CSRF protection during redirect)
    // -------------------------------------------------------------------------

    private function saveState(string $state, string $provider): void
    {
        // Encode both state and provider in the cookie so callback can verify both
        setcookie(self::STATE_COOKIE, $state . '|' . $provider, [
            'expires'  => time() + self::STATE_COOKIE_TTL,
            'path'     => '/',
            'secure'   => ($_SERVER['HTTPS'] ?? '') !== '',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private function verifyState(string $incoming, string $provider): void
    {
        $cookie = $_COOKIE[self::STATE_COOKIE] ?? '';
        $parts  = explode('|', $cookie, 2);
        [$storedState, $storedProvider] = [$parts[0] ?? '', $parts[1] ?? ''];

        if (!hash_equals($storedState, $incoming) || $storedProvider !== $provider) {
            throw new \RuntimeException('OAuth state mismatch — possible CSRF attempt.');
        }
    }

    private function clearState(): void
    {
        setcookie(self::STATE_COOKIE, '', [
            'expires'  => time() - 1,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    // -------------------------------------------------------------------------
    // HTTP helpers (curl — no Composer)
    // -------------------------------------------------------------------------

    private function exchangeCode(
        string $provider,
        string $code,
        array  $cfg,
        string $tokenUrl,
    ): string {
        $response = $this->httpPost($tokenUrl, [
            'client_id'     => $cfg['client_id'],
            'client_secret' => $cfg['client_secret'],
            'code'          => $code,
            'redirect_uri'  => $cfg['redirect_uri'],
            'grant_type'    => 'authorization_code',
        ]);

        if (empty($response['access_token'])) {
            throw new \RuntimeException(
                "OAuth token exchange failed for {$provider}: "
                    . ($response['error_description'] ?? $response['error'] ?? 'unknown error'),
            );
        }

        return $response['access_token'];
    }

    private function fetchUserInfo(string $url, string $accessToken): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$accessToken}"],
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_USERAGENT      => 'ConquerGame/1.0',
        ]);

        $body  = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false || $body === '') {
            throw new \RuntimeException("OAuth user info request failed: {$error}");
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new \RuntimeException('OAuth user info response was not valid JSON.');
        }

        return $data;
    }

    private function httpPost(string $url, array $params): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_USERAGENT      => 'ConquerGame/1.0',
        ]);

        $body  = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new \RuntimeException("HTTP POST to {$url} failed: {$error}");
        }

        return json_decode($body, true) ?? [];
    }

    // -------------------------------------------------------------------------
    // User normalization + DB
    // -------------------------------------------------------------------------

    private function normalizeUser(string $provider, array $raw): array
    {
        return match ($provider) {
            'google' => [
                'provider_user_id' => (string) ($raw['id'] ?? ''),
                'email'            => strtolower(trim($raw['email'] ?? '')),
                'display_name'     => $raw['name'] ?? $raw['email'] ?? 'Player',
            ],
            'discord' => [
                'provider_user_id' => (string) ($raw['id'] ?? ''),
                'email'            => strtolower(trim($raw['email'] ?? '')),
                'display_name'     => $raw['global_name'] ?? $raw['username'] ?? 'Player',
            ],
            default => throw new \RuntimeException("Unknown provider: {$provider}"),
        };
    }

    private function findOrCreatePlayer(string $provider, array $user): int
    {
        $db = Connection::getInstance();

        // 1. Known OAuth link → return existing player
        $existing = $db->query(
            'SELECT player_id FROM oauth_accounts WHERE provider = ? AND provider_user_id = ?',
            [$provider, $user['provider_user_id']],
        )->fetch();

        if ($existing !== false) {
            return (int) $existing['player_id'];
        }

        // 2. Same email registered via another provider → link, don't duplicate
        if ($user['email'] !== '') {
            $byEmail = $db->query(
                'SELECT id FROM players WHERE email = ?',
                [$user['email']],
            )->fetch();

            if ($byEmail !== false) {
                $playerId = (int) $byEmail['id'];
                $db->execute(
                    'INSERT INTO oauth_accounts (player_id, provider, provider_user_id) VALUES (?, ?, ?)',
                    [$playerId, $provider, $user['provider_user_id']],
                );
                Logger::getInstance()->info(
                    "OAuth account linked: {$provider} → player_id={$playerId}",
                );
                return $playerId;
            }
        }

        // Closed alpha: a social login may resume/link an invited account,
        // but it must never create a new player around the invite gate.
        throw new \DomainException('Erstelle dein Alpha-Konto zuerst mit deinem persönlichen Key. Danach kannst du diesen Anmeldedienst verknüpfen.');
    }

    /**
     * Creates a default city with all canonical buildings in the explicit or first open world.
     */
    public static function createDefaultCity(Connection $db, int $playerId, string $username,?int $worldId=null): void
    {
        $worldId??=(int)($db->query("SELECT id FROM worlds WHERE status IN ('open','running') ORDER BY id LIMIT 1")->fetchColumn()?:0);
        if($worldId<1)throw new \DomainException('Momentan ist keine Welt für neue Königreiche geöffnet.');
        if (!$db->getPdo()->inTransaction()) {
            $db->transaction(static function (Connection $db) use ($playerId, $username,$worldId): void {
                self::createDefaultCity($db, $playerId, $username,$worldId);
            });
            return;
        }

        // Reserve a dry, unoccupied 4×4 footprint until the city is inserted.
        \Conquer\Game\World\WorldContext::assertActionAvailable($worldId);
        if($db->query('SELECT id FROM cities WHERE player_id=? AND world_id=?',[$playerId,$worldId])->fetchColumn())return;
        \Conquer\Game\World\WorldService::initializeWorld($worldId);
        $coord = self::randomCoord($db,$worldId);

        $cityName = $username . "'s City";
        $initialPower = array_sum(array_map(
            static fn(string $code): int => \Conquer\Game\City\BuildingData::getTotalPower($code, 1),
            CityState::BUILDING_CODES,
        ));
        $db->execute(
            'INSERT INTO cities
                 (player_id, world_id, name, coord_x, coord_y,
                  food, lumber, stone, gold,
                  wall_hp_current, wall_hp_max, castle_level, power)
             VALUES (?, ?, ?, ?, ?, 10000, 10000, 10000, 5000, 5000, 5000, 1, ?)',
            [$playerId,$worldId, $cityName, $coord['x'], $coord['y'], $initialPower],
        );
        $cityId = $db->lastInsertId();

        // Insert all canonical buildings at level 1.
        foreach (CityState::BUILDING_CODES as $code) {
            $db->execute(
                'INSERT INTO city_buildings (city_id, building_code, level) VALUES (?, ?, 1)',
                [$cityId, $code],
            );
        }
    }

    /**
     * Finds a dry, unoccupied city footprint within the active world bounds.
     * The caller keeps the world placement lock until its transaction commits.
     *
     * @return array{x: int, y: int}
     */
    private static function randomCoord(Connection $db,int $worldId, ?int $ignoreCityId = null): array
    {
        $size = WorldPlacement::lockWorld($db, $worldId);
        $min = $size > 22 ? 10 : 1;
        $max = min($size - $min - 1, $size - 3);
        if ($max < $min) {
            throw new \RuntimeException('The world has no space for a city.');
        }

        for ($i = 0; $i < 64; $i++) {
            $x = random_int($min, $max);
            $y = random_int($min, $max);
            if (WorldPlacement::canPlace($db, $worldId, 'city', $x, $y, $ignoreCityId)) {
                return ['x' => $x, 'y' => $y];
            }
        }

        $coord = WorldPlacement::findNear($db, $worldId, 'city', intdiv($size, 2), intdiv($size, 2), $ignoreCityId, $size);
        if ($coord !== null) {
            return ['x' => $coord[0], 'y' => $coord[1]];
        }
        throw new \RuntimeException('Could not find a dry, unoccupied city footprint.');
    }

    /**
     * Picks a username based on the provider display name, making it unique
     * by appending an incrementing number if needed.
     */
    private function uniqueUsername(Connection $db, string $base): string
    {
        // Keep only alphanumeric + underscores, max 25 chars (leaves room for suffix)
        $clean = preg_replace('/[^A-Za-z0-9_]/', '', $base);
        $clean = substr($clean !== '' ? $clean : 'Player', 0, 25);

        $candidate = $clean;
        $suffix    = 2;

        while (
            $db->query('SELECT 1 FROM players WHERE username = ?', [$candidate])->fetch() !== false
        ) {
            $candidate = $clean . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    // -------------------------------------------------------------------------
    // Inactivity system
    // -------------------------------------------------------------------------

    /**
     * When a player was hidden (>30 days inactive), show their city again on login.
     * If the city was hidden, also assign new random coordinates so they don't
     * return to a spot that may be contested.
     */
    private function restoreHiddenCityOnLogin(int $playerId): void
    {
        $db = Connection::getInstance();
        $restored = $db->transaction(static function (Connection $db) use ($playerId): bool {
            $player = $db->query('SELECT is_hidden FROM players WHERE id = ? FOR UPDATE', [$playerId])->fetch();
            if ($player === false || (int) $player['is_hidden'] === 0) {
                return false;
            }
            $cities = $db->query('SELECT id,world_id FROM cities WHERE player_id = ? ORDER BY world_id FOR UPDATE', [$playerId])->fetchAll();
            if (!$cities) {
                return false;
            }
            // Do not reveal the city unless a valid destination has been reserved.
            foreach ($cities as $city) {
            WorldPlacement::lockWorld($db, (int) $city['world_id']);
            $coord = self::randomCoord($db, (int) $city['world_id'], (int) $city['id']);
            $db->execute(
                'UPDATE cities
                 SET coord_x = ?, coord_y = ?, is_hidden = 0
                 WHERE id = ?',
                [$coord['x'], $coord['y'], (int) $city['id']],
            );
            }
            $db->execute('UPDATE players SET is_hidden = 0 WHERE id = ?', [$playerId]);
            return true;
        });
        if (!$restored) {
            return;
        }

        // Send welcome-back notification
        \Conquer\Game\Notification\NotificationService::push($playerId, 'welcome_back', []);

        Logger::getInstance()->info("Inactivity: player {$playerId} restored from hidden state.");
    }

    // -------------------------------------------------------------------------

    private function providerCfg(string $provider): array
    {
        if (!array_key_exists($provider, self::PROVIDERS)) {
            throw new \RuntimeException("Unknown OAuth provider: {$provider}");
        }

        $cfg = $this->oauthConfig[$provider] ?? [];

        if (empty($cfg['client_id']) || empty($cfg['client_secret']) || empty($cfg['redirect_uri'])) {
            throw new \RuntimeException(
                "OAuth config incomplete for provider '{$provider}'. "
                    . "Set oauth.{$provider}.client_id/client_secret/redirect_uri in config/app.php.",
            );
        }

        return $cfg;
    }
}
