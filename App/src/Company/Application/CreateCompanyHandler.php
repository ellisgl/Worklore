<?php

declare(strict_types=1);

namespace App\Company\Application;

use App\Company\CompanyEntity;
use App\Company\CompanyRepository;
use App\Shared\Doctrine\OwnerReferences;
use App\Shared\InvalidValueException;
use Doctrine\ORM\Exception\ORMException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Runs inside command.bus's transaction: the new company is flushed and
 * committed when this returns, and nothing is saved if it throws.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class CreateCompanyHandler
{
    public function __construct(
        private CompanyRepository $companies,
        private OwnerReferences $owners,
    ) {
    }

    /**
     * @throws CompanyNameTaken
     * @throws InvalidValueException if the name is blank or too long
     * @throws ORMException
     */
    public function __invoke(CreateCompany $command): void
    {
        $owner = $this->owners->get($command->ownerId);

        if ($this->companies->nameExistsForOwner($owner, $command->name)) {
            throw new CompanyNameTaken($command->name);
        }

        $this->companies->add(new CompanyEntity(
            $owner,
            $command->name,
            $command->overview,
            id: $command->companyId,
        ));
    }
}
