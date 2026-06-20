<?php

declare(strict_types=1);

namespace Ordinary\Router;

use Ordinary\Router\Exception\InvalidArgumentException;

/**
 * Generates URLs for named routes.
 */
interface UrlGeneratorInterface
{
    /**
     * Generate a URL for the named route, substituting the given param values.
     *
     * Param values are validated against their registered constraints before substitution.
     *
     * @param array<string, string|int> $params
     *
     * @throws InvalidArgumentException if the name is unknown
     * @throws InvalidArgumentException if a required param is missing
     * @throws InvalidArgumentException if a param value fails its registered constraint
     */
    public function generate(string $name, array $params = []): string;

    /**
     * Returns true if a route with the given name exists.
     */
    public function has(string $name): bool;
}
