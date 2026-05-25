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
 * Minimal metrics recorder contract.
 */
interface MetricsRecorderInterface
{
    /**
     * @param array<string, string|int|float|bool|null> $tags
     */
    public function timing(string $name, float $milliseconds, array $tags = []): void;

    /**
     * @param array<string, string|int|float|bool|null> $tags
     */
    public function increment(string $name, int $value = 1, array $tags = []): void;

    /**
     * @param array<string, string|int|float|bool|null> $tags
     */
    public function gauge(string $name, int|float $value, array $tags = []): void;
}