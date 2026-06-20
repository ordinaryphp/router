<?php

declare(strict_types=1);

namespace Ordinary\Router\Exception;

/**
 * Thrown when an invalid argument is provided to the router API.
 *
 * Examples: unregistered param referenced in a route path, invalid regex pattern,
 * duplicate param name, or a non-BackedEnum class passed to ParamBuilder::enum().
 */
final class InvalidArgumentException extends \InvalidArgumentException implements ExceptionInterface {}
