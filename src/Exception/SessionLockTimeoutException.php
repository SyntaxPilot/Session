<?php

declare(strict_types=1);

namespace SyntaxPilot\Session\Exception;

/**
 * Thrown when a session lock cannot be acquired in time.
 */
final class SessionLockTimeoutException extends SessionException
{
}