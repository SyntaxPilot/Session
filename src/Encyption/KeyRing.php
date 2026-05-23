<?php

declare(strict_types=1);

namespace SyntaxPilot\Session\Encryption;

use SyntaxPilot\Session\Exception\SessionConfigurationException;

/**
 * Holds current and previous encryption keys for key rotation.
 */
final class KeyRing
{
    /**
     * @param array<string, string> $keys Key ID => raw binary key.
     */
    public function __construct(
        private readonly string $currentKeyId,
        private readonly array $keys,
    ) {
        if ($this->currentKeyId === '') {
            throw new SessionConfigurationException('Current key ID cannot be empty.');
        }

        if (!isset($this->keys[$this->currentKeyId])) {
            throw new SessionConfigurationException('Current key ID does not exist in key ring.');
        }

        foreach ($this->keys as $id => $key) {
            if (!is_string($id) || $id === '') {
                throw new SessionConfigurationException('Key ring contains an invalid key ID.');
            }

            if (strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
                throw new SessionConfigurationException(
                    "Session encryption key [{$id}] must be exactly " .
                    SODIUM_CRYPTO_SECRETBOX_KEYBYTES .
                    ' bytes.'
                );
            }
        }
    }

    public function currentKeyId(): string
    {
        return $this->currentKeyId;
    }

    public function currentKey(): string
    {
        return $this->keys[$this->currentKeyId];
    }

    public function get(string $id): ?string
    {
        return $this->keys[$id] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->keys;
    }

    /**
     * Create a key ring from config.
     *
     * Supported:
     * [
     *   'current' => 'v2',
     *   'keys' => [
     *     'v2' => ['key_file' => '/path/key-v2.txt'],
     *     'v1' => ['key_base64' => '...'],
     *   ]
     * ]
     *
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        $current = $config['current'] ?? null;
        $keysConfig = $config['keys'] ?? null;

        if (!is_string($current) || $current === '') {
            throw new SessionConfigurationException('Key ring current key ID is required.');
        }

        if (!is_array($keysConfig)) {
            throw new SessionConfigurationException('Key ring keys are required.');
        }

        $keys = [];

        foreach ($keysConfig as $id => $keyConfig) {
            if (!is_string($id) || $id === '') {
                throw new SessionConfigurationException('Invalid key ID in key ring.');
            }

            if (is_string($keyConfig)) {
                $decoded = base64_decode(trim($keyConfig), true);

                if ($decoded === false) {
                    throw new SessionConfigurationException("Invalid base64 key for key ID [{$id}].");
                }

                $keys[$id] = $decoded;
                continue;
            }

            if (!is_array($keyConfig)) {
                throw new SessionConfigurationException("Invalid key config for key ID [{$id}].");
            }

            $keys[$id] = self::loadKey($keyConfig);
        }

        return new self($current, $keys);
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function loadKey(array $config): string
    {
        if (isset($config['key']) && is_string($config['key'])) {
            return $config['key'];
        }

        if (isset($config['key_base64']) && is_string($config['key_base64'])) {
            $key = base64_decode(trim($config['key_base64']), true);

            if ($key === false) {
                throw new SessionConfigurationException('Invalid base64 encryption key.');
            }

            return $key;
        }

        if (isset($config['key_file']) && is_string($config['key_file'])) {
            if (!is_file($config['key_file']) || !is_readable($config['key_file'])) {
                throw new SessionConfigurationException('Encryption key file is missing or unreadable.');
            }

            $key = base64_decode(trim((string) file_get_contents($config['key_file'])), true);

            if ($key === false) {
                throw new SessionConfigurationException('Encryption key file does not contain valid base64.');
            }

            return $key;
        }

        throw new SessionConfigurationException('Encryption key configuration is missing.');
    }
}