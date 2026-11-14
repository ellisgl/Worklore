<?php

declare(strict_types=1);

namespace App\Company\Application;

use App\Company\ProductRepository;
use App\Shared\EventSourcing\ConcurrentModification;
use DateTimeZone;
use Doctrine\DBAL\Exception;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class ArchiveProductHandler
{
    public function __construct(
        private ProductRepository $products,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws ConcurrentModification
     * @throws Exception
     * @throws ExceptionInterface
     * @throws ProductNotFound
     */
    public function __invoke(ArchiveProduct $command): void
    {
        $product = $this->products->findForOwner($command->productId, $command->ownerId)
            ?? throw new ProductNotFound();

        $product->archive($this->clock->now()->setTimezone(new DateTimeZone('UTC')));
        $this->products->save($product);
    }
}
