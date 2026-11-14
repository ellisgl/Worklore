<?php

declare(strict_types=1);

namespace App\Company\Application;

use App\Company\ProjectRepository;
use App\Company\Query\CompanyNames;
use App\Shared\EventSourcing\ConcurrentModification;
use App\Shared\InvalidValueException;
use App\Taxonomy\Application\TagLookup;
use App\Taxonomy\Application\TagNotFound;
use Doctrine\DBAL\Exception;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

/**
 * Every change or none: the rules are checked before anything is saved.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class EditProjectHandler
{
    public function __construct(
        private ProjectRepository $projects,
        private CompanyNames $names,
        private TagLookup $tags,
    ) {
    }

    /**
     * @throws ConcurrentModification
     * @throws Exception
     * @throws ExceptionInterface
     * @throws InvalidValueException  if the name is blank or too long
     * @throws ProjectNameTaken
     * @throws ProjectNotFound
     * @throws TagNotFound
     */
    public function __invoke(EditProject $command): void
    {
        $project = $this->projects->findForOwner($command->projectId, $command->ownerId)
            ?? throw new ProjectNotFound();

        if (null !== $command->name) {
            if ($this->names->projectNameTaken($project->productId, $command->name, except: $project->id())) {
                throw new ProjectNameTaken($command->name);
            }
            $project->rename($command->name);
        }
        if (null !== $command->overview) {
            $project->describe($command->overview);
        }
        if (null !== $command->tagIds) {
            $project->retag($this->tags->idsOwnedBy($command->ownerId, $command->tagIds));
        }

        $this->projects->save($project);
    }
}
