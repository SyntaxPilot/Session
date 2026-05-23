<?php

declare(strict_types=1);

namespace SyntaxPilot\Session\Contract;

/**
 * Serializes and unserializes framework-managed session arrays.
 */
interface SessionSerializerInterface
{
    /**
     * @param array<string, mixed> $data
     */
    public function serialize(array $data): string;

    /**
     * @return array<string, mixed>
     */
    public function unserialize(string $payload): array;
}