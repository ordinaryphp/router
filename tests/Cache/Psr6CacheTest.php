<?php

declare(strict_types=1);

namespace Ordinary\Router\Tests\Cache;

use Ordinary\Router\Cache\Psr6Cache;
use Ordinary\Router\Compiler\CompiledRoutes;
use Ordinary\Router\Router;
use Ordinary\Router\Tests\Fixtures\ArrayCachePool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Psr6Cache::class)]
final class Psr6CacheTest extends TestCase
{
    #[Test]
    public function loadReturnsNullWhenNotSet(): void
    {
        $cache = new Psr6Cache(new ArrayCachePool());

        $this->assertNotInstanceOf(CompiledRoutes::class, $cache->load());
    }

    #[Test]
    public function storeAndLoadRoundTrip(): void
    {
        $router = new Router();
        $router->get('/ping', 'pong');

        $pool = new ArrayCachePool();
        $cache = new Psr6Cache($pool);

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

        $pool = new ArrayCachePool();
        $cache = new Psr6Cache($pool);

        $router->compile(cache: $cache);
        $cache->invalidate();

        $this->assertNotInstanceOf(CompiledRoutes::class, $cache->load());
    }

    #[Test]
    public function loadReturnsNullWhenStoredValueIsWrongType(): void
    {
        $pool = new ArrayCachePool();
        $item = $pool->getItem('ordinary_router_compiled');
        $item->set('not-compiled-routes');

        $pool->save($item);

        $cache = new Psr6Cache($pool);

        $this->assertNotInstanceOf(CompiledRoutes::class, $cache->load());
    }

    #[Test]
    public function customCacheKeyIsUsed(): void
    {
        $pool = new ArrayCachePool();
        $cache = new Psr6Cache($pool, 'my_custom_key');

        $router = new Router();
        $router->get('/foo', 'bar');
        $router->compile(cache: $cache);

        $this->assertTrue($pool->hasItem('my_custom_key'));
        $this->assertFalse($pool->hasItem('ordinary_router_compiled'));
    }
}
