<?php

declare(strict_types=1);

namespace App\Taxonomy\Application;

use App\Shared\Doctrine\OwnerReferences;
use App\Shared\InvalidValueException;
use App\Taxonomy\CategoryEntity;
use App\Taxonomy\CategoryRepository;
use Doctrine\ORM\Exception\ORMException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Runs inside command.bus's transaction: the new category is flushed and
 * committed when this returns.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class CreateCategoryHandler
{
    public function __construct(
        private CategoryRepository $categories,
        private OwnerReferences $owners,
    ) {
    }

    /**
     * @throws InvalidValueException if the name is blank or too long
     * @throws ORMException
     */
    public function __invoke(CreateCategory $command): void
    {
        $this->categories->add(new CategoryEntity(
            $this->owners->get($command->ownerId),
            $command->name,
            $command->description,
            id: $command->categoryId,
        ));
    }
}
