<?php

declare(strict_types=1);

namespace App\Company\Application;

use App\Company\CompanyRepository;
use App\Shared\Doctrine\OwnerReferences;
use App\Shared\InvalidValueException;
use Doctrine\ORM\Exception\ORMException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Adds the product through its company (the aggregate root); the bus
 * commits it.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class CreateProductHandler
{
    public function __construct(
        private CompanyRepository $companies,
        private OwnerReferences $owners,
    ) {
    }

    /**
     * @throws CompanyNotFound
     * @throws InvalidValueException if the name is blank or too long
     * @throws ORMException
     */
    public function __invoke(CreateProduct $command): void
    {
        $company = $this->companies->findOneByIdForOwner($command->companyId, $this->owners->get($command->ownerId))
            ?? throw new CompanyNotFound();

        $company->addProduct($command->name, $command->overview, id: $command->productId);
    }
}
