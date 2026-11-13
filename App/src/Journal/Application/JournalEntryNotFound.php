<?php

declare(strict_types=1);

namespace App\Journal\Application;

use App\Shared\NotFoundException;

/**
 * No journal entry with this id belongs to the caller (it doesn't exist, or
 * it's someone else's; the two look the same).
 */
final class JournalEntryNotFound extends NotFoundException
{
    public function __construct()
    {
        parent::__construct('Journal entry not found.');
    }
}
