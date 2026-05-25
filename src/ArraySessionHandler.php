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
 * In-memory session handler.
 *
 * Useful for tests. Not suitable for normal web requests.
 */
final class ArraySessionHandler implements SessionHandlerInterface
{
    /**
     * @var array<string, array{data: string, last_activity: int}>
     */
    private static array $storage = [];

    public function __construct(
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
        if (!isset(self::$storage[$id])) {
            return '';
        }

        $item = self::$storage[$id];

        if ($item['last_activity'] < time() - $this->ttl) {
            unset(self::$storage[$id]);

            return '';
        }

        return $item['data'];
    }

    public function write(string $id, string $data): bool
    {
        self::$storage[$id] = [
            'data' => $data,
            'last_activity' => time(),
        ];

        return true;
    }

    public function destroy(string $id): bool
    {
        unset(self::$storage[$id]);

        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        $deleted = 0;
        $expiresBefore = time() - $this->ttl;

        foreach (self::$storage as $id => $item) {
            if ($item['last_activity'] < $expiresBefore) {
                unset(self::$storage[$id]);
                $deleted++;
            }
        }

        return $deleted;
    }

    public static function reset(): void
    {
        self::$storage = [];
    }
}