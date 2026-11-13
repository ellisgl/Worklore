<?php

declare(strict_types=1);

namespace App\Tests\Fixtures\EventSourcing;

use App\Shared\EventSourcing\DomainEvent;
use App\Shared\EventSourcing\ListOf;
use App\Shared\UserId;
use DateTimeImmutable;

final readonly class NoteWritten implements DomainEvent
{
    /**
     * @param list<NoteId> $linkedNoteIds
     */
    public function __construct(
        public NoteId $noteId,
        public UserId $ownerId,
        public string $text,
        public ?string $mood,
        public DateTimeImmutable $writtenAt,
        #[ListOf(NoteId::class)]
        public array $linkedNoteIds = [],
        public int $priority = 0,
    ) {
    }
}
