<?php
declare(strict_types=1);
namespace ConquerTests;

final class FeatureDatabase
{
    private \PDO $admin;
    private string $name;
    private string $directory;
    private mixed $server=null;

    /** Optional isolated HTTP process; the router contains only test-selected handlers. */
    public function serve(string $routes, array $phpOptions = []): string
    {
        if(is_resource($this->server))throw new \RuntimeException('Fixture server already running.');
        $router="<?php declare(strict_types=1); define('ROOT_DIR',".var_export(ROOT_DIR,true)."); require ROOT_DIR.'/src/Autoloader.php'; (new \\Conquer\\Autoloader(ROOT_DIR.'/src'))->register(); date_default_timezone_set('UTC'); \\Conquer\\Db\\Connection::init(__DIR__); \\Conquer\\Logger::init(__DIR__.'/http.log'); ". $routes;
        file_put_contents($this->directory.'/router.php',$router);
        $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);
        if(!$socket)throw new \RuntimeException('No fixture HTTP port.');
        $address=stream_socket_get_name($socket,false);fclose($socket);
        $this->server=proc_open(array_merge([PHP_BINARY],$phpOptions,['-S',$address,'-t',$this->directory,$this->directory.'/router.php']),[0=>['pipe','r'],1=>['file',$this->directory.'/server.log','a'],2=>['file',$this->directory.'/server.log','a']],$pipes,$this->directory,null,['bypass_shell'=>true]);
        if(!is_resource($this->server))throw new \RuntimeException('Fixture server did not start.');
        fclose($pipes[0]);usleep(200000);return 'http://'.$address;
    }

    public function __construct()
    {
        $cfg=require ROOT_DIR.'/config/database.php';if(!in_array($cfg['host']??'',['localhost','127.0.0.1'],true))throw new \RuntimeException('Local MySQL only.');
        $source=$cfg['database'];if(!preg_match('/^[A-Za-z0-9_]+$/D',$source))throw new \RuntimeException('Invalid database.');
        $this->name='conquer_feature_test_'.bin2hex(random_bytes(6));$this->directory=sys_get_temp_dir().DIRECTORY_SEPARATOR.$this->name;
        $this->admin=new \PDO('mysql:host='.$cfg['host'].';port='.($cfg['port']??3306).';charset=utf8mb4',$cfg['username'],$cfg['password'],[\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION]);
        $this->admin->exec('CREATE DATABASE `'.$this->name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        try{
            foreach($this->admin->query('SHOW TABLES FROM `'.$source.'`')->fetchAll(\PDO::FETCH_COLUMN)as$t){if(!preg_match('/^[A-Za-z0-9_]+$/D',$t))throw new \RuntimeException('Invalid table.');$this->admin->exec('CREATE TABLE `'.$this->name.'`.`'.$t.'` LIKE `'.$source.'`.`'.$t.'`');}
            $this->admin->exec('INSERT INTO `'.$this->name.'`.worlds SELECT * FROM `'.$source.'`.worlds WHERE id=1');
            mkdir($this->directory.'/config',0700,true);$cfg['database']=$this->name;file_put_contents($this->directory.'/config/database.php',"<?php return ".var_export($cfg,true).';');
            \Conquer\Db\Connection::init($this->directory);
            // Bring the disposable schema up to the feature contracts, even while
            // the user's database has not yet received these additive migrations.
            foreach(['0083_reward_overrides.sql','0084_land_progression.sql','0085_monster_charms.sql','0086_reward_world_revisions.sql','0087_charm_compatibility.sql','0088_march_skins.sql','0089_mailbox.sql','0090_training_buildings.sql','0091_hospital_healing.sql','0092_theme_bundles.sql','0093_theme_bundle_order_once.sql','0094_building_cost_snapshot.sql','0095_epic_charm_bonus.sql','0096_private_chat.sql','0097_start_at_vip_one.sql','0098_shared_battle_reports.sql'] as $migration){
                $path=ROOT_DIR.'/migrations/'.$migration;
                if(is_file($path))\Conquer\Db\MigrationSql::apply(\Conquer\Db\Connection::getInstance()->getPdo(),(string)file_get_contents($path));
            }
            \Conquer\Db\MigrationSql::apply(\Conquer\Db\Connection::getInstance()->getPdo(), (string)file_get_contents(ROOT_DIR.'/migrations/0103_security_rate_limits.sql'));
            \Conquer\Db\MigrationSql::apply(\Conquer\Db\Connection::getInstance()->getPdo(), (string)file_get_contents(ROOT_DIR.'/migrations/0104_api_receipts_and_activity.sql'));
            \Conquer\Db\MigrationSql::apply(\Conquer\Db\Connection::getInstance()->getPdo(), (string)file_get_contents(ROOT_DIR.'/migrations/0105_admin_password_change.sql'));
            \Conquer\Db\MigrationSql::apply(\Conquer\Db\Connection::getInstance()->getPdo(), (string)file_get_contents(ROOT_DIR.'/migrations/0106_alpha_waitlist.sql'));
            \Conquer\Db\MigrationSql::apply(\Conquer\Db\Connection::getInstance()->getPdo(), (string)file_get_contents(ROOT_DIR.'/migrations/0107_bug_report_ideas_and_screenshots.sql'));
        }catch(\Throwable $e){$this->close();throw $e;}
    }
    public function close(): void
    {
        if(is_resource($this->server)){proc_terminate($this->server);proc_close($this->server);$this->server=null;}
        foreach(['router.php','http.log','server.log'] as $name){$path=$this->directory.'/'.$name;if(is_file($path))unlink($path);}
        if(preg_match('/^conquer_feature_test_[a-f0-9]{12}$/D',$this->name))$this->admin->exec('DROP DATABASE IF EXISTS `'.$this->name.'`');
        $file=$this->directory.'/config/database.php';if(is_file($file))unlink($file);if(is_dir($this->directory.'/config'))rmdir($this->directory.'/config');if(is_dir($this->directory))rmdir($this->directory);
    }
}
