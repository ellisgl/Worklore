<?php

declare(strict_types=1);

namespace App\Shared\EventSourcing;

use App\Shared\EntityId;
use DateMalformedStringException;
use Doctrine\DBAL\Exception;
use JsonException;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

/**
 * What the event-sourced repositories share: load a stream's events, and
 * save an aggregate's new ones. Saving appends them to the event store,
 * projects them into the read tables in the same transaction, and publishes
 * them on event.bus; inside a command those listeners run after it commits.
 */
final readonly class AggregateStore
{
    public function __construct(
        private EventStore $events,
        private Projections $projections,
        // Named autowiring: $eventBus is the event.bus service.
        private MessageBusInterface $eventBus,
    ) {
    }

    /**
     * @return list<DomainEvent> oldest first; empty if there is no such stream
     *
     * @throws DateMalformedStringException
     * @throws Exception
     * @throws JsonException
     */
    public function history(string $streamType, EntityId $id): array
    {
        $history = [];
        foreach ($this->events->stream($streamType, $id) as $stored) {
            $history[] = $stored->event;
        }

        return $history;
    }

    /**
     * @throws ConcurrentModification
     * @throws Exception
     * @throws ExceptionInterface
     * @throws JsonException
     */
    public function save(string $streamType, AggregateRoot $aggregate): void
    {
        $expectedVersion = $aggregate->version();
        $events = $aggregate->releaseEvents();
        if ([] === $events) {
            return;
        }

        foreach ($this->events->append($streamType, $aggregate->id(), $aggregate->ownerId(), $expectedVersion, $events) as $stored) {
            $this->projections->project($stored);
            $this->eventBus->dispatch($stored->event, [new DispatchAfterCurrentBusStamp()]);
        }
    }
}
