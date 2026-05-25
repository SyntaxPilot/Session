<?php

declare(strict_types=1);

namespace SyntaxPilot\Tests\Session;

use PHPUnit\Framework\TestCase;
use SyntaxPilot\Session\CacheSessionHandler;
use SyntaxPilot\Tests\Session\Support\InMemoryCache;

final class CacheSessionHandlerTest extends TestCase
{
    public function testItReadsEmptyStringForMissingSession(): void
    {
        $handler = new CacheSessionHandler(new InMemoryCache());

        self::assertSame('', $handler->read('missing'));
    }

    public function testItWritesAndReadsSessionData(): void
    {
        $handler = new CacheSessionHandler(new InMemoryCache(), prefix: 'test:', ttl: 60);

        self::assertTrue($handler->write('abc123', 'data'));
        self::assertSame('data', $handler->read('abc123'));
    }

    public function testItDestroysSessionData(): void
    {
        $handler = new CacheSessionHandler(new InMemoryCache());

        $handler->write('abc123', 'data');

        self::assertTrue($handler->destroy('abc123'));
        self::assertSame('', $handler->read('abc123'));
    }

    public function testGarbageCollectionIsHandledByCache(): void
    {
        $handler = new CacheSessionHandler(new InMemoryCache());

        self::assertSame(0, $handler->gc(60));
    }
}