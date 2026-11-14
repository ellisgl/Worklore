<?php

declare(strict_types=1);

namespace App\Journal\Application;

use App\Journal\JournalEntryRepository;
use App\Journal\MediaNotFound;
use App\Shared\EventSourcing\ConcurrentModification;
use DateTimeZone;
use Doctrine\DBAL\Exception;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class RemoveMediaHandler
{
    public function __construct(
        private JournalEntryRepository $entries,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws ConcurrentModification
     * @throws Exception
     * @throws ExceptionInterface
     * @throws JournalEntryNotFound
     * @throws MediaNotFound
     */
    public function __invoke(RemoveMedia $command): void
    {
        $entry = $this->entries->findForOwner($command->entryId, $command->ownerId)
            ?? throw new JournalEntryNotFound();

        $entry->removeMedia($command->mediaId, $this->clock->now()->setTimezone(new DateTimeZone('UTC')));
        $this->entries->save($entry);
    }
}
