<?php

declare(strict_types=1);

namespace Ordinary\Router;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Dispatches incoming HTTP requests against the compiled route table.
 */
interface DispatcherInterface
{
    /**
     * Dispatch by raw HTTP method and path strings.
     *
     * Prefer this in long-running processes where you control the method/path extraction.
     */
    public function dispatch(string $method, string $path): MatchResultInterface;

    /**
     * Convenience wrapper: extracts method and path from a PSR-7 request.
     */
    public function dispatchRequest(ServerRequestInterface $request): MatchResultInterface;
}
