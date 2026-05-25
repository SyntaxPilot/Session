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
 * Minimal event dispatcher contract.
 */
interface EventDispatcherInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    public function dispatch(string $event, array $payload = []): void;
}