<?php

declare(strict_types=1);

namespace App\Company\Application;

use App\Company\CompanyRepository;
use App\Shared\Doctrine\OwnerReferences;
use App\Shared\InvalidValueException;
use App\Shared\OwnershipMismatchException;
use App\Taxonomy\Application\TagLookup;
use App\Taxonomy\Application\TagNotFound;
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
        private TagLookup $tags,
    ) {
    }

    /**
     * @throws InvalidValueException      if the name is blank or too long
     * @throws ORMException
     * @throws OwnershipMismatchException never: the product and tags are looked up for the same owner
     * @throws ProductNotFound
     * @throws ProjectNameTaken
     * @throws TagNotFound
     */
    public function __invoke(CreateProject $command): void
    {
        $owner = $this->owners->get($command->ownerId);
        $product = $this->companies->findProductByIdForOwner($command->productId, $owner)
            ?? throw new ProductNotFound();

        if ($this->companies->projectNameExistsInProduct($product, $command->name)) {
            throw new ProjectNameTaken($command->name);
        }

        $tags = $this->tags->ownedBy($owner, $command->tagIds);

        $product->addProject($command->name, $command->overview, id: $command->projectId)->setTags(...$tags);
    }
}
