<?php

declare(strict_types=1);

namespace Ordinary\Router\Compiler;

use Ordinary\Router\Param\ParamDefinition;

/**
 * Compiles registered route data into the optimised CompiledRoutes structure.
 *
 * Separates static routes (O(1) hash lookup) from dynamic routes (indexed by
 * segment count to narrow the search space before regex evaluation).
 */
final class RouteCompiler
{
    /**
     * @param list<array{methods: list<string>, path: string, paramNames: list<string>, hasWildcard: bool, isDynamic: bool, name: ?string, index: int}> $routes
     * @param array<string, ParamDefinition> $paramDefs
     */
    public function compile(array $routes, array $paramDefs): CompiledRoutes
    {
        /** @var array<string, array<string, int>> $staticRoutes */
        $staticRoutes = [];
        /** @var array<int, string> $routeTemplates */
        $routeTemplates = [];
        /** @var array<string, int> $namedRoutes */
        $namedRoutes = [];
        /** @var array<string, array{regex: non-empty-string, paramNames: list<string>, hasWildcard: bool, methodRoutes: array<string, int>}> $dynamicByPath */
        $dynamicByPath = [];

        foreach ($routes as $route) {
            $routeTemplates[$route['index']] = $route['path'];

            if ($route['name'] !== null) {
                $namedRoutes[$route['name']] = $route['index'];
            }

            if (!$route['isDynamic']) {
                foreach ($route['methods'] as $method) {
                    $staticRoutes[$route['path']][$method] = $route['index'];
                }

                continue;
            }

            if (!isset($dynamicByPath[$route['path']])) {
                $regex = $this->buildRegex($route['path'], $paramDefs);
                $dynamicByPath[$route['path']] = [
                    'regex' => $regex,
                    'paramNames' => $route['paramNames'],
                    'hasWildcard' => $route['hasWildcard'],
                    'methodRoutes' => [],
                ];
            }

            foreach ($route['methods'] as $method) {
                $dynamicByPath[$route['path']]['methodRoutes'][$method] = $route['index'];
            }
        }

        /** @var array<int, list<CompiledDynamicEntry>> $dynamicRoutes */
        $dynamicRoutes = [];
        /** @var list<CompiledDynamicEntry> $wildcardRoutes */
        $wildcardRoutes = [];

        foreach ($dynamicByPath as $path => $data) {
            $entry = new CompiledDynamicEntry(
                regex: $data['regex'],
                paramNames: $data['paramNames'],
                methodRoutes: $data['methodRoutes'],
            );

            if ($data['hasWildcard']) {
                $wildcardRoutes[] = $entry;
            } else {
                $segCount = $this->countSegments($path);
                $dynamicRoutes[$segCount][] = $entry;
            }
        }

        return new CompiledRoutes(
            staticRoutes: $staticRoutes,
            dynamicRoutes: $dynamicRoutes,
            wildcardRoutes: $wildcardRoutes,
            namedRoutes: $namedRoutes,
            routeTemplates: $routeTemplates,
        );
    }

    /**
     * @param array<string, ParamDefinition> $paramDefs
     *
     * @return non-empty-string
     */
    private function buildRegex(string $path, array $paramDefs): string
    {
        $parts = \preg_split('/(\{[^}]+\})/', $path, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($parts === false) {
            return '~^' . \preg_quote($path, '~') . '$~';
        }

        $regex = '';
        foreach ($parts as $part) {
            if (\str_starts_with($part, '{') && \str_ends_with($part, '}')) {
                $paramName = \substr($part, 1, -1);
                $pattern = $paramDefs[$paramName]->pattern;
                $regex .= '(?P<' . $paramName . '>' . $pattern . ')';
            } else {
                $regex .= \preg_quote($part, '~');
            }
        }

        return '~^' . $regex . '$~';
    }

    private function countSegments(string $path): int
    {
        $trimmed = \trim($path, '/');

        return $trimmed === '' ? 0 : \substr_count($trimmed, '/') + 1;
    }
}
