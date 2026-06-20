<?php

declare(strict_types=1);

namespace Ordinary\Router;

/**
 * The outcome of a dispatch call.
 *
 * Use an exhaustive match expression on this value — all four cases must be handled.
 */
enum MatchStatus
{
    /** A route was found and all parameter constraints passed. */
    case Found;

    /** No route matched the given path. */
    case NotFound;

    /** A route matched the path but not the HTTP method. */
    case MethodNotAllowed;

    /** The path had a trailing slash; the caller should redirect to the canonical URL. */
    case RedirectRequired;
}
