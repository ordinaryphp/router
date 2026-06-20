<?php

declare(strict_types=1);

namespace Ordinary\Router;

/**
 * The result of a dispatch call.
 *
 * Always check $status before accessing $handler or $params.
 * When status is Found, $handler is non-null and $params contains validated values.
 * When status is MethodNotAllowed, $allowedMethods lists the accepted methods.
 * When status is RedirectRequired, $redirectTo contains the canonical URL.
 */
interface MatchResultInterface
{
    public MatchStatus $status { get; }

    /** Non-null only when status is Found. */
    public mixed $handler { get; }

    /** @var array<string, string>  Populated only when status is Found */
    public array $params { get; }

    /** @var list<string>  Populated only when status is MethodNotAllowed */
    public array $allowedMethods { get; }

    /** Non-null only when status is RedirectRequired. */
    public ?string $redirectTo { get; }
}
