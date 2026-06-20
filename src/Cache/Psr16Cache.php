<?php

declare(strict_types=1);

namespace Ordinary\Router\Cache;

use Ordinary\Router\Compiler\CompiledRoutes;
use Psr\SimpleCache\CacheInterface as Psr16;

/**
 * PSR-16 (SimpleCache) adapter.
 *
 * Requires psr/simple-cache: composer require psr/simple-cache
 */
final readonly class Psr16Cache implements CacheInterface
{
    public function __construct(
        private Psr16 $cache,
        private string $key = 'ordinary_router_compiled',
    ) {}

    public function load(): ?CompiledRoutes
    {
        $data = $this->cache->get($this->key);

        return $data instanceof CompiledRoutes ? $data : null;
    }

    public function store(CompiledRoutes $routes): void
    {
        $this->cache->set($this->key, $routes);
    }

    public function invalidate(): void
    {
        $this->cache->delete($this->key);
    }
}
