<?php

declare(strict_types=1);

namespace App\Company\Application;

use App\Company\CompanyRepository;
use App\Shared\Doctrine\OwnerReferences;
use DateTimeZone;
use Doctrine\ORM\Exception\ORMException;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class ArchiveCompanyHandler
{
    public function __construct(
        private CompanyRepository $companies,
        private OwnerReferences $owners,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws CompanyNotFound
     * @throws ORMException
     */
    public function __invoke(ArchiveCompany $command): void
    {
        $company = $this->companies->findOneByIdForOwner($command->companyId, $this->owners->get($command->ownerId))
            ?? throw new CompanyNotFound();

        $company->archive($this->clock->now()->setTimezone(new DateTimeZone('UTC')));
    }
}
