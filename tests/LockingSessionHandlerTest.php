<?php

declare(strict_types=1);

namespace SyntaxPilot\Tests\Session;

use PHPUnit\Framework\TestCase;
use SyntaxPilot\Session\ArraySessionHandler;
use SyntaxPilot\Session\LockingSessionHandler;

final class LockingSessionHandlerTest extends TestCase
{
    private string $lockDirectory;

    protected function setUp(): void
    {
        ArraySessionHandler::reset();

        $this->lockDirectory = sys_get_temp_dir() . '/syntaxpilot_locks_' . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->lockDirectory);
    }

    public function testItLocksReadsAndWritesThroughInnerHandler(): void
    {
        $inner = new ArraySessionHandler();

        $handler = new LockingSessionHandler(
            innerHandler: $inner,
            lockDirectory: $this->lockDirectory
        );

        self::assertTrue($handler->open('', 'TEST_SESSION'));
        self::assertSame('', $handler->read('abc123'));
        self::assertTrue($handler->write('abc123', 'data'));
        self::assertTrue($handler->close());

        self::assertSame('data', $inner->read('abc123'));
    }

    public function testItDestroysThroughInnerHandler(): void
    {
        $inner = new ArraySessionHandler();
        $inner->write('abc123', 'data');

        $handler = new LockingSessionHandler($inner, $this->lockDirectory);

        self::assertTrue($handler->open('', 'TEST_SESSION'));
        self::assertTrue($handler->destroy('abc123'));
        self::assertTrue($handler->close());

        self::assertSame('', $inner->read('abc123'));
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