<?php

declare(strict_types=1);

namespace App\Taxonomy\Application;

use App\Shared\EventSourcing\ConcurrentModification;
use App\Taxonomy\CategoryRepository;
use Doctrine\DBAL\Exception;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class UnarchiveCategoryHandler
{
    public function __construct(
        private CategoryRepository $categories,
    ) {
    }

    /**
     * @throws CategoryNotFound
     * @throws ConcurrentModification
     * @throws Exception
     * @throws ExceptionInterface
     */
    public function __invoke(UnarchiveCategory $command): void
    {
        $category = $this->categories->findForOwner($command->categoryId, $command->ownerId)
            ?? throw new CategoryNotFound();

        $category->unarchive();
        $this->categories->save($category);
    }
}
