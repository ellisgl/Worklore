<?php

declare(strict_types=1);

namespace App\Taxonomy\Application;

use App\Shared\Doctrine\OwnerReferences;
use App\Taxonomy\CategoryRepository;
use Doctrine\ORM\Exception\ORMException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class UnarchiveCategoryHandler
{
    public function __construct(
        private CategoryRepository $categories,
        private OwnerReferences $owners,
    ) {
    }

    /**
     * @throws CategoryNotFound
     * @throws ORMException
     */
    public function __invoke(UnarchiveCategory $command): void
    {
        $category = $this->categories->findOneByIdForOwner($command->categoryId, $this->owners->get($command->ownerId))
            ?? throw new CategoryNotFound();

        $category->unarchive();
    }
}
