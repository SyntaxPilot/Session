<?php

declare(strict_types=1);

namespace SyntaxPilot\Tests\Session;

use PHPUnit\Framework\TestCase;
use SyntaxPilot\Session\CookieSessionHandler;

final class CookieSessionHandlerTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_COOKIE['TEST_SESSION_DATA']);
    }

    public function testItReadsEmptyStringWhenCookieIsMissing(): void
    {
        if (!extension_loaded('sodium')) {
            self::markTestSkipped('Sodium extension is not available.');
        }

        $handler = new CookieSessionHandler(
            cookieName: 'TEST_SESSION_DATA',
            key: sodium_crypto_secretbox_keygen()
        );

        self::assertSame('', $handler->read('abc123'));
    }

    public function testItReadsEmptyStringWhenCookieIsTampered(): void
    {
        if (!extension_loaded('sodium')) {
            self::markTestSkipped('Sodium extension is not available.');
        }

        $_COOKIE['TEST_SESSION_DATA'] = 'tampered';

        $handler = new CookieSessionHandler(
            cookieName: 'TEST_SESSION_DATA',
            key: sodium_crypto_secretbox_keygen()
        );

        self::assertSame('', $handler->read('abc123'));
    }

    public function testItRejectsInvalidKeyLength(): void
    {
        if (!extension_loaded('sodium')) {
            self::markTestSkipped('Sodium extension is not available.');
        }

        $this->expectException(\InvalidArgumentException::class);

        new CookieSessionHandler(
            cookieName: 'TEST_SESSION_DATA',
            key: 'short'
        );
    }
}