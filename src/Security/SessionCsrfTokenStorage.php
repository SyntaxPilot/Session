<?php

/**
 * Copyright (c) 2027 Nicholas English
 *
 * This file is licensed under the MIT License.
 * See the LICENSE file in the project root for full license information.
 */

declare(strict_types=1);

namespace SyntaxPilot\Session\Security;

use SyntaxPilot\Security\Csrf\CsrfTokenStorageInterface;
use SyntaxPilot\Session\Contract\SessionStoreInterface;

/**
 * Session-backed CSRF token storage.
 */
final class SessionCsrfTokenStorage implements CsrfTokenStorageInterface
{
    public function __construct(
        private readonly SessionStoreInterface $session,
        private readonly string $key = '_csrf_tokens',
    ) {
    }

    public function get(string $id): ?string
    {
        $tokens = $this->tokens();

        $token = $tokens[$id] ?? null;

        return is_string($token) && $token !== '' ? $token : null;
    }

    public function set(string $id, string $token): void
    {
        $tokens = $this->tokens();

        $tokens[$id] = $token;

        $this->session->set($this->key, $tokens);
    }

    public function has(string $id): bool
    {
        return $this->get($id) !== null;
    }

    public function remove(string $id): void
    {
        $tokens = $this->tokens();

        unset($tokens[$id]);

        $this->session->set($this->key, $tokens);
    }

    public function clear(): void
    {
        $this->session->remove($this->key);
    }

    /**
     * @return array<string, string>
     */
    private function tokens(): array
    {
        $tokens = $this->session->get($this->key, []);

        if (!is_array($tokens)) {
            return [];
        }

        $valid = [];

        foreach ($tokens as $id => $token) {
            if (is_string($id) && is_string($token) && $token !== '') {
                $valid[$id] = $token;
            }
        }

        return $valid;
    }
}