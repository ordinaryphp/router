<?php

declare(strict_types=1);

namespace Ordinary\Router;

/**
 * Controls how the dispatcher handles paths with a trailing slash.
 */
enum TrailingSlashMode
{
    /**
     * /foo and /foo/ are distinct routes.
     * Register them separately if both should match.
     */
    case Strict;

    /**
     * The trailing slash is stripped before matching.
     * /foo/ dispatches as /foo without informing the caller.
     */
    case Ignore;

    /**
     * A trailing slash triggers a MatchStatus::RedirectRequired result
     * pointing to the canonical (slash-free) URL.
     * The root path / is never redirected.
     */
    case Redirect;
}
