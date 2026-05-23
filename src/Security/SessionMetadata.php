<?php

declare(strict_types=1);

namespace SyntaxPilot\Session\Security;

/**
 * Internal session metadata keys.
 */
final class SessionMetadata
{
    public const KEY = '_meta';

    public const CREATED_AT = 'created_at';

    public const LAST_ACTIVITY = 'last_activity';

    public const LAST_REGENERATED_AT = 'last_regenerated_at';

    public const IP_HASH = 'ip_hash';

    public const USER_AGENT_HASH = 'user_agent_hash';
}