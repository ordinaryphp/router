<?php

declare(strict_types=1);

namespace Ordinary\Router\Cache;

use Ordinary\Router\Compiler\CompiledRoutes;
use Ordinary\Router\Exception\InvalidArgumentException;

/**
 * File-based cache that writes serialized route data as a PHP file.
 *
 * The file can be warmed by opcache, making cold-start overhead minimal on FPM.
 * Cache invalidation is explicit — call invalidate() when routes change.
 */
final readonly class FileCache implements CacheInterface
{
    /**
     * @param non-empty-string $path Absolute path to the cache file
     *
     * @throws InvalidArgumentException if the directory containing $path is not writable
     */
    public function __construct(private string $path)
    {
        $dir = \dirname($path);
        if (!\is_dir($dir) || !\is_writable($dir)) {
            throw new InvalidArgumentException(
                \sprintf('Cache directory "%s" does not exist or is not writable', $dir),
            );
        }
    }

    public function load(): ?CompiledRoutes
    {
        if (!\file_exists($this->path)) {
            return null;
        }

        $data = include $this->path;

        return $data instanceof CompiledRoutes ? $data : null;
    }

    public function store(CompiledRoutes $routes): void
    {
        $serialized = \var_export(\serialize($routes), true);
        $content = '<?php return unserialize(' . $serialized . ');' . PHP_EOL;

        \file_put_contents($this->path, $content, LOCK_EX);
    }

    public function invalidate(): void
    {
        if (\file_exists($this->path)) {
            \unlink($this->path);
        }
    }
}
