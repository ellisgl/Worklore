<?php

declare(strict_types=1);

namespace App\Company\Application;

use App\Company\CompanyRepository;
use App\Shared\EventSourcing\ConcurrentModification;
use DateTimeZone;
use Doctrine\DBAL\Exception;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class ArchiveCompanyHandler
{
    public function __construct(
        private CompanyRepository $companys,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws CompanyNotFound
     * @throws ConcurrentModification
     * @throws Exception
     * @throws ExceptionInterface
     */
    public function __invoke(ArchiveCompany $command): void
    {
        $company = $this->companys->findForOwner($command->companyId, $command->ownerId)
            ?? throw new CompanyNotFound();

        $company->archive($this->clock->now()->setTimezone(new DateTimeZone('UTC')));
        $this->companys->save($company);
    }
}
