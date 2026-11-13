<?php

declare(strict_types=1);

namespace App\Company\Application;

use App\Company\CompanyRepository;
use App\Shared\Doctrine\OwnerReferences;
use Doctrine\ORM\Exception\ORMException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class UnarchiveProductHandler
{
    public function __construct(
        private CompanyRepository $companies,
        private OwnerReferences $owners,
    ) {
    }

    /**
     * @throws ORMException
     * @throws ProductNotFound
     */
    public function __invoke(UnarchiveProduct $command): void
    {
        $product = $this->companies->findProductByIdForOwner($command->productId, $this->owners->get($command->ownerId))
            ?? throw new ProductNotFound();

        $product->unarchive();
    }
}
