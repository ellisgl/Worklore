<?php

declare(strict_types=1);

namespace App\Company\Application;

use App\Company\CompanyRepository;
use App\Shared\Doctrine\OwnerReferences;
use App\Shared\InvalidValueException;
use Doctrine\ORM\Exception\ORMException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Runs inside command.bus's transaction, so a request that changes the name
 * and the overview either changes both or neither.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class EditCompanyHandler
{
    public function __construct(
        private CompanyRepository $companies,
        private OwnerReferences $owners,
    ) {
    }

    /**
     * @throws CompanyNameTaken
     * @throws CompanyNotFound
     * @throws InvalidValueException if the name is blank or too long
     * @throws ORMException
     */
    public function __invoke(EditCompany $command): void
    {
        $owner = $this->owners->get($command->ownerId);
        $company = $this->companies->findOneByIdForOwner($command->companyId, $owner)
            ?? throw new CompanyNotFound();

        if (null !== $command->name) {
            if ($this->companies->nameExistsForOwner($owner, $command->name, except: $company)) {
                throw new CompanyNameTaken($command->name);
            }
            $company->setName($command->name);
        }

        if (null !== $command->overview) {
            $company->setOverview($command->overview);
        }
    }
}
