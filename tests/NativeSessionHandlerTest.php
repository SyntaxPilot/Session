<?php

declare(strict_types=1);

namespace SyntaxPilot\Tests\Session;

use PHPUnit\Framework\TestCase;
use SessionHandlerInterface;
use SyntaxPilot\Session\NativeSessionHandler;

final class NativeSessionHandlerTest extends TestCase
{
    public function testItImplementsSessionHandlerInterface(): void
    {
        $handler = new NativeSessionHandler();

        self::assertInstanceOf(SessionHandlerInterface::class, $handler);
    }
}