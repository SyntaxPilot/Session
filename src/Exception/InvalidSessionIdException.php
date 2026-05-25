<?php
/**
 * Copyright (c) 2027 Nicholas English
 *
 * This file is licensed under the MIT License.
 * See the LICENSE file in the project root for full license information.
 */

declare(strict_types=1);

namespace SyntaxPilot\Session\Exception;

/**
 * Thrown when a session ID is malformed or unsafe.
 */
final class InvalidSessionIdException extends SessionException
{
}