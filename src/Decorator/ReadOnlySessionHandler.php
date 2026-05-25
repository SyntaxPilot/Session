<?php
/**
 * Copyright (c) 2027 Nicholas English
 *
 * This file is licensed under the MIT License.
 * See the LICENSE file in the project root for full license information.
 */

declare(strict_types=1);

namespace SyntaxPilot\Session\Decorator;

use SessionHandlerInterface;

/**
 * Read-only session handler decorator.
 *
 * Reads from the inner handler but ignores writes.
 */
final class ReadOnlySessionHandler implements SessionHandlerInterface
{
    public function __construct(
        private readonly SessionHandlerInterface $innerHandler,
    ) {
    }

    public function open(string $path, string $name): bool
    {
        return $this->innerHandler->open($path, $name);
    }

    public function close(): bool
    {
        return $this->innerHandler->close();
    }

    public function read(string $id): string|false
    {
        return $this->innerHandler->read($id);
    }

    public function write(string $id, string $data): bool
    {
        return true;
    }

    public function destroy(string $id): bool
    {
        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        return 0;
    }
}