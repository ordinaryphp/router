<?php

declare(strict_types=1);

namespace Ordinary\Router\Tests\Attribute;

use Ordinary\Router\Attribute\AttributeRouteLoader;
use Ordinary\Router\Exception\InvalidArgumentException;
use Ordinary\Router\MatchStatus;
use Ordinary\Router\Router;
use Ordinary\Router\Tests\Attribute\Fixtures\BookController;
use Ordinary\Router\Tests\Attribute\Fixtures\ShowBookAction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AttributeRouteLoader::class)]
final class AttributeRouteLoaderTest extends TestCase
{
    #[Test]
    public function loadClassRegistersRouteClassAttribute(): void
    {
        $router = new Router();
        $loader = new AttributeRouteLoader($router);
        $loader->loadClass(ShowBookAction::class);

        $dispatcher = $router->compile();
        $result = $dispatcher->dispatch('GET', '/books/42');

        $this->assertSame(MatchStatus::Found, $result->status);
        $this->assertSame(ShowBookAction::class, $result->handler);
    }

    #[Test]
    public function loadClassRegistersRepeatableRouteOnMethods(): void
    {
        $router = new Router();
        $loader = new AttributeRouteLoader($router);
        $loader->loadClass(BookController::class);

        $dispatcher = $router->compile();

        $listResult = $dispatcher->dispatch('GET', '/books');
        $this->assertSame(MatchStatus::Found, $listResult->status);
        $this->assertSame([BookController::class, 'index'], $listResult->handler);

        $createResult = $dispatcher->dispatch('POST', '/books');
        $this->assertSame(MatchStatus::Found, $createResult->status);
        $this->assertSame([BookController::class, 'create'], $createResult->handler);
    }

    #[Test]
    public function loadMethodRegistersOnlyThatMethodsRoutes(): void
    {
        $router = new Router();
        $loader = new AttributeRouteLoader($router);
        $loader->loadMethod(BookController::class, 'index');

        $dispatcher = $router->compile();

        $this->assertSame(MatchStatus::Found, $dispatcher->dispatch('GET', '/books')->status);
        $this->assertSame(MatchStatus::MethodNotAllowed, $dispatcher->dispatch('POST', '/books')->status);
    }

    #[Test]
    public function loadDirectoryScansAndRegistersRoutes(): void
    {
        $router = new Router();
        $loader = new AttributeRouteLoader($router);
        $loader->loadDirectory(__DIR__ . '/Fixtures');

        $dispatcher = $router->compile();

        $this->assertSame(MatchStatus::Found, $dispatcher->dispatch('GET', '/books')->status);
    }

    #[Test]
    public function throwsWhenDirectoryDoesNotExist(): void
    {
        $router = new Router();
        $loader = new AttributeRouteLoader($router);

        $this->expectException(InvalidArgumentException::class);

        $loader->loadDirectory('/nonexistent/directory');
    }

    #[Test]
    public function urlGeneratorWorksForAttributeLoadedRoutes(): void
    {
        $router = new Router();
        $loader = new AttributeRouteLoader($router);
        $loader->loadClass(BookController::class);

        $gen = $router->compile();

        $this->assertSame('/books', $gen->generate('book.index'));
        $this->assertTrue($gen->has('book.create'));
    }
}
