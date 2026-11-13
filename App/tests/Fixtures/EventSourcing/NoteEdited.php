<?php

declare(strict_types=1);

namespace App\Tests\Fixtures\EventSourcing;

use App\Shared\EventSourcing\DomainEvent;

final readonly class NoteEdited implements DomainEvent
{
    public function __construct(
        public NoteId $noteId,
        public string $text,
    ) {
    }
}
