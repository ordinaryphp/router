<?php

declare(strict_types=1);

namespace Ordinary\Router\Tests\Attribute\Fixtures;

use Ordinary\Router\Attribute\RouteClass;
use Ordinary\Router\HttpMethod;

#[RouteClass(method: HttpMethod::Get, path: '/books/42', name: 'book.show')]
final class ShowBookAction
{
    public function __invoke(): string
    {
        return 'book:42';
    }
}
