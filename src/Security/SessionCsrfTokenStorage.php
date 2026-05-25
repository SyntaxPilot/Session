<?php
/**
 * Copyright (c) 2027 Nicholas English
 *
 * This file is licensed under the MIT License.
 * See the LICENSE file in the project root for full license information.
 */

declare(strict_types=1);

namespace SyntaxPilot\Session\Security;

use SyntaxPilot\Security\Csrf\CsrfTokenPayload;
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

    public function get(string $id): ?CsrfTokenPayload
    {
        $tokens = $this->tokens();

        $payload = $tokens[$id] ?? null;

        return is_array($payload) ? CsrfTokenPayload::fromArray($payload) : null;
    }

    public function set(string $id, CsrfTokenPayload $payload): void
    {
        $tokens = $this->tokens();

        $tokens[$id] = $payload->toArray();

        $this->session->set($this->key, $tokens);
    }

    public function has(string $id): bool
    {
        return $this->get($id) instanceof CsrfTokenPayload;
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

    public function prune(): void
    {
        $tokens = $this->tokens();

        foreach ($tokens as $id => $payload) {
            if (!is_array($payload)) {
                unset($tokens[$id]);
                continue;
            }

            $csrfPayload = CsrfTokenPayload::fromArray($payload);

            if (!$csrfPayload instanceof CsrfTokenPayload || $csrfPayload->isExpired()) {
                unset($tokens[$id]);
            }
        }

        $this->session->set($this->key, $tokens);
    }

    /**
     * @return array<string, mixed>
     */
    private function tokens(): array
    {
        $tokens = $this->session->get($this->key, []);

        return is_array($tokens) ? $tokens : [];
    }
}