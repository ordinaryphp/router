<?php

declare(strict_types=1);

namespace Ordinary\Router\Tests\Fixtures;

use Ordinary\Router\Cache\CacheInterface;
use Ordinary\Router\Compiler\CompiledRoutes;

final class InMemoryCache implements CacheInterface
{
    private ?CompiledRoutes $stored = null;

    public function load(): ?CompiledRoutes
    {
        return $this->stored;
    }

    public function store(CompiledRoutes $routes): void
    {
        $this->stored = $routes;
    }

    public function invalidate(): void
    {
        $this->stored = null;
    }
}
