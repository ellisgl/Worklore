<?php

declare(strict_types=1);

namespace App\Company\Application;

use App\Company\CompanyRepository;
use App\Shared\Doctrine\OwnerReferences;
use App\Shared\InvalidValueException;
use App\Shared\OwnershipMismatchException;
use App\Taxonomy\Application\TagLookup;
use App\Taxonomy\Application\TagNotFound;
use Doctrine\ORM\Exception\ORMException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class EditProjectHandler
{
    public function __construct(
        private CompanyRepository $companies,
        private OwnerReferences $owners,
        private TagLookup $tags,
    ) {
    }

    /**
     * @throws InvalidValueException      if the name is blank or too long
     * @throws ORMException
     * @throws OwnershipMismatchException never: the tags are looked up for the project's owner
     * @throws ProjectNameTaken
     * @throws ProjectNotFound
     * @throws TagNotFound
     */
    public function __invoke(EditProject $command): void
    {
        $owner = $this->owners->get($command->ownerId);
        $project = $this->companies->findProjectByIdForOwner($command->projectId, $owner)
            ?? throw new ProjectNotFound();

        if (null !== $command->name) {
            if ($this->companies->projectNameExistsInProduct($project->product, $command->name, except: $project)) {
                throw new ProjectNameTaken($command->name);
            }
            $project->setName($command->name);
        }
        if (null !== $command->overview) {
            $project->setOverview($command->overview);
        }
        if (null !== $command->tagIds) {
            $project->setTags(...$this->tags->ownedBy($owner, $command->tagIds));
        }
    }
}
