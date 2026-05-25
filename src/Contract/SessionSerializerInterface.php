<?php
/**
 * Copyright (c) 2027 Nicholas English
 *
 * This file is licensed under the MIT License.
 * See the LICENSE file in the project root for full license information.
 */

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