<?php

declare(strict_types=1);

namespace App\Shared\EventSourcing;

use App\Shared\EntityId;
use App\Shared\UserId;

use function count;

/**
 * Base for event-sourced aggregates. State only ever changes in apply(),
 * from an event: either one the aggregate just recorded (recordThat()) or
 * one from its stored history (replay()). Methods that change something
 * check the rules first, then record what happened.
 */
abstract class AggregateRoot
{
    /** @var list<DomainEvent> recorded since load, not yet saved */
    private array $recorded = [];

    /** Events saved so far; the stream's version when it was loaded */
    private int $version = 0;

    abstract public function id(): EntityId;

    abstract public function ownerId(): UserId;

    /**
     * Changes state to reflect $event. Never checks rules or records events:
     * replaying history must always succeed.
     */
    abstract protected function apply(DomainEvent $event): void;

    /**
     * The stream version the aggregate was loaded at; saving expects the
     * stream to still be at this version.
     */
    public function version(): int
    {
        return $this->version;
    }

    /**
     * The events recorded since the last call; they count as saved from now.
     *
     * @return list<DomainEvent>
     */
    public function releaseEvents(): array
    {
        $events = $this->recorded;
        $this->recorded = [];
        $this->version += count($events);

        return $events;
    }

    protected function recordThat(DomainEvent $event): void
    {
        $this->apply($event);
        $this->recorded[] = $event;
    }

    /**
     * @param iterable<DomainEvent> $history
     */
    protected function replay(iterable $history): void
    {
        foreach ($history as $event) {
            $this->apply($event);
            ++$this->version;
        }
    }
}
