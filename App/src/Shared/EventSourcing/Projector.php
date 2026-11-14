<?php

declare(strict_types=1);

namespace App\Shared\EventSourcing;

use Doctrine\DBAL\Exception;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Keeps read tables (the projections the query classes read) up to date
 * from events. Called synchronously, in the same transaction as the append,
 * so reads see every change at once. Ignores events it doesn't use.
 */
#[AutoconfigureTag]
interface Projector
{
    /**
     * @throws Exception
     */
    public function project(StoredEvent $event): void;

    /**
     * The tables it writes, emptied before a rebuild replays every event.
     *
     * @return list<string>
     */
    public function tables(): array;

    /**
     * Deletes the rows for these records of $streamType, whose events were
     * just purged (true deletion). Other stream types are not its business.
     *
     * @param list<string> $ids
     *
     * @throws Exception
     */
    public function forget(string $streamType, array $ids): void;
}
