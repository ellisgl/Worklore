<?php

declare(strict_types=1);

namespace App\Company\Application;

use App\Company\Company;
use App\Company\CompanyRepository;
use App\Company\Query\CompanyNames;
use App\Shared\EventSourcing\ConcurrentModification;
use App\Shared\InvalidValueException;
use DateTimeZone;
use Doctrine\DBAL\Exception;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

/**
 * Names are unique per owner, ignoring case; checked here, with the projection's unique constraint as the backstop.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class CreateCompanyHandler
{
    public function __construct(
        private CompanyRepository $companies,
        private CompanyNames $names,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws CompanyNameTaken
     * @throws ConcurrentModification
     * @throws Exception
     * @throws ExceptionInterface
     * @throws InvalidValueException  if the name is blank or too long
     */
    public function __invoke(CreateCompany $command): void
    {
        if ($this->names->companyNameTaken($command->ownerId, $command->name)) {
            throw new CompanyNameTaken($command->name);
        }

        $this->companies->save(Company::create($command->companyId, $command->ownerId, $command->name, $command->overview, $this->clock->now()->setTimezone(new DateTimeZone('UTC'))));
    }
}
