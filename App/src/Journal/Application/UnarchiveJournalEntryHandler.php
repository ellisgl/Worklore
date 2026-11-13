<?php

declare(strict_types=1);

namespace App\Journal\Application;

use App\Journal\JournalEntryRepository;
use App\Shared\Doctrine\OwnerReferences;
use Doctrine\ORM\Exception\ORMException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class UnarchiveJournalEntryHandler
{
    public function __construct(
        private JournalEntryRepository $entries,
        private OwnerReferences $owners,
    ) {
    }

    /**
     * @throws JournalEntryNotFound
     * @throws ORMException
     */
    public function __invoke(UnarchiveJournalEntry $command): void
    {
        $entry = $this->entries->findOneByIdForOwner($command->entryId, $this->owners->get($command->ownerId))
            ?? throw new JournalEntryNotFound();

        $entry->unarchive();
    }
}
