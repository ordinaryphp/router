<?php

declare(strict_types=1);

namespace Ordinary\Router;

use Ordinary\Router\Compiler\CompiledDynamicEntry;
use Ordinary\Router\Compiler\CompiledRoutes;
use Ordinary\Router\Exception\InvalidArgumentException;
use Ordinary\Router\Exception\LogicException;
use Ordinary\Router\Param\ParamDefinition;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Compiled dispatcher that resolves HTTP method + path to a registered handler.
 *
 * Also implements URL generation for named routes.
 *
 * Lookup order:
 *   1. Static routes (hash map — O(1))
 *   2. Dynamic routes indexed by segment count
 *   3. Wildcard routes (tried last)
 */
final readonly class Dispatcher implements DispatcherInterface, UrlGeneratorInterface
{
    /**
     * @param list<mixed> $handlers Indexed by route registration order
     * @param array<string, ParamDefinition> $paramDefs
     */
    public function __construct(
        private CompiledRoutes $routes,
        private array $handlers,
        private array $paramDefs,
        private TrailingSlashMode $trailingSlash,
    ) {}

    public function dispatch(string $method, string $path): MatchResultInterface
    {
        $method = \strtoupper($method);

        [$effectivePath, $redirectTo] = $this->resolveTrailingSlash($path);

        if ($redirectTo !== null) {
            return new MatchResult(status: MatchStatus::RedirectRequired, redirectTo: $redirectTo);
        }

        // 1. Static routes — O(1) lookup
        if (isset($this->routes->staticRoutes[$effectivePath])) {
            $methodMap = $this->routes->staticRoutes[$effectivePath];
            if (isset($methodMap[$method])) {
                return new MatchResult(
                    status: MatchStatus::Found,
                    handler: $this->handlers[$methodMap[$method]],
                );
            }

            return new MatchResult(
                status: MatchStatus::MethodNotAllowed,
                allowedMethods: \array_keys($methodMap),
            );
        }

        // 2. Dynamic routes indexed by segment count
        $segCount = $this->countSegments($effectivePath);

        foreach ($this->routes->dynamicRoutes[$segCount] ?? [] as $entry) {
            $result = $this->matchEntry($entry, $method, $effectivePath);
            if ($result instanceof MatchResultInterface) {
                return $result;
            }
        }

        // 3. Wildcard routes
        foreach ($this->routes->wildcardRoutes as $entry) {
            $result = $this->matchEntry($entry, $method, $effectivePath);
            if ($result instanceof MatchResultInterface) {
                return $result;
            }
        }

        return new MatchResult(status: MatchStatus::NotFound);
    }

    public function dispatchRequest(ServerRequestInterface $request): MatchResultInterface
    {
        return $this->dispatch(
            method: $request->getMethod(),
            path: '/' . \ltrim($request->getUri()->getPath(), '/'),
        );
    }

    public function generate(string $name, array $params = []): string
    {
        if (!isset($this->routes->namedRoutes[$name])) {
            throw new InvalidArgumentException(
                \sprintf('No route named "%s"', $name),
            );
        }

        $routeIdx = $this->routes->namedRoutes[$name];
        $template = $this->routes->routeTemplates[$routeIdx];

        $result = \preg_replace_callback(
            '/\{([^}]+)\}/',
            function (array $m) use ($params, $name): string {
                $paramName = $m[1];

                if (!\array_key_exists($paramName, $params)) {
                    throw new InvalidArgumentException(
                        \sprintf('Missing required param "%s" for route "%s"', $paramName, $name),
                    );
                }

                $value = (string) $params[$paramName];
                $def = $this->paramDefs[$paramName] ?? null;

                if ($def !== null) {
                    $this->validateGeneratedParam($def, $value);
                }

                return $value;
            },
            $template,
        );

        if ($result === null) {
            throw new LogicException('URL generation failed due to regex error');
        }

        return $result;
    }

    public function has(string $name): bool
    {
        return isset($this->routes->namedRoutes[$name]);
    }

    /**
     * Try to match a single dynamic entry against the path and method.
     *
     * Returns a MethodNotAllowed result when the path matches but the method does not.
     * Returns null when the path does not match (try the next entry).
     */
    private function matchEntry(CompiledDynamicEntry $entry, string $method, string $path): ?MatchResultInterface
    {
        if (\preg_match($entry->regex, $path, $matches) !== 1) {
            return null;
        }

        if (!isset($entry->methodRoutes[$method])) {
            return new MatchResult(
                status: MatchStatus::MethodNotAllowed,
                allowedMethods: \array_keys($entry->methodRoutes),
            );
        }

        $routeIdx = $entry->methodRoutes[$method];
        $extractedParams = $this->extractAndValidateParams($matches, $entry->paramNames);

        if ($extractedParams === null) {
            return null;
        }

        return new MatchResult(
            status: MatchStatus::Found,
            handler: $this->handlers[$routeIdx],
            params: $extractedParams,
        );
    }

    /**
     * Extract named capture groups and run post-match range validation.
     *
     * Returns null if any param fails its constraint (treat as no-match so routing
     * can continue to the next candidate, e.g. /books/0 with min:1 → NotFound).
     *
     * @param array<string|int, string> $matches
     * @param list<string> $paramNames
     *
     * @return array<string, string>|null
     */
    private function extractAndValidateParams(array $matches, array $paramNames): ?array
    {
        $params = [];

        foreach ($paramNames as $name) {
            $value = isset($matches[$name]) && $matches[$name] !== '' ? $matches[$name] : '';
            $def = $this->paramDefs[$name] ?? null;

            if ($def !== null && ($def->rangeMin !== null || $def->rangeMax !== null)) {
                $intVal = (int) $value;

                if ($def->rangeMin !== null && $intVal < $def->rangeMin) {
                    return null;
                }

                if ($def->rangeMax !== null && $intVal > $def->rangeMax) {
                    return null;
                }
            }

            $params[$name] = $value;
        }

        return $params;
    }

    /**
     * Validate a param value for URL generation against its constraint.
     *
     * @throws InvalidArgumentException
     */
    private function validateGeneratedParam(ParamDefinition $def, string $value): void
    {
        if (\preg_match('~^(?:' . $def->pattern . ')$~', $value) !== 1) {
            throw new InvalidArgumentException(
                \sprintf('Param "%s" value "%s" does not satisfy its constraint', $def->name, $value),
            );
        }

        if ($def->rangeMin !== null || $def->rangeMax !== null) {
            $intVal = (int) $value;

            if ($def->rangeMin !== null && $intVal < $def->rangeMin) {
                throw new InvalidArgumentException(
                    \sprintf('Param "%s" value %d is below minimum %d', $def->name, $intVal, $def->rangeMin),
                );
            }

            if ($def->rangeMax !== null && $intVal > $def->rangeMax) {
                throw new InvalidArgumentException(
                    \sprintf('Param "%s" value %d exceeds maximum %d', $def->name, $intVal, $def->rangeMax),
                );
            }
        }
    }

    /**
     * Apply the trailing slash policy to the incoming path.
     *
     * Returns [effectivePath, redirectTo] where redirectTo is non-null only when
     * a redirect should be issued.
     *
     * @return array{string, string|null}
     */
    private function resolveTrailingSlash(string $path): array
    {
        return match ($this->trailingSlash) {
            TrailingSlashMode::Strict => [$path, null],
            TrailingSlashMode::Ignore => [
                \strlen($path) > 1 ? \rtrim($path, '/') : $path,
                null,
            ],
            TrailingSlashMode::Redirect => \strlen($path) > 1 && \str_ends_with($path, '/')
                ? [\rtrim($path, '/'), \rtrim($path, '/')]
                : [$path, null],
        };
    }

    private function countSegments(string $path): int
    {
        $trimmed = \trim($path, '/');

        return $trimmed === '' ? 0 : \substr_count($trimmed, '/') + 1;
    }
}
