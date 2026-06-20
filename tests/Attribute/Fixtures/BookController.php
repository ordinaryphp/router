<?php

declare(strict_types=1);

namespace Ordinary\Router\Tests\Attribute\Fixtures;

use Ordinary\Router\Attribute\Route;
use Ordinary\Router\HttpMethod;

final class BookController
{
    #[Route(method: HttpMethod::Get, path: '/books', name: 'book.index')]
    public function index(): string
    {
        return 'index';
    }

    #[Route(method: HttpMethod::Post, path: '/books', name: 'book.create')]
    public function create(): string
    {
        return 'create';
    }
}
