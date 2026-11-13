<?php

declare(strict_types=1);

namespace App\Journal\Application;

use App\Journal\JournalEntryRepository;
use App\Shared\Doctrine\OwnerReferences;
use App\Shared\InvalidValueException;
use App\Shared\OwnershipMismatchException;
use App\Taxonomy\Application\TagNotFound;
use Doctrine\ORM\Exception\ORMException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

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
     * @throws InvalidValueException      if the title or entry is blank, or the title is too long
     * @throws JournalEntryNotFound
     * @throws ORMException
     * @throws OwnershipMismatchException never: the tags are looked up for the entry's owner
     * @throws TagNotFound
     */
    public function __invoke(EditJournalEntry $command): void
    {
        $owner = $this->owners->get($command->ownerId);
        $entry = $this->entries->findOneByIdForOwner($command->entryId, $owner)
            ?? throw new JournalEntryNotFound();

        if (null !== $command->title) {
            $entry->setTitle($command->title);
        }
        if (null !== $command->entry) {
            $entry->setEntry($command->entry);
        }
        if (null !== $command->tagIds) {
            $entry->setTags(...$this->tags->ownedBy($owner, $command->tagIds));
        }
    }
}
