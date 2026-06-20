<?php

declare(strict_types=1);

namespace Ordinary\Router;

/**
 * Standard HTTP methods as a backed enum.
 *
 * Pass these to map() for type safety, or use raw uppercase strings for
 * non-standard methods (e.g. 'PURGE', 'PROPFIND').
 */
enum HttpMethod: string
{
    case Get     = 'GET';
    case Post    = 'POST';
    case Put     = 'PUT';
    case Patch   = 'PATCH';
    case Delete  = 'DELETE';
    case Head    = 'HEAD';
    case Options = 'OPTIONS';
}
