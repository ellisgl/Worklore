<?php

declare(strict_types=1);

namespace App\Shared\EventSourcing;

use App\Shared\UserId;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

/**
 * An event as the event store holds it: the event plus where and when it
 * was recorded.
 */
final readonly class StoredEvent
{
    /**
     * @param int                  $sequence position in the whole store, from 1
     * @param int                  $version  position in its stream, from 1
     * @param array<string, mixed> $payload  as stored
     */
    public function __construct(
        public int $sequence,
        public string $streamType,
        public Uuid $streamId,
        public int $version,
        public UserId $ownerId,
        public string $type,
        public array $payload,
        public DateTimeImmutable $recordedAt,
        public DomainEvent $event,
        /** The admin who recorded it while impersonating the owner, if one did */
        public ?UserId $impersonatorId = null,
    ) {
    }
}
