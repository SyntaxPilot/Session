<?php

declare(strict_types=1);

namespace SyntaxPilot\Session;

use InvalidArgumentException;
use MongoDB\BSON\Binary;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;
use SessionHandlerInterface;

/**
 * MongoDB-backed session handler.
 */
final class MongoDbSessionHandler implements SessionHandlerInterface
{
    public function __construct(
        private readonly Collection $collection,
        private readonly int $ttl = 1440,
    ) {
        if ($this->ttl <= 0) {
            throw new InvalidArgumentException('Session TTL must be greater than zero.');
        }
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
        $document = $this->collection->findOne([
            '_id' => $id,
            'expires_at' => [
                '$gt' => new UTCDateTime(time() * 1000),
            ],
        ]);

        if ($document === null) {
            return '';
        }

        $data = $document['data'] ?? '';

        if ($data instanceof Binary) {
            return $data->getData();
        }

        return is_string($data) ? $data : '';
    }

    public function write(string $id, string $data): bool
    {
        $now = time();

        $result = $this->collection->updateOne(
            [
                '_id' => $id,
            ],
            [
                '$set' => [
                    'data' => new Binary($data, Binary::TYPE_GENERIC),
                    'last_activity' => new UTCDateTime($now * 1000),
                    'expires_at' => new UTCDateTime(($now + $this->ttl) * 1000),
                ],
            ],
            [
                'upsert' => true,
            ]
        );

        return $result->isAcknowledged();
    }

    public function destroy(string $id): bool
    {
        $result = $this->collection->deleteOne([
            '_id' => $id,
        ]);

        return $result->isAcknowledged();
    }

    public function gc(int $max_lifetime): int|false
    {
        $result = $this->collection->deleteMany([
            'expires_at' => [
                '$lte' => new UTCDateTime(time() * 1000),
            ],
        ]);

        return $result->isAcknowledged()
            ? $result->getDeletedCount()
            : false;
    }
}