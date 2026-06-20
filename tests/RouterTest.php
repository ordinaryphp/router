<?php

declare(strict_types=1);

namespace Ordinary\Router\Tests;

use Ordinary\Router\Exception\InvalidArgumentException;
use Ordinary\Router\Exception\LogicException;
use Ordinary\Router\HttpMethod;
use Ordinary\Router\MatchStatus;
use Ordinary\Router\Router;
use Ordinary\Router\Tests\Fixtures\InMemoryCache;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Router::class)]
final class RouterTest extends TestCase
{
    #[Test]
    public function compilesWithNoRoutes(): void
    {
        $dispatcher = new Router()->compile();

        $result = $dispatcher->dispatch('GET', '/anything');

        $this->assertSame(MatchStatus::NotFound, $result->status);
    }

    #[Test]
    public function throwsWhenDuplicateParamRegistered(): void
    {
        $router = new Router();
        $router->param('id')->integer(min: 1);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"id"');

        $router->param('id')->any();
    }

    #[Test]
    public function throwsWhenRouteReferencesUnregisteredParam(): void
    {
        $router = new Router();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"bookId"');

        $router->get('/books/{bookId}', static fn(): null => null);
    }

    #[Test]
    public function throwsOnDuplicateMethodPathCombination(): void
    {
        $router = new Router();
        $router->param('id')->integer(min: 1);
        $router->get('/items/{id}', 'handler1');

        $this->expectException(LogicException::class);

        $router->get('/items/{id}', 'handler2');
    }

    #[Test]
    public function throwsOnDuplicateRouteName(): void
    {
        $router = new Router();
        $router->get('/foo', 'handler1', name: 'my.route');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('"my.route"');

        $router->get('/bar', 'handler2', name: 'my.route');
    }

    #[Test]
    public function throwsWhenWildcardParamIsNotLastSegment(): void
    {
        $router = new Router();
        $router->param('file')->wildcard();
        $router->param('suffix')->any();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('last');

        $router->get('/uploads/{file}/{suffix}', 'handler');
    }

    #[Test]
    public function autoHeadFromGetMapsGetRoutesToHead(): void
    {
        $router = new Router(autoHeadFromGet: true);
        $router->get('/ping', 'pingHandler');

        $dispatcher = $router->compile();

        $this->assertSame(MatchStatus::Found, $dispatcher->dispatch('HEAD', '/ping')->status);
        $this->assertSame('pingHandler', $dispatcher->dispatch('HEAD', '/ping')->handler);
    }

    #[Test]
    public function autoHeadFromGetDisabledDoesNotMapHead(): void
    {
        $router = new Router(autoHeadFromGet: false);
        $router->get('/ping', 'pingHandler');

        $dispatcher = $router->compile();

        $this->assertSame(MatchStatus::MethodNotAllowed, $dispatcher->dispatch('HEAD', '/ping')->status);
    }

    #[Test]
    public function acceptsHttpMethodEnumAndRawString(): void
    {
        $router = new Router();
        $router->map([HttpMethod::Get, 'PURGE'], '/resource', 'handler');

        $dispatcher = $router->compile();

        $this->assertSame(MatchStatus::Found, $dispatcher->dispatch('GET', '/resource')->status);
        $this->assertSame(MatchStatus::Found, $dispatcher->dispatch('PURGE', '/resource')->status);
    }

    #[Test]
    public function normalizesMethodsToUppercase(): void
    {
        $router = new Router();
        $router->map(['get', 'post'], '/resource', 'handler');

        $dispatcher = $router->compile();

        $this->assertSame(MatchStatus::Found, $dispatcher->dispatch('GET', '/resource')->status);
        $this->assertSame(MatchStatus::Found, $dispatcher->dispatch('POST', '/resource')->status);
    }

    #[Test]
    public function samePathDifferentMethodsRegisteredSeparately(): void
    {
        $router = new Router();
        $router->param('id')->integer(min: 1);
        $router->get('/items/{id}', 'getHandler', name: 'item.show');
        $router->put('/items/{id}', 'putHandler', name: 'item.update');

        $dispatcher = $router->compile();

        $this->assertSame('getHandler', $dispatcher->dispatch('GET', '/items/5')->handler);
        $this->assertSame('putHandler', $dispatcher->dispatch('PUT', '/items/5')->handler);
    }

    #[Test]
    public function httpShorthandMethodsRegisterAndDispatchCorrectly(): void
    {
        $router = new Router(autoHeadFromGet: false);
        $router->post('/resource', 'postHandler');
        $router->patch('/resource', 'patchHandler');
        $router->delete('/resource', 'deleteHandler');
        $router->head('/resource', 'headHandler');
        $router->options('/resource', 'optionsHandler');

        $dispatcher = $router->compile();

        $this->assertSame('postHandler', $dispatcher->dispatch('POST', '/resource')->handler);
        $this->assertSame('patchHandler', $dispatcher->dispatch('PATCH', '/resource')->handler);
        $this->assertSame('deleteHandler', $dispatcher->dispatch('DELETE', '/resource')->handler);
        $this->assertSame('headHandler', $dispatcher->dispatch('HEAD', '/resource')->handler);
        $this->assertSame('optionsHandler', $dispatcher->dispatch('OPTIONS', '/resource')->handler);
    }

    #[Test]
    public function compileUsesExistingCacheWhenAvailable(): void
    {
        $router = new Router();
        $router->get('/cached', 'handler');

        $cache = new InMemoryCache();
        $router->compile(cache: $cache); // warms the cache

        // New router with no routes — should still find the route from cache
        $router2 = new Router();
        $router2->get('/cached', 'handler');
        // must register handler at same index
        $dispatcher = $router2->compile(cache: $cache);

        $this->assertSame(MatchStatus::Found, $dispatcher->dispatch('GET', '/cached')->status);
    }
}
