<?php

declare(strict_types=1);

namespace SyntaxPilot\Session;

use SyntaxPilot\Session\Config\SessionConfig;
use SyntaxPilot\Session\Contract\CacheInterface;
use SyntaxPilot\Session\Contract\EventDispatcherInterface;
use SyntaxPilot\Session\Contract\MetricsRecorderInterface;
use SyntaxPilot\Session\Decorator\CompressedSessionHandler;
use SyntaxPilot\Session\Decorator\InstrumentedSessionHandler;
use SyntaxPilot\Session\Decorator\KeyRotatingEncryptedSessionHandler;
use SyntaxPilot\Session\Decorator\PrefixSessionHandler;
use SyntaxPilot\Session\Decorator\ReadOnlySessionHandler;
use SyntaxPilot\Session\Encryption\KeyRing;
use SyntaxPilot\Session\Exception\SessionConfigurationException;
use SyntaxPilot\Session\Lock\RedisLockingSessionHandler;
use MongoDB\Collection;
use PDO;
use Redis;
use SessionHandlerInterface;

/**
 * Builds session handler stacks from config.
 */
final class SessionHandlerFactory
{
    /**
     * @param array<string, PDO>            $pdoConnections
     * @param array<string, Redis>          $redisConnections
     * @param array<string, Collection>     $mongoCollections
     * @param array<string, CacheInterface> $caches
     */
    public function __construct(
        private readonly array $pdoConnections = [],
        private readonly array $redisConnections = [],
        private readonly array $mongoCollections = [],
        private readonly array $caches = [],
        private readonly ?MetricsRecorderInterface $metrics = null,
        private readonly ?EventDispatcherInterface $events = null,
    ) {
    }

    public function make(SessionConfig $config): SessionHandlerInterface
    {
        $handler = $this->makeBaseHandler($config);

        $prefix = $config->driverOptions['prefix_id'] ?? null;

        if (is_string($prefix) && $prefix !== '') {
            $handler = new PrefixSessionHandler($handler, $prefix);
        }

        if (($config->migration['enabled'] ?? false) === true) {
            $legacyConfig = $config->migration['legacy'] ?? null;

            if (!is_array($legacyConfig)) {
                throw new SessionConfigurationException('Legacy migration config is missing.');
            }

            $legacyHandler = $this->makeBaseHandler(SessionConfig::fromArray($legacyConfig));

            $handler = new MigratingSessionHandler(
                legacyHandler: $legacyHandler,
                newHandler: $handler,
                destroyLegacyAfterWrite: (bool) ($config->migration['destroy_legacy_after_write'] ?? true)
            );
        }

        if (($config->compression['enabled'] ?? false) === true) {
            $handler = new CompressedSessionHandler(
                innerHandler: $handler,
                level: (int) ($config->compression['level'] ?? 6)
            );
        }

        if (($config->encryption['enabled'] ?? false) === true) {
            $keyRingConfig = $config->encryption['key_ring'] ?? null;

            if (is_array($keyRingConfig)) {
                $keyRing = KeyRing::fromArray($keyRingConfig);
            } else {
                $keyRing = new KeyRing('current', [
                    'current' => $this->loadKey($config->encryption),
                ]);
            }

            $handler = new KeyRotatingEncryptedSessionHandler(
                innerHandler: $handler,
                keyRing: $keyRing
            );
        }

        if (($config->driverOptions['read_only'] ?? false) === true) {
            $handler = new ReadOnlySessionHandler($handler);
        }

        if (($config->locking['enabled'] ?? false) === true) {
            $type = (string) ($config->locking['type'] ?? 'file');

            if ($type === 'redis') {
                $handler = new RedisLockingSessionHandler(
                    innerHandler: $handler,
                    redis: $this->redis($config->locking['connection'] ?? 'default'),
                    prefix: (string) ($config->locking['prefix'] ?? 'session_lock:'),
                    lockTtlSeconds: (int) ($config->locking['ttl'] ?? 10),
                    waitTimeoutSeconds: (int) ($config->locking['timeout'] ?? 5),
                    retryMicroseconds: (int) ($config->locking['retry_microseconds'] ?? 50_000),
                );
            } else {
                $directory = $config->locking['directory'] ?? null;

                if (!is_string($directory) || $directory === '') {
                    throw new SessionConfigurationException('File lock directory is required.');
                }

                $handler = new LockingSessionHandler(
                    innerHandler: $handler,
                    lockDirectory: $directory,
                    lockTimeoutSeconds: (int) ($config->locking['timeout'] ?? 5),
                    lockRetryMicroseconds: (int) ($config->locking['retry_microseconds'] ?? 50_000),
                );
            }
        }

        if (($config->instrumentation['enabled'] ?? false) === true) {
            $handler = new InstrumentedSessionHandler(
                innerHandler: $handler,
                metrics: $this->metrics,
                events: $this->events,
                tags: [
                    'driver' => $config->driver,
                    'encrypted' => (bool) ($config->encryption['enabled'] ?? false),
                    'compressed' => (bool) ($config->compression['enabled'] ?? false),
                    'locked' => (bool) ($config->locking['enabled'] ?? false),
                ]
            );
        }

        return $handler;
    }

    private function makeBaseHandler(SessionConfig $config): SessionHandlerInterface
    {
        $options = $config->driverOptions;
        $ttl = $config->ttl;

        return match ($config->driver) {
            'native' => new NativeSessionHandler(),

            'file', 'filesystem' => new FilesystemSessionHandler(
                directory: $this->requireString($options, 'path', 'Session filesystem path is required.'),
                ttl: $ttl
            ),

            'pdo', 'database' => new PdoSessionHandler(
                pdo: $this->pdo($options['connection'] ?? 'default'),
                table: (string) ($options['table'] ?? 'sessions'),
                ttl: $ttl
            ),

            'redis' => new RedisSessionHandler(
                redis: $this->redis($options['connection'] ?? 'default'),
                prefix: (string) ($options['prefix'] ?? 'session:'),
                ttl: $ttl
            ),

            'mongodb', 'mongo' => new MongoDbSessionHandler(
                collection: $this->mongoCollection($options['collection'] ?? 'default'),
                ttl: $ttl
            ),

            'cache' => new CacheSessionHandler(
                cache: $this->cache($options['store'] ?? 'default'),
                prefix: (string) ($options['prefix'] ?? 'session:'),
                ttl: $ttl
            ),

            'cookie' => new CookieSessionHandler(
                cookieName: (string) ($options['cookie_name'] ?? 'APP_SESSION_DATA'),
                key: $this->loadKey($config->encryption ?: $options),
                cookieOptions: is_array($config->cookie) ? $config->cookie : []
            ),

            'array' => new ArraySessionHandler(ttl: $ttl),

            'null' => new NullSessionHandler(),

            default => throw new SessionConfigurationException("Unsupported session driver [{$config->driver}]."),
        };
    }

    private function pdo(mixed $name): PDO
    {
        $name = is_string($name) ? $name : 'default';

        return $this->pdoConnections[$name]
            ?? throw new SessionConfigurationException("PDO connection [{$name}] is not registered.");
    }

    private function redis(mixed $name): Redis
    {
        $name = is_string($name) ? $name : 'default';

        return $this->redisConnections[$name]
            ?? throw new SessionConfigurationException("Redis connection [{$name}] is not registered.");
    }

    private function mongoCollection(mixed $name): Collection
    {
        $name = is_string($name) ? $name : 'default';

        return $this->mongoCollections[$name]
            ?? throw new SessionConfigurationException("MongoDB collection [{$name}] is not registered.");
    }

    private function cache(mixed $name): CacheInterface
    {
        $name = is_string($name) ? $name : 'default';

        return $this->caches[$name]
            ?? throw new SessionConfigurationException("Cache store [{$name}] is not registered.");
    }

    /**
     * @param array<string, mixed> $config
     */
    private function loadKey(array $config): string
    {
        if (isset($config['key']) && is_string($config['key'])) {
            return $this->validateKey($config['key']);
        }

        if (isset($config['key_base64']) && is_string($config['key_base64'])) {
            $key = base64_decode(trim($config['key_base64']), true);

            if ($key === false) {
                throw new SessionConfigurationException('Invalid base64 session encryption key.');
            }

            return $this->validateKey($key);
        }

        if (isset($config['key_file']) && is_string($config['key_file'])) {
            if (!is_file($config['key_file']) || !is_readable($config['key_file'])) {
                throw new SessionConfigurationException('Session encryption key file is missing or unreadable.');
            }

            $key = base64_decode(trim((string) file_get_contents($config['key_file'])), true);

            if ($key === false) {
                throw new SessionConfigurationException('Session encryption key file does not contain valid base64.');
            }

            return $this->validateKey($key);
        }

        throw new SessionConfigurationException('Session encryption key is missing.');
    }

    private function validateKey(string $key): string
    {
        if (strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new SessionConfigurationException(
                'Session encryption key must be exactly ' .
                SODIUM_CRYPTO_SECRETBOX_KEYBYTES .
                ' bytes.'
            );
        }

        return $key;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function requireString(array $config, string $key, string $message): string
    {
        $value = $config[$key] ?? null;

        if (!is_string($value) || $value === '') {
            throw new SessionConfigurationException($message);
        }

        return $value;
    }
}