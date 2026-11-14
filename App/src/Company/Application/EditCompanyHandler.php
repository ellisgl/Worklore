<?php

declare(strict_types=1);

namespace App\Company\Application;

use App\Company\CompanyRepository;
use App\Company\Query\CompanyNames;
use App\Shared\EventSourcing\ConcurrentModification;
use App\Shared\InvalidValueException;
use Doctrine\DBAL\Exception;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

/**
 * Every change or none: the rules are checked before anything is saved.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class EditCompanyHandler
{
    public function __construct(
        private CompanyRepository $companies,
        private CompanyNames $names,
    ) {
    }

    /**
     * @throws CompanyNameTaken
     * @throws CompanyNotFound
     * @throws ConcurrentModification
     * @throws Exception
     * @throws ExceptionInterface
     * @throws InvalidValueException  if the name is blank or too long
     */
    public function __invoke(EditCompany $command): void
    {
        $company = $this->companies->findForOwner($command->companyId, $command->ownerId)
            ?? throw new CompanyNotFound();

        if (null !== $command->name) {
            if ($this->names->companyNameTaken($command->ownerId, $command->name, except: $company->id())) {
                throw new CompanyNameTaken($command->name);
            }
            $company->rename($command->name);
        }
        if (null !== $command->overview) {
            $company->describe($command->overview);
        }

        $this->companies->save($company);
    }
}
