<?php

declare(strict_types=1);

namespace Ordinary\Router\Cache;

use Ordinary\Router\Compiler\CompiledRoutes;

/**
 * No-op cache that never stores or returns anything.
 *
 * Useful in tests and long-running processes where routes are compiled once
 * at startup and held in memory for the lifetime of the process.
 */
final class NullCache implements CacheInterface
{
    public function load(): ?CompiledRoutes
    {
        return null;
    }

    public function store(CompiledRoutes $routes): void {}

    public function invalidate(): void {}
}
