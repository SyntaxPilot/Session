<?php
/**
 * Copyright (c) 2027 Nicholas English
 *
 * This file is licensed under the MIT License.
 * See the LICENSE file in the project root for full license information.
 */

declare(strict_types=1);

namespace SyntaxPilot\Session\Config;

use SyntaxPilot\Session\Exception\SessionConfigurationException;

/**
 * Typed session configuration value object.
 */
final class SessionConfig
{
    /**
     * @param array<string, mixed> $cookie
     * @param array<string, mixed> $driverOptions
     * @param array<string, mixed> $encryption
     * @param array<string, mixed> $locking
     * @param array<string, mixed> $migration
     * @param array<string, mixed> $compression
     * @param array<string, mixed> $instrumentation
     */
    public function __construct(
        public readonly string $name = 'APP_SESSION',
        public readonly string $driver = 'native',
        public readonly int $ttl = 1440,
        public readonly array $cookie = [],
        public readonly array $driverOptions = [],
        public readonly array $encryption = [],
        public readonly array $locking = [],
        public readonly array $migration = [],
        public readonly array $compression = [],
        public readonly array $instrumentation = [],
        public readonly bool $strictMode = true,
        public readonly bool $onlyCookies = true,
        public readonly bool $lazy = false,
    ) {
        $this->validate();
    }

    /**
     * Create config from array.
     *
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            name: (string) ($config['name'] ?? 'APP_SESSION'),
            driver: (string) ($config['driver'] ?? 'native'),
            ttl: (int) ($config['ttl'] ?? 1440),
            cookie: is_array($config['cookie'] ?? null) ? $config['cookie'] : [],
            driverOptions: is_array($config['options'] ?? null) ? $config['options'] : $config,
            encryption: is_array($config['encryption'] ?? null) ? $config['encryption'] : [],
            locking: is_array($config['locking'] ?? null) ? $config['locking'] : [],
            migration: is_array($config['migration'] ?? null) ? $config['migration'] : [],
            compression: is_array($config['compression'] ?? null) ? $config['compression'] : [],
            instrumentation: is_array($config['instrumentation'] ?? null) ? $config['instrumentation'] : [],
            strictMode: (bool) ($config['strict_mode'] ?? true),
            onlyCookies: (bool) ($config['only_cookies'] ?? true),
            lazy: (bool) ($config['lazy'] ?? false),
        );
    }

    /**
     * Validate session configuration.
     */
    private function validate(): void
    {
        if ($this->name === '') {
            throw new SessionConfigurationException('Session name cannot be empty.');
        }

        if ($this->ttl <= 0) {
            throw new SessionConfigurationException('Session TTL must be greater than zero.');
        }

        $sameSite = $this->cookie['samesite'] ?? $this->cookie['sameSite'] ?? null;

        if ($sameSite === 'None' && ($this->cookie['secure'] ?? false) !== true) {
            throw new SessionConfigurationException('SameSite=None requires Secure=true.');
        }

        if (str_starts_with($this->name, '__Host-')) {
            if (($this->cookie['secure'] ?? false) !== true) {
                throw new SessionConfigurationException('__Host- session cookies require Secure=true.');
            }

            if (($this->cookie['path'] ?? '/') !== '/') {
                throw new SessionConfigurationException('__Host- session cookies require Path=/.');
            }

            if (($this->cookie['domain'] ?? '') !== '') {
                throw new SessionConfigurationException('__Host- session cookies must not set Domain.');
            }
        }

        if (str_starts_with($this->name, '__Secure-')) {
            if (($this->cookie['secure'] ?? false) !== true) {
                throw new SessionConfigurationException('__Secure- session cookies require Secure=true.');
            }
        }
    }
}