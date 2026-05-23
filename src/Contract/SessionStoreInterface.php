<?php

declare(strict_types=1);

namespace SyntaxPilot\Session\Contract;

/**
 * High-level session store API.
 */
interface SessionStoreInterface
{
    public function start(): void;

    public function startReadOnly(): void;

    public function save(): void;

    public function destroy(): void;

    public function regenerateId(bool $deleteOldSession = true): void;

    public function isStarted(): bool;

    public function id(): string;

    public function get(string $key, mixed $default = null): mixed;

    public function set(string $key, mixed $value): void;

    public function has(string $key): bool;

    public function remove(string $key): void;

    public function all(): array;

    public function replace(array $data): void;

    public function clear(): void;

    public function pull(string $key, mixed $default = null): mixed;
}