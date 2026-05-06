<?php
declare(strict_types=1);

namespace Conquer;

/**
 * File-based logger. Singleton, initialized once by Bootstrap.
 *
 * Levels (ascending severity): DEBUG < INFO < WARN < ERROR
 * Only messages at or above the configured minimum level are written.
 */
final class Logger
{
    private static ?self $instance = null;

    private const LEVELS = [
        'DEBUG' => 0,
        'INFO'  => 1,
        'WARN'  => 2,
        'ERROR' => 3,
    ];

    private readonly int $minLevel;

    private function __construct(
        private readonly string $logFile,
        string $minLevel,
    ) {
        $this->minLevel = self::LEVELS[$minLevel] ?? 0;
    }

    /**
     * Initialize the logger. Must be called once during Bootstrap.
     *
     * @param string $minLevel  DEBUG | INFO | WARN | ERROR (case-insensitive)
     */
    public static function init(string $logFile, string $minLevel = 'DEBUG'): self
    {
        self::$instance = new self($logFile, self::normalizeLevel($minLevel));
        return self::$instance;
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            throw new \RuntimeException('Logger not initialized — call Logger::init() first.');
        }
        return self::$instance;
    }

    public function debug(string $message): void
    {
        $this->write('DEBUG', $message);
    }

    public function info(string $message): void
    {
        $this->write('INFO', $message);
    }

    public function warn(string $message): void
    {
        $this->write('WARN', $message);
    }

    public function error(string $message): void
    {
        $this->write('ERROR', $message);
    }

    private function write(string $level, string $message): void
    {
        if ((self::LEVELS[$level] ?? 0) < $this->minLevel) {
            return;
        }

        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $timestamp = gmdate('Y-m-d H:i:s');
        $line = "[{$timestamp} UTC] [{$level}] {$message}" . PHP_EOL;

        file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * Normalizes level strings from config ('warning' → 'WARN', etc.)
     */
    private static function normalizeLevel(string $level): string
    {
        $upper = strtoupper($level);
        // config/app.example.php uses 'warning' — map it to our canonical 'WARN'
        if ($upper === 'WARNING') {
            return 'WARN';
        }
        return isset(self::LEVELS[$upper]) ? $upper : 'DEBUG';
    }
}
