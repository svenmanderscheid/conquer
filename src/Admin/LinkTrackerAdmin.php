<?php
declare(strict_types=1);
namespace Conquer\Admin;

use Conquer\Analytics\LinkTracker;
use Conquer\Db\Connection;
use Conquer\Game\Locale;

final class LinkTrackerAdmin
{
    /** Runs inside the existing authenticated receipt/audit transaction. */
    public static function save(Connection $db, array $input): array
    {
        $id = filter_var($input['link_id'] ?? 0, FILTER_VALIDATE_INT, ['options'=>['min_range'=>0]]);
        $label = $input['label'] ?? null;
        if ($id === false || !is_string($label) || !mb_check_encoding($label, 'UTF-8') || trim($label) === '' || mb_strlen(trim($label)) > 120 || preg_match('/\p{C}/u', $label)) {
            throw new \InvalidArgumentException(Locale::t('links.error_label'));
        }
        $label = trim($label);
        $slug = $input['slug'] ?? null;
        if (!is_string($slug) || !preg_match('/^[a-z0-9][a-z0-9-]{2,63}$/D', $slug) || str_starts_with($slug, 'site-')) {
            throw new \InvalidArgumentException(Locale::t('links.error_slug'));
        }
        $target = LinkTracker::destination($input['destination'] ?? null);
        $enabled = $input['enabled'] ?? '1';
        if (!in_array($enabled, ['0','1'], true)) throw new \InvalidArgumentException(Locale::t('links.error_status'));
        $before = $id ? $db->query("SELECT * FROM link_tracker_links WHERE id=? AND kind='campaign' FOR UPDATE", [$id])->fetch() : null;
        if ($id && !$before) throw new \InvalidArgumentException(Locale::t('links.error_missing'));
        if ($before && ($slug !== $before['slug'] || (string)($input['revision'] ?? '') !== (string)$before['revision'])) {
            throw new \DomainException(Locale::t('links.error_stale'));
        }
        try {
            if ($id) $db->execute('UPDATE link_tracker_links SET label=?,destination=?,enabled=?,revision=revision+1 WHERE id=?', [$label,$target,(int)$enabled,$id]);
            else {
                $db->execute("INSERT INTO link_tracker_links(slug,label,destination,kind,enabled) VALUES(?,?,?,'campaign',?)", [$slug,$label,$target,(int)$enabled]);
                $id = $db->lastInsertId();
            }
        } catch (\PDOException $e) {
            if (($e->errorInfo[1] ?? 0) === 1062) throw new \InvalidArgumentException(Locale::t('links.error_duplicate'));
            throw $e;
        }
        return ['target_type'=>'tracked_link','target_id'=>$id,'before'=>$before,
            'after'=>['slug'=>$slug,'label'=>$label,'destination'=>$target,'enabled'=>(int)$enabled], 'message'=>Locale::t('links.saved')];
    }

    public static function listing(Connection $db, array $input): array
    {
        $days = is_scalar($input['days'] ?? null) ? (int)$input['days'] : 30;
        if (!in_array($days, [1,7,30,90], true)) $days = 30;
        // Calendar days in UTC, including today; distinct from a rolling 24-hour window.
        $since = gmdate('Y-m-d', strtotime('today UTC') - ($days - 1) * 86400);
        $kind = is_string($input['kind'] ?? null) && in_array($input['kind'], ['campaign','website'], true) ? $input['kind'] : '';
        $id = is_scalar($input['link_id'] ?? null) ? max(0, (int)$input['link_id']) : 0;
        $selected = $id ? $db->query('SELECT * FROM link_tracker_links WHERE id=?', [$id])->fetch() : null;
        $id = $selected ? $id : 0;
        $where = $kind ? ' WHERE l.kind=?' : '';
        $params = $kind ? [$kind] : [];
        $total = (int)$db->query('SELECT COUNT(*) FROM link_tracker_links l' . $where, $params)->fetchColumn();
        $pages = max(1, (int)ceil($total / 25));
        $page = min($pages, max(1, (int)(is_scalar($input['page'] ?? null) ? $input['page'] : 1)));
        $rows = $db->query('SELECT l.*,COALESCE(s.clicks,0) AS clicks,COALESCE(s.lifetime,0) AS lifetime,s.last_click '
            . 'FROM link_tracker_links l LEFT JOIN (SELECT link_id,SUM(IF(day>=?,clicks,0)) AS clicks,SUM(clicks) AS lifetime,MAX(last_click_at) AS last_click FROM link_tracker_daily GROUP BY link_id) s ON s.link_id=l.id'
            . $where . ' ORDER BY l.kind,l.id DESC LIMIT 25 OFFSET ' . (($page - 1) * 25), [$since,...$params])->fetchAll();
        $filter = ' WHERE d.day>=?' . ($id ? ' AND l.id=?' : ($kind ? ' AND l.kind=?' : ''));
        $filterParams = [$since,...($id ? [$id] : $params)];
        $join = ' FROM link_tracker_daily d JOIN link_tracker_links l ON l.id=d.link_id' . $filter;
        $summary = $db->query("SELECT COALESCE(SUM(d.clicks),0) AS clicks,COALESCE(SUM(IF(l.kind='campaign',d.clicks,0)),0) AS campaign,COALESCE(SUM(IF(l.kind='website',d.clicks,0)),0) AS website" . $join, $filterParams)->fetch();
        $daily = $db->query('SELECT d.day,SUM(d.clicks) AS clicks' . $join . ' GROUP BY d.day ORDER BY d.day', $filterParams)->fetchAll();
        $sources = $db->query('SELECT d.source,SUM(d.clicks) AS clicks' . $join . ' GROUP BY d.source ORDER BY clicks DESC', $filterParams)->fetchAll();
        $devices = $db->query('SELECT d.device,SUM(d.clicks) AS clicks' . $join . ' GROUP BY d.device ORDER BY clicks DESC', $filterParams)->fetchAll();
        return compact('days','since','kind','id','selected','rows','total','pages','page','summary','daily','sources','devices');
    }

    public static function label(array $row): string
    {
        return $row['kind'] === 'website' ? Locale::t('links.site.' . $row['label']) : $row['label'];
    }

    public static function publicUrl(string $slug): string
    {
        $root = rtrim((string)(\Conquer\Bootstrap::getConfig()['base_url'] ?? ''), '/');
        $requestHost = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
        $host = strtolower((string)parse_url('http://' . $requestHost, PHP_URL_HOST));
        $base = defined('APP_BASE') ? APP_BASE : '';
        // The website and game share a deployment. Old game base_url values must
        // not put campaign links on a retired host or send local previews there.
        if (in_array($host, ['unionofkingdoms.com','www.unionofkingdoms.com','play.unionofkingdoms.com'], true)) {
            $root = 'https://unionofkingdoms.com' . $base;
        } elseif (in_array($host, ['localhost','127.0.0.1','[::1]','::1'], true)) {
            $root = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . $requestHost . $base;
        }
        if (!filter_var($root, FILTER_VALIDATE_URL)) $root = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . (defined('APP_BASE') ? APP_BASE : '');
        return $root . '/go/' . $slug;
    }

    public static function export(Connection $db, array $input): void
    {
        $list = self::listing($db, $input);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="union-of-kingdoms-links.csv"');
        header('X-Content-Type-Options: nosniff');
        $stream = fopen('php://output', 'wb');
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, array_map(Locale::t(...), ['links.date','links.label','links.type','links.source','links.device','links.clicks']), ';', '"', '');
        // Stream aggregate rows instead of collecting the complete export in memory.
        $where = 'd.day>=?' . ($list['id'] ? ' AND l.id=?' : ($list['kind'] ? ' AND l.kind=?' : ''));
        $params = [$list['since'],...($list['id'] ? [$list['id']] : ($list['kind'] ? [$list['kind']] : []))];
        $stmt = $db->query('SELECT d.*,l.label,l.kind FROM link_tracker_daily d JOIN link_tracker_links l ON l.id=d.link_id WHERE ' . $where . ' ORDER BY d.day,l.id,d.device,d.source', $params);
        while ($row = $stmt->fetch()) {
            fputcsv($stream, array_map(static fn($value)=>AlphaWaitlistAdmin::csvCell((string)$value), [$row['day'],self::label($row),Locale::t('links.'.$row['kind']),Locale::t('links.source.'.$row['source']),Locale::t('links.device.'.$row['device']),$row['clicks']]), ';', '"', '');
        }
        fclose($stream);
    }
}
