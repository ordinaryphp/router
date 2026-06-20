<?php

declare(strict_types=1);

namespace Ordinary\Router\Tests\Cache;

use Ordinary\Router\Cache\Psr16Cache;
use Ordinary\Router\Compiler\CompiledRoutes;
use Ordinary\Router\Router;
use Ordinary\Router\Tests\Fixtures\ArraySimpleCache;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Psr16Cache::class)]
final class Psr16CacheTest extends TestCase
{
    #[Test]
    public function loadReturnsNullWhenNotSet(): void
    {
        $cache = new Psr16Cache(new ArraySimpleCache());

        $this->assertNotInstanceOf(CompiledRoutes::class, $cache->load());
    }

    #[Test]
    public function storeAndLoadRoundTrip(): void
    {
        $router = new Router();
        $router->get('/ping', 'pong');

        $inner = new ArraySimpleCache();
        $cache = new Psr16Cache($inner);

        $router->compile(cache: $cache);

        $loaded = $cache->load();

        $this->assertInstanceOf(CompiledRoutes::class, $loaded);
        $this->assertArrayHasKey('/ping', $loaded->staticRoutes);
    }

    #[Test]
    public function invalidateRemovesStoredRoutes(): void
    {
        $router = new Router();
        $router->get('/ping', 'pong');

        $inner = new ArraySimpleCache();
        $cache = new Psr16Cache($inner);

        $router->compile(cache: $cache);
        $cache->invalidate();

        $this->assertNotInstanceOf(CompiledRoutes::class, $cache->load());
    }

    #[Test]
    public function loadReturnsNullWhenStoredValueIsWrongType(): void
    {
        $inner = new ArraySimpleCache();
        $inner->set('ordinary_router_compiled', 'not-compiled-routes');

        $cache = new Psr16Cache($inner);

        $this->assertNotInstanceOf(CompiledRoutes::class, $cache->load());
    }

    #[Test]
    public function customCacheKeyIsUsed(): void
    {
        $inner = new ArraySimpleCache();
        $cache = new Psr16Cache($inner, 'my_custom_key');

        $router = new Router();
        $router->get('/foo', 'bar');
        $router->compile(cache: $cache);

        $this->assertTrue($inner->has('my_custom_key'));
        $this->assertFalse($inner->has('ordinary_router_compiled'));
    }
}
