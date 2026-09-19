<?php
declare(strict_types=1);

// Isolated orchestration regression: no real database, accounts or saved cities.
namespace Conquer\Db {
    final class Connection {
        public bool $active = false;
        public array $events = [];
        public function getPdo(): self { return $this; }
        public function inTransaction(): bool { return $this->active; }
        public function transaction(callable $fn): mixed {
            if ($this->active) { throw new \RuntimeException('Nested transaction'); }
            $this->active = true; $this->events[] = 'begin';
            try { $result = $fn($this); $this->events[] = 'commit'; return $result; }
            catch (\Throwable $e) { $this->events[] = 'rollback'; throw $e; }
            finally { $this->active = false; }
        }
        public function execute(string $sql, array $params = []): int {
            $this->events[] = [$sql, $params]; return 1;
        }
        public function lastInsertId(): int { return 9; }
        public function query(string $sql, array $params = []): object {
            return new class($sql) {
                public function __construct(private string $sql) {}
                public function fetchColumn(): int { return 1; }
                public function fetch(): array {
                    return ['id'=>9, 'is_hidden'=>1, 'wall_hp_current'=>0, 'wall_hp_max'=>5000];
                }
            };
        }
        public static ?self $instance = null;
        public static function getInstance(): self { return self::$instance; }
    }
}
namespace Conquer\Game\Map {
    final class WorldPlacement {
        public static bool $available = true;
        public static ?array $near = null;
        public static array $candidates = [];
        public static function lockWorld(\Conquer\Db\Connection $db, int $id): int {
            if (!$db->active) { throw new \RuntimeException('Placement outside transaction'); }
            $db->events[] = 'lock'; return 256;
        }
        public static function canPlace(\Conquer\Db\Connection $db, int $world, string $kind, int $x, int $y, ?int $ignore = null): bool {
            if (!$db->active || !in_array('lock', $db->events, true)) { throw new \RuntimeException('Missing placement lock'); }
            self::$candidates[] = [$world, $kind, $x, $y, $ignore]; return self::$available;
        }
        public static function findNear(\Conquer\Db\Connection $db, int $world, string $kind, int $x, int $y, ?int $ignore = null, int $radius = 24): ?array {
            return self::$near;
        }
    }
}
namespace Conquer\Game\City { final class CityState { public const BUILDING_CODES = ['castle', 'wall']; } }
namespace Conquer\Game\Notification { final class NotificationService { public static function push(int $player, string $kind, array $data): void {} } }
namespace Conquer { final class Logger { public static function getInstance(): self { return new self; } public function error(string $message): void {} public function info(string $message): void {} } }
namespace {
    if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
    require dirname(__DIR__) . '/src/Auth/OAuth.php';
    require dirname(__DIR__) . '/src/Game/March/MarchTick.php';
    use Conquer\Db\Connection;
    use Conquer\Game\Map\WorldPlacement;
    use Conquer\Auth\OAuth;
    function check(bool $ok, string $label): void {
        if (!$ok) { throw new RuntimeException($label); }
        echo "PASS $label\n";
    }
    function writes(Connection $db): array { return array_values(array_filter($db->events, 'is_array')); }
    $db = new Connection;
    OAuth::createDefaultCity($db, 7, 'Fixture');
    check($db->events[0] === 'begin' && $db->events[1] === 'lock' && end($db->events) === 'commit', 'standalone creation holds world lock through commit');
    check(count(writes($db)) === 3, 'city and buildings created together');
    $db = new Connection; $db->active = true;
    OAuth::createDefaultCity($db, 7, 'Fixture');
    check(!in_array('begin', $db->events, true) && !in_array('commit', $db->events, true), 'existing registration transaction remains owned by caller');

    WorldPlacement::$available = false;
    $db = new Connection;
    try { OAuth::createDefaultCity($db, 7, 'Fixture'); throw new RuntimeException('Expected rejection'); }
    catch (RuntimeException $e) { check(str_contains($e->getMessage(), 'footprint'), 'creation rejects a full or unsuitable map'); }
    check(writes($db) === [] && end($db->events) === 'rollback', 'failed placement creates no partial city');

    WorldPlacement::$near = [23, 31];
    $db = new Connection;
    OAuth::createDefaultCity($db, 7, 'Fixture');
    check(array_slice(writes($db)[0][1], 2) === [23, 31], 'creation uses checked search destination after random candidates fail');

    WorldPlacement::$near = null;
    $db = Connection::$instance = new Connection;
    $restore = new ReflectionMethod(OAuth::class, 'restoreHiddenCityOnLogin');
    try { $restore->invoke(new OAuth([]), 7); throw new RuntimeException('Expected rejection'); }
    catch (RuntimeException $e) { check(str_contains($e->getMessage(), 'footprint'), 'hidden city requires a valid new destination'); }
    check(writes($db) === [] && end($db->events) === 'rollback', 'failed restore keeps both player and city hidden');

    $wall = new ReflectionMethod(Conquer\Game\March\MarchTick::class, 'checkWallDestroyed');
    $db = new Connection; $wall->invoke(null, $db, 9, 7);
    check(writes($db) === [] && end($db->events) === 'rollback', 'failed wall teleport leaves city and wall unchanged');

    WorldPlacement::$available = true; WorldPlacement::$candidates = [];
    $db = new Connection; $wall->invoke(null, $db, 9, 7);
    $candidate = WorldPlacement::$candidates[0];
    check($candidate[0] === 1 && $candidate[1] === 'city' && $candidate[4] === 9, 'wall teleport checks city footprint while ignoring only itself');
    check($candidate[2] >= 1 && $candidate[2] <= 253 && $candidate[3] >= 1 && $candidate[3] <= 253, 'wall teleport keeps the complete 4x4 within actual 256-tile map bounds');
    check(count(writes($db)) === 1 && end($db->events) === 'commit', 'valid teleport updates destination atomically');
}
