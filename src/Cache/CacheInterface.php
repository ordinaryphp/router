<?php

declare(strict_types=1);

namespace Ordinary\Router\Cache;

use Ordinary\Router\Compiler\CompiledRoutes;

/**
 * Stores and retrieves compiled route data to avoid recompilation on every request.
 *
 * Handlers are not cached — they always live in the Router's in-memory handler array.
 * Only the regex/index dispatch data is persisted.
 */
interface CacheInterface
{
    /**
     * Load compiled routes from the cache.
     *
     * Returns null when no valid cache entry exists (cache miss or cold start).
     */
    public function load(): ?CompiledRoutes;

    /**
     * Persist compiled routes to the cache.
     */
    public function store(CompiledRoutes $routes): void;

    /**
     * Remove the cached entry, forcing recompilation on the next load.
     */
    public function invalidate(): void;
}
