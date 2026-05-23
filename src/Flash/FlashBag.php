<?php

declare(strict_types=1);

namespace SyntaxPilot\Session\Flash;

use SyntaxPilot\Session\Contract\SessionStoreInterface;

/**
 * Flash message/value bag.
 *
 * Flash values are available for the next request and then removed when aged.
 */
final class FlashBag
{
    public function __construct(
        private readonly SessionStoreInterface $session,
    ) {
    }

    public function add(string $key, mixed $value): void
    {
        $flash = $this->flashData();

        $flash['new'][$key] = $value;

        $this->session->set('_flash', $flash);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $flash = $this->flashData();

        return $flash['old'][$key]
            ?? $flash['new'][$key]
            ?? $default;
    }

    public function has(string $key): bool
    {
        $flash = $this->flashData();

        return array_key_exists($key, $flash['old'])
            || array_key_exists($key, $flash['new']);
    }

    public function all(): array
    {
        $flash = $this->flashData();

        return [
            ...$flash['old'],
            ...$flash['new'],
        ];
    }

    public function remove(string $key): void
    {
        $flash = $this->flashData();

        unset($flash['old'][$key], $flash['new'][$key]);

        $this->session->set('_flash', $flash);
    }

    public function clear(): void
    {
        $this->session->set('_flash', [
            'old' => [],
            'new' => [],
        ]);
    }

    public function age(): void
    {
        $flash = $this->flashData();

        $this->session->set('_flash', [
            'old' => $flash['new'],
            'new' => [],
        ]);
    }

    private function flashData(): array
    {
        $flash = $this->session->get('_flash', []);

        if (!is_array($flash)) {
            $flash = [];
        }

        return [
            'old' => is_array($flash['old'] ?? null) ? $flash['old'] : [],
            'new' => is_array($flash['new'] ?? null) ? $flash['new'] : [],
        ];
    }
}