<?php

declare(strict_types=1);

namespace Ordinary\Router\Exception;

/**
 * Marker interface for all exceptions thrown by ordinary/router.
 *
 * Catch this to handle any error from this package in a single clause.
 */
interface ExceptionInterface extends \Throwable {}
