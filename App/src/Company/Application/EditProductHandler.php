<?php

declare(strict_types=1);

namespace App\Company\Application;

use App\Company\CompanyRepository;
use App\Shared\Doctrine\OwnerReferences;
use App\Shared\InvalidValueException;
use Doctrine\ORM\Exception\ORMException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class EditProductHandler
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
     */
    public function __invoke(EditProduct $command): void
    {
        $product = $this->companies->findProductByIdForOwner($command->productId, $this->owners->get($command->ownerId))
            ?? throw new ProductNotFound();

        if (null !== $command->name) {
            $product->setName($command->name);
        }
        if (null !== $command->overview) {
            $product->setOverview($command->overview);
        }
    }
}
