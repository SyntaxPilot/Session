<?php

declare(strict_types=1);

namespace SyntaxPilot\Tests\Session;

use PHPUnit\Framework\TestCase;
use SyntaxPilot\Session\ArraySessionHandler;
use SyntaxPilot\Session\EncryptedSessionHandler;

final class EncryptedSessionHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        ArraySessionHandler::reset();
    }

    public function testItEncryptsAndDecryptsSessionData(): void
    {
        if (!extension_loaded('sodium')) {
            self::markTestSkipped('Sodium extension is not available.');
        }

        $inner = new ArraySessionHandler();
        $key = sodium_crypto_secretbox_keygen();

        $handler = new EncryptedSessionHandler($inner, $key);

        self::assertTrue($handler->write('abc123', 'secret-data'));
        self::assertSame('secret-data', $handler->read('abc123'));

        self::assertNotSame('secret-data', $inner->read('abc123'));
    }

    public function testItReturnsEmptyStringForTamperedPayload(): void
    {
        if (!extension_loaded('sodium')) {
            self::markTestSkipped('Sodium extension is not available.');
        }

        $inner = new ArraySessionHandler();
        $key = sodium_crypto_secretbox_keygen();

        $inner->write('abc123', 'not-valid-encrypted-data');

        $handler = new EncryptedSessionHandler($inner, $key);

        self::assertSame('', $handler->read('abc123'));
    }

    public function testItRejectsInvalidKeyLength(): void
    {
        if (!extension_loaded('sodium')) {
            self::markTestSkipped('Sodium extension is not available.');
        }

        $this->expectException(\InvalidArgumentException::class);

        new EncryptedSessionHandler(new ArraySessionHandler(), 'short-key');
    }
}