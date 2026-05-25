<?php

declare(strict_types=1);

namespace SyntaxPilot\Tests\Session;

use PHPUnit\Framework\TestCase;
use SyntaxPilot\Session\ArraySessionHandler;

final class ArraySessionHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        ArraySessionHandler::reset();
    }

    public function testItReadsEmptyStringForMissingSession(): void
    {
        $handler = new ArraySessionHandler();

        self::assertSame('', $handler->read('missing'));
    }

    public function testItWritesAndReadsSessionData(): void
    {
        $handler = new ArraySessionHandler();

        self::assertTrue($handler->write('abc123', 'foo|s:3:"bar";'));
        self::assertSame('foo|s:3:"bar";', $handler->read('abc123'));
    }

    public function testItDestroysSessionData(): void
    {
        $handler = new ArraySessionHandler();

        $handler->write('abc123', 'data');

        self::assertTrue($handler->destroy('abc123'));
        self::assertSame('', $handler->read('abc123'));
    }

    public function testItGarbageCollectsExpiredSessions(): void
    {
        $handler = new ArraySessionHandler(ttl: 1);

        $handler->write('abc123', 'data');

        sleep(2);

        self::assertSame(1, $handler->gc(1));
        self::assertSame('', $handler->read('abc123'));
    }
}