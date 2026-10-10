<?php
declare(strict_types=1);
/** Preview/apply a temporary Wiltz start on the existing default Luxembourg world. */
if (PHP_SAPI!=='cli') { http_response_code(403); exit; }
define('ROOT_DIR',getenv('CONQUER_ROOT') ?: dirname(__DIR__));
require ROOT_DIR.'/src/Bootstrap.php'; \Conquer\Bootstrap::init(ROOT_DIR);

use Conquer\Db\Connection;
use Conquer\Game\Map\WorldPlacement;
use Conquer\Game\World\{WorldEntry,WorldMapProfile,LuxembourgGeography};

try {
    $options=getopt('',['until:','apply']);
    $date=$options['until']??'';
    $local=DateTimeImmutable::createFromFormat('!Y-m-d',(string)$date,new DateTimeZone('Europe/Luxembourg'));
    if (!$local || $local->format('Y-m-d')!==$date) throw new InvalidArgumentException('Use --until=YYYY-MM-DD (inclusive Luxembourg date); add --apply to save.');
    $end=$local->modify('+1 day')->setTimezone(new DateTimeZone('UTC'));
    if ($end->getTimestamp()<=time()) throw new InvalidArgumentException('The end date must be in the future.');
    $db=Connection::getInstance(); $worldId=WorldEntry::defaultWorld();
    if (!$worldId || !WorldMapProfile::isLuxembourg($worldId) || !WorldEntry::settings($worldId)) throw new RuntimeException('The default world must have existing Luxembourg entry settings.');
    $proposed=['spawn_canton'=>'08','spawn_x'=>170,'spawn_y'=>370,'spawn_radius'=>64,'spawn_until'=>$end->format('Y-m-d H:i:s')];
    if ((LuxembourgGeography::at(170,370)['canton_id']??null)!=='08') throw new RuntimeException('The approved start is outside Wiltz.');
    $before=WorldEntry::settings($worldId);
    if (isset($options['apply'])) {
        $migration='0139_temporary_player_spawn.sql';
        \Conquer\Db\MigrationSql::apply($db->getPdo(),(string)file_get_contents(ROOT_DIR.'/migrations/'.$migration));
        $db->execute('INSERT IGNORE INTO migrations(filename) VALUES(?)',[$migration]);
        $db->transaction(static function(Connection $db) use ($worldId,$proposed): void {
            WorldPlacement::lockWorld($db,$worldId);
            if (WorldEntry::defaultWorld()!==$worldId) throw new RuntimeException('The default world changed; retry the preview.');
            $db->execute('UPDATE world_entry_settings SET spawn_canton=?,spawn_x=?,spawn_y=?,spawn_radius=?,spawn_until=? WHERE world_id=?',array_merge(array_values($proposed),[$worldId]));
        });
    }
    echo json_encode(['applied'=>isset($options['apply']),'world_id'=>$worldId,'before'=>$before,'proposed'=>$proposed,'inclusive_end_date'=>$date,'timezone'=>'Europe/Luxembourg','after'=>WorldEntry::settings($worldId)],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
} catch (Throwable $e) { fwrite(STDERR,$e->getMessage()."\n"); exit(1); }
