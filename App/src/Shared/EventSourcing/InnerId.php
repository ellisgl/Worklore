<?php

declare(strict_types=1);

namespace App\Shared\EventSourcing;

/**
 * Marks an id of something that lives inside an aggregate rather than in a
 * stream of its own (e.g. a journal entry's media). Imports give each one a
 * new id, as they do streams.
 */
interface InnerId
{
}
