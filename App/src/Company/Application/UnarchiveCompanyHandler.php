<?php

declare(strict_types=1);

namespace App\Company\Application;

use App\Company\CompanyRepository;
use App\Shared\Doctrine\OwnerReferences;
use Doctrine\ORM\Exception\ORMException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class UnarchiveCompanyHandler
{
    public function __construct(
        private CompanyRepository $companies,
        private OwnerReferences $owners,
    ) {
    }

    /**
     * @throws CompanyNotFound
     * @throws ORMException
     */
    public function __invoke(UnarchiveCompany $command): void
    {
        $company = $this->companies->findOneByIdForOwner($command->companyId, $this->owners->get($command->ownerId))
            ?? throw new CompanyNotFound();

        $company->unarchive();
    }
}
