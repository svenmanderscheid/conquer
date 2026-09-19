<?php
declare(strict_types=1);
namespace Conquer\Db;

/** MySQL 8 and MariaDB portability for resumable additive column migrations. */
final class MigrationSql
{
    public static function apply(\PDO $pdo,string $sql): void
    {
        $sql=preg_replace_callback('/ALTER\s+TABLE\s+([a-zA-Z0-9_]+)\s+ADD\s+((?:UNIQUE\s+)?(?:KEY|INDEX))\s+IF\s+NOT\s+EXISTS\s+([a-zA-Z0-9_]+)\s+(\([^;]+);/i',static function($m)use($pdo):string{
            $indexes=$pdo->query('SHOW INDEX FROM `'.$m[1].'`')->fetchAll(\PDO::FETCH_ASSOC);
            foreach($indexes as $index)if($index['Key_name']===$m[3])return '';
            return 'ALTER TABLE `'.$m[1].'` ADD '.$m[2].' `'.$m[3].'` '.$m[4].';';
        },$sql);
        $sql=preg_replace_callback('/ALTER\s+TABLE\s+([a-zA-Z0-9_]+)\s+DROP\s+INDEX\s+IF\s+EXISTS\s+([a-zA-Z0-9_]+)\s*;/i',static function($m)use($pdo):string{
            $indexes=$pdo->query('SHOW INDEX FROM `'.$m[1].'`')->fetchAll(\PDO::FETCH_ASSOC);
            foreach($indexes as $index)if($index['Key_name']===$m[2])return 'ALTER TABLE `'.$m[1].'` DROP INDEX `'.$m[2].'`;';
            return '';
        },$sql);
        $sql=preg_replace_callback('/ALTER\s+TABLE\s+([a-zA-Z0-9_]+)\s+ADD\s+COLUMN\s+IF\s+NOT\s+EXISTS\s+([a-zA-Z0-9_]+)\s+([^;]+);/i',static function($m)use($pdo):string{
            $columns=$pdo->query('SHOW COLUMNS FROM `'.$m[1].'`')->fetchAll(\PDO::FETCH_COLUMN);
            return in_array($m[2],$columns,true)?'':'ALTER TABLE `'.$m[1].'` ADD COLUMN `'.$m[2].'` '.$m[3].';';
        },$sql);
        if(trim($sql)!=='')$pdo->exec($sql);
    }
}
