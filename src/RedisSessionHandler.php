<?php
/**
 * Copyright (c) 2027 Nicholas English
 *
 * This file is licensed under the MIT License.
 * See the LICENSE file in the project root for full license information.
 */

declare(strict_types=1);

namespace SyntaxPilot\Session;

use InvalidArgumentException;
use Redis;
use SessionHandlerInterface;

/**
 * Redis-backed session handler.
 */
final class RedisSessionHandler implements SessionHandlerInterface
{
    public function __construct(
        private readonly Redis $redis,
        private readonly string $prefix = 'session:',
        private readonly int $ttl = 1440,
    ) {
        if ($this->ttl <= 0) {
            throw new InvalidArgumentException('Session TTL must be greater than zero.');
        }
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $data = $this->redis->get($this->key($id));

        return $data === false ? '' : (string) $data;
    }

    public function write(string $id, string $data): bool
    {
        return (bool) $this->redis->setex(
            $this->key($id),
            $this->ttl,
            $data
        );
    }

    public function destroy(string $id): bool
    {
        $this->redis->del($this->key($id));

        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        return 0;
    }

    private function key(string $id): string
    {
        return $this->prefix . $id;
    }
}