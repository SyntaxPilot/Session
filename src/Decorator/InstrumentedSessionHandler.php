<?php
/**
 * Copyright (c) 2027 Nicholas English
 *
 * This file is licensed under the MIT License.
 * See the LICENSE file in the project root for full license information.
 */

declare(strict_types=1);

namespace SyntaxPilot\Session\Decorator;

use SyntaxPilot\Session\Contract\EventDispatcherInterface;
use SyntaxPilot\Session\Contract\MetricsRecorderInterface;
use SessionHandlerInterface;
use Throwable;

/**
 * Adds metrics and events around a session handler.
 */
final class InstrumentedSessionHandler implements SessionHandlerInterface
{
    /**
     * @param array<string, string|int|float|bool|null> $tags
     */
    public function __construct(
        private readonly SessionHandlerInterface $innerHandler,
        private readonly ?MetricsRecorderInterface $metrics = null,
        private readonly ?EventDispatcherInterface $events = null,
        private readonly array $tags = [],
    ) {
    }

    public function open(string $path, string $name): bool
    {
        return $this->measure('open', fn (): bool => $this->innerHandler->open($path, $name));
    }

    public function close(): bool
    {
        return $this->measure('close', fn (): bool => $this->innerHandler->close());
    }

    public function read(string $id): string|false
    {
        return $this->measure('read', fn (): string|false => $this->innerHandler->read($id));
    }

    public function write(string $id, string $data): bool
    {
        $this->metrics?->gauge('session.payload_bytes', strlen($data), $this->tags);

        return $this->measure('write', fn (): bool => $this->innerHandler->write($id, $data));
    }

    public function destroy(string $id): bool
    {
        return $this->measure('destroy', fn (): bool => $this->innerHandler->destroy($id));
    }

    public function gc(int $max_lifetime): int|false
    {
        return $this->measure('gc', fn (): int|false => $this->innerHandler->gc($max_lifetime));
    }

    /**
     * @template T
     *
     * @param callable():T $callback
     *
     * @return T
     */
    private function measure(string $operation, callable $callback): mixed
    {
        $start = microtime(true);

        $this->events?->dispatch("session.{$operation}.starting", $this->tags);

        try {
            $result = $callback();

            $duration = (microtime(true) - $start) * 1000;

            $this->metrics?->timing("session.{$operation}_ms", $duration, $this->tags);
            $this->metrics?->increment("session.{$operation}.success", 1, $this->tags);

            $this->events?->dispatch("session.{$operation}.finished", [
                ...$this->tags,
                'duration_ms' => $duration,
            ]);

            return $result;
        } catch (Throwable $exception) {
            $duration = (microtime(true) - $start) * 1000;

            $this->metrics?->timing("session.{$operation}_ms", $duration, $this->tags);
            $this->metrics?->increment("session.{$operation}.failure", 1, $this->tags);

            $this->events?->dispatch("session.{$operation}.failed", [
                ...$this->tags,
                'duration_ms' => $duration,
                'exception' => $exception::class,
            ]);

            throw $exception;
        }
    }
}