<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));
require ROOT_DIR.'/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
date_default_timezone_set('UTC');
$db=\Conquer\Db\Connection::init(ROOT_DIR);
$apply=in_array('--apply',$argv,true);
$result=\Conquer\Game\City\TroopTierTransition::run($db,$apply,static function(array $snapshot):void{
    $path=ROOT_DIR.'/data/balance-history/local-five-tiers-backup-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(3)).'.json';
    if(file_put_contents($path,json_encode($snapshot,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR))===false)throw new RuntimeException('Could not save transition backup.');
    echo 'Backup: '.$path."\n";
});
$counts=array_count_values(array_column($result['changes'],'table'));
echo ($result['already_applied']?'Already applied':($apply?'APPLIED':'PREVIEW')).': '.json_encode($counts)."\n";
if(!$apply&&!$result['already_applied'])echo "Run with --apply to convert higher tiers to T5 and preserve monster health percentages.\n";
