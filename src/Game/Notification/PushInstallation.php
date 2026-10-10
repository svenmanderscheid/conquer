<?php
declare(strict_types=1);
namespace Conquer\Game\Notification;

use Conquer\Db\Connection;
use Conquer\Db\MigrationSql;

/** Narrow installation contract: no general migration runner and no configuration writes. */
final class PushInstallation
{
    public const MIGRATION = '0145_web_push.sql';

    /** Type, nullable, default, extra. Integer display widths are not storage types. */
    private const COLUMNS = [
        'push_subscriptions'=>[
            'id'=>['bigint unsigned',false,null,'auto_increment'],
            'player_id'=>['int',false,null,''], 'session_id'=>['bigint unsigned',false,null,''],
            'platform'=>['varchar(12)',false,'web',''], 'endpoint_hash'=>['char(64)',false,null,''],
            'endpoint'=>['varchar(2048)',false,null,''], 'public_key'=>['varchar(100)',false,null,''],
            'auth_token'=>['varchar(32)',false,null,''], 'locale'=>['varchar(8)',false,'en',''],
            'completions'=>['tinyint',false,'1',''], 'security'=>['tinyint',false,'1',''],
            'last_notification_id'=>['bigint unsigned',false,'0',''],
            'scanned_at'=>['datetime',true,null,''], 'last_test_at'=>['datetime',true,null,''],
            'created_at'=>['datetime',false,'current_timestamp',''], 'updated_at'=>['datetime',false,'current_timestamp',''],
        ],
        'push_deliveries'=>[
            'id'=>['bigint unsigned',false,null,'auto_increment'], 'subscription_id'=>['bigint unsigned',false,null,''],
            'notification_id'=>['bigint unsigned',true,null,''], 'world_id'=>['int',false,'1',''],
            'category'=>['varchar(16)',false,null,''], 'delivery_tag'=>['char(32)',false,null,''],
            'attempts'=>['tinyint unsigned',false,'0',''], 'next_attempt_at'=>['datetime',false,'current_timestamp',''],
            'completed_at'=>['datetime',true,null,''], 'outcome'=>['varchar(16)',true,null,''],
            'created_at'=>['datetime',false,'current_timestamp',''],
        ],
    ];

    private const INDEXES = [
        'push_subscriptions'=>['PRIMARY'=>[true,['id']], 'push_endpoint'=>[true,['endpoint_hash']],
            'push_player'=>[false,['player_id']], 'push_scan'=>[false,['scanned_at']], 'session_id'=>[false,['session_id']]],
        'push_deliveries'=>['PRIMARY'=>[true,['id']], 'push_notification'=>[true,['subscription_id','notification_id']],
            'push_pending'=>[false,['completed_at','next_attempt_at']]],
    ];

    private const FOREIGN_KEYS = [
        'push_subscriptions'=>['player_id'=>['players','id','CASCADE'],'session_id'=>['sessions','id','CASCADE']],
        'push_deliveries'=>['subscription_id'=>['push_subscriptions','id','CASCADE']],
    ];

    // These are notification/settlement prerequisites, not permission to install other migrations.
    private const PREREQUISITES = [
        'migrations'=>['filename'], 'players'=>['id','is_banned'], 'sessions'=>['id','player_id','expires_at'],
        'worlds'=>['id','status'], 'cities'=>['id','player_id','world_id'],
        'notifications'=>['id','player_id','type','data_json','created_at'],
        'kingdom_profiles'=>['player_id','report_build_complete','report_train_complete','report_research_complete','report_heal_complete'],
        'building_queue'=>['city_id','is_processed','finishes_at'],
        'troop_queue'=>['city_id','is_processed','finishes_at'],
        'research_queue'=>['player_id','world_id','is_processed','finishes_at'],
        'hospital_wounded'=>['city_id','healing_count','healing_ends_at'],
    ];

    public static function inspect(): array
    {
        $db=Connection::getInstance();
        $sql=self::migrationSql();
        $tables=$db->query('SELECT TABLE_NAME,ENGINE,TABLE_COLLATION FROM information_schema.tables WHERE table_schema=DATABASE()')->fetchAll();
        $tableMap=array_column($tables,null,'TABLE_NAME');
        $columns=$db->query('SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA,COLLATION_NAME FROM information_schema.columns WHERE table_schema=DATABASE()')->fetchAll();
        $columnMap=[];
        foreach($columns as $column)$columnMap[$column['TABLE_NAME']][$column['COLUMN_NAME']]=$column;
        $missing=[];
        foreach(self::PREREQUISITES as $table=>$names)foreach($names as $name)if(!isset($columnMap[$table][$name]))$missing[]=$table.'.'.$name;
        $schema=[];$drift=[];
        foreach(self::COLUMNS as $table=>$expected){
            $schema[$table]=isset($tableMap[$table]);
            if(!$schema[$table])continue;
            if(strtolower((string)$tableMap[$table]['ENGINE'])!=='innodb'||$tableMap[$table]['TABLE_COLLATION']!=='utf8mb4_unicode_ci')$drift[]=$table.':engine_or_collation';
            $actual=$columnMap[$table]??[];
            foreach(array_diff(array_keys($actual),array_keys($expected))as$name)$drift[]=$table.'.'.$name.':unexpected_column';
            foreach($expected as $name=>[$type,$nullable,$default,$extra]){
                if(!isset($actual[$name])){$drift[]=$table.'.'.$name.':missing_column';continue;}
                $column=$actual[$name];
                $actualType=preg_replace('/\b(tinyint|smallint|mediumint|int|bigint)\(\d+\)/','$1',strtolower($column['COLUMN_TYPE']));
                $actualExtra=trim(str_replace('default_generated','',strtolower($column['EXTRA'])));
                if($actualType!==$type||($column['IS_NULLABLE']==='YES')!==$nullable||self::defaultValue($column['COLUMN_DEFAULT'])!==$default||$actualExtra!==$extra)$drift[]=$table.'.'.$name.':definition';
                if($column['COLLATION_NAME']!==null){
                    $collation=in_array($name,['endpoint_hash','delivery_tag'],true)?'ascii_bin':'utf8mb4_unicode_ci';
                    if($column['COLLATION_NAME']!==$collation)$drift[]=$table.'.'.$name.':collation';
                }
            }
            $indexRows=$db->query('SELECT INDEX_NAME,NON_UNIQUE,COLUMN_NAME,SUB_PART FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=? ORDER BY INDEX_NAME,SEQ_IN_INDEX',[$table])->fetchAll();
            $indexes=[];
            foreach($indexRows as $index){$key=$index['INDEX_NAME'];$indexes[$key][0]=!(bool)$index['NON_UNIQUE'];$indexes[$key][1][]=$index['COLUMN_NAME'];if($index['SUB_PART']!==null)$drift[]=$table.':partial_index:'.$key;}
            if(count($indexes)!==count(self::INDEXES[$table]))$drift[]=$table.':index_count';
            foreach(self::INDEXES[$table]as$name=>$expectedIndex)if(($indexes[$name]??null)!==$expectedIndex)$drift[]=$table.':index:'.$name;
            // Keep both metadata reads explicitly scoped; joining information_schema
            // catalogs can scan unrelated schemas on MariaDB/shared hosting.
            $fkRows=$db->query('SELECT CONSTRAINT_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.key_column_usage WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_NAME IS NOT NULL',[$table])->fetchAll();
            $rules=$db->query('SELECT CONSTRAINT_NAME,DELETE_RULE,UPDATE_RULE FROM information_schema.referential_constraints WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table])->fetchAll();
            $ruleMap=array_column($rules,null,'CONSTRAINT_NAME');
            $fks=[];foreach($fkRows as$fk){$rule=$ruleMap[$fk['CONSTRAINT_NAME']]??[];$fks[$fk['COLUMN_NAME']]=[$fk['REFERENCED_TABLE_NAME'],$fk['REFERENCED_COLUMN_NAME'],$rule['DELETE_RULE']??''];if(!in_array($rule['UPDATE_RULE']??'',['RESTRICT','NO ACTION'],true))$drift[]=$table.':foreign_key_update:'.$fk['COLUMN_NAME'];}
            if(count($fkRows)!==count(self::FOREIGN_KEYS[$table]))$drift[]=$table.':foreign_key_count';
            foreach(self::FOREIGN_KEYS[$table]as$name=>$expectedFk)if(($fks[$name]??null)!==$expectedFk)$drift[]=$table.':foreign_key:'.$name;
        }
        $recorded=isset($columnMap['migrations']['filename'])&&(bool)$db->query('SELECT 1 FROM migrations WHERE filename=?',[self::MIGRATION])->fetchColumn();
        $extensions=[];foreach(['pdo_mysql','curl','openssl','mbstring']as$extension)$extensions[$extension]=extension_loaded($extension);
        $runtime=PHP_VERSION_ID>=80200&&!in_array(false,$extensions,true)&&PushConfig::dependencies();
        $complete=!in_array(false,$schema,true)&&$drift===[];
        return ['database'=>(string)$db->query('SELECT DATABASE()')->fetchColumn(),
            'migration'=>self::MIGRATION,'migration_sha256'=>hash('sha256',$sql),'recorded'=>$recorded,
            'runtime'=>['php'=>PHP_VERSION,'extensions'=>$extensions,'composer_runtime'=>PushConfig::dependencies(),
                'composer_lock_sha256'=>is_file(dirname(__DIR__,3).'/composer.lock')?hash_file('sha256',dirname(__DIR__,3).'/composer.lock'):null],
            'tables'=>$schema,'schema_drift'=>array_values(array_unique($drift)),'missing_prerequisites'=>$missing,
            'can_apply'=>$missing===[]&&$drift===[]&&!$db->getPdo()->inTransaction(),
            'ready'=>$runtime&&$complete&&$recorded&&$missing===[],
            'scope'=>'Only 0145; no other migrations, game state, private keys, config or scheduler changes.'];
    }

    public static function apply(): array
    {
        $db=Connection::getInstance();
        if($db->getPdo()->inTransaction())throw new \RuntimeException('Push installation requires a connection outside any transaction.');
        $lock='uok-install-push-'.substr(hash('sha256',(string)$db->query('SELECT DATABASE()')->fetchColumn()),0,32);
        if((int)$db->query('SELECT GET_LOCK(?,10)',[$lock])->fetchColumn()!==1)throw new \RuntimeException('Push installation is already running.');
        try{
            $before=self::inspect();
            if(!$before['can_apply'])throw new \RuntimeException('Push installation stopped: inspect schema drift and missing prerequisites.');
            // MySQL DDL commits implicitly. Both statements are additive and repeatable;
            // partial compatible installation is verified before a safe retry.
            MigrationSql::apply($db->getPdo(),self::migrationSql());
            $after=self::inspect();
            if(in_array(false,$after['tables'],true)||$after['schema_drift']!==[])throw new \RuntimeException('Push schema verification failed; migration was not recorded.');
            $db->transaction(static fn()=> $db->execute('INSERT IGNORE INTO migrations(filename) VALUES(?)',[self::MIGRATION]));
            return self::inspect()+['applied'=>true,'previously_recorded'=>$before['recorded']];
        }finally{$db->query('SELECT RELEASE_LOCK(?)',[$lock]);}
    }

    private static function defaultValue(mixed $value): ?string
    {
        if($value===null||strtoupper((string)$value)==='NULL')return null;
        $value=trim((string)$value,"'");
        return str_starts_with(strtolower($value),'current_timestamp')?'current_timestamp':$value;
    }

    private static function migrationSql(): string
    {
        $sql=file_get_contents(dirname(__DIR__,3).'/migrations/'.self::MIGRATION);
        if($sql===false)throw new \RuntimeException('Push migration file is missing.');
        $statements=array_values(array_filter(array_map('trim',explode(';',preg_replace('/--[^\r\n]*/','',$sql)))));
        $expected=['push_subscriptions','push_deliveries'];
        if(count($statements)!==2)throw new \RuntimeException('Push migration contains unexpected statements.');
        foreach($statements as$i=>$statement)if(!preg_match('/^CREATE TABLE IF NOT EXISTS '. $expected[$i] .' \([\s\S]+\) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci$/D',$statement))throw new \RuntimeException('Push migration scope is invalid.');
        return $sql;
    }
}
