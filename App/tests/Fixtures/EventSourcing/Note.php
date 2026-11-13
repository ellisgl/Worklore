<?php

declare(strict_types=1);

namespace App\Tests\Fixtures\EventSourcing;

use App\Shared\EventSourcing\AggregateRoot;
use App\Shared\EventSourcing\DomainEvent;
use App\Shared\UserId;
use DateTimeImmutable;
use LogicException;

/**
 * A minimal event-sourced aggregate for testing the infrastructure.
 */
final class Note extends AggregateRoot
{
    private NoteId $id;
    private UserId $ownerId;
    private(set) string $text = '';

    private function __construct()
    {
    }

    public static function write(NoteId $id, UserId $ownerId, string $text): self
    {
        $note = new self();
        $note->recordThat(new NoteWritten($id, $ownerId, $text, null, new DateTimeImmutable('2026-01-01 00:00:00 UTC')));

        return $note;
    }

    /**
     * @param iterable<DomainEvent> $history
     */
    public static function fromHistory(iterable $history): self
    {
        $note = new self();
        $note->replay($history);

        return $note;
    }

    public function edit(string $text): void
    {
        if ($text !== $this->text) {
            $this->recordThat(new NoteEdited($this->id, $text));
        }
    }

    public function id(): NoteId
    {
        return $this->id;
    }

    public function ownerId(): UserId
    {
        return $this->ownerId;
    }

    protected function apply(DomainEvent $event): void
    {
        match (true) {
            $event instanceof NoteWritten => [$this->id, $this->ownerId, $this->text] = [$event->noteId, $event->ownerId, $event->text],
            $event instanceof NoteEdited => $this->text = $event->text,
            default => throw new LogicException('Not a note event: '.$event::class),
        };
    }
}
