<?php

declare(strict_types=1);

namespace Ordinary\Router\Attribute;

use Ordinary\Router\HttpMethod;

/**
 * Declares a single route on an invokable class.
 *
 * The handler value stored in the router is the class name (FQCN string).
 * The framework is responsible for instantiating and invoking the class via __invoke.
 *
 * Not repeatable — one class handles one route. For multiple routes on a class,
 * use #[Route] on individual methods instead.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class RouteClass
{
    public function __construct(
        public HttpMethod|string $method,
        public string $path,
        public string $name,
    ) {}
}
