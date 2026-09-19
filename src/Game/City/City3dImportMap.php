<?php
declare(strict_types=1);

namespace Conquer\Game\City;

/**
 * Produces one content-addressed module graph for the playable 3D city.
 *
 * Source modules still contain their authored relative imports, including
 * historic `?v=` values.  The browser resolves those URLs through this map to
 * a single current graph version, avoiding stale child modules and duplicate
 * Three.js/style module instances.
 */
final class City3dImportMap
{
    private function __construct() {}

    /**
     * @return array{version:string,imports:array<string,string>}
     */
    public static function build(string $rootDir, string $appBase): array
    {
        $cityDir = rtrim($rootDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'city3d';
        $sources = self::sources($cityDir);
        $version = self::contentHash($sources);
        $canonical = [];

        foreach ($sources as $relative => $source) {
            $canonical[$relative] = self::publicUrl($appBase, $relative) . '?v=city3d-' . $version;
        }

        $imports = [];
        foreach ($canonical as $relative => $url) {
            $imports[self::publicUrl($appBase, $relative)] = $url;
        }

        foreach ($sources as $relative => $source) {
            $directory = dirname($relative);
            foreach (self::relativeImports($source) as $specifier) {
                [$path, $query] = array_pad(explode('?', $specifier, 2), 2, null);
                $target = self::normalisePath(($directory === '.' ? '' : $directory . '/') . $path);
                if (!isset($canonical[$target])) {
                    continue;
                }
                $alias = self::publicUrl($appBase, $target) . ($query === null ? '' : '?' . $query);
                $imports[$alias] = $canonical[$target];
            }
        }

        ksort($imports, SORT_STRING);
        return ['version' => $version, 'imports' => $imports];
    }

    /** @param array<string,string> $map */
    public static function json(array $map): string
    {
        return json_encode(
            $map,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
        );
    }

    /** @return array<string,string> relative city3d JS path => source */
    private static function sources(string $cityDir): array
    {
        if (!is_dir($cityDir)) {
            throw new \RuntimeException('City3D asset directory is missing.');
        }

        $sources = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($cityDir, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'js') {
                continue;
            }
            $path = $file->getPathname();
            $relative = str_replace('\\', '/', substr($path, strlen($cityDir) + 1));
            $contents = file_get_contents($path);
            if ($contents === false) {
                throw new \RuntimeException('Unable to read City3D module: ' . $relative);
            }
            $sources[$relative] = $contents;
        }
        ksort($sources, SORT_STRING);
        return $sources;
    }

    /** @param array<string,string> $sources */
    private static function contentHash(array $sources): string
    {
        $hash = hash_init('sha256');
        foreach ($sources as $relative => $source) {
            hash_update($hash, $relative . "\0" . $source . "\0");
        }
        return substr(hash_final($hash), 0, 16);
    }

    /** @return list<string> */
    private static function relativeImports(string $source): array
    {
        preg_match_all(
            '~\b(?:import|export)\s+(?:[^;\n]*?\s+from\s+)?[\'\"](\.{1,2}/[^\'\"\s]+\.js(?:\?[^\'\"\s]+)?)[\'\"]~',
            $source,
            $matches,
        );
        return array_values(array_unique($matches[1] ?? []));
    }

    private static function normalisePath(string $path): string
    {
        $parts = [];
        foreach (explode('/', str_replace('\\', '/', $path)) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($parts);
                continue;
            }
            $parts[] = $part;
        }
        return implode('/', $parts);
    }

    private static function publicUrl(string $appBase, string $relative): string
    {
        $base = rtrim($appBase, '/');
        $encoded = implode('/', array_map('rawurlencode', explode('/', $relative)));
        return ($base === '' ? '' : $base) . '/assets/city3d/' . $encoded;
    }
}
