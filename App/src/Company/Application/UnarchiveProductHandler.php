<?php

declare(strict_types=1);

namespace App\Company\Application;

use App\Company\ProductRepository;
use App\Shared\EventSourcing\ConcurrentModification;
use Doctrine\DBAL\Exception;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class UnarchiveProductHandler
{
    public function __construct(
        private ProductRepository $products,
    ) {
    }

    /**
     * @throws ConcurrentModification
     * @throws Exception
     * @throws ExceptionInterface
     * @throws ProductNotFound
     */
    public function __invoke(UnarchiveProduct $command): void
    {
        $product = $this->products->findForOwner($command->productId, $command->ownerId)
            ?? throw new ProductNotFound();

        $product->unarchive();
        $this->products->save($product);
    }
}
