<?php

declare(strict_types=1);

namespace App\Journal\Doctrine;

use App\Journal\JournalEntryId;
use App\Shared\Doctrine\EntityIdType;

final class JournalEntryIdType extends EntityIdType
{
    public const string NAME = 'journal_entry_id';

    protected function idClass(): string
    {
        return JournalEntryId::class;
    }
}
