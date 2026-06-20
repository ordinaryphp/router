<?php

declare(strict_types=1);

namespace Ordinary\Router\Compiler;

/**
 * A compiled representation of all HTTP methods registered for one dynamic path pattern.
 *
 * Multiple methods sharing the same path (GET + PUT + DELETE on /books/{bookId})
 * compile to a single entry so the regex is only evaluated once per dispatch.
 */
final readonly class CompiledDynamicEntry
{
    /**
     * @param non-empty-string $regex Full PCRE regex including delimiters and anchors
     * @param list<string> $paramNames Ordered list of named capture groups in the regex
     * @param array<string, int> $methodRoutes HTTP method => route index in the handler array
     */
    public function __construct(
        public string $regex,
        public array $paramNames,
        public array $methodRoutes,
    ) {}
}
