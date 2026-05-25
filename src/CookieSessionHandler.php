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
 * Encrypted cookie-based session handler.
 *
 * Stores the serialized session payload inside a browser cookie.
 * Only use this for small sessions.
 */
final class CookieSessionHandler implements SessionHandlerInterface
{
    private const NONCE_BYTES = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;

    private const KEY_BYTES = SODIUM_CRYPTO_SECRETBOX_KEYBYTES;

    public function __construct(
        private readonly string $cookieName,
        private readonly string $key,
        private readonly array $cookieOptions = [],
    ) {
        if (!extension_loaded('sodium')) {
            throw new RuntimeException('The Sodium PHP extension is required.');
        }

        if (strlen($this->key) !== self::KEY_BYTES) {
            throw new InvalidArgumentException(
                'Cookie session key must be exactly ' . self::KEY_BYTES . ' bytes.'
            );
        }
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $payload = $_COOKIE[$this->cookieName] ?? '';

        if (!is_string($payload) || $payload === '') {
            return '';
        }

        return $this->decrypt($payload);
    }

    public function write(string $id, string $data): bool
    {
        if (headers_sent()) {
            return false;
        }

        return setcookie(
            $this->cookieName,
            $this->encrypt($data),
            $this->normalizedCookieOptions()
        );
    }

    public function destroy(string $id): bool
    {
        if (headers_sent()) {
            return false;
        }

        return setcookie(
            $this->cookieName,
            '',
            [
                ...$this->normalizedCookieOptions(),
                'expires' => time() - 3600,
            ]
        );
    }

    public function gc(int $max_lifetime): int|false
    {
        return 0;
    }

    private function encrypt(string $plaintext): string
    {
        $nonce = random_bytes(self::NONCE_BYTES);

        $ciphertext = sodium_crypto_secretbox(
            $plaintext,
            $nonce,
            $this->key
        );

        return $this->base64UrlEncode($nonce . $ciphertext);
    }

    private function decrypt(string $payload): string
    {
        $decoded = $this->base64UrlDecode($payload);

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

    private function normalizedCookieOptions(): array
    {
        return [
            'expires' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
            ...$this->cookieOptions,
        ];
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