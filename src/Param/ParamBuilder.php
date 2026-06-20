<?php

declare(strict_types=1);

namespace Ordinary\Router\Param;

use Ordinary\Router\Exception\InvalidArgumentException;

/**
 * Fluent builder returned by Router::param().
 *
 * Call exactly one terminal method to define the parameter's constraint.
 * The terminal method registers the param in the router immediately.
 */
final readonly class ParamBuilder
{
    /** @param \Closure(ParamDefinition): void $register */
    public function __construct(
        private string $name,
        private \Closure $register,
    ) {}

    /**
     * Match against a custom regular expression.
     *
     * @param non-empty-string $regex PCRE pattern fragment without delimiters or anchors
     *
     * @throws InvalidArgumentException if the regex is syntactically invalid
     */
    public function pattern(string $regex): void
    {
        $this->assertValidRegex($regex);
        ($this->register)(new ParamDefinition(name: $this->name, pattern: $regex));
    }

    /**
     * Match one or more ASCII digits, then validate the integer is within [$min, $max].
     *
     * Accepts negative values when $min is negative (prepends -? to the pattern).
     */
    public function integer(int $min = PHP_INT_MIN, int $max = PHP_INT_MAX): void
    {
        $pattern = $min < 0 ? '-?\d+' : '\d+';
        ($this->register)(new ParamDefinition(
            name: $this->name,
            pattern: $pattern,
            rangeMin: $min,
            rangeMax: $max,
        ));
    }

    /**
     * Match a positive integer within [$min, $max].
     *
     * Equivalent to integer() with explicit bounds — reads more clearly when both are provided.
     */
    public function range(int $min, int $max): void
    {
        ($this->register)(new ParamDefinition(
            name: $this->name,
            pattern: '\d+',
            rangeMin: $min,
            rangeMax: $max,
        ));
    }

    /**
     * Match only the string values of a BackedEnum.
     *
     * The generated regex is an alternation of all case values.
     *
     * @throws InvalidArgumentException if $enumClass is not a BackedEnum class
     */
    public function enum(string $enumClass): void
    {
        if (!\is_a($enumClass, \BackedEnum::class, true)) {
            throw new InvalidArgumentException(
                \sprintf('"%s" must be a BackedEnum class', $enumClass),
            );
        }

        /** @var class-string<\BackedEnum> $enumClass */
        $cases = $enumClass::cases();
        if ($cases === []) {
            throw new InvalidArgumentException(
                \sprintf('BackedEnum "%s" has no cases', $enumClass),
            );
        }

        $values = \array_map(
            static fn(\BackedEnum $case): string => \preg_quote((string) $case->value, '~'),
            $cases,
        );
        $pattern = \implode('|', $values);

        ($this->register)(new ParamDefinition(name: $this->name, pattern: $pattern));
    }

    /**
     * Match lowercase alphanumeric characters and hyphens: [a-z0-9][a-z0-9-]*.
     */
    public function slug(): void
    {
        ($this->register)(new ParamDefinition(name: $this->name, pattern: '[a-z0-9][a-z0-9-]*'));
    }

    /**
     * Match a UUID v4 (case-insensitive).
     */
    public function uuid(): void
    {
        ($this->register)(new ParamDefinition(
            name: $this->name,
            pattern: '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}',
        ));
    }

    /**
     * Match ASCII letters only: [a-zA-Z]+.
     */
    public function alpha(): void
    {
        ($this->register)(new ParamDefinition(name: $this->name, pattern: '[a-zA-Z]+'));
    }

    /**
     * Match ASCII letters and digits: [a-zA-Z0-9]+.
     */
    public function alphanumeric(): void
    {
        ($this->register)(new ParamDefinition(name: $this->name, pattern: '[a-zA-Z0-9]+'));
    }

    /**
     * Match any non-slash character sequence: [^/]+.
     */
    public function any(): void
    {
        ($this->register)(new ParamDefinition(name: $this->name, pattern: '[^/]+'));
    }

    /**
     * Match one or more characters including path separators.
     *
     * Must be the last segment in the route path.
     * Use for file paths or catch-all segments that span multiple directories.
     */
    public function wildcard(): void
    {
        ($this->register)(new ParamDefinition(
            name: $this->name,
            pattern: '.+',
            isWildcard: true,
        ));
    }

    /**
     * @param non-empty-string $regex
     *
     * @throws InvalidArgumentException
     */
    private function assertValidRegex(string $regex): void
    {
        if (@\preg_match('~' . $regex . '~', '') === false) {
            throw new InvalidArgumentException(
                \sprintf('Invalid regex pattern for param "%s": %s', $this->name, $regex),
            );
        }
    }
}
