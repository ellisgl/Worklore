<?php

declare(strict_types=1);

namespace App\Taxonomy\Application;

use App\Shared\EventSourcing\ConcurrentModification;
use App\Shared\InvalidValueException;
use App\Taxonomy\CategoryRepository;
use App\Taxonomy\Query\TaxonomyNames;
use Doctrine\DBAL\Exception;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

/**
 * Every change or none: the rules are checked before anything is saved.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class EditCategoryHandler
{
    public function __construct(
        private CategoryRepository $categories,
        private TaxonomyNames $names,
    ) {
    }

    /**
     * @throws CategoryNameTaken
     * @throws CategoryNotFound
     * @throws ConcurrentModification
     * @throws Exception
     * @throws ExceptionInterface
     * @throws InvalidValueException  if the name is blank or too long
     */
    public function __invoke(EditCategory $command): void
    {
        $category = $this->categories->findForOwner($command->categoryId, $command->ownerId)
            ?? throw new CategoryNotFound();

        if (null !== $command->name) {
            if ($this->names->categoryNameTaken($command->ownerId, $command->name, except: $category->id())) {
                throw new CategoryNameTaken($command->name);
            }
            $category->rename($command->name);
        }
        if (null !== $command->description) {
            $category->describe($command->description);
        }

        $this->categories->save($category);
    }
}
