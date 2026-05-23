<?php

declare(strict_types=1);

namespace SyntaxPilot\Session\Exception;

/**
 * Thrown when a session ID is malformed or unsafe.
 */
final class InvalidSessionIdException extends SessionException
{
}