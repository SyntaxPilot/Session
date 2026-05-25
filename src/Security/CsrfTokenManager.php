<?php
/**
 * Copyright (c) 2027 Nicholas English
 *
 * This file is licensed under the MIT License.
 * See the LICENSE file in the project root for full license information.
 */

declare(strict_types=1);

namespace SyntaxPilot\Session\Security;

use SyntaxPilot\Session\Contract\SessionStoreInterface;

/**
 * Session-backed CSRF token manager.
 */
final class CsrfTokenManager
{
    public function __construct(
        private readonly SessionStoreInterface $session,
        private readonly string $key = '_csrf_token',
    ) {
    }

    public function token(): string
    {
        $token = $this->session->get($this->key);

        if (!is_string($token) || $token === '') {
            $token = $this->regenerate();
        }

        return $token;
    }

    public function regenerate(): string
    {
        $token = bin2hex(random_bytes(32));

        $this->session->set($this->key, $token);

        return $token;
    }

    public function validate(?string $token): bool
    {
        if (!is_string($token) || $token === '') {
            return false;
        }

        $known = $this->session->get($this->key, '');

        return is_string($known) && $known !== '' && hash_equals($known, $token);
    }

    public function invalidate(): void
    {
        $this->session->remove($this->key);
    }
}