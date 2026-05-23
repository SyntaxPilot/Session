<?php

declare(strict_types=1);

namespace Syntax\Session\Lock;

use SyntaxPilot\Session\Exception\SessionLockTimeoutException;
use InvalidArgumentException;
use Redis;
use SessionHandlerInterface;

/**
 * Redis-backed locking decorator for distributed session locking.
 *
 * Uses SET NX EX to acquire a lock and a Lua script to safely release it only
 * if the lock token still matches.
 */
final class RedisLockingSessionHandler implements SessionHandlerInterface
{
    private ?string $lockedId = null;

    private ?string $lockToken = null;

    public function __construct(
        private readonly SessionHandlerInterface $innerHandler,
        private readonly Redis $redis,
        private readonly string $prefix = 'session_lock:',
        private readonly int $lockTtlSeconds = 10,
        private readonly int $waitTimeoutSeconds = 5,
        private readonly int $retryMicroseconds = 50_000,
    ) {
        if ($this->lockTtlSeconds <= 0) {
            throw new InvalidArgumentException('Redis lock TTL must be greater than zero.');
        }

        if ($this->waitTimeoutSeconds <= 0) {
            throw new InvalidArgumentException('Redis lock wait timeout must be greater than zero.');
        }

        if ($this->retryMicroseconds <= 0) {
            throw new InvalidArgumentException('Redis lock retry delay must be greater than zero.');
        }
    }

    public function open(string $path, string $name): bool
    {
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
        if ($this->lockedId === $id && $this->lockToken !== null) {
            return;
        }

        $this->releaseLock();

        $key = $this->key($id);
        $token = bin2hex(random_bytes(32));
        $startedAt = microtime(true);

        do {
            $result = $this->redis->set(
                $key,
                $token,
                [
                    'nx',
                    'ex' => $this->lockTtlSeconds,
                ]
            );

            if ($result === true) {
                $this->lockedId = $id;
                $this->lockToken = $token;

                return;
            }

            usleep($this->retryMicroseconds);
        } while ((microtime(true) - $startedAt) < $this->waitTimeoutSeconds);

        throw new SessionLockTimeoutException('Timed out waiting for Redis session lock.');
    }

    private function releaseLock(): void
    {
        if ($this->lockedId === null || $this->lockToken === null) {
            return;
        }

        $script = <<<'LUA'
if redis.call("get", KEYS[1]) == ARGV[1] then
    return redis.call("del", KEYS[1])
end

return 0
LUA;

        $this->redis->eval($script, [$this->key($this->lockedId), $this->lockToken], 1);

        $this->lockedId = null;
        $this->lockToken = null;
    }

    private function key(string $id): string
    {
        return $this->prefix . hash('sha256', $id);
    }
}