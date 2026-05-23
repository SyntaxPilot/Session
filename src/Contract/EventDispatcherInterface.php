<?php

declare(strict_types=1);

namespace SyntaxPilot\Session\Contract;

/**
 * Minimal event dispatcher contract.
 */
interface EventDispatcherInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    public function dispatch(string $event, array $payload = []): void;
}