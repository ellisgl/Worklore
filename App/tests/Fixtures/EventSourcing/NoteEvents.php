<?php

declare(strict_types=1);

namespace App\Tests\Fixtures\EventSourcing;

use App\Shared\EventSourcing\EventCatalog;

final class NoteEvents implements EventCatalog
{
    public function events(): array
    {
        return ['note.written' => NoteWritten::class, 'note.edited' => NoteEdited::class];
    }
}
