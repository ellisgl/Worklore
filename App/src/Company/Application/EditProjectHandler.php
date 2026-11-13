<?php

declare(strict_types=1);

namespace App\Company\Application;

use App\Company\CompanyRepository;
use App\Shared\Doctrine\OwnerReferences;
use App\Shared\InvalidValueException;
use Doctrine\ORM\Exception\ORMException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class EditProjectHandler
{
    public function __construct(
        private CompanyRepository $companies,
        private OwnerReferences $owners,
    ) {
    }

    /**
     * @throws InvalidValueException if the name is blank or too long
     * @throws ORMException
     * @throws ProjectNameTaken
     * @throws ProjectNotFound
     */
    public function __invoke(EditProject $command): void
    {
        $project = $this->companies->findProjectByIdForOwner($command->projectId, $this->owners->get($command->ownerId))
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
    }
}
