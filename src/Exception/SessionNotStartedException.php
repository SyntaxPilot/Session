<?php

declare(strict_types=1);

namespace SyntaxPilot\Session\Exception;

/**
 * Thrown when session data is accessed before the session is started.
 */
final class SessionNotStartedException extends SessionException
{
}