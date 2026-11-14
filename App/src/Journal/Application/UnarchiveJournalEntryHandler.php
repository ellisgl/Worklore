<?php

declare(strict_types=1);

namespace App\Journal\Application;

use App\Journal\JournalEntryRepository;
use App\Shared\EventSourcing\ConcurrentModification;
use Doctrine\DBAL\Exception;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class UnarchiveJournalEntryHandler
{
    public function __construct(
        private JournalEntryRepository $entries,
    ) {
    }

    /**
     * @throws ConcurrentModification
     * @throws Exception
     * @throws ExceptionInterface
     * @throws JournalEntryNotFound
     */
    public function __invoke(UnarchiveJournalEntry $command): void
    {
        $entry = $this->entries->findForOwner($command->entryId, $command->ownerId)
            ?? throw new JournalEntryNotFound();

        $entry->unarchive();
        $this->entries->save($entry);
    }
}
