<?php

declare(strict_types=1);

namespace Ordinary\Router\Tests\Cache;

use Ordinary\Router\Cache\FileCache;
use Ordinary\Router\Compiler\CompiledRoutes;
use Ordinary\Router\Compiler\RouteCompiler;
use Ordinary\Router\Exception\InvalidArgumentException;
use Ordinary\Router\Router;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FileCache::class)]
final class FileCacheTest extends TestCase
{
    /** @var non-empty-string */
    private string $cacheFile;

    protected function setUp(): void
    {
        $this->cacheFile = \sys_get_temp_dir() . '/ordinary_router_test_' . \uniqid() . '.php';
    }

    protected function tearDown(): void
    {
        if (\file_exists($this->cacheFile)) {
            \unlink($this->cacheFile);
        }
    }

    #[Test]
    public function loadReturnsNullWhenFileDoesNotExist(): void
    {
        $cache = new FileCache($this->cacheFile);

        $this->assertNotInstanceOf(CompiledRoutes::class, $cache->load());
    }

    #[Test]
    public function storeAndLoadRoundTrip(): void
    {
        $router = new Router();
        $router->get('/ping', 'pong');

        $compiled = new RouteCompiler()->compile([], []);

        $cache = new FileCache($this->cacheFile);
        $cache->store($compiled);

        $loaded = $cache->load();

        $this->assertInstanceOf(CompiledRoutes::class, $loaded);
    }

    #[Test]
    public function storePreservesRouteData(): void
    {
        $router = new Router();
        $router->get('/books', 'handler', name: 'book.index');

        // Compile manually to get the CompiledRoutes
        $router->compile();

        $cache = new FileCache($this->cacheFile);
        $router->compile(cache: $cache); // warms the cache

        $loaded = $cache->load();

        $this->assertInstanceOf(CompiledRoutes::class, $loaded);
        $this->assertArrayHasKey('/books', $loaded->staticRoutes);
    }

    #[Test]
    public function invalidateDeletesTheCacheFile(): void
    {
        $cache = new FileCache($this->cacheFile);
        $compiled = new CompiledRoutes([], [], [], [], []);
        $cache->store($compiled);

        $this->assertFileExists($this->cacheFile);

        $cache->invalidate();

        $this->assertFileDoesNotExist($this->cacheFile);
    }

    #[Test]
    public function invalidateDoesNothingWhenFileDoesNotExist(): void
    {
        $cache = new FileCache($this->cacheFile);

        // Should not throw
        $cache->invalidate();

        $this->assertFileDoesNotExist($this->cacheFile);
    }

    #[Test]
    public function throwsWhenDirectoryDoesNotExist(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new FileCache('/nonexistent/directory/route.cache.php');
    }
}
