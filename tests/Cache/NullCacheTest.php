<?php

declare(strict_types=1);

namespace Ordinary\Router\Tests\Cache;

use Ordinary\Router\Cache\NullCache;
use Ordinary\Router\Compiler\CompiledRoutes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(NullCache::class)]
final class NullCacheTest extends TestCase
{
    #[Test]
    public function loadAlwaysReturnsNull(): void
    {
        $cache = new NullCache();

        $this->assertNotInstanceOf(CompiledRoutes::class, $cache->load());
    }

    #[Test]
    public function storeDoesNotAffectLoad(): void
    {
        $cache = new NullCache();
        $cache->store(new CompiledRoutes([], [], [], [], []));

        $this->assertNotInstanceOf(CompiledRoutes::class, $cache->load());
    }

    #[Test]
    public function invalidateDoesNotThrow(): void
    {
        $cache = new NullCache();
        $cache->invalidate();

        $this->assertNotInstanceOf(CompiledRoutes::class, $cache->load());
    }
}
