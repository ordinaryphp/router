<?php

declare(strict_types=1);

namespace Ordinary\Router\Compiler;

/**
 * The fully compiled and serializable route data produced by RouteCompiler.
 *
 * Does not contain handlers — those live in the Router's handler array and are
 * referenced by the integer route indices stored here.
 */
final readonly class CompiledRoutes
{
    /**
     * @param array<string, array<string, int>> $staticRoutes
     *                                                        path => [HTTP method => route index]
     * @param array<int, list<CompiledDynamicEntry>> $dynamicRoutes
     *                                                              segment count => list of compiled entries (tried in registration order)
     * @param list<CompiledDynamicEntry> $wildcardRoutes
     *                                                   Routes containing a wildcard param; tried after all fixed-segment routes fail
     * @param array<string, int> $namedRoutes
     *                                        route name => route index (for URL generation)
     * @param array<int, string> $routeTemplates
     *                                           route index => original path template (for URL generation)
     */
    public function __construct(
        public array $staticRoutes,
        public array $dynamicRoutes,
        public array $wildcardRoutes,
        public array $namedRoutes,
        public array $routeTemplates,
    ) {}
}
