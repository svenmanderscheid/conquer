<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

define('ROOT_DIR', dirname(__DIR__));
require_once ROOT_DIR . '/src/Bootstrap.php';
\Conquer\Bootstrap::init(ROOT_DIR);

$label = trim((string) ($argv[1] ?? 'Geschlossene Alpha'));
$maxUses = filter_var($argv[2] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
$expiresAt = isset($argv[3]) && trim((string) $argv[3]) !== '' ? trim((string) $argv[3]) : null;
if ($maxUses === false) {
    fwrite(STDERR, "Nutzung: php bin/create_alpha_key.php \"Bezeichnung\" [maximale_Nutzungen] [YYYY-MM-DD HH:MM:SS]\n");
    exit(1);
}

try {
    $key = \Conquer\Auth\AlphaAccess::generate(
        \Conquer\Db\Connection::getInstance(),
        $label,
        (int) $maxUses,
        $expiresAt,
    );
    echo "Alpha-Key (wird nur jetzt angezeigt): {$key}\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
