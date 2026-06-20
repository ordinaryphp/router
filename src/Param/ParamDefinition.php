<?php

declare(strict_types=1);

namespace Ordinary\Router\Param;

/**
 * Immutable definition of a named route parameter.
 *
 * Created by ParamBuilder terminal methods and stored in the Router's
 * parameter registry. Used by the compiler to build route regexes and
 * by the dispatcher for post-match range validation.
 */
final readonly class ParamDefinition
{
    /**
     * @param string $pattern PCRE pattern fragment (no delimiters, no anchors)
     * @param int|null $rangeMin Lower bound for integer/range params; null means no lower bound
     * @param int|null $rangeMax Upper bound for integer/range params; null means no upper bound
     * @param bool $isWildcard True for wildcard params — these match across path separators
     */
    public function __construct(
        public string $name,
        public string $pattern,
        public ?int $rangeMin = null,
        public ?int $rangeMax = null,
        public bool $isWildcard = false,
    ) {}
}
