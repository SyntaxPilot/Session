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
use RuntimeException;
use SessionHandlerInterface;

/**
 * File-locking session handler decorator.
 *
 * Adds a filesystem lock around any other session handler.
 */
final class LockingSessionHandler implements SessionHandlerInterface
{
    /**
     * @var resource|null
     */
    private $lockHandle = null;

    private ?string $lockedId = null;

    public function __construct(
        private readonly SessionHandlerInterface $innerHandler,
        private readonly string $lockDirectory,
        private readonly int $lockTimeoutSeconds = 5,
        private readonly int $lockRetryMicroseconds = 50_000,
    ) {
        if ($this->lockTimeoutSeconds <= 0) {
            throw new InvalidArgumentException('Lock timeout must be greater than zero.');
        }

        if ($this->lockRetryMicroseconds <= 0) {
            throw new InvalidArgumentException('Lock retry delay must be greater than zero.');
        }
    }

    public function open(string $path, string $name): bool
    {
        if (!is_dir($this->lockDirectory)) {
            if (!mkdir($this->lockDirectory, 0700, true) && !is_dir($this->lockDirectory)) {
                return false;
            }
        }

        return $this->innerHandler->open($path, $name);
    }

    public function close(): bool
    {
        $closed = $this->innerHandler->close();

        $this->releaseLock();

        return $closed;
    }

    public function read(string $id): string|false
    {
        $this->acquireLock($id);

        return $this->innerHandler->read($id);
    }

    public function write(string $id, string $data): bool
    {
        if ($this->lockedId !== $id) {
            $this->acquireLock($id);
        }

        return $this->innerHandler->write($id, $data);
    }

    public function destroy(string $id): bool
    {
        if ($this->lockedId !== $id) {
            $this->acquireLock($id);
        }

        return $this->innerHandler->destroy($id);
    }

    public function gc(int $max_lifetime): int|false
    {
        return $this->innerHandler->gc($max_lifetime);
    }

    private function acquireLock(string $id): void
    {
        if ($this->lockedId === $id && is_resource($this->lockHandle)) {
            return;
        }

        $this->releaseLock();

        $file = $this->lockDirectory . DIRECTORY_SEPARATOR . 'lock_' . hash('sha256', $id);

        $handle = fopen($file, 'c');

        if ($handle === false) {
            throw new RuntimeException('Unable to open session lock file.');
        }

        $startedAt = microtime(true);

        do {
            if (flock($handle, LOCK_EX | LOCK_NB)) {
                $this->lockHandle = $handle;
                $this->lockedId = $id;

                return;
            }

            usleep($this->lockRetryMicroseconds);
        } while ((microtime(true) - $startedAt) < $this->lockTimeoutSeconds);

        fclose($handle);

        throw new RuntimeException('Timed out waiting for session lock.');
    }

    private function releaseLock(): void
    {
        if (is_resource($this->lockHandle)) {
            flock($this->lockHandle, LOCK_UN);
            fclose($this->lockHandle);
        }

        $this->lockHandle = null;
        $this->lockedId = null;
    }
}