<?php

declare(strict_types=1);

namespace SyntaxPilot\Tests\Session;

use PHPUnit\Framework\TestCase;
use SyntaxPilot\Session\FilesystemSessionHandler;

final class FilesystemSessionHandlerTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/syntaxpilot_sessions_' . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->directory);
    }

    public function testItCreatesDirectoryOnOpen(): void
    {
        $handler = new FilesystemSessionHandler($this->directory);

        self::assertTrue($handler->open('', 'TEST_SESSION'));
        self::assertDirectoryExists($this->directory);
    }

    public function testItWritesAndReadsSessionData(): void
    {
        $handler = new FilesystemSessionHandler($this->directory);

        self::assertTrue($handler->open('', 'TEST_SESSION'));
        self::assertSame('', $handler->read('abc123abc123abc123'));
        self::assertTrue($handler->write('abc123abc123abc123', 'data'));
        self::assertTrue($handler->close());

        self::assertTrue($handler->open('', 'TEST_SESSION'));
        self::assertSame('data', $handler->read('abc123abc123abc123'));
        self::assertTrue($handler->close());
    }

    public function testItDestroysSessionFile(): void
    {
        $handler = new FilesystemSessionHandler($this->directory);

        $handler->open('', 'TEST_SESSION');
        $handler->read('abc123abc123abc123');
        $handler->write('abc123abc123abc123', 'data');
        $handler->close();

        self::assertTrue($handler->destroy('abc123abc123abc123'));
        self::assertSame('', $handler->read('abc123abc123abc123'));
        $handler->close();
    }

    public function testItRejectsUnsafeSessionIds(): void
    {
        $handler = new FilesystemSessionHandler($this->directory);

        $this->expectException(\InvalidArgumentException::class);

        $handler->read('../bad');
    }

    public function testItGarbageCollectsExpiredFiles(): void
    {
        $handler = new FilesystemSessionHandler($this->directory, ttl: 1);

        $handler->open('', 'TEST_SESSION');
        $handler->read('abc123abc123abc123');
        $handler->write('abc123abc123abc123', 'data');
        $handler->close();

        sleep(2);

        self::assertSame(1, $handler->gc(1));
    }

    private function deleteDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            is_dir($file) ? $this->deleteDirectory($file) : unlink($file);
        }

        rmdir($directory);
    }
}