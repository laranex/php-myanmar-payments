<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\Support;

use DateInterval;
use DateTimeImmutable;
use Psr\SimpleCache\CacheInterface;

/**
 * In-memory PSR-16 cache used when no cache is given. It only lives for the current PHP process.
 */
final class ArrayCache implements CacheInterface
{
    /**
     * @var array<string, array{value: mixed, expiresAt: int|null}>
     */
    private array $items = [];

    public function get(string $key, mixed $default = null): mixed
    {
        if (! $this->has($key)) {
            return $default;
        }

        return $this->items[$key]['value'];
    }

    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        $this->items[$key] = ['value' => $value, 'expiresAt' => $this->expiresAt($ttl)];

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->items[$key]);

        return true;
    }

    public function clear(): bool
    {
        $this->items = [];

        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $values = [];

        foreach ($keys as $key) {
            $values[$key] = $this->get($key, $default);
        }

        return $values;
    }

    /**
     * @param  iterable<string, mixed>  $values
     */
    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }

        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    public function has(string $key): bool
    {
        if (! isset($this->items[$key])) {
            return false;
        }

        $expiresAt = $this->items[$key]['expiresAt'];

        if ($expiresAt !== null && $expiresAt <= time()) {
            unset($this->items[$key]);

            return false;
        }

        return true;
    }

    private function expiresAt(null|int|DateInterval $ttl): ?int
    {
        if ($ttl === null) {
            return null;
        }

        if ($ttl instanceof DateInterval) {
            return (new DateTimeImmutable)->add($ttl)->getTimestamp();
        }

        return time() + $ttl;
    }
}
