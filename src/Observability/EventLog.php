<?php
declare(strict_types=1);
namespace Conquer\Observability;

use Conquer\Db\Connection;

/** Best-effort, rollback-independent diagnostics. Observability may never break the game. */
final class EventLog
{
    private static ?string $root = null;
    private static ?string $directory = null;
    private static ?\PDO $sink = null;
    private static bool $unavailable = false;
    private static bool $writing = false;
    private static string $release = '';
    private static ?\WeakMap $observedExceptions = null;

    public static function init(string $root, string $directory, string $release = ''): void
    {
        self::$root = $root; self::$directory = $directory;
        self::$release = SafeData::token($release); self::$sink = null; self::$unavailable = false;
    }

    public static function release(): string { return self::$release; }

    public static function exception(\Throwable $error, string $source, string $code = 'CAUGHT_EXCEPTION'): bool
    {
        self::$observedExceptions ??= new \WeakMap();
        if(isset(self::$observedExceptions[$error]))return true;
        self::$observedExceptions[$error]=true;
        $file=str_replace('\\','/',$error->getFile());
        $line=$error->getLine();
        if($error instanceof \PDOException){
            foreach($error->getTrace() as $frame){
                $candidate=str_replace('\\','/',(string)($frame['file']??''));
                if($candidate!=='' && !str_ends_with($candidate,'/src/Db/Connection.php')){$file=$candidate;$line=(int)($frame['line']??0);break;}
            }
        }
        $root=defined('ROOT_DIR')?str_replace('\\','/',ROOT_DIR):'';
        $file=$root!=='' && str_starts_with($file,$root.'/')?'/'.substr($file,strlen($root)+1):'';
        $constraint=$error instanceof \PDOException && (string)$error->getCode()==='23000';
        return self::record(['category'=>$constraint?'system':'error','severity'=>$constraint?'warning':'error',
            'code'=>$constraint?'DATABASE_CONSTRAINT':$code,'message'=>$constraint?'Database constraint rejected an operation':'Server operation failed',
            'outcome'=>$constraint?'rejected':'failed','context'=>['source'=>$source,'exception_class'=>$error::class,
                'exception_code'=>$error->getCode(),'file'=>$file,'line'=>$line]]);
    }

    /** Returns false when only the local fallback (or no sink) was available. */
    public static function record(array $event): bool
    {
        if (self::$writing) return false;
        self::$writing = true;
        try {
            $context = SafeData::context(is_array($event['context'] ?? null) ? $event['context'] : []);
            $category = in_array($event['category'] ?? '', ['action','error','connection','reward','system'], true) ? $event['category'] : 'system';
            $severity = in_array($event['severity'] ?? '', ['info','warning','error','critical'], true) ? $event['severity'] : 'info';
            $origin = ($event['origin'] ?? 'server') === 'client' ? 'client' : 'server';
            $code = SafeData::token($event['code'] ?? 'UNSPECIFIED', 96);
            $route = SafeData::route($event['route'] ?? RequestTrace::route());
            $player = max(0, (int)($event['player_id'] ?? RequestTrace::player()));
            $world = max(0, (int)($event['world_id'] ?? RequestTrace::world()));
            $time = microtime(true);
            if ($origin === 'client' && is_numeric($event['occurred_at'] ?? null)) {
                // Client clocks are untrusted; preserve at most the bounded queue's 24-hour window.
                $time = max($time - 86400, min($time, (float)$event['occurred_at']));
            }
            $group = hash('sha256', implode('|', [$category,$origin,$code,preg_replace('~/\d+(?=/|$)~', '/:id', $route),
                $context['action'] ?? '',$context['file'] ?? '',$context['line'] ?? '',$context['source_type'] ?? '',$context['source']??'']));
            $row = [
                'event_uid'=>SafeData::token($event['event_uid'] ?? bin2hex(random_bytes(16))),
                'category'=>$category,'severity'=>$severity,'origin'=>$origin,'code'=>$code,
                'message'=>SafeData::message((string)($event['message'] ?? $code)),
                'outcome'=>SafeData::token($event['outcome'] ?? 'observed',24),
                'occurred_at'=>gmdate('Y-m-d H:i:s',(int)$time).sprintf('.%03d',(int)(($time-floor($time))*1000)),
                'player_id'=>$player ?: null,'world_id'=>$world ?: null,
                'request_id'=>SafeData::token($event['request_id'] ?? RequestTrace::id()),
                'operation_id'=>SafeData::token($event['operation_id'] ?? RequestTrace::operation()),
                'release_id'=>SafeData::token($event['release_id'] ?? self::$release),
                'route'=>$route,'duration_ms'=>isset($event['duration_ms']) ? min(86400000,max(0,(int)$event['duration_ms'])) : null,
                'context_json'=>json_encode($context, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR),
                'group_hash'=>$group,
            ];
            try {
                $db = self::sink();
                $columns = implode(',',array_keys($row));
                $stmt = $db->prepare('INSERT IGNORE INTO operational_events ('.$columns.') VALUES ('.implode(',',array_fill(0,count($row),'?')).')');
                $stmt->execute(array_values($row));
                return true;
            } catch (\Throwable) {
                self::$unavailable = true;
                self::fallback($row);
                return false;
            }
        } catch (\Throwable) { return false; }
        finally { self::$writing = false; }
    }

    private static function sink(): \PDO
    {
        if (self::$unavailable) throw new \RuntimeException('Diagnostic sink unavailable');
        if (self::$sink !== null) return self::$sink;
        // Service fixtures without Bootstrap may use their isolated existing connection only when safe.
        if (self::$root === null) {
            $pdo = Connection::getInstance()->getPdo();
            if ($pdo->inTransaction()) throw new \RuntimeException('Independent diagnostic sink not configured');
            return $pdo;
        }
        if(!is_file(self::$root.'/config/database.php'))throw new \RuntimeException('Diagnostic database configuration unavailable');
        $cfg = require self::$root.'/config/database.php';
        $dsn = sprintf('%s:host=%s;port=%d;dbname=%s;charset=%s',$cfg['driver']??'mysql',$cfg['host']??'127.0.0.1',$cfg['port']??3306,$cfg['database']??'',$cfg['charset']??'utf8mb4');
        $pdo = new \PDO($dsn,$cfg['username']??'',$cfg['password']??'',[
            \PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION,\PDO::ATTR_DEFAULT_FETCH_MODE=>\PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES=>false,\PDO::ATTR_TIMEOUT=>1,
        ]);
        // Refuse accidental fixture/live cross-writes if a caller configured the wrong root.
        if (Connection::isInitialized() && $pdo->query('SELECT DATABASE()')->fetchColumn() !== Connection::getInstance()->query('SELECT DATABASE()')->fetchColumn()) {
            throw new \RuntimeException('Diagnostic database mismatch');
        }
        $pdo->exec("SET time_zone = '+00:00'");
        $pdo->exec('SET SESSION innodb_lock_wait_timeout = 1');
        return self::$sink = $pdo;
    }

    private static function fallback(array $row): void
    {
        $directory = self::$directory ?? sys_get_temp_dir().'/conquer-observability';
        if (!is_dir($directory) && !@mkdir($directory,0700,true) && !is_dir($directory)) return;
        // A bounded daily spool makes unavailable telemetry explicit without exhausting the disk.
        $file = $directory.'/operational-fallback-'.gmdate('Y-m-d').'.ndjson';
        $handle = @fopen($file,'ab');
        if (!$handle) return;
        try {
            if (!flock($handle,LOCK_EX)) return;
            $stat = fstat($handle);
            if (($stat['size'] ?? 0) < 10*1024*1024) {
                fwrite($handle,json_encode(['sink'=>'file_only','event'=>$row],JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES)."\n");
            }
            flock($handle,LOCK_UN);
        } finally { fclose($handle); }
    }

    /** No log contents exposed; lets administrators see an indexing gap even after recovery. */
    public static function fallbackStatus(): array
    {
        $status=['files'=>0,'bytes'=>0,'latest'=>null];
        try{
            $directory=self::$directory??sys_get_temp_dir().'/conquer-observability';
            foreach(glob($directory.'/operational-fallback-????-??-??.ndjson')?:[] as $file){
                if(!is_file($file))continue;
                $status['files']++;$status['bytes']+=(int)filesize($file);
                $modified=(int)filemtime($file);
                if($status['latest']===null || $modified>strtotime($status['latest'].' UTC'))$status['latest']=gmdate('Y-m-d H:i:s',$modified);
            }
        }catch(\Throwable){}
        return $status;
    }

    /** Indexed bounded retention job, explicitly invoked by the maintenance command. */
    public static function prune(int $days = 30, int $limit = 1000): int
    {
        $days = min(365,max(7,$days)); $limit = min(10000,max(1,$limit));
        return Connection::getInstance()->execute('DELETE FROM operational_events WHERE received_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL '.$days.' DAY) LIMIT '.$limit);
    }
}
