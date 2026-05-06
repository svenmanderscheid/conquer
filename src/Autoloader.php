<?php
declare(strict_types=1);

namespace Conquer;

/**
 * PSR-4-style autoloader for the Conquer\ namespace.
 *
 * Maps Conquer\Foo\Bar → <srcDir>/Foo/Bar.php
 */
final class Autoloader
{
    private const PREFIX = 'Conquer\\';

    public function __construct(
        private readonly string $srcDir,
    ) {}

    public function register(): void
    {
        spl_autoload_register([$this, 'load']);
    }

    public function load(string $class): void
    {
        if (!str_starts_with($class, self::PREFIX)) {
            return;
        }

        $relative = substr($class, strlen(self::PREFIX));
        $file = $this->srcDir . DIRECTORY_SEPARATOR
            . str_replace('\\', DIRECTORY_SEPARATOR, $relative)
            . '.php';

        if (is_file($file)) {
            require $file;
        }
    }
}
