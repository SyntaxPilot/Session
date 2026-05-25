<?php
/**
 * Copyright (c) 2027 Nicholas English
 *
 * This file is licensed under the MIT License.
 * See the LICENSE file in the project root for full license information.
 */

declare(strict_types=1);

namespace SyntaxPilot\Session;

use SessionHandlerInterface;

/**
 * Migrating session handler decorator.
 *
 * Reads from the new handler first, then legacy. Writes always go to the new
 * handler. Useful for moving users from one storage backend to another.
 */
final class MigratingSessionHandler implements SessionHandlerInterface
{
    private bool $readFromLegacy = false;

    public function __construct(
        private readonly SessionHandlerInterface $legacyHandler,
        private readonly SessionHandlerInterface $newHandler,
        private readonly bool $destroyLegacyAfterWrite = true,
    ) {
    }

    public function open(string $path, string $name): bool
    {
        $legacyOpened = $this->legacyHandler->open($path, $name);
        $newOpened = $this->newHandler->open($path, $name);

        return $legacyOpened && $newOpened;
    }

    public function close(): bool
    {
        $legacyClosed = $this->legacyHandler->close();
        $newClosed = $this->newHandler->close();

        return $legacyClosed && $newClosed;
    }

    public function read(string $id): string|false
    {
        $this->readFromLegacy = false;

        $newData = $this->newHandler->read($id);

        if ($newData !== false && $newData !== '') {
            return $newData;
        }

        $legacyData = $this->legacyHandler->read($id);

        if ($legacyData !== false && $legacyData !== '') {
            $this->readFromLegacy = true;

            return $legacyData;
        }

        return '';
    }

    public function write(string $id, string $data): bool
    {
        $written = $this->newHandler->write($id, $data);

        if ($written && $this->readFromLegacy && $this->destroyLegacyAfterWrite) {
            $this->legacyHandler->destroy($id);
            $this->readFromLegacy = false;
        }

        return $written;
    }

    public function destroy(string $id): bool
    {
        $legacyDestroyed = $this->legacyHandler->destroy($id);
        $newDestroyed = $this->newHandler->destroy($id);

        return $legacyDestroyed && $newDestroyed;
    }

    public function gc(int $max_lifetime): int|false
    {
        $legacyDeleted = $this->legacyHandler->gc($max_lifetime);
        $newDeleted = $this->newHandler->gc($max_lifetime);

        if ($legacyDeleted === false || $newDeleted === false) {
            return false;
        }

        return $legacyDeleted + $newDeleted;
    }
}