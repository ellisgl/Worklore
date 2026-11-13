<?php

declare(strict_types=1);

namespace App\Taxonomy\Application;

use App\Shared\Doctrine\OwnerReferences;
use App\Shared\InvalidValueException;
use App\Taxonomy\CategoryRepository;
use Doctrine\ORM\Exception\ORMException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Adds the tag through its category (the aggregate root); the bus commits it.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class CreateTagHandler
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
     * @throws TagNameTaken
     */
    public function __invoke(CreateTag $command): void
    {
        $category = $this->categories->findOneByIdForOwner($command->categoryId, $this->owners->get($command->ownerId))
            ?? throw new CategoryNotFound();

        if ($this->categories->nameExistsInCategory($category, $command->name)) {
            throw new TagNameTaken($command->name);
        }

        $category->addTag($command->name, id: $command->tagId);
    }
}
