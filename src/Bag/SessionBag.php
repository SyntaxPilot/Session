<?php

declare(strict_types=1);

namespace SyntaxPilot\Session\Bag;

use SyntaxPilot\Session\Contract\SessionBagInterface;
use SyntaxPilot\Session\Contract\SessionStoreInterface;

/**
 * Namespaced session bag.
 */
final class SessionBag implements SessionBagInterface
{
    public function __construct(
        private readonly SessionStoreInterface $session,
        private readonly string $name,
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $bag = $this->all();

        return $bag[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $bags = $this->session->get('_bags', []);

        if (!is_array($bags)) {
            $bags = [];
        }

        if (!isset($bags[$this->name]) || !is_array($bags[$this->name])) {
            $bags[$this->name] = [];
        }

        $bags[$this->name][$key] = $value;

        $this->session->set('_bags', $bags);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    public function remove(string $key): void
    {
        $bags = $this->session->get('_bags', []);

        if (!is_array($bags)) {
            return;
        }

        unset($bags[$this->name][$key]);

        $this->session->set('_bags', $bags);
    }

    public function all(): array
    {
        $bags = $this->session->get('_bags', []);

        if (!is_array($bags)) {
            return [];
        }

        $bag = $bags[$this->name] ?? [];

        return is_array($bag) ? $bag : [];
    }

    public function clear(): void
    {
        $bags = $this->session->get('_bags', []);

        if (!is_array($bags)) {
            $bags = [];
        }

        $bags[$this->name] = [];

        $this->session->set('_bags', $bags);
    }
}