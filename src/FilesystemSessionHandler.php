<?php

declare(strict_types=1);

namespace SyntaxPilot\Session;

use InvalidArgumentException;
use SessionHandlerInterface;

/**
 * Filesystem-backed session handler with file locking.
 */
final class FilesystemSessionHandler implements SessionHandlerInterface
{
    private ?string $currentFile = null;

    /**
     * @var resource|null
     */
    private $lockHandle = null;

    public function __construct(
        private readonly string $directory,
        private readonly int $ttl = 1440,
        private readonly int $directoryMode = 0700,
        private readonly int $fileMode = 0600,
    ) {
        if ($this->ttl <= 0) {
            throw new InvalidArgumentException('Session TTL must be greater than zero.');
        }
    }

    public function open(string $path, string $name): bool
    {
        if (!is_dir($this->directory)) {
            if (!mkdir($this->directory, $this->directoryMode, true) && !is_dir($this->directory)) {
                return false;
            }
        }

        return is_readable($this->directory) && is_writable($this->directory);
    }

    public function close(): bool
    {
        if (is_resource($this->lockHandle)) {
            flock($this->lockHandle, LOCK_UN);
            fclose($this->lockHandle);
        }

        $this->lockHandle = null;
        $this->currentFile = null;

        return true;
    }

    public function read(string $id): string|false
    {
        $file = $this->path($id);

        $handle = fopen($file, 'c+b');

        if ($handle === false) {
            return false;
        }

        if (!flock($handle, LOCK_EX)) {
            fclose($handle);
            return false;
        }

        $this->lockHandle = $handle;
        $this->currentFile = $file;

        chmod($file, $this->fileMode);

        clearstatcache(true, $file);

        $modifiedAt = filemtime($file);

        if ($modifiedAt !== false && $modifiedAt < time() - $this->ttl) {
            ftruncate($handle, 0);
            rewind($handle);

            return '';
        }

        rewind($handle);

        $contents = stream_get_contents($handle);

        return $contents === false ? false : $contents;
    }

    public function write(string $id, string $data): bool
    {
        if (!is_resource($this->lockHandle)) {
            $file = $this->path($id);

            $handle = fopen($file, 'c+b');

            if ($handle === false) {
                return false;
            }

            if (!flock($handle, LOCK_EX)) {
                fclose($handle);
                return false;
            }

            $this->lockHandle = $handle;
            $this->currentFile = $file;
        }

        rewind($this->lockHandle);

        if (!ftruncate($this->lockHandle, 0)) {
            return false;
        }

        $bytes = fwrite($this->lockHandle, $data);

        if ($bytes === false || $bytes < strlen($data)) {
            return false;
        }

        fflush($this->lockHandle);

        if ($this->currentFile !== null) {
            chmod($this->currentFile, $this->fileMode);
        }

        return true;
    }

    public function destroy(string $id): bool
    {
        $file = $this->path($id);

        if (is_resource($this->lockHandle)) {
            $this->close();
        }

        if (is_file($file)) {
            return unlink($file);
        }

        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        $deleted = 0;
        $expiresBefore = time() - $this->ttl;

        $files = glob($this->directory . DIRECTORY_SEPARATOR . 'sess_*');

        if ($files === false) {
            return false;
        }

        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }

            $modifiedAt = filemtime($file);

            if ($modifiedAt !== false && $modifiedAt < $expiresBefore) {
                if (@unlink($file)) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    private function path(string $id): string
    {
        if (!preg_match('/^[a-zA-Z0-9,-]{16,256}$/', $id)) {
            throw new InvalidArgumentException('Invalid session ID.');
        }

        return $this->directory . DIRECTORY_SEPARATOR . 'sess_' . $id;
    }
}