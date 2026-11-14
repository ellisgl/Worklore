<?php

declare(strict_types=1);

namespace App\Company\Application;

use App\Company\ProductRepository;
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
final readonly class EditProductHandler
{
    public function __construct(
        private ProductRepository $products,
        private CompanyNames $names,
    ) {
    }

    /**
     * @throws ConcurrentModification
     * @throws Exception
     * @throws ExceptionInterface
     * @throws InvalidValueException  if the name is blank or too long
     * @throws ProductNameTaken
     * @throws ProductNotFound
     */
    public function __invoke(EditProduct $command): void
    {
        $product = $this->products->findForOwner($command->productId, $command->ownerId)
            ?? throw new ProductNotFound();

        if (null !== $command->name) {
            if ($this->names->productNameTaken($product->companyId, $command->name, except: $product->id())) {
                throw new ProductNameTaken($command->name);
            }
            $product->rename($command->name);
        }
        if (null !== $command->overview) {
            $product->describe($command->overview);
        }

        $this->products->save($product);
    }
}
