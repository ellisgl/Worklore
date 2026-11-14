<?php

declare(strict_types=1);

namespace App\Shared\EventSourcing;

use App\Shared\EntityId;
use App\Shared\Security\Impersonation;
use App\Shared\UserId;
use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Generator;
use JsonException;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;
use UnexpectedValueException;

use function is_array;
use function is_int;
use function is_numeric;
use function is_string;
use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * The single source of truth: an append-only log of every domain event,
 * one stream per aggregate (table event_store). A row it can't read is
 * corrupt data and raises UnexpectedValueException.
 *
 * @phpstan-type EventRow array{sequence: int|string, stream_type: string, stream_id: string, stream_version: int|string, user_id: string, impersonator_id: string|null, event_type: string, payload: string, recorded_at: string}
 */
final readonly class EventStore
{
    private const string SELECT = 'SELECT sequence, stream_type, stream_id, stream_version, user_id, impersonator_id, event_type, payload, recorded_at FROM event_store';

    public function __construct(
        private Connection $db,
        private EventSerializer $serializer,
        private ClockInterface $clock,
        private Impersonation $impersonation,
    ) {
    }

    /**
     * Appends events to a stream that must be at $expectedVersion.
     *
     * @param list<DomainEvent> $events
     *
     * @return list<StoredEvent>
     *
     * @throws ConcurrentModification if the stream moved past $expectedVersion
     * @throws Exception
     */
    public function append(string $streamType, EntityId $streamId, UserId $ownerId, int $expectedVersion, array $events): array
    {
        $recordedAt = $this->clock->now();
        $stored = [];
        foreach ($events as $i => $event) {
            $stored[] = $this->insert($streamType, $streamId->uuid, $ownerId, $expectedVersion + $i + 1, $event, $recordedAt);
        }

        return $stored;
    }

    /**
     * Adds one event as it was recorded elsewhere, e.g. in a data export:
     * at its original time, as $version of its stream.
     *
     * @throws ConcurrentModification if the stream already has that version
     * @throws Exception
     */
    public function import(string $streamType, Uuid $streamId, UserId $ownerId, int $version, DomainEvent $event, DateTimeImmutable $recordedAt): StoredEvent
    {
        return $this->insert($streamType, $streamId, $ownerId, $version, $event, $recordedAt);
    }

    /**
     * @throws ConcurrentModification
     * @throws Exception
     */
    private function insert(string $streamType, Uuid $streamId, UserId $ownerId, int $version, DomainEvent $event, DateTimeImmutable $recordedAt): StoredEvent
    {
        $recordedAt = $recordedAt->setTimezone(new DateTimeZone('UTC'));
        $type = $this->serializer->typeOf($event);
        $payload = $this->serializer->payload($event);
        $impersonatorId = $this->impersonation->impersonatorId();
        try {
            $sequence = $this->db->fetchOne(
                'INSERT INTO event_store (stream_type, stream_id, stream_version, user_id, impersonator_id, event_type, payload, recorded_at)
                 VALUES (:stream_type, :stream_id, :version, :owner, :impersonator, :type, :payload, :recorded_at) RETURNING sequence',
                [
                    'stream_type' => $streamType,
                    'stream_id' => $streamId->toRfc4122(),
                    'version' => $version,
                    'owner' => $ownerId->toRfc4122(),
                    'impersonator' => $impersonatorId?->toRfc4122(),
                    'type' => $type,
                    'payload' => self::json($payload),
                    'recorded_at' => $recordedAt->format('Y-m-d H:i:s'),
                ],
            );
        } catch (UniqueConstraintViolationException) {
            throw new ConcurrentModification();
        }

        return new StoredEvent(self::int($sequence), $streamType, $streamId, $version, $ownerId, $type, $payload, $recordedAt, $event, $impersonatorId);
    }

    /**
     * Erases these streams of the owner's, every event (true deletion).
     *
     * @param list<string> $streamIds
     *
     * @return int events erased
     *
     * @throws Exception
     */
    public function erase(UserId $ownerId, array $streamIds): int
    {
        if ([] === $streamIds) {
            return 0;
        }

        return (int) $this->db->executeStatement(
            'DELETE FROM event_store WHERE user_id = :owner AND stream_id IN (:ids)',
            ['owner' => $ownerId->toRfc4122(), 'ids' => $streamIds],
            ['ids' => ArrayParameterType::STRING],
        );
    }

    /**
     * One stream's events, oldest first; empty if there is no such stream.
     *
     * @return list<StoredEvent>
     *
     * @throws Exception
     */
    public function stream(string $streamType, EntityId $streamId): array
    {
        /** @var list<EventRow> $rows */
        $rows = $this->db->fetchAllAssociative(
            self::SELECT.' WHERE stream_id = :id AND stream_type = :type ORDER BY stream_version',
            ['id' => $streamId->toRfc4122(), 'type' => $streamType],
        );

        $events = [];
        foreach ($rows as $row) {
            $events[] = $this->stored($row);
        }

        return $events;
    }

    /**
     * Every event one user owns, in the order it was recorded.
     *
     * @return list<StoredEvent>
     *
     * @throws Exception
     */
    public function ownedBy(UserId $ownerId): array
    {
        /** @var list<EventRow> $rows */
        $rows = $this->db->fetchAllAssociative(self::SELECT.' WHERE user_id = :owner ORDER BY sequence', ['owner' => $ownerId->toRfc4122()]);

        $events = [];
        foreach ($rows as $row) {
            $events[] = $this->stored($row);
        }

        return $events;
    }

    /**
     * Every event in the order it was recorded, read in batches.
     *
     * @return Generator<StoredEvent>
     *
     * @throws Exception
     */
    public function all(int $batchSize = 500): Generator
    {
        $after = 0;
        do {
            /** @var list<EventRow> $rows */
            $rows = $this->db->fetchAllAssociative(
                self::SELECT.' WHERE sequence > :after ORDER BY sequence LIMIT '.$batchSize,
                ['after' => $after],
            );
            foreach ($rows as $row) {
                $event = $this->stored($row);
                $after = $event->sequence;
                yield $event;
            }
        } while ([] !== $rows);
    }

    /**
     * @param EventRow $row
     */
    private function stored(array $row): StoredEvent
    {
        try {
            $decoded = json_decode($row['payload'], true, flags: JSON_THROW_ON_ERROR);
            $recordedAt = new DateTimeImmutable($row['recorded_at'], new DateTimeZone('UTC'));
        } catch (JsonException|DateMalformedStringException $e) {
            throw new UnexpectedValueException('Unreadable event '.$row['sequence'].' in the event store.', previous: $e);
        }
        if (!is_array($decoded)) {
            throw new UnexpectedValueException('Stored payload is not a JSON object.');
        }
        /** @var array<string, mixed> $payload */
        $payload = $decoded;

        return new StoredEvent(
            sequence: self::int($row['sequence']),
            streamType: $row['stream_type'],
            streamId: Uuid::fromString($row['stream_id']),
            version: self::int($row['stream_version']),
            ownerId: UserId::fromString($row['user_id']),
            type: $row['event_type'],
            payload: $payload,
            recordedAt: $recordedAt,
            event: $this->serializer->event($row['event_type'], $payload),
            impersonatorId: null === $row['impersonator_id'] ? null : UserId::fromString($row['impersonator_id']),
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function json(array $payload): string
    {
        try {
            return json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new UnexpectedValueException('An event payload is not JSON-encodable.', previous: $e);
        }
    }

    private static function int(mixed $value): int
    {
        return match (true) {
            is_int($value) => $value,
            is_string($value) && is_numeric($value) => (int) $value,
            default => throw new UnexpectedValueException('Expected an integer from the database.'),
        };
    }
}
