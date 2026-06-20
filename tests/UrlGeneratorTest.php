<?php

declare(strict_types=1);

namespace Ordinary\Router\Tests;

use Ordinary\Router\Dispatcher;
use Ordinary\Router\Exception\InvalidArgumentException;
use Ordinary\Router\Router;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Dispatcher::class)]
final class UrlGeneratorTest extends TestCase
{
    #[Test]
    public function generatesUrlForStaticRoute(): void
    {
        $router = new Router();
        $router->get('/books', 'handler', name: 'book.index');

        $gen = $router->compile();

        $this->assertSame('/books', $gen->generate('book.index'));
    }

    #[Test]
    public function generatesUrlWithSubstitutedParams(): void
    {
        $router = new Router();
        $router->param('bookId')->integer(min: 1);
        $router->get('/books/{bookId}', 'handler', name: 'book.show');
        $gen = $router->compile();

        $this->assertSame('/books/42', $gen->generate('book.show', ['bookId' => 42]));
    }

    #[Test]
    public function generatesUrlWithMultipleParams(): void
    {
        $router = new Router();
        $router->param('bookId')->integer(min: 1);
        $router->param('chapterId')->integer(min: 1);
        $router->get('/books/{bookId}/chapters/{chapterId}', 'handler', name: 'chapter.show');
        $gen = $router->compile();

        $this->assertSame(
            '/books/3/chapters/7',
            $gen->generate('chapter.show', ['bookId' => 3, 'chapterId' => 7]),
        );
    }

    #[Test]
    public function generatesUrlWithWildcardParamPreservingSlashes(): void
    {
        $router = new Router();
        $router->param('filePath')->wildcard();
        $router->get('/uploads/{filePath}', 'handler', name: 'file.show');
        $gen = $router->compile();

        $this->assertSame(
            '/uploads/2024/january/cover.jpg',
            $gen->generate('file.show', ['filePath' => '2024/january/cover.jpg']),
        );
    }

    #[Test]
    public function throwsForUnknownRouteName(): void
    {
        $gen = new Router()->compile();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"no.such.route"');

        $gen->generate('no.such.route');
    }

    #[Test]
    public function throwsWhenRequiredParamMissing(): void
    {
        $router = new Router();
        $router->param('bookId')->integer(min: 1);
        $router->get('/books/{bookId}', 'handler', name: 'book.show');
        $gen = $router->compile();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"bookId"');

        $gen->generate('book.show', []);
    }

    #[Test]
    public function throwsWhenParamValueFailsConstraint(): void
    {
        $router = new Router();
        $router->param('bookId')->integer(min: 1);
        $router->get('/books/{bookId}', 'handler', name: 'book.show');
        $gen = $router->compile();

        $this->expectException(InvalidArgumentException::class);

        $gen->generate('book.show', ['bookId' => 0]);
    }

    #[Test]
    public function throwsWhenParamValueFailsRegexPattern(): void
    {
        $router = new Router();
        $router->param('slug')->slug();
        $router->get('/by-slug/{slug}', 'handler', name: 'slug.show');
        $gen = $router->compile();

        $this->expectException(InvalidArgumentException::class);

        $gen->generate('slug.show', ['slug' => 'INVALID SLUG!']);
    }

    #[Test]
    public function hasReturnsTrueForRegisteredName(): void
    {
        $router = new Router();
        $router->get('/books', 'handler', name: 'book.index');

        $gen = $router->compile();

        $this->assertTrue($gen->has('book.index'));
        $this->assertFalse($gen->has('book.show'));
    }

    #[Test]
    public function throwsWhenParamValueExceedsMaximum(): void
    {
        $router = new Router();
        $router->param('page')->range(min: 1, max: 100);
        $router->get('/pages/{page}', 'handler', name: 'page.show');
        $gen = $router->compile();

        $this->expectException(InvalidArgumentException::class);

        $gen->generate('page.show', ['page' => 101]);
    }

    #[Test]
    public function intValueCastToStringBeforeValidation(): void
    {
        $router = new Router();
        $router->param('id')->integer(min: 1);
        $router->get('/items/{id}', 'handler', name: 'item.show');
        $gen = $router->compile();

        $this->assertSame('/items/99', $gen->generate('item.show', ['id' => 99]));
    }
}
