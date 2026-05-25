<?php

declare(strict_types=1);

namespace SyntaxPilot\Tests\Session;

use PHPUnit\Framework\TestCase;
use Redis;
use SyntaxPilot\Session\RedisSessionHandler;

final class RedisSessionHandlerTest extends TestCase
{
    private ?Redis $redis = null;

    protected function setUp(): void
    {
        if (!extension_loaded('redis')) {
            self::markTestSkipped('Redis extension is not available.');
        }

        $redis = new Redis();

        try {
            $redis->connect('127.0.0.1', 6379, 0.2);
        } catch (\Throwable) {
            self::markTestSkipped('Redis server is not available.');
        }

        $this->redis = $redis;
        $this->redis->flushDB();
    }

    protected function tearDown(): void
    {
        $this->redis?->flushDB();
        $this->redis?->close();
    }

    public function testItWritesAndReadsSessionData(): void
    {
        self::assertInstanceOf(Redis::class, $this->redis);

        $handler = new RedisSessionHandler($this->redis, prefix: 'test:session:', ttl: 60);

        self::assertTrue($handler->write('abc123', 'data'));
        self::assertSame('data', $handler->read('abc123'));
    }

    public function testItDestroysSessionData(): void
    {
        self::assertInstanceOf(Redis::class, $this->redis);

        $handler = new RedisSessionHandler($this->redis, prefix: 'test:session:', ttl: 60);

        $handler->write('abc123', 'data');

        self::assertTrue($handler->destroy('abc123'));
        self::assertSame('', $handler->read('abc123'));
    }

    public function testGarbageCollectionIsHandledByRedis(): void
    {
        self::assertInstanceOf(Redis::class, $this->redis);

        $handler = new RedisSessionHandler($this->redis);

        self::assertSame(0, $handler->gc(60));
    }
}