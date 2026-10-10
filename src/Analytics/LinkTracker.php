<?php
declare(strict_types=1);
namespace Conquer\Analytics;

use Conquer\Bootstrap;
use Conquer\Db\Connection;
use Conquer\Security\RateLimit;

/** Public anonymous counters. Never associates a click with a player or session. */
final class LinkTracker
{
    public const SOURCES = ['direct','instagram','discord','facebook','google','youtube','tiktok','x','other'];

    /** Keep the counters compatible with both published gallery layouts. */
    public static function galleryTarget(string $art): string
    {
        return match ($art) {
            'city','01-build' => 'site-gallery-city',
            'army','02-army','03-infantry','04-archers','05-cavalry' => 'site-gallery-army',
            '10-alpha' => 'site-register-guide',
            default => 'site-gallery-world',
        };
    }

    public static function enabled(): bool
    {
        return (Bootstrap::getConfig()['link_tracking_enabled'] ?? true) === true;
    }

    public static function ignored(array $server): bool
    {
        if (!self::enabled() || ($server['HTTP_DNT'] ?? '') === '1' || ($server['HTTP_SEC_GPC'] ?? '') === '1') return true;
        if (preg_match('/prefetch|prerender/i', ($server['HTTP_SEC_PURPOSE'] ?? '') . ' ' . ($server['HTTP_PURPOSE'] ?? ''))) return true;
        $ua = $server['HTTP_USER_AGENT'] ?? '';
        return $ua === '' || (bool) preg_match('/bot|crawler|spider|slurp|preview|facebookexternalhit|whatsapp|telegram|headless|curl|wget/i', $ua);
    }

    public static function device(string $ua): string
    {
        if (preg_match('/ipad|tablet|android(?!.*mobile)/i', $ua)) return 'tablet';
        if (preg_match('/iphone|ipod|android|mobile/i', $ua)) return 'mobile';
        return preg_match('/windows|macintosh|linux|cros/i', $ua) ? 'desktop' : 'other';
    }

    public static function source(string $referrer): string
    {
        $host = strtolower((string) parse_url($referrer, PHP_URL_HOST));
        if ($host === '') return 'direct';
        foreach (['instagram'=>['instagram.com'], 'discord'=>['discord.com','discord.gg'], 'facebook'=>['facebook.com','fb.com'],
            'google'=>['google.com','google.lu','google.de','google.fr','google.co.uk'], 'youtube'=>['youtube.com','youtu.be'],
            'tiktok'=>['tiktok.com'], 'x'=>['x.com','twitter.com','t.co']] as $source=>$domains) {
            foreach ($domains as $domain) if ($host === $domain || str_ends_with($host, '.' . $domain)) return $source;
        }
        return 'other';
    }

    public static function record(int $id, string $source, array $server): void
    {
        if (self::ignored($server)) return;
        // Existing abuse protection retains only a hashed, daily changing bucket.
        if (RateLimit::consume('links.click', gmdate('Y-m-d') . ':' . RateLimit::ip(), 120, 60) > 0) return;
        Connection::getInstance()->execute(
            'INSERT INTO link_tracker_daily(link_id,day,device,source,clicks,last_click_at) VALUES(?,UTC_DATE(),?,?,1,UTC_TIMESTAMP()) '
            . 'ON DUPLICATE KEY UPDATE clicks=clicks+1,last_click_at=UTC_TIMESTAMP()',
            [$id, self::device((string) ($server['HTTP_USER_AGENT'] ?? '')), in_array($source, self::SOURCES, true) ? $source : 'other']
        );
    }

    /** A saved campaign target is the sole redirect authority; query parameters cannot replace it. */
    public static function redirect(string $slug): void
    {
        header('Cache-Control: private, no-store');
        header('X-Robots-Tag: noindex, nofollow');
        header('Referrer-Policy: no-referrer');
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (!in_array($method, ['GET','HEAD'], true)) { http_response_code(405); header('Allow: GET, HEAD'); return; }
        if (!preg_match('/^[a-z0-9][a-z0-9-]{2,63}$/D', $slug)) { http_response_code(404); return; }
        try {
            $row = Connection::getInstance()->query("SELECT id,destination FROM link_tracker_links WHERE slug=? AND kind='campaign' AND enabled=1", [$slug])->fetch();
        } catch (\Throwable) { http_response_code(503); header('Retry-After: 30'); return; }
        if (!$row) { http_response_code(404); return; }
        try { $destination = self::destination($row['destination']); }
        catch (\InvalidArgumentException) { http_response_code(404); return; }
        if (str_starts_with($destination, '/')) $destination = (defined('APP_BASE') ? APP_BASE : '') . $destination;
        if ($method === 'GET') {
            try { self::record((int)$row['id'], self::source((string) ($_SERVER['HTTP_REFERER'] ?? '')), $_SERVER); }
            catch (\Throwable $error) { \Conquer\Observability\EventLog::exception($error,'links.counter'); }
        }
        // Even a failed counter must never block the saved destination.
        header('Location: ' . $destination, true, 302);
    }

    public static function click(): void
    {
        header('Cache-Control: private, no-store');
        header('X-Robots-Tag: noindex, nofollow');
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); header('Allow: POST'); return; }
        if (!self::sameOrigin($_SERVER)) { http_response_code(403); return; }
        $body = file_get_contents('php://input', false, null, 0, 1025);
        if ($body === false || strlen($body) > 1024) { http_response_code(413); return; }
        $data = json_decode($body, true, 4);
        if (!is_array($data) || !is_string($data['link'] ?? null) || !preg_match('/^site-[a-z-]{1,59}$/D', $data['link'])) { http_response_code(422); return; }
        $source = is_string($data['source'] ?? null) && in_array($data['source'], self::SOURCES, true) ? $data['source'] : 'other';
        if (!self::ignored($_SERVER)) try {
            $row = Connection::getInstance()->query("SELECT id FROM link_tracker_links WHERE slug=? AND kind='website' AND enabled=1", [$data['link']])->fetch();
            if (!$row) { http_response_code(422); return; }
            self::record((int)$row['id'], $source, $_SERVER);
        } catch (\Throwable) {
            // Analytics is best effort; do not log submitted content or SQL values.
            \Conquer\Logger::getInstance()->error('Link tracker counter unavailable.');
        }
        http_response_code(204);
    }

    public static function sameOrigin(array $server): bool
    {
        $origin = $server['HTTP_ORIGIN'] ?? '';
        if (!is_string($origin) || $origin === '' || $origin === 'null') return false;
        $scheme = !empty($server['HTTPS']) && strtolower((string)$server['HTTPS']) !== 'off' ? 'https' : 'http';
        return strtolower($origin) === $scheme . '://' . strtolower((string) ($server['HTTP_HOST'] ?? ''));
    }

    public static function destination(mixed $value): string
    {
        if (!is_string($value) || $value === '' || strlen($value) > 2048 || preg_match('/[\x00-\x20\x7f\\\\]/', $value)) {
            throw new \InvalidArgumentException(\Conquer\Game\Locale::t('links.error_target'));
        }
        if (str_starts_with($value, '/') && !str_starts_with($value, '//')) {
            $path = (string) parse_url($value, PHP_URL_PATH);
            // Public destinations only; prevent forwarding into game mutations or tracker loops.
            if (in_array($path, ['/','/privacy','/account-deletion','/city'], true)) return $value;
        }
        $parts = parse_url($value);
        $path = is_array($parts) ? rawurldecode(rawurldecode($parts['path'] ?? '')) : '';
        if (filter_var($value, FILTER_VALIDATE_URL) && is_array($parts) && ($parts['scheme'] ?? '') === 'https'
            && !isset($parts['user']) && !isset($parts['pass']) && !isset($parts['port'])
            && !preg_match('~/(?:go|api|admin|auth)(?:/|$)~i', $path)) return $value;
        throw new \InvalidArgumentException(\Conquer\Game\Locale::t('links.error_target'));
    }
}
