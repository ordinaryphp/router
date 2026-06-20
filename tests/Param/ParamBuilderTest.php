<?php

declare(strict_types=1);

namespace Ordinary\Router\Tests\Param;

use Ordinary\Router\Exception\InvalidArgumentException;
use Ordinary\Router\MatchStatus;
use Ordinary\Router\Param\ParamBuilder;
use Ordinary\Router\Router;
use Ordinary\Router\Tests\Fixtures\EmptyEnum;
use Ordinary\Router\Tests\Fixtures\ItemStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ParamBuilder::class)]
final class ParamBuilderTest extends TestCase
{
    #[Test]
    public function integerPatternMatchesDigits(): void
    {
        $router = new Router();
        $router->param('id')->integer(min: 1);
        $router->get('/items/{id}', 'h');
        $d = $router->compile();

        $this->assertSame(MatchStatus::Found, $d->dispatch('GET', '/items/123')->status);
        $this->assertSame(MatchStatus::NotFound, $d->dispatch('GET', '/items/abc')->status);
    }

    #[Test]
    public function slugPatternMatchesLowercaseAlphanumericHyphens(): void
    {
        $router = new Router();
        $router->param('slug')->slug();
        $router->get('/posts/{slug}', 'h');
        $d = $router->compile();

        $this->assertSame(MatchStatus::Found, $d->dispatch('GET', '/posts/my-post-title')->status);
        $this->assertSame(MatchStatus::NotFound, $d->dispatch('GET', '/posts/UPPER')->status);
        $this->assertSame(MatchStatus::NotFound, $d->dispatch('GET', '/posts/has space')->status);
    }

    #[Test]
    public function uuidPatternMatchesValidUuid(): void
    {
        $router = new Router();
        $router->param('uuid')->uuid();
        $router->get('/items/{uuid}', 'h');
        $d = $router->compile();

        $this->assertSame(
            MatchStatus::Found,
            $d->dispatch('GET', '/items/550e8400-e29b-41d4-a716-446655440000')->status,
        );
        $this->assertSame(MatchStatus::NotFound, $d->dispatch('GET', '/items/not-a-uuid')->status);
    }

    #[Test]
    public function alphaPatternMatchesLettersOnly(): void
    {
        $router = new Router();
        $router->param('word')->alpha();
        $router->get('/words/{word}', 'h');
        $d = $router->compile();

        $this->assertSame(MatchStatus::Found, $d->dispatch('GET', '/words/hello')->status);
        $this->assertSame(MatchStatus::NotFound, $d->dispatch('GET', '/words/hello1')->status);
    }

    #[Test]
    public function alphanumericPatternMatchesLettersAndDigits(): void
    {
        $router = new Router();
        $router->param('code')->alphanumeric();
        $router->get('/codes/{code}', 'h');
        $d = $router->compile();

        $this->assertSame(MatchStatus::Found, $d->dispatch('GET', '/codes/abc123')->status);
        $this->assertSame(MatchStatus::NotFound, $d->dispatch('GET', '/codes/abc-123')->status);
    }

    #[Test]
    public function anyPatternMatchesNonSlashCharacters(): void
    {
        $router = new Router();
        $router->param('token')->any();
        $router->get('/tokens/{token}', 'h');
        $d = $router->compile();

        $this->assertSame(MatchStatus::Found, $d->dispatch('GET', '/tokens/abc.xyz_123')->status);
        $this->assertSame(MatchStatus::NotFound, $d->dispatch('GET', '/tokens/a/b')->status);
    }

    #[Test]
    public function enumPatternOnlyMatchesDefinedCaseValues(): void
    {
        $router = new Router();
        $router->param('status')->enum(ItemStatus::class);
        $router->get('/items/{status}', 'h');
        $d = $router->compile();

        $this->assertSame(MatchStatus::Found, $d->dispatch('GET', '/items/active')->status);
        $this->assertSame(MatchStatus::Found, $d->dispatch('GET', '/items/inactive')->status);
        $this->assertSame(MatchStatus::Found, $d->dispatch('GET', '/items/draft')->status);
        $this->assertSame(MatchStatus::NotFound, $d->dispatch('GET', '/items/archived')->status);
    }

    #[Test]
    public function customPatternIsApplied(): void
    {
        $router = new Router();
        $router->param('hex')->pattern('[0-9a-f]+');
        $router->get('/colors/{hex}', 'h');
        $d = $router->compile();

        $this->assertSame(MatchStatus::Found, $d->dispatch('GET', '/colors/ff00cc')->status);
        $this->assertSame(MatchStatus::NotFound, $d->dispatch('GET', '/colors/FFAABB')->status);
    }

    #[Test]
    public function throwsForInvalidRegexPattern(): void
    {
        $router = new Router();

        $this->expectException(InvalidArgumentException::class);

        $router->param('bad')->pattern('[unclosed');
    }

    #[Test]
    public function throwsWhenEnumClassIsNotBackedEnum(): void
    {
        $router = new Router();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('BackedEnum');

        $router->param('val')->enum(\stdClass::class);
    }

    #[Test]
    public function throwsWhenEnumHasNoCases(): void
    {
        $router = new Router();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('no cases');

        $router->param('empty')->enum(EmptyEnum::class);
    }

    #[Test]
    public function wildcardPatternMatchesAcrossSegments(): void
    {
        $router = new Router();
        $router->param('path')->wildcard();
        $router->get('/files/{path}', 'h');
        $d = $router->compile();

        $this->assertSame(MatchStatus::Found, $d->dispatch('GET', '/files/a/b/c.txt')->status);
        $this->assertSame(['path' => 'a/b/c.txt'], $d->dispatch('GET', '/files/a/b/c.txt')->params);
    }

    /**
     * @return \Iterator<string, array{string, string}>
     */
    public static function rangeProvider(): \Iterator
    {
        yield 'within range' => ['5', MatchStatus::Found->name];
        yield 'at minimum' => ['1', MatchStatus::Found->name];
        yield 'at maximum' => ['10', MatchStatus::Found->name];
        yield 'below minimum' => ['0', MatchStatus::NotFound->name];
        yield 'above maximum' => ['11', MatchStatus::NotFound->name];
    }

    #[Test]
    #[DataProvider('rangeProvider')]
    public function rangeParamEnforcesMinAndMax(string $value, string $expectedStatus): void
    {
        $router = new Router();
        $router->param('page')->range(min: 1, max: 10);
        $router->get('/pages/{page}', 'h');
        $d = $router->compile();

        $result = $d->dispatch('GET', '/pages/' . $value);

        $this->assertSame($expectedStatus, $result->status->name);
    }
}
