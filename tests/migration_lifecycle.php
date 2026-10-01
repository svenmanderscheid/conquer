<?php
declare(strict_types=1);
// Execute the actual CLI migration runner in disposable roots/databases only.
if (PHP_SAPI !== 'cli') exit(1);
$sourceRoot = dirname(__DIR__);
require_once $sourceRoot.'/src/Db/MigrationSql.php';
$cfg = require $sourceRoot.'/config/database.php';
if (!in_array($cfg['host'] ?? '', ['localhost','127.0.0.1'], true)) throw new RuntimeException('Local database server required');
$admin = new PDO('mysql:host='.$cfg['host'].';port='.($cfg['port'] ?? 3306).';charset=utf8mb4', $cfg['username'], $cfg['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$names=[]; $roots=[];
function migrationCopy(string $from, string $to): void {
    if (!is_dir($to)) mkdir($to,0700,true);
    foreach (new DirectoryIterator($from) as $entry) {
        if ($entry->isDot() || $entry->isLink()) continue;
        $dest=$to.'/'.$entry->getFilename();
        if ($entry->isDir()) migrationCopy($entry->getPathname(),$dest); else copy($entry->getPathname(),$dest);
    }
}
function migrationCleanup(string $path, string $base): void {
    $resolved=realpath($path); $root=realpath($base);
    if (!$resolved || !$root || ($resolved!==$root && !str_starts_with($resolved,$root.DIRECTORY_SEPARATOR))) throw new RuntimeException('Unsafe fixture cleanup');
    foreach (new DirectoryIterator($path) as $entry) {
        if ($entry->isDot()) continue;
        if ($entry->isDir() && !$entry->isLink()) migrationCleanup($entry->getPathname(),$base); else unlink($entry->getPathname());
    }
    rmdir($path);
}
function migrationRun(string $root): string {
    $process=proc_open([PHP_BINARY,$root.'/migrations/run.php'],[0=>['pipe','r'],1=>['file',$root.'/run.log','w'],2=>['file',$root.'/run.log','a']],$pipes,$root,null,['bypass_shell'=>true]);
    if (!is_resource($process)) throw new RuntimeException('Cannot launch migrations');
    fclose($pipes[0]); $code=proc_close($process); $log=(string)file_get_contents($root.'/run.log');
    if ($code!==0) throw new RuntimeException('Migration runner failed: '.implode("\n",array_slice(explode("\n",$log),-8)));
    return $log;
}
try {
    $signatures=[];
    foreach (['fresh','upgrade'] as $mode) {
        $name='conquer_migration_test_'.bin2hex(random_bytes(6)); $names[]=$name;
        $root=sys_get_temp_dir().DIRECTORY_SEPARATOR.$name; $roots[]=$root;
        mkdir($root.'/config',0700,true); mkdir($root.'/logs',0700,true);
        migrationCopy($sourceRoot.'/src',$root.'/src'); migrationCopy($sourceRoot.'/migrations',$root.'/migrations');
        $admin->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $local=$cfg; $local['database']=$name;
        file_put_contents($root.'/config/database.php','<?php return '.var_export($local,true).';');
        file_put_contents($root.'/config/app.php',"<?php return ['env'=>'production','debug'=>false];");
        $db=new PDO('mysql:host='.$cfg['host'].';port='.($cfg['port']??3306).';dbname='.$name.';charset=utf8mb4',$cfg['username'],$cfg['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        if ($mode==='upgrade') {
            foreach (glob($root.'/migrations/*.sql') as $file) if ((int)basename($file)>102) unlink($file);
            migrationRun($root);
            $db->exec("INSERT INTO players(username,email,password_hash,gems) VALUES('MigrationSentinel','migration@test.invalid','not-a-login',321)");
            migrationCopy($sourceRoot.'/migrations',$root.'/migrations');
        }
        migrationRun($root);
        $applied=$db->query('SELECT filename FROM migrations ORDER BY filename')->fetchAll(PDO::FETCH_COLUMN);
        $expected=array_map('basename',glob($sourceRoot.'/migrations/*.sql')); sort($expected);
        if ($applied!==$expected) throw new RuntimeException('Incomplete migration inventory: '.$mode);
        if (!str_contains(migrationRun($root),'All migrations already applied')) throw new RuntimeException('Replay was not a no-op');
        if ($mode==='upgrade' && (int)$db->query("SELECT gems FROM players WHERE username='MigrationSentinel'")->fetchColumn()!==321) throw new RuntimeException('Upgrade changed existing player balance');
        // The compatibility layer must reconcile in execution order and retain
        // literal semicolons/commas, even on replay after a partially applied file.
        $probe=<<<'SQL'
        CREATE TABLE IF NOT EXISTS migration_probe(id INT PRIMARY KEY);
        ALTER TABLE migration_probe ADD COLUMN IF NOT EXISTS label VARCHAR(64) DEFAULT 'semi; comma, quote''ok',
          ADD COLUMN IF NOT EXISTS amount DECIMAL(8,2) DEFAULT 1.25;
        -- A comment containing ; must not create a statement.
        ALTER TABLE migration_probe ADD INDEX IF NOT EXISTS label_index (label);
        ALTER TABLE migration_probe DROP INDEX IF EXISTS label_index;
        ALTER TABLE migration_probe ADD INDEX IF NOT EXISTS label_index (label);
        SQL;
        \Conquer\Db\MigrationSql::apply($db,$probe);
        \Conquer\Db\MigrationSql::apply($db,$probe);
        $db->exec('INSERT INTO migration_probe(id) VALUES(1)');
        $probeRow=$db->query('SELECT label,amount FROM migration_probe')->fetch(PDO::FETCH_ASSOC);
        if ($probeRow['label']!=="semi; comma, quote'ok" || (float)$probeRow['amount']!==1.25) throw new RuntimeException('SQL literals changed');
        if (count($db->query('SHOW COLUMNS FROM migration_probe')->fetchAll())!==3) throw new RuntimeException('Additive replay changed schema');
        $db->exec('DROP TABLE migration_probe');
        $statement=$db->prepare('SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? ORDER BY TABLE_NAME,ORDINAL_POSITION');
        $statement->execute([$name]); $signatures[$mode]=$statement->fetchAll(PDO::FETCH_ASSOC);
        echo 'PASS '.$mode.': '.count($expected)." migrations; repeat is a no-op; ".count($signatures[$mode])." columns\n";
    }
    if ($signatures['fresh']!==$signatures['upgrade']) throw new RuntimeException('Fresh/upgrade schemas differ');
    echo "PASS fresh and upgrade schemas agree; existing balance preserved\n";
} finally {
    foreach ($names as $name) if (preg_match('/^conquer_migration_test_[a-f0-9]{12}$/D',$name)) $admin->exec('DROP DATABASE IF EXISTS `'.$name.'`');
    foreach ($roots as $root) if (is_dir($root)) migrationCleanup($root,$root);
}
