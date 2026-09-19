<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require dirname(__DIR__) . '/src/Game/City/City3dImportMap.php';

use Conquer\Game\City\City3dImportMap;

function checkImportMap(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS $message\n";
}

$fixture = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'conquer-city3d-importmap-' . bin2hex(random_bytes(6));
$cityDir = $fixture . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'city3d';
mkdir($cityDir . DIRECTORY_SEPARATOR . 'vendor', 0777, true);

try {
    file_put_contents($cityDir . DIRECTORY_SEPARATOR . 'scene.js', "import { building } from './building.js?v=finished-village5';\nimport * as T from './vendor/three.module.js';\nexport { building, T };\n");
    file_put_contents($cityDir . DIRECTORY_SEPARATOR . 'building.js', "export const building = 'first';\n");
    file_put_contents($cityDir . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'three.module.js', "export const revision = 'fixture';\n");
    file_put_contents($cityDir . DIRECTORY_SEPARATOR . "quote's module.js", "export const escaped = true;\n");

    $first = City3dImportMap::build($fixture, '/conquer');
    $building = '/conquer/assets/city3d/building.js';
    $scene = '/conquer/assets/city3d/scene.js';
    checkImportMap(isset($first['imports'][$building]), 'maps unversioned root module');
    checkImportMap(
        ($first['imports'][$building . '?v=finished-village5'] ?? null) === $first['imports'][$building],
        'maps legacy relative query to the canonical module URL',
    );
    checkImportMap(
        ($first['imports']['/conquer/assets/city3d/vendor/three.module.js'] ?? null) === '/conquer/assets/city3d/vendor/three.module.js?v=city3d-' . $first['version'],
        'keeps Three.js on the same graph version',
    );
    checkImportMap(
        ($first['imports'][$scene] ?? null) === $scene . '?v=city3d-' . $first['version'],
        'gives entry modules the common content hash',
    );
    $json = City3dImportMap::json($first);
    checkImportMap(json_decode($json, true, 512, JSON_THROW_ON_ERROR)['version'] === $first['version'], 'serializes import map as valid JSON');
    checkImportMap(isset($first['imports']['/conquer/assets/city3d/quote%27s%20module.js']), 'encodes unusual filenames in module URLs');
    $safeJson = City3dImportMap::json(['imports' => ['</script>"' => '/city3d']]);
    checkImportMap(!str_contains($safeJson, '</script>') && json_decode($safeJson, true, 512, JSON_THROW_ON_ERROR)['imports']['</script>"'] === '/city3d', 'escapes import-map JSON for HTML');

    $mtime = filemtime($cityDir . DIRECTORY_SEPARATOR . 'building.js');
    file_put_contents($cityDir . DIRECTORY_SEPARATOR . 'building.js', "export const building = 'second';\n");
    touch($cityDir . DIRECTORY_SEPARATOR . 'building.js', $mtime);
    clearstatcache(true, $cityDir . DIRECTORY_SEPARATOR . 'building.js');
    $second = City3dImportMap::build($fixture, '/conquer');
    checkImportMap($second['version'] !== $first['version'], 'changes version when content changes at the same mtime');

    $vendor = $cityDir . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'three.module.js';
    $vendorMtime = filemtime($vendor);
    file_put_contents($vendor, "export const revision = 'changed fixture';\n");
    touch($vendor, $vendorMtime);
    clearstatcache(true, $vendor);
    $third = City3dImportMap::build($fixture, '/conquer');
    checkImportMap($third['version'] !== $second['version'], 'changes version when a nested city module changes at the same mtime');
} finally {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixture, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    if (is_dir($fixture)) {
        rmdir($fixture);
    }
}
