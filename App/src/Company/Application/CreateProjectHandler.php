<?php

declare(strict_types=1);

namespace App\Company\Application;

use App\Company\CompanyRepository;
use App\Shared\Doctrine\OwnerReferences;
use App\Shared\InvalidValueException;
use Doctrine\ORM\Exception\ORMException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Adds the project through its product (inside the Company aggregate); the
 * bus commits it.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class CreateProjectHandler
{
    public function __construct(
        private CompanyRepository $companies,
        private OwnerReferences $owners,
    ) {
    }

    /**
     * @throws InvalidValueException if the name is blank or too long
     * @throws ORMException
     * @throws ProductNotFound
     * @throws ProjectNameTaken
     */
    public function __invoke(CreateProject $command): void
    {
        $product = $this->companies->findProductByIdForOwner($command->productId, $this->owners->get($command->ownerId))
            ?? throw new ProductNotFound();

        if ($this->companies->projectNameExistsInProduct($product, $command->name)) {
            throw new ProjectNameTaken($command->name);
        }

        $product->addProject($command->name, $command->overview, id: $command->projectId);
    }
}
