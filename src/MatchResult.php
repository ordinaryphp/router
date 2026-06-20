<?php

declare(strict_types=1);

namespace Ordinary\Router;

/**
 * Immutable result of a dispatch call.
 */
final readonly class MatchResult implements MatchResultInterface
{
    /**
     * @param array<string, string> $params
     * @param list<string> $allowedMethods
     */
    public function __construct(
        public MatchStatus $status,
        public mixed $handler = null,
        public array $params = [],
        public array $allowedMethods = [],
        public ?string $redirectTo = null,
    ) {}
}
