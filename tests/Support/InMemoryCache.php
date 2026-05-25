<?php

declare(strict_types=1);

namespace SyntaxPilot\Tests\Session\Support;

use DateInterval;
use DateTimeImmutable;
use SyntaxPilot\Session\CacheInterface;

final class InMemoryCache implements CacheInterface
{
    /**
     * @var array<string, array{value: mixed, expires_at: int|null}>
     */
    private array $items = [];

    public function get(string $key, mixed $default = null): mixed
    {
        if (!isset($this->items[$key])) {
            return $default;
        }

        $item = $this->items[$key];

        if ($item['expires_at'] !== null && $item['expires_at'] <= time()) {
            unset($this->items[$key]);

            return $default;
        }

        return $item['value'];
    }

    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        $expiresAt = null;

        if (is_int($ttl)) {
            $expiresAt = time() + $ttl;
        }

        if ($ttl instanceof DateInterval) {
            $expiresAt = (new DateTimeImmutable())->add($ttl)->getTimestamp();
        }

        $this->items[$key] = [
            'value' => $value,
            'expires_at' => $expiresAt,
        ];

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->items[$key]);

        return true;
    }
}