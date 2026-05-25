<?php
/**
 * Copyright (c) 2027 Nicholas English
 *
 * This file is licensed under the MIT License.
 * See the LICENSE file in the project root for full license information.
 */

declare(strict_types=1);

namespace SyntaxPilot\Session\Decorator;

use InvalidArgumentException;
use RuntimeException;
use SessionHandlerInterface;

/**
 * Compresses session payloads before passing them to the inner handler.
 *
 * Best used before encryption in the conceptual pipeline:
 *
 * write: compress -> encrypt -> storage
 * read:  storage -> decrypt -> decompress
 *
 * In decorator order, use:
 *
 * new CompressedSessionHandler(
 *     new KeyRotatingEncryptedSessionHandler($base, $keyRing)
 * )
 *
 * if you want this class to compress first and then let the inner encrypted
 * handler encrypt the compressed data.
 */
final class CompressedSessionHandler implements SessionHandlerInterface
{
    private const PREFIX = 'gz:';

    public function __construct(
        private readonly SessionHandlerInterface $innerHandler,
        private readonly int $level = 6,
    ) {
        if ($this->level < -1 || $this->level > 9) {
            throw new InvalidArgumentException('Compression level must be between -1 and 9.');
        }

        if (!function_exists('gzencode') || !function_exists('gzdecode')) {
            throw new RuntimeException('The zlib extension is required for compressed sessions.');
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

        if (!str_starts_with($payload, self::PREFIX)) {
            return $payload;
        }

        $decoded = base64_decode(substr($payload, strlen(self::PREFIX)), true);

        if ($decoded === false) {
            return '';
        }

        $plain = gzdecode($decoded);

        return $plain === false ? '' : $plain;
    }

    public function write(string $id, string $data): bool
    {
        $compressed = gzencode($data, $this->level);

        if ($compressed === false) {
            return false;
        }

        return $this->innerHandler->write(
            $id,
            self::PREFIX . base64_encode($compressed)
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