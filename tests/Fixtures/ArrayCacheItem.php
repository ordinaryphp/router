<?php

declare(strict_types=1);

namespace Ordinary\Router\Tests\Fixtures;

use Psr\Cache\CacheItemInterface;

/**
 * Minimal PSR-6 cache item for testing Psr6Cache.
 */
final class ArrayCacheItem implements CacheItemInterface
{
    public function __construct(private readonly string $key, private mixed $value, private readonly bool $hit) {}

    public function getKey(): string
    {
        return $this->key;
    }

    public function get(): mixed
    {
        return $this->value;
    }

    public function isHit(): bool
    {
        return $this->hit;
    }

    public function set(mixed $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function expiresAt(?\DateTimeInterface $expiration): static
    {
        return $this;
    }

    public function expiresAfter(\DateInterval|int|null $time): static
    {
        return $this;
    }
}
