<?php
declare(strict_types=1);
namespace Conquer\Observability;

/** Only diagnostic metadata crosses the log boundary; never a request or response body. */
final class SafeData
{
    private const KEYS = ['action','method','http_status','exception_class','exception_code','file','line','column',
        'client_request_id','client_release','screen','browser','platform','online','visibility','queue_dropped',
        'reason','item_id','item_code','quantity','source_type','source_key','rule_revision','reference',
        'target_id','city_id','march_id','report_id','order_id','building_code','research_code','troop_code',
        'result_id','result_march_id','result_report_id','result_queue_id','result_order_id',
        'retry','replayed','received_late','started_at','previous_code','source','admin_id','transaction_state','client_world_id','repeat_count'];

    public static function token(mixed $value, int $limit = 64): string
    {
        if (!is_scalar($value)) return '';
        return substr((string)preg_replace('/[^a-zA-Z0-9_.:\/-]/', '', (string)$value), 0, $limit);
    }

    public static function route(mixed $value): string
    {
        if (!is_string($value)) return '';
        $path = (string)(parse_url($value, PHP_URL_PATH) ?? '');
        // Arbitrary URL paths can contain bearer/reset tokens. Keep known application routes only.
        if (!preg_match('~^/(?:[a-zA-Z0-9_-]+/)*(?:api|assets)/~', $path)
            && !preg_match('~^/(?:city|game|admin)(?:/|$)~', $path)) return '';
        $path = preg_replace('~[a-zA-Z0-9_-]{48,}~', '[redacted]', $path);
        return substr((string)$path, 0, 160);
    }

    public static function message(string $value, int $limit = 512): string
    {
        $value = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $value);
        $value = preg_replace('~https?://[^\s]+~i', '[url]', (string)$value);
        $value = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[email]', (string)$value);
        $value = preg_replace('/\b(password|passwd|token|secret|authorization|cookie|csrf|session|body|payload)\b\s*[:=]\s*[^\s,;]+/i', '$1=[redacted]', (string)$value);
        $value = preg_replace('/([\x22\x27]).*?\1/', '[quoted value]', (string)$value);
        $value = preg_replace('/\b[A-Za-z0-9_\-]{32,}\b/', '[redacted]', (string)$value);
        return mb_substr((string)$value, 0, $limit, 'UTF-8');
    }

    public static function context(array $value): array
    {
        $safe = [];
        foreach (self::KEYS as $key) {
            if (!array_key_exists($key, $value) || (!is_scalar($value[$key]) && $value[$key] !== null)) continue;
            $entry = $value[$key];
            if (is_string($entry)) {
                if ($key === 'file') {
                    $path=(string)(parse_url($entry,PHP_URL_PATH)??'');
                    $entry=preg_match('~^/(?:assets|src|views)/[a-zA-Z0-9_./-]+$~D',$path)||$path==='/index.php' ? substr($path,0,160) : '';
                } else $entry = self::token($entry, 160);
            }
            if (is_float($entry) && !is_finite($entry)) continue;
            $safe[$key] = $entry;
        }
        return $safe;
    }
}
