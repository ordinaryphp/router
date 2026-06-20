<?php

declare(strict_types=1);

namespace Ordinary\Router\Tests;

use Nyholm\Psr7\ServerRequest;
use Ordinary\Router\Dispatcher;
use Ordinary\Router\MatchStatus;
use Ordinary\Router\Router;
use Ordinary\Router\Tests\Fixtures\ItemStatus;
use Ordinary\Router\TrailingSlashMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Dispatcher::class)]
final class DispatcherTest extends TestCase
{
    #[Test]
    public function staticRouteFoundWithCorrectHandler(): void
    {
        $dispatcher = $this->buildRouter([
            fn(Router $r) => $r->get('/books', 'listBooks'),
        ])->compile();

        $result = $dispatcher->dispatch('GET', '/books');

        $this->assertSame(MatchStatus::Found, $result->status);
        $this->assertSame('listBooks', $result->handler);
        $this->assertSame([], $result->params);
    }

    #[Test]
    public function dynamicRouteExtractsParamValues(): void
    {
        $router = new Router();
        $router->param('bookId')->integer(min: 1);
        $router->get('/books/{bookId}', 'showBook');

        $dispatcher = $router->compile();
        $result = $dispatcher->dispatch('GET', '/books/42');

        $this->assertSame(MatchStatus::Found, $result->status);
        $this->assertSame('showBook', $result->handler);
        $this->assertSame(['bookId' => '42'], $result->params);
    }

    #[Test]
    public function unknownPathReturnsNotFound(): void
    {
        $dispatcher = new Router()->compile();

        $this->assertSame(MatchStatus::NotFound, $dispatcher->dispatch('GET', '/nope')->status);
    }

    #[Test]
    public function wrongMethodReturnsMethodNotAllowedWithAllowedList(): void
    {
        $router = new Router(autoHeadFromGet: false);
        $router->get('/items', 'listItems');
        $router->post('/items', 'createItem');

        $dispatcher = $router->compile();

        $result = $dispatcher->dispatch('DELETE', '/items');

        $this->assertSame(MatchStatus::MethodNotAllowed, $result->status);
        $this->assertContains('GET', $result->allowedMethods);
        $this->assertContains('POST', $result->allowedMethods);
    }

    #[Test]
    public function integerParamRejectsNonNumericSegment(): void
    {
        $router = new Router();
        $router->param('id')->integer(min: 1);
        $router->get('/items/{id}', 'handler');
        $dispatcher = $router->compile();

        $this->assertSame(MatchStatus::NotFound, $dispatcher->dispatch('GET', '/items/abc')->status);
    }

    #[Test]
    public function integerParamRejectsBelowMinimum(): void
    {
        $router = new Router();
        $router->param('id')->integer(min: 1);
        $router->get('/items/{id}', 'handler');
        $dispatcher = $router->compile();

        $this->assertSame(MatchStatus::NotFound, $dispatcher->dispatch('GET', '/items/0')->status);
    }

    #[Test]
    public function integerParamRejectsAboveMaximum(): void
    {
        $router = new Router();
        $router->param('page')->range(min: 1, max: 100);
        $router->get('/items', 'handler');
        $router->get('/pages/{page}', 'handler2');

        $dispatcher = $router->compile();

        $this->assertSame(MatchStatus::NotFound, $dispatcher->dispatch('GET', '/pages/101')->status);
    }

    #[Test]
    public function enumParamMatchesValidCaseValue(): void
    {
        $router = new Router();
        $router->param('status')->enum(ItemStatus::class);
        $router->get('/items/by-status/{status}', 'handler');
        $dispatcher = $router->compile();

        $result = $dispatcher->dispatch('GET', '/items/by-status/active');

        $this->assertSame(MatchStatus::Found, $result->status);
        $this->assertSame(['status' => 'active'], $result->params);
    }

    #[Test]
    public function enumParamRejectsInvalidValue(): void
    {
        $router = new Router();
        $router->param('status')->enum(ItemStatus::class);
        $router->get('/items/by-status/{status}', 'handler');
        $dispatcher = $router->compile();

        $this->assertSame(MatchStatus::NotFound, $dispatcher->dispatch('GET', '/items/by-status/invalid')->status);
    }

    #[Test]
    public function wildcardParamCapturesMultipleSegments(): void
    {
        $router = new Router();
        $router->param('filePath')->wildcard();
        $router->get('/uploads/{filePath}', 'serveFile');
        $dispatcher = $router->compile();

        $result = $dispatcher->dispatch('GET', '/uploads/2024/january/cover.jpg');

        $this->assertSame(MatchStatus::Found, $result->status);
        $this->assertSame(['filePath' => '2024/january/cover.jpg'], $result->params);
    }

    #[Test]
    public function staticRouteTakesPrecedenceOverDynamic(): void
    {
        $router = new Router();
        $router->param('bookId')->any();
        $router->get('/books/new', 'newBookForm');
        $router->get('/books/{bookId}', 'showBook');

        $dispatcher = $router->compile();

        $result = $dispatcher->dispatch('GET', '/books/new');

        $this->assertSame(MatchStatus::Found, $result->status);
        $this->assertSame('newBookForm', $result->handler);
    }

    #[Test]
    public function trailingSlashIgnoreModeMatchesWithOrWithoutSlash(): void
    {
        $router = new Router(trailingSlash: TrailingSlashMode::Ignore);
        $router->get('/books', 'listBooks');

        $dispatcher = $router->compile();

        $this->assertSame(MatchStatus::Found, $dispatcher->dispatch('GET', '/books')->status);
        $this->assertSame(MatchStatus::Found, $dispatcher->dispatch('GET', '/books/')->status);
    }

    #[Test]
    public function trailingSlashRedirectModeReturnsRedirectForSlashedPath(): void
    {
        $router = new Router(trailingSlash: TrailingSlashMode::Redirect);
        $router->get('/books', 'listBooks');

        $dispatcher = $router->compile();

        $result = $dispatcher->dispatch('GET', '/books/');

        $this->assertSame(MatchStatus::RedirectRequired, $result->status);
        $this->assertSame('/books', $result->redirectTo);
    }

    #[Test]
    public function trailingSlashRedirectModeDoesNotRedirectRoot(): void
    {
        $router = new Router(trailingSlash: TrailingSlashMode::Redirect);
        $router->get('/', 'home');

        $dispatcher = $router->compile();

        $result = $dispatcher->dispatch('GET', '/');

        $this->assertSame(MatchStatus::Found, $result->status);
    }

    #[Test]
    public function trailingSlashStrictModeTreatsPathsAsDistinct(): void
    {
        $router = new Router(trailingSlash: TrailingSlashMode::Strict);
        $router->get('/books', 'withoutSlash');
        $router->get('/books/', 'withSlash');

        $dispatcher = $router->compile();

        $this->assertSame('withoutSlash', $dispatcher->dispatch('GET', '/books')->handler);
        $this->assertSame('withSlash', $dispatcher->dispatch('GET', '/books/')->handler);
    }

    #[Test]
    public function dispatchRequestExtractsMethodAndPathFromPsrRequest(): void
    {
        $router = new Router();
        $router->get('/ping', 'pong');

        $dispatcher = $router->compile();

        $request = new ServerRequest('GET', 'http://example.com/ping');
        $result = $dispatcher->dispatchRequest($request);

        $this->assertSame(MatchStatus::Found, $result->status);
        $this->assertSame('pong', $result->handler);
    }

    #[Test]
    public function multipleParamsExtractedCorrectly(): void
    {
        $router = new Router();
        $router->param('bookId')->integer(min: 1);
        $router->param('chapterId')->integer(min: 1);
        $router->get('/books/{bookId}/chapters/{chapterId}', 'showChapter');
        $dispatcher = $router->compile();

        $result = $dispatcher->dispatch('GET', '/books/3/chapters/7');

        $this->assertSame(MatchStatus::Found, $result->status);
        $this->assertSame(['bookId' => '3', 'chapterId' => '7'], $result->params);
    }

    /**
     * @param list<\Closure(Router): void> $setup
     */
    private function buildRouter(array $setup, TrailingSlashMode $mode = TrailingSlashMode::Strict): Router
    {
        $router = new Router(trailingSlash: $mode);

        foreach ($setup as $fn) {
            $fn($router);
        }

        return $router;
    }
}
