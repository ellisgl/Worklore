<?php

declare(strict_types=1);

namespace App\Journal\Application;

use App\Company\Application\ProjectNotFound;
use App\Company\CompanyRepository;
use App\Journal\JournalEntryEntity;
use App\Journal\JournalEntryRepository;
use App\Shared\Doctrine\OwnerReferences;
use App\Shared\InvalidValueException;
use App\Shared\OwnershipMismatchException;
use App\Taxonomy\Application\TagNotFound;
use Doctrine\ORM\Exception\ORMException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Runs inside command.bus's transaction: the entry and its tags are saved
 * together, or not at all.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class CreateJournalEntryHandler
{
    public function __construct(
        private JournalEntryRepository $entries,
        private CompanyRepository $companies,
        private TagLookup $tags,
        private OwnerReferences $owners,
    ) {
    }

    /**
     * @throws InvalidValueException      if the title or entry is blank, or the title is too long
     * @throws ORMException
     * @throws OwnershipMismatchException never: the project and tags are looked up for the same owner
     * @throws ProjectNotFound
     * @throws TagNotFound
     */
    public function __invoke(CreateJournalEntry $command): void
    {
        $owner = $this->owners->get($command->ownerId);
        $project = $this->companies->findProjectByIdForOwner($command->projectId, $owner)
            ?? throw new ProjectNotFound();
        $tags = $this->tags->ownedBy($owner, $command->tagIds);

        $entry = new JournalEntryEntity($project, $owner, $command->title, $command->entry, id: $command->entryId);
        $this->entries->add($entry->setTags(...$tags));
    }
}
