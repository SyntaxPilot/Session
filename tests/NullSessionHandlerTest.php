<?php

declare(strict_types=1);

namespace SyntaxPilot\Tests\Session;

use PHPUnit\Framework\TestCase;
use SyntaxPilot\Session\NullSessionHandler;

final class NullSessionHandlerTest extends TestCase
{
    public function testItNeverStoresData(): void
    {
        $handler = new NullSessionHandler();

        self::assertTrue($handler->open('', 'TEST_SESSION'));
        self::assertTrue($handler->write('abc123', 'data'));
        self::assertSame('', $handler->read('abc123'));
        self::assertTrue($handler->destroy('abc123'));
        self::assertSame(0, $handler->gc(60));
        self::assertTrue($handler->close());
    }
}