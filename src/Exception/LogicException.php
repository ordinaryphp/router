<?php

declare(strict_types=1);

namespace Ordinary\Router\Exception;

/**
 * Thrown when the router is used in a way that violates its invariants.
 *
 * Examples: registering the same HTTP method + path combination twice,
 * registering the same route name twice.
 */
final class LogicException extends \LogicException implements ExceptionInterface {}
