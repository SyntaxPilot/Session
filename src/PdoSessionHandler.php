<?php
/**
 * Copyright (c) 2027 Nicholas English
 *
 * This file is licensed under the MIT License.
 * See the LICENSE file in the project root for full license information.
 */

declare(strict_types=1);

namespace SyntaxPilot\Session;

use InvalidArgumentException;
use PDO;
use SessionHandlerInterface;
use Throwable;

/**
 * PDO-backed session handler.
 *
 * Supports MySQL/MariaDB, SQLite, PostgreSQL, and fallback update-then-insert.
 */
final class PdoSessionHandler implements SessionHandlerInterface
{
    private string $driver;

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $table = 'sessions',
        private readonly int $ttl = 1440,
    ) {
        if ($this->ttl <= 0) {
            throw new InvalidArgumentException('Session TTL must be greater than zero.');
        }

        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $this->table)) {
            throw new InvalidArgumentException('Invalid session table name.');
        }

        $this->driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $stmt = $this->pdo->prepare(
            "SELECT data FROM {$this->table}
             WHERE id = :id AND last_activity > :expires"
        );

        $stmt->execute([
            'id' => $id,
            'expires' => time() - $this->ttl,
        ]);

        $data = $stmt->fetchColumn();

        return $data === false ? '' : (string) $data;
    }

    public function write(string $id, string $data): bool
    {
        return match ($this->driver) {
            'mysql' => $this->writeMysql($id, $data),
            'sqlite' => $this->writeSqlite($id, $data),
            'pgsql' => $this->writePostgres($id, $data),
            default => $this->writeUpdateThenInsert($id, $data),
        };
    }

    public function destroy(string $id): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM {$this->table} WHERE id = :id"
        );

        return $stmt->execute([
            'id' => $id,
        ]);
    }

    public function gc(int $max_lifetime): int|false
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM {$this->table}
             WHERE last_activity < :expires"
        );

        $stmt->execute([
            'expires' => time() - $this->ttl,
        ]);

        return $stmt->rowCount();
    }

    private function writeMysql(string $id, string $data): bool
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO {$this->table} (id, data, last_activity)
             VALUES (:id, :data, :last_activity)
             ON DUPLICATE KEY UPDATE
                data = VALUES(data),
                last_activity = VALUES(last_activity)"
        );

        return $stmt->execute([
            'id' => $id,
            'data' => $data,
            'last_activity' => time(),
        ]);
    }

    private function writeSqlite(string $id, string $data): bool
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO {$this->table} (id, data, last_activity)
             VALUES (:id, :data, :last_activity)
             ON CONFLICT(id) DO UPDATE SET
                data = excluded.data,
                last_activity = excluded.last_activity"
        );

        return $stmt->execute([
            'id' => $id,
            'data' => $data,
            'last_activity' => time(),
        ]);
    }

    private function writePostgres(string $id, string $data): bool
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO {$this->table} (id, data, last_activity)
             VALUES (:id, :data, :last_activity)
             ON CONFLICT (id) DO UPDATE SET
                data = EXCLUDED.data,
                last_activity = EXCLUDED.last_activity"
        );

        return $stmt->execute([
            'id' => $id,
            'data' => $data,
            'last_activity' => time(),
        ]);
    }

    private function writeUpdateThenInsert(string $id, string $data): bool
    {
        $now = time();

        $this->pdo->beginTransaction();

        try {
            $update = $this->pdo->prepare(
                "UPDATE {$this->table}
                 SET data = :data, last_activity = :last_activity
                 WHERE id = :id"
            );

            $update->execute([
                'id' => $id,
                'data' => $data,
                'last_activity' => $now,
            ]);

            if ($update->rowCount() === 0) {
                $insert = $this->pdo->prepare(
                    "INSERT INTO {$this->table} (id, data, last_activity)
                     VALUES (:id, :data, :last_activity)"
                );

                $insert->execute([
                    'id' => $id,
                    'data' => $data,
                    'last_activity' => $now,
                ]);
            }

            return $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();

            throw $exception;
        }
    }
}