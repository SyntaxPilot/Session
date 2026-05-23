<?php

declare(strict_types=1);

namespace SyntaxPilot\Session\Decorator;

use SessionHandlerInterface;

/**
 * Adds a prefix to session IDs before delegating to another handler.
 *
 * Useful when multiple applications share the same backend.
 */
final class PrefixSessionHandler implements SessionHandlerInterface
{
    public function __construct(
        private readonly SessionHandlerInterface $innerHandler,
        private readonly string $prefix,
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
        return $this->innerHandler->read($this->prefix . $id);
    }

    public function write(string $id, string $data): bool
    {
        return $this->innerHandler->write($this->prefix . $id, $data);
    }

    public function destroy(string $id): bool
    {
        return $this->innerHandler->destroy($this->prefix . $id);
    }

    public function gc(int $max_lifetime): int|false
    {
        return $this->innerHandler->gc($max_lifetime);
    }
}