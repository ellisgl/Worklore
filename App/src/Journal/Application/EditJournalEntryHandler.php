<?php

declare(strict_types=1);

namespace App\Journal\Application;

use App\Journal\JournalEntryRepository;
use App\Shared\Doctrine\OwnerReferences;
use App\Shared\EventSourcing\ConcurrentModification;
use App\Shared\InvalidValueException;
use App\Taxonomy\Application\TagLookup;
use App\Taxonomy\Application\TagNotFound;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\Exception\ORMException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

/**
 * Applies every change or none: the rules are checked before anything is
 * saved, and the events are appended together.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class EditJournalEntryHandler
{
    public function __construct(
        private JournalEntryRepository $entries,
        private TagLookup $tags,
        private OwnerReferences $owners,
    ) {
    }

    /**
     * @throws ConcurrentModification
     * @throws Exception
     * @throws ExceptionInterface
     * @throws InvalidValueException  if the title or entry is blank, or the title is too long
     * @throws JournalEntryNotFound
     * @throws ORMException
     * @throws TagNotFound
     */
    public function __invoke(EditJournalEntry $command): void
    {
        $entry = $this->entries->findForOwner($command->entryId, $command->ownerId)
            ?? throw new JournalEntryNotFound();

        if (null !== $command->title) {
            $entry->retitle($command->title);
        }
        if (null !== $command->entry) {
            $entry->rewrite($command->entry);
        }
        if (null !== $command->tagIds) {
            $entry->retag($this->tags->idsOwnedBy($this->owners->get($command->ownerId), $command->tagIds));
        }

        $this->entries->save($entry);
    }
}
