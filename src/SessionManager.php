<?php

declare(strict_types=1);

namespace SyntaxPilot\Session;

use SyntaxPilot\Session\Bag\SessionBag;
use SyntaxPilot\Session\Config\SessionConfig;
use SyntaxPilot\Session\Contract\SessionBagInterface;
use SyntaxPilot\Session\Contract\SessionStoreInterface;
use SyntaxPilot\Session\Exception\SessionDestroyException;
use SyntaxPilot\Session\Exception\SessionNotStartedException;
use SyntaxPilot\Session\Exception\SessionStartException;
use SyntaxPilot\Session\Flash\FlashBag;
use SyntaxPilot\Session\Security\CsrfTokenManager;
use RuntimeException;
use SessionHandlerInterface;

/**
 * High-level session manager.
 */
final class SessionManager implements SessionStoreInterface
{
    private bool $started = false;

    private bool $readOnly = false;

    private ?FlashBag $flash = null;

    private ?CsrfTokenManager $csrf = null;

    /**
     * @var array<string, SessionBagInterface>
     */
    private array $bags = [];

    public function __construct(
        private readonly SessionConfig $config,
        private readonly ?SessionHandlerInterface $handler = null,
    ) {
    }

    public function start(): void
    {
        $this->startInternal(readOnly: false);
    }

    public function startReadOnly(): void
    {
        $this->startInternal(readOnly: true);
    }

    public function save(): void
    {
        if (!$this->isStarted()) {
            return;
        }

        session_write_close();

        $this->started = false;
        $this->readOnly = false;
    }

    public function destroy(): void
    {
        $this->ensureStarted();

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                [
                    'expires' => time() - 3600,
                    'path' => $params['path'],
                    'domain' => $params['domain'],
                    'secure' => $params['secure'],
                    'httponly' => $params['httponly'],
                    'samesite' => $params['samesite'] ?? 'Lax',
                ]
            );
        }

        if (!session_destroy()) {
            throw new SessionDestroyException('Failed to destroy session.');
        }

        $this->started = false;
        $this->readOnly = false;
    }

    public function regenerateId(bool $deleteOldSession = true): void
    {
        $this->ensureWritable();

        if (!session_regenerate_id($deleteOldSession)) {
            throw new RuntimeException('Failed to regenerate session ID.');
        }
    }

    public function isStarted(): bool
    {
        return $this->started || session_status() === PHP_SESSION_ACTIVE;
    }

    public function id(): string
    {
        $this->ensureStarted();

        return session_id();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->lazyStart();

        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->lazyStart();
        $this->ensureWritable();

        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        $this->lazyStart();

        return array_key_exists($key, $_SESSION);
    }

    public function remove(string $key): void
    {
        $this->lazyStart();
        $this->ensureWritable();

        unset($_SESSION[$key]);
    }

    public function all(): array
    {
        $this->lazyStart();

        return $_SESSION;
    }

    public function replace(array $data): void
    {
        $this->lazyStart();
        $this->ensureWritable();

        $_SESSION = $data;
    }

    public function clear(): void
    {
        $this->lazyStart();
        $this->ensureWritable();

        $_SESSION = [];
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        $this->lazyStart();
        $this->ensureWritable();

        $value = $_SESSION[$key] ?? $default;

        unset($_SESSION[$key]);

        return $value;
    }

    public function increment(string $key, int $amount = 1): int
    {
        $this->lazyStart();
        $this->ensureWritable();

        $value = (int) ($_SESSION[$key] ?? 0);
        $value += $amount;

        $_SESSION[$key] = $value;

        return $value;
    }

    public function decrement(string $key, int $amount = 1): int
    {
        return $this->increment($key, -$amount);
    }

    public function bag(string $name): SessionBagInterface
    {
        return $this->bags[$name] ??= new SessionBag($this, $name);
    }

    public function flash(): FlashBag
    {
        return $this->flash ??= new FlashBag($this);
    }

    public function csrf(): CsrfTokenManager
    {
        return $this->csrf ??= new CsrfTokenManager($this);
    }

    private function startInternal(bool $readOnly): void
    {
        if ($this->isStarted()) {
            $this->started = true;
            $this->readOnly = $readOnly;

            return;
        }

        if (headers_sent($file, $line)) {
            throw new SessionStartException(
                "Cannot start session because headers were already sent in {$file}:{$line}."
            );
        }

        $this->configure();

        if ($this->handler !== null) {
            session_set_save_handler($this->handler, true);
        }

        $options = [];

        if ($readOnly) {
            $options['read_and_close'] = true;
        }

        if (!session_start($options)) {
            throw new SessionStartException('Failed to start session.');
        }

        $this->started = !$readOnly;
        $this->readOnly = $readOnly;
    }

    private function configure(): void
    {
        session_name($this->config->name);

        ini_set('session.use_trans_sid', '0');

        if ($this->config->onlyCookies) {
            ini_set('session.use_only_cookies', '1');
        }

        if ($this->config->strictMode) {
            ini_set('session.use_strict_mode', '1');
        }

        session_set_cookie_params($this->normalizedCookieOptions());
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizedCookieOptions(): array
    {
        return [
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $this->isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
            ...$this->config->cookie,
        ];
    }

    private function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }

        if (($_SERVER['SERVER_PORT'] ?? null) === '443') {
            return true;
        }

        if (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? null) === 'https') {
            return true;
        }

        return false;
    }

    private function lazyStart(): void
    {
        if (!$this->isStarted() && $this->config->lazy) {
            $this->start();
        }

        $this->ensureStarted();
    }

    private function ensureStarted(): void
    {
        if (!$this->isStarted() && !$this->readOnly) {
            throw new SessionNotStartedException('Session has not been started.');
        }
    }

    private function ensureWritable(): void
    {
        if ($this->readOnly) {
            throw new SessionNotStartedException('Session is read-only.');
        }

        $this->ensureStarted();
    }
}