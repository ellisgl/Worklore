<?php

declare(strict_types=1);

namespace App\Taxonomy\Application;

use App\Shared\Doctrine\OwnerReferences;
use App\Shared\InvalidValueException;
use App\Taxonomy\CategoryRepository;
use Doctrine\ORM\Exception\ORMException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class EditCategoryHandler
{
    public function __construct(
        private CategoryRepository $categories,
        private OwnerReferences $owners,
    ) {
    }

    /**
     * @throws CategoryNotFound
     * @throws InvalidValueException if the name is blank or too long
     * @throws ORMException
     */
    public function __invoke(EditCategory $command): void
    {
        $category = $this->categories->findOneByIdForOwner($command->categoryId, $this->owners->get($command->ownerId))
            ?? throw new CategoryNotFound();

        if (null !== $command->name) {
            $category->setName($command->name);
        }
        if (null !== $command->description) {
            $category->setDescription($command->description);
        }
    }
}
