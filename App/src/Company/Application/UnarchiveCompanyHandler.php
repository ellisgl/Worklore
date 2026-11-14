<?php

declare(strict_types=1);

namespace App\Company\Application;

use App\Company\CompanyRepository;
use App\Shared\EventSourcing\ConcurrentModification;
use Doctrine\DBAL\Exception;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class UnarchiveCompanyHandler
{
    public function __construct(
        private CompanyRepository $companys,
    ) {
    }

    /**
     * @throws CompanyNotFound
     * @throws ConcurrentModification
     * @throws Exception
     * @throws ExceptionInterface
     */
    public function __invoke(UnarchiveCompany $command): void
    {
        $company = $this->companys->findForOwner($command->companyId, $command->ownerId)
            ?? throw new CompanyNotFound();

        $company->unarchive();
        $this->companys->save($company);
    }
}
