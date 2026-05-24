<?php

declare(strict_types=1);

namespace SyntaxPilot\Session;

use SessionHandler;
use SessionHandlerInterface;

/**
 * Adapter for PHP's native file-based session handler.
 */
final class NativeSessionHandler implements SessionHandlerInterface
{
    private SessionHandler $handler;

    public function __construct(?SessionHandler $handler = null)
    {
        $this->handler = $handler ?? new SessionHandler();
    }

    public function open(string $path, string $name): bool
    {
        return $this->handler->open($path, $name);
    }

    public function close(): bool
    {
        return $this->handler->close();
    }

    public function read(string $id): string|false
    {
        return $this->handler->read($id);
    }

    public function write(string $id, string $data): bool
    {
        return $this->handler->write($id, $data);
    }

    public function destroy(string $id): bool
    {
        return $this->handler->destroy($id);
    }

    public function gc(int $max_lifetime): int|false
    {
        return $this->handler->gc($max_lifetime);
    }
}