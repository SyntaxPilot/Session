<?php

declare(strict_types=1);

namespace SyntaxPilot\Tests\Session;

use MongoDB\Client;
use MongoDB\Collection;
use PHPUnit\Framework\TestCase;
use SyntaxPilot\Session\MongoDbSessionHandler;

final class MongoDbSessionHandlerTest extends TestCase
{
    private ?Collection $collection = null;

    protected function setUp(): void
    {
        if (!class_exists(Client::class)) {
            self::markTestSkipped('mongodb/mongodb package is not installed.');
        }

        try {
            $client = new Client('mongodb://127.0.0.1:27017', [], [
                'serverSelectionTimeoutMS' => 200,
            ]);

            $client->selectDatabase('admin')->command(['ping' => 1]);
        } catch (\Throwable) {
            self::markTestSkipped('MongoDB server is not available.');
        }

        $this->collection = $client
            ->selectDatabase('syntaxpilot_test')
            ->selectCollection('sessions');

        $this->collection->deleteMany([]);
    }

    protected function tearDown(): void
    {
        $this->collection?->deleteMany([]);
    }

    public function testItWritesAndReadsSessionData(): void
    {
        self::assertInstanceOf(Collection::class, $this->collection);

        $handler = new MongoDbSessionHandler($this->collection, ttl: 60);

        self::assertTrue($handler->write('abc123', 'data'));
        self::assertSame('data', $handler->read('abc123'));
    }

    public function testItDestroysSessionData(): void
    {
        self::assertInstanceOf(Collection::class, $this->collection);

        $handler = new MongoDbSessionHandler($this->collection, ttl: 60);

        $handler->write('abc123', 'data');

        self::assertTrue($handler->destroy('abc123'));
        self::assertSame('', $handler->read('abc123'));
    }

    public function testItGarbageCollectsExpiredDocuments(): void
    {
        self::assertInstanceOf(Collection::class, $this->collection);

        $handler = new MongoDbSessionHandler($this->collection, ttl: 1);

        $handler->write('abc123', 'data');

        sleep(2);

        self::assertSame(1, $handler->gc(1));
        self::assertSame('', $handler->read('abc123'));
    }
}