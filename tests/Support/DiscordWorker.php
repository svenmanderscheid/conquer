<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
define('ROOT_DIR', dirname(__DIR__, 2));
require ROOT_DIR . '/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR . '/src'))->register();
date_default_timezone_set('UTC');
\Conquer\Db\Connection::init($argv[1]);
$db = \Conquer\Db\Connection::getInstance();
if (!preg_match('/^conquer_feature_test_[a-f0-9]{12}$/D', (string)$db->query('SELECT DATABASE()')->fetchColumn())) throw new RuntimeException('Disposable fixture required.');
$db->execute('SET SESSION innodb_lock_wait_timeout=1');
\Conquer\Logger::init($argv[1] . '/http.log');
$input = json_decode(stream_get_contents(STDIN), true, 32, JSON_THROW_ON_ERROR);
echo json_encode(\Conquer\Discord\MinigameService::handle($input['interaction'], $input['config']), JSON_THROW_ON_ERROR);
