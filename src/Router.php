<?php

declare(strict_types=1);

namespace Ordinary\Router;

use Ordinary\Router\Cache\CacheInterface;
use Ordinary\Router\Compiler\CompiledRoutes;
use Ordinary\Router\Compiler\RouteCompiler;
use Ordinary\Router\Exception\InvalidArgumentException;
use Ordinary\Router\Exception\LogicException;
use Ordinary\Router\Param\ParamBuilder;
use Ordinary\Router\Param\ParamDefinition;

/**
 * Primary entry point for registering params and routes.
 *
 * Register all params before routes that reference them, then call compile().
 * The returned Dispatcher is immutable and safe for concurrent use across requests.
 */
final class Router implements RouterInterface
{
    /** @var array<string, ParamDefinition> */
    private array $paramDefs = [];

    /**
     * @var list<array{methods: list<string>, path: string, paramNames: list<string>, hasWildcard: bool, isDynamic: bool, name: ?string, index: int}>
     */
    private array $registeredRoutes = [];

    /** @var list<mixed> */
    private array $handlers = [];

    /** @var array<string, true>  "METHOD:path" keys for duplicate detection */
    private array $routeKeys = [];

    /** @var array<string, true>  registered route names */
    private array $routeNames = [];

    public function __construct(
        private readonly TrailingSlashMode $trailingSlash = TrailingSlashMode::Strict,
        private readonly bool $autoHeadFromGet = true,
    ) {}

    public function param(string $name): ParamBuilder
    {
        if (isset($this->paramDefs[$name])) {
            throw new InvalidArgumentException(
                \sprintf('Param "%s" is already registered', $name),
            );
        }

        return new ParamBuilder(
            name: $name,
            register: function (ParamDefinition $def): void {
                $this->paramDefs[$def->name] = $def;
            },
        );
    }

    public function get(string $path, mixed $handler, ?string $name = null): void
    {
        $this->map(methods: [HttpMethod::Get], path: $path, handler: $handler, name: $name);
    }

    public function post(string $path, mixed $handler, ?string $name = null): void
    {
        $this->map(methods: [HttpMethod::Post], path: $path, handler: $handler, name: $name);
    }

    public function put(string $path, mixed $handler, ?string $name = null): void
    {
        $this->map(methods: [HttpMethod::Put], path: $path, handler: $handler, name: $name);
    }

    public function patch(string $path, mixed $handler, ?string $name = null): void
    {
        $this->map(methods: [HttpMethod::Patch], path: $path, handler: $handler, name: $name);
    }

    public function delete(string $path, mixed $handler, ?string $name = null): void
    {
        $this->map(methods: [HttpMethod::Delete], path: $path, handler: $handler, name: $name);
    }

    public function head(string $path, mixed $handler, ?string $name = null): void
    {
        $this->map(methods: [HttpMethod::Head], path: $path, handler: $handler, name: $name);
    }

    public function options(string $path, mixed $handler, ?string $name = null): void
    {
        $this->map(methods: [HttpMethod::Options], path: $path, handler: $handler, name: $name);
    }

    /**
     * @param non-empty-list<HttpMethod|string> $methods
     */
    public function map(array $methods, string $path, mixed $handler, ?string $name = null): void
    {
        $normalizedMethods = $this->normalizeMethods($methods);

        if ($this->autoHeadFromGet
            && \in_array('GET', $normalizedMethods, true)
            && !\in_array('HEAD', $normalizedMethods, true)
        ) {
            $normalizedMethods[] = 'HEAD';
        }

        foreach ($normalizedMethods as $method) {
            $key = $method . ':' . $path;
            if (isset($this->routeKeys[$key])) {
                throw new LogicException(
                    \sprintf('Route "%s %s" is already registered', $method, $path),
                );
            }
        }

        if ($name !== null && isset($this->routeNames[$name])) {
            throw new LogicException(
                \sprintf('Route name "%s" is already registered', $name),
            );
        }

        [$paramNames, $isDynamic, $hasWildcard] = $this->parsePath($path);

        $index = \count($this->handlers);
        $this->handlers[] = $handler;

        foreach ($normalizedMethods as $method) {
            $this->routeKeys[$method . ':' . $path] = true;
        }

        if ($name !== null) {
            $this->routeNames[$name] = true;
        }

        $this->registeredRoutes[] = [
            'methods'     => $normalizedMethods,
            'path'        => $path,
            'paramNames'  => $paramNames,
            'hasWildcard' => $hasWildcard,
            'isDynamic'   => $isDynamic,
            'name'        => $name,
            'index'       => $index,
        ];
    }

    public function compile(?CacheInterface $cache = null): DispatcherInterface&UrlGeneratorInterface
    {
        $compiled = $cache?->load();

        if (!$compiled instanceof CompiledRoutes) {
            $compiler = new RouteCompiler();
            $compiled = $compiler->compile($this->registeredRoutes, $this->paramDefs);
            $cache?->store($compiled);
        }

        return new Dispatcher(
            routes: $compiled,
            handlers: $this->handlers,
            paramDefs: $this->paramDefs,
            trailingSlash: $this->trailingSlash,
        );
    }

    /**
     * Parse a path string, validate all {param} placeholders are registered, and
     * return the param names, whether the route is dynamic, and whether it has a wildcard.
     *
     * @throws InvalidArgumentException if an unregistered param is referenced
     * @throws InvalidArgumentException if a wildcard param is not the last segment
     *
     * @return array{list<string>, bool, bool} [paramNames, isDynamic, hasWildcard]
     */
    private function parsePath(string $path): array
    {
        \preg_match_all('/\{([^}]+)\}/', $path, $matches);
        $paramNames = $matches[1];

        if ($paramNames === []) {
            return [[], false, false];
        }

        $hasWildcard = false;

        foreach ($paramNames as $i => $name) {
            if (!isset($this->paramDefs[$name])) {
                throw new InvalidArgumentException(
                    \sprintf('Route path "%s" references unregistered param "%s"', $path, $name),
                );
            }

            if ($this->paramDefs[$name]->isWildcard) {
                $hasWildcard = true;

                if ($i !== \count($paramNames) - 1) {
                    throw new InvalidArgumentException(
                        \sprintf('Wildcard param "%s" must be the last param in path "%s"', $name, $path),
                    );
                }

                // Must also be at the end of the path string
                if (!\str_ends_with($path, '{' . $name . '}')) {
                    throw new InvalidArgumentException(
                        \sprintf('Wildcard param "%s" must be the last segment in path "%s"', $name, $path),
                    );
                }
            }
        }

        return [$paramNames, true, $hasWildcard];
    }

    /**
     * Normalise a mixed list of HttpMethod cases and raw strings to uppercase strings.
     *
     * @param non-empty-list<HttpMethod|string> $methods
     *
     * @return non-empty-list<string>
     */
    private function normalizeMethods(array $methods): array
    {
        return \array_map(
            static fn(HttpMethod|string $m): string => $m instanceof HttpMethod
                ? $m->value
                : \strtoupper($m),
            $methods,
        );
    }
}
