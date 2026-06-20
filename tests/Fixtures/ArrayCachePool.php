<?php

declare(strict_types=1);

namespace Ordinary\Router\Tests\Fixtures;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Minimal in-memory PSR-6 pool for testing Psr6Cache.
 */
final class ArrayCachePool implements CacheItemPoolInterface
{
    /** @var array<string, mixed> */
    private array $stored = [];

    public function getItem(string $key): CacheItemInterface
    {
        if (\array_key_exists($key, $this->stored)) {
            return new ArrayCacheItem($key, $this->stored[$key], true);
        }

        return new ArrayCacheItem($key, null, false);
    }

    public function save(CacheItemInterface $item): bool
    {
        $this->stored[$item->getKey()] = $item->get();

        return true;
    }

    public function deleteItem(string $key): bool
    {
        unset($this->stored[$key]);

        return true;
    }

    public function hasItem(string $key): bool
    {
        return \array_key_exists($key, $this->stored);
    }

    public function clear(): bool
    {
        $this->stored = [];

        return true;
    }

    /**
     * @param string[] $keys
     *
     * @return iterable<string, CacheItemInterface>
     */
    public function getItems(array $keys = []): iterable
    {
        foreach ($keys as $key) {
            yield $key => $this->getItem($key);
        }
    }

    /** @param string[] $keys */
    public function deleteItems(array $keys): bool
    {
        foreach ($keys as $key) {
            $this->deleteItem($key);
        }

        return true;
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        return $this->save($item);
    }

    public function commit(): bool
    {
        return true;
    }
}
