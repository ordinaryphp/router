<?php

declare(strict_types=1);

namespace Ordinary\Router\Cache;

use Ordinary\Router\Compiler\CompiledRoutes;
use Psr\Cache\CacheItemPoolInterface as Psr6Pool;

/**
 * PSR-6 (CacheItemPool) adapter.
 *
 * Requires psr/cache: composer require psr/cache
 */
final readonly class Psr6Cache implements CacheInterface
{
    public function __construct(
        private Psr6Pool $pool,
        private string $key = 'ordinary_router_compiled',
    ) {}

    public function load(): ?CompiledRoutes
    {
        $item = $this->pool->getItem($this->key);

        if (!$item->isHit()) {
            return null;
        }

        $data = $item->get();

        return $data instanceof CompiledRoutes ? $data : null;
    }

    public function store(CompiledRoutes $routes): void
    {
        $item = $this->pool->getItem($this->key);
        $item->set($routes);

        $this->pool->save($item);
    }

    public function invalidate(): void
    {
        $this->pool->deleteItem($this->key);
    }
}
