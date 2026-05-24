<?php

declare(strict_types=1);

namespace SyntaxPilot\Session;

use InvalidArgumentException;
use RuntimeException;
use SessionHandlerInterface;

/**
 * Encrypting session handler decorator.
 */
final class EncryptedSessionHandler implements SessionHandlerInterface
{
    private const NONCE_BYTES = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;

    private const KEY_BYTES = SODIUM_CRYPTO_SECRETBOX_KEYBYTES;

    public function __construct(
        private readonly SessionHandlerInterface $innerHandler,
        private readonly string $key,
    ) {
        if (!extension_loaded('sodium')) {
            throw new RuntimeException('The Sodium PHP extension is required.');
        }

        if (strlen($this->key) !== self::KEY_BYTES) {
            throw new InvalidArgumentException(
                'Session encryption key must be exactly ' . self::KEY_BYTES . ' bytes.'
            );
        }
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
        $payload = $this->innerHandler->read($id);

        if ($payload === false || $payload === '') {
            return '';
        }

        $decoded = base64_decode($payload, true);

        if ($decoded === false || strlen($decoded) < self::NONCE_BYTES) {
            return '';
        }

        $nonce = substr($decoded, 0, self::NONCE_BYTES);
        $ciphertext = substr($decoded, self::NONCE_BYTES);

        $plaintext = sodium_crypto_secretbox_open(
            $ciphertext,
            $nonce,
            $this->key
        );

        return $plaintext === false ? '' : $plaintext;
    }

    public function write(string $id, string $data): bool
    {
        $nonce = random_bytes(self::NONCE_BYTES);

        $ciphertext = sodium_crypto_secretbox(
            $data,
            $nonce,
            $this->key
        );

        return $this->innerHandler->write(
            $id,
            base64_encode($nonce . $ciphertext)
        );
    }

    public function destroy(string $id): bool
    {
        return $this->innerHandler->destroy($id);
    }

    public function gc(int $max_lifetime): int|false
    {
        return $this->innerHandler->gc($max_lifetime);
    }
}