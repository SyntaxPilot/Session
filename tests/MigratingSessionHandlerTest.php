<?php

declare(strict_types=1);

namespace SyntaxPilot\Tests\Session;

use PHPUnit\Framework\TestCase;
use SyntaxPilot\Session\ArraySessionHandler;
use SyntaxPilot\Session\MigratingSessionHandler;

final class MigratingSessionHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        ArraySessionHandler::reset();
    }

    public function testItReadsFromNewHandlerFirst(): void
    {
        $legacy = new ArraySessionHandler();
        $new = new ArraySessionHandler();

        $legacy->write('abc123', 'legacy-data');
        $new->write('abc123', 'new-data');

        $handler = new MigratingSessionHandler($legacy, $new);

        self::assertSame('new-data', $handler->read('abc123'));
    }

    public function testItFallsBackToLegacyHandler(): void
    {
        $legacy = new ArraySessionHandler();
        $new = new ArraySessionHandler();

        $legacy->write('abc123', 'legacy-data');

        $handler = new MigratingSessionHandler($legacy, $new);

        self::assertSame('legacy-data', $handler->read('abc123'));
    }

    public function testItWritesMigratedDataToNewHandlerAndDeletesLegacy(): void
    {
        $legacy = new ArraySessionHandler();
        $new = new ArraySessionHandler();

        $legacy->write('abc123', 'legacy-data');

        $handler = new MigratingSessionHandler($legacy, $new, destroyLegacyAfterWrite: true);

        self::assertSame('legacy-data', $handler->read('abc123'));
        self::assertTrue($handler->write('abc123', 'updated-data'));

        self::assertSame('updated-data', $new->read('abc123'));
        self::assertSame('', $legacy->read('abc123'));
    }

    public function testDestroyDeletesFromBothHandlers(): void
    {
        $legacy = new ArraySessionHandler();
        $new = new ArraySessionHandler();

        $legacy->write('abc123', 'legacy-data');
        $new->write('abc123', 'new-data');

        $handler = new MigratingSessionHandler($legacy, $new);

        self::assertTrue($handler->destroy('abc123'));
        self::assertSame('', $legacy->read('abc123'));
        self::assertSame('', $new->read('abc123'));
    }
}