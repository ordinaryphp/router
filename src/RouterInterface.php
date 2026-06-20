<?php

declare(strict_types=1);

namespace Ordinary\Router;

use Ordinary\Router\Cache\CacheInterface;
use Ordinary\Router\Exception\InvalidArgumentException;
use Ordinary\Router\Exception\LogicException;
use Ordinary\Router\Param\ParamBuilder;

/**
 * Collects route and parameter registrations, then compiles them into a Dispatcher.
 *
 * Register all params before registering routes that reference them.
 * Register all routes, then call compile() once.
 */
interface RouterInterface
{
    /**
     * Begin registering a named route parameter.
     *
     * Call a terminal method on the returned builder to define the constraint.
     * Must be called before any route that references {name} in its path.
     *
     * @throws InvalidArgumentException if $name is already registered
     */
    public function param(string $name): ParamBuilder;

    /**
     * Register a GET route.
     *
     * When autoHeadFromGet is enabled (the default), HEAD is also mapped to the same handler.
     *
     * @throws InvalidArgumentException if the path references an unregistered param
     * @throws LogicException if the method + path combination is already registered
     * @throws LogicException if $name is provided and already registered
     */
    public function get(string $path, mixed $handler, ?string $name = null): void;

    /**
     * @throws InvalidArgumentException if the path references an unregistered param
     * @throws LogicException if the method + path combination is already registered
     * @throws LogicException if $name is provided and already registered
     */
    public function post(string $path, mixed $handler, ?string $name = null): void;

    /**
     * @throws InvalidArgumentException if the path references an unregistered param
     * @throws LogicException if the method + path combination is already registered
     * @throws LogicException if $name is provided and already registered
     */
    public function put(string $path, mixed $handler, ?string $name = null): void;

    /**
     * @throws InvalidArgumentException if the path references an unregistered param
     * @throws LogicException if the method + path combination is already registered
     * @throws LogicException if $name is provided and already registered
     */
    public function patch(string $path, mixed $handler, ?string $name = null): void;

    /**
     * @throws InvalidArgumentException if the path references an unregistered param
     * @throws LogicException if the method + path combination is already registered
     * @throws LogicException if $name is provided and already registered
     */
    public function delete(string $path, mixed $handler, ?string $name = null): void;

    /**
     * @throws InvalidArgumentException if the path references an unregistered param
     * @throws LogicException if the method + path combination is already registered
     * @throws LogicException if $name is provided and already registered
     */
    public function head(string $path, mixed $handler, ?string $name = null): void;

    /**
     * @throws InvalidArgumentException if the path references an unregistered param
     * @throws LogicException if the method + path combination is already registered
     * @throws LogicException if $name is provided and already registered
     */
    public function options(string $path, mixed $handler, ?string $name = null): void;

    /**
     * Register a route for one or more HTTP methods.
     *
     * Accepts HttpMethod enum cases, raw uppercase strings, or a mix.
     * Non-standard methods (e.g. 'PURGE', 'PROPFIND') are accepted.
     *
     * @param non-empty-list<HttpMethod|string> $methods
     *
     * @throws InvalidArgumentException if the path references an unregistered param
     * @throws LogicException if any method + path combination is already registered
     * @throws LogicException if $name is provided and already registered
     */
    public function map(array $methods, string $path, mixed $handler, ?string $name = null): void;

    /**
     * Compile registered routes into an optimised dispatcher.
     *
     * If $cache is provided and contains a valid entry, compilation is skipped and
     * the cached route data is used directly. Handlers are always kept in memory.
     */
    public function compile(?CacheInterface $cache = null): DispatcherInterface&UrlGeneratorInterface;
}
