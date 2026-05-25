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
use SessionHandlerInterface;

/**
 * Generic cache-backed session handler.
 */
final class CacheSessionHandler implements SessionHandlerInterface
{
    public function __construct(
        private readonly CacheInterface $cache,
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
        $data = $this->cache->get($this->key($id), '');

        return is_string($data) ? $data : '';
    }

    public function write(string $id, string $data): bool
    {
        return $this->cache->set(
            $this->key($id),
            $data,
            $this->ttl
        );
    }

    public function destroy(string $id): bool
    {
        return $this->cache->delete($this->key($id));
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