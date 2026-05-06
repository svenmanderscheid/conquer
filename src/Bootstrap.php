<?php
declare(strict_types=1);

namespace Conquer;

/**
 * Application bootstrap.
 *
 * Initialization order:
 *   1. Load config/app.php (optional — falls back to safe defaults)
 *   2. Configure PHP error reporting
 *   3. Register Autoloader
 *   4. Initialize Logger
 *   5. Register error / exception handlers
 *   6. Initialize DB connection (non-fatal if config/database.php is missing)
 */
final class Bootstrap
{
    private static bool $initialized = false;

    // Static-only class — no instantiation.
    private function __construct() {}

    public static function init(string $rootDir): void
    {
        if (self::$initialized) {
            return;
        }

        // 1. Load config (Autoloader not yet available — plain require)
        $config = self::loadConfig($rootDir);

        // 2. Configure PHP
        $env = $config['env'] ?? 'production';
        ini_set('display_errors', $env === 'development' ? '1' : '0');
        error_reporting(E_ALL);
        date_default_timezone_set('UTC');

        // 3. Register Autoloader
        // Logger.php and Autoloader.php are required manually here because
        // the autoloader itself is not yet active at this point.
        require_once $rootDir . '/src/Autoloader.php';
        require_once $rootDir . '/src/Logger.php';

        $autoloader = new Autoloader($rootDir . '/src');
        $autoloader->register();

        // 4. Initialize Logger
        $logFile  = ($config['paths']['logs'] ?? $rootDir . '/logs') . '/app.log';
        $logLevel = $config['log_level'] ?? ($env === 'development' ? 'DEBUG' : 'INFO');
        Logger::init($logFile, $logLevel);

        // 5. Register error / exception handlers
        self::registerHandlers($env);

        // 6. Initialize DB connection (optional — site stays up without it)
        self::initDb($rootDir);

        self::$initialized = true;
        Logger::getInstance()->info('Bootstrap initialized [env=' . $env . ']');
    }

    // -------------------------------------------------------------------------

    private static function initDb(string $rootDir): void
    {
        try {
            \Conquer\Db\Connection::init($rootDir);
            Logger::getInstance()->debug('DB connection established');
        } catch (\RuntimeException $e) {
            // config/database.php missing — expected in fresh dev setups
            Logger::getInstance()->warn('DB unavailable: ' . $e->getMessage());
        } catch (\PDOException $e) {
            Logger::getInstance()->error('DB connection failed: ' . $e->getMessage());
        }
    }

    private static function loadConfig(string $rootDir): array
    {
        $file = $rootDir . '/config/app.php';
        if (is_file($file)) {
            /** @var array<string, mixed> $cfg */
            $cfg = require $file;
            return is_array($cfg) ? $cfg : [];
        }
        return [];
    }

    private static function registerHandlers(string $env): void
    {
        set_error_handler(static function (
            int $errno,
            string $errstr,
            string $errfile,
            int $errline,
        ): bool {
            // Respect the @ suppress operator
            if (!(error_reporting() & $errno)) {
                return false;
            }

            $logger  = Logger::getInstance();
            $message = "PHP Error [{$errno}]: {$errstr} in {$errfile}:{$errline}";

            match (true) {
                in_array($errno, [E_ERROR, E_USER_ERROR, E_PARSE, E_COMPILE_ERROR], true)
                    => $logger->error($message),
                in_array($errno, [E_WARNING, E_USER_WARNING, E_CORE_WARNING, E_COMPILE_WARNING], true)
                    => $logger->warn($message),
                default
                    => $logger->debug($message),
            };

            // Do NOT suppress — let PHP handle display based on display_errors
            return false;
        });

        set_exception_handler(static function (\Throwable $e) use ($env): void {
            Logger::getInstance()->error(
                'Uncaught ' . $e::class . ': ' . $e->getMessage()
                    . ' in ' . $e->getFile() . ':' . $e->getLine()
                    . PHP_EOL . $e->getTraceAsString(),
            );

            if (!headers_sent()) {
                http_response_code(500);
            }

            if ($env === 'development') {
                echo '<pre style="background:#1e1e1e;color:#f8f8f2;padding:1rem;font-family:monospace">';
                echo htmlspecialchars(
                    $e::class . ': ' . $e->getMessage()
                        . ' in ' . $e->getFile() . ':' . $e->getLine()
                        . PHP_EOL . $e->getTraceAsString(),
                    ENT_QUOTES | ENT_SUBSTITUTE,
                );
                echo '</pre>';
            } else {
                echo '<!DOCTYPE html><html lang="en"><body>'
                    . '<h1>500 — Internal Server Error</h1>'
                    . '<p>Something went wrong. Please try again later.</p>'
                    . '</body></html>';
            }
        });
    }
}
