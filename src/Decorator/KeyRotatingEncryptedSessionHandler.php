<?php

declare(strict_types=1);

namespace SyntaxPilot\Session\Decorator;

use SyntaxPilot\Session\Encryption\KeyRing;
use RuntimeException;
use SessionHandlerInterface;

/**
 * Encrypted session handler with key rotation support.
 *
 * Payload format:
 *
 * v1:<keyId>:<base64url(nonce + ciphertext)>
 */
final class KeyRotatingEncryptedSessionHandler implements SessionHandlerInterface
{
    private const VERSION = 'v1';
    private const NONCE_BYTES = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;

    public function __construct(
        private readonly SessionHandlerInterface $innerHandler,
        private readonly KeyRing $keyRing,
    ) {
        if (!extension_loaded('sodium')) {
            throw new RuntimeException('The Sodium PHP extension is required.');
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

        $parts = explode(':', $payload, 3);

        if (count($parts) !== 3 || $parts[0] !== self::VERSION) {
            return '';
        }

        [$version, $keyId, $encoded] = $parts;

        $decoded = $this->base64UrlDecode($encoded);

        if ($decoded === false || strlen($decoded) < self::NONCE_BYTES) {
            return '';
        }

        $nonce = substr($decoded, 0, self::NONCE_BYTES);
        $ciphertext = substr($decoded, self::NONCE_BYTES);

        $preferredKey = $this->keyRing->get($keyId);

        if ($preferredKey !== null) {
            $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $preferredKey);

            if ($plaintext !== false) {
                return $plaintext;
            }
        }

        foreach ($this->keyRing->all() as $key) {
            $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);

            if ($plaintext !== false) {
                return $plaintext;
            }
        }

        return '';
    }

    public function write(string $id, string $data): bool
    {
        $nonce = random_bytes(self::NONCE_BYTES);

        $ciphertext = sodium_crypto_secretbox(
            $data,
            $nonce,
            $this->keyRing->currentKey()
        );

        $payload = self::VERSION .
            ':' .
            $this->keyRing->currentKeyId() .
            ':' .
            $this->base64UrlEncode($nonce . $ciphertext);

        return $this->innerHandler->write($id, $payload);
    }

    public function destroy(string $id): bool
    {
        return $this->innerHandler->destroy($id);
    }

    public function gc(int $max_lifetime): int|false
    {
        return $this->innerHandler->gc($max_lifetime);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string|false
    {
        $padding = strlen($value) % 4;

        if ($padding > 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        return base64_decode(strtr($value, '-_', '+/'), true);
    }
}