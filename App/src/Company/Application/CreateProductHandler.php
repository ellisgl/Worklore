<?php

declare(strict_types=1);

namespace App\Company\Application;

use App\Company\CompanyRepository;
use App\Company\Product;
use App\Company\ProductRepository;
use App\Company\Query\CompanyNames;
use App\Shared\EventSourcing\ConcurrentModification;
use App\Shared\InvalidValueException;
use DateTimeZone;
use Doctrine\DBAL\Exception;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

/**
 * The company must be the owner's; product names are unique within it.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class CreateProductHandler
{
    public function __construct(
        private ProductRepository $products,
        private CompanyRepository $companies,
        private CompanyNames $names,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws CompanyNotFound
     * @throws ConcurrentModification
     * @throws Exception
     * @throws ExceptionInterface
     * @throws InvalidValueException  if the name is blank or too long
     * @throws ProductNameTaken
     */
    public function __invoke(CreateProduct $command): void
    {
        $company = $this->companies->findForOwner($command->companyId, $command->ownerId)
            ?? throw new CompanyNotFound();

        if ($this->names->productNameTaken($company->id(), $command->name)) {
            throw new ProductNameTaken($command->name);
        }

        $this->products->save(Product::create($command->productId, $company->id(), $command->ownerId, $command->name, $command->overview, $this->clock->now()->setTimezone(new DateTimeZone('UTC'))));
    }
}
