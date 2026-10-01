<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/src/Autoloader.php';
(new \Conquer\Autoloader(dirname(__DIR__) . '/src'))->register();

use Conquer\Discord\{BotConfig, InteractionProtocol};

// Preview is safe without credentials and never contacts Discord.
if (!in_array('--apply', $argv, true)) {
    echo json_encode(InteractionProtocol::commands(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), "\n";
    echo "Preview only. Configure config/discord.php and use --apply --guild=SERVER_ID to register.\n";
    exit;
}
$config = BotConfig::load();
$guild = '';
foreach ($argv as $argument) if (str_starts_with($argument, '--guild=')) $guild = substr($argument, 8);
if (!BotConfig::enabled($config) || !in_array($guild, $config['guild_ids'], true) || !is_string($config['bot_token'] ?? null) || $config['bot_token'] === '') {
    fwrite(STDERR, "Enabled configuration, bot token and an allowlisted --guild are required.\n"); exit(1);
}
if (!function_exists('curl_init')) { fwrite(STDERR, "PHP cURL is required.\n"); exit(1); }
foreach (InteractionProtocol::commands() as $command) {
    $curl = curl_init('https://discord.com/api/v10/applications/' . $config['application_id'] . '/guilds/' . $guild . '/commands');
    curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['Authorization: Bot ' . $config['bot_token'], 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($command, JSON_THROW_ON_ERROR)]);
    $body = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    if ($body === false || !in_array($status, [200, 201], true)) {
        fwrite(STDERR, 'Registration failed for /' . $command['name'] . ' (HTTP ' . $status . "). Check app ownership, guild installation and rate limits.\n"); exit(1);
    }
    echo 'Registered /' . $command['name'] . "\n";
}
