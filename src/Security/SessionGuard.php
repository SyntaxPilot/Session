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
 * Applies common session security policies.
 */
final class SessionGuard
{
    public function __construct(
        private readonly SessionStoreInterface $session,
    ) {
    }

    public function initialize(?string $ip = null, ?string $userAgent = null): void
    {
        $meta = $this->metadata();
        $now = time();

        $changed = false;

        if (!isset($meta[SessionMetadata::CREATED_AT])) {
            $meta[SessionMetadata::CREATED_AT] = $now;
            $changed = true;
        }

        if (!isset($meta[SessionMetadata::LAST_REGENERATED_AT])) {
            $meta[SessionMetadata::LAST_REGENERATED_AT] = $now;
            $changed = true;
        }

        $meta[SessionMetadata::LAST_ACTIVITY] = $now;
        $changed = true;

        if ($ip !== null && !isset($meta[SessionMetadata::IP_HASH])) {
            $meta[SessionMetadata::IP_HASH] = $this->hash($ip);
            $changed = true;
        }

        if ($userAgent !== null && !isset($meta[SessionMetadata::USER_AGENT_HASH])) {
            $meta[SessionMetadata::USER_AGENT_HASH] = $this->hash($userAgent);
            $changed = true;
        }

        if ($changed) {
            $this->session->set(SessionMetadata::KEY, $meta);
        }
    }

    public function enforceIdleTimeout(int $seconds): bool
    {
        if ($seconds <= 0) {
            return true;
        }

        $meta = $this->metadata();
        $lastActivity = (int) ($meta[SessionMetadata::LAST_ACTIVITY] ?? time());

        if ((time() - $lastActivity) > $seconds) {
            $this->session->destroy();

            return false;
        }

        $meta[SessionMetadata::LAST_ACTIVITY] = time();
        $this->session->set(SessionMetadata::KEY, $meta);

        return true;
    }

    public function enforceAbsoluteTimeout(int $seconds): bool
    {
        if ($seconds <= 0) {
            return true;
        }

        $meta = $this->metadata();
        $createdAt = (int) ($meta[SessionMetadata::CREATED_AT] ?? time());

        if ((time() - $createdAt) > $seconds) {
            $this->session->destroy();

            return false;
        }

        return true;
    }

    public function regeneratePeriodically(int $seconds): bool
    {
        if ($seconds <= 0) {
            return false;
        }

        $meta = $this->metadata();
        $lastRegeneratedAt = (int) ($meta[SessionMetadata::LAST_REGENERATED_AT] ?? 0);

        if ((time() - $lastRegeneratedAt) <= $seconds) {
            return false;
        }

        $this->session->regenerateId(true);

        $meta[SessionMetadata::LAST_REGENERATED_AT] = time();
        $this->session->set(SessionMetadata::KEY, $meta);

        return true;
    }

    public function validateFingerprint(?string $ip = null, ?string $userAgent = null): bool
    {
        $meta = $this->metadata();

        if ($ip !== null && isset($meta[SessionMetadata::IP_HASH])) {
            if (!hash_equals((string) $meta[SessionMetadata::IP_HASH], $this->hash($ip))) {
                return false;
            }
        }

        if ($userAgent !== null && isset($meta[SessionMetadata::USER_AGENT_HASH])) {
            if (!hash_equals((string) $meta[SessionMetadata::USER_AGENT_HASH], $this->hash($userAgent))) {
                return false;
            }
        }

        return true;
    }

    private function metadata(): array
    {
        $meta = $this->session->get(SessionMetadata::KEY, []);

        return is_array($meta) ? $meta : [];
    }

    private function hash(string $value): string
    {
        return hash('sha256', $value);
    }
}