<?php

declare(strict_types=1);

namespace App\Journal\Application;

use App\Journal\JournalEntryRepository;
use App\Shared\Doctrine\OwnerReferences;
use DateTimeZone;
use Doctrine\ORM\Exception\ORMException;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class ArchiveJournalEntryHandler
{
    public function __construct(
        private JournalEntryRepository $entries,
        private OwnerReferences $owners,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws JournalEntryNotFound
     * @throws ORMException
     */
    public function __invoke(ArchiveJournalEntry $command): void
    {
        $entry = $this->entries->findOneByIdForOwner($command->entryId, $this->owners->get($command->ownerId))
            ?? throw new JournalEntryNotFound();

        $entry->archive($this->clock->now()->setTimezone(new DateTimeZone('UTC')));
    }
}
