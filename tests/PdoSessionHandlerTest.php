<?php

declare(strict_types=1);

namespace SyntaxPilot\Tests\Session;

use PDO;
use PHPUnit\Framework\TestCase;
use SyntaxPilot\Session\PdoSessionHandler;

final class PdoSessionHandlerTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec(
            'CREATE TABLE sessions (
                id TEXT PRIMARY KEY,
                data BLOB NOT NULL,
                last_activity INTEGER NOT NULL
            )'
        );

        $this->pdo->exec(
            'CREATE INDEX sessions_last_activity_index ON sessions (last_activity)'
        );
    }

    public function testItReadsEmptyStringForMissingSession(): void
    {
        $handler = new PdoSessionHandler($this->pdo);

        self::assertSame('', $handler->read('missing'));
    }

    public function testItWritesAndReadsSessionData(): void
    {
        $handler = new PdoSessionHandler($this->pdo, ttl: 60);

        self::assertTrue($handler->write('abc123', 'data'));
        self::assertSame('data', $handler->read('abc123'));
    }

    public function testItOverwritesSessionData(): void
    {
        $handler = new PdoSessionHandler($this->pdo, ttl: 60);

        $handler->write('abc123', 'old');
        $handler->write('abc123', 'new');

        self::assertSame('new', $handler->read('abc123'));
    }

    public function testItDestroysSessionData(): void
    {
        $handler = new PdoSessionHandler($this->pdo);

        $handler->write('abc123', 'data');

        self::assertTrue($handler->destroy('abc123'));
        self::assertSame('', $handler->read('abc123'));
    }

    public function testItGarbageCollectsExpiredRows(): void
    {
        $handler = new PdoSessionHandler($this->pdo, ttl: 1);

        $handler->write('abc123', 'data');

        sleep(2);

        self::assertSame(1, $handler->gc(1));
        self::assertSame('', $handler->read('abc123'));
    }

    public function testItRejectsUnsafeTableName(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new PdoSessionHandler($this->pdo, 'sessions; DROP TABLE sessions');
    }
}