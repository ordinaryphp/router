<?php

declare(strict_types=1);

namespace Ordinary\Router\Attribute;

use Ordinary\Router\HttpMethod;

/**
 * Declares a route on a controller method.
 *
 * The handler value stored in the router is [ClassName::class, 'methodName'].
 * The framework is responsible for resolving the class and invoking the method.
 *
 * Repeatable — a single method may handle multiple routes.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final readonly class Route
{
    public function __construct(
        public HttpMethod|string $method,
        public string $path,
        public string $name,
    ) {}
}
