<?php

declare(strict_types=1);

namespace App\Taxonomy\Application;

use App\Shared\Doctrine\OwnerReferences;
use App\Shared\InvalidValueException;
use App\Shared\OwnershipMismatchException;
use App\Taxonomy\CategoryRepository;
use Doctrine\ORM\Exception\ORMException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * The name must be free in the category the tag ends up in, whether it's
 * renamed, moved, or both.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class EditTagHandler
{
    public function __construct(
        private CategoryRepository $categories,
        private OwnerReferences $owners,
    ) {
    }

    /**
     * @throws CategoryNotFound           if moving to a category that isn't the owner's
     * @throws InvalidValueException      if the name is blank or too long
     * @throws ORMException
     * @throws OwnershipMismatchException never: both are looked up for the same owner
     * @throws TagNameTaken
     * @throws TagNotFound
     */
    public function __invoke(EditTag $command): void
    {
        $owner = $this->owners->get($command->ownerId);
        $tag = $this->categories->findTagByIdForOwner($command->tagId, $owner)
            ?? throw new TagNotFound();

        $category = null === $command->categoryId
            ? $tag->category
            : $this->categories->findOneByIdForOwner($command->categoryId, $owner) ?? throw new CategoryNotFound();
        $name = $command->name ?? $tag->name->value;

        if ($this->categories->nameExistsInCategory($category, $name, except: $tag)) {
            throw new TagNameTaken($name);
        }

        $tag->setName($name);
        $tag->setCategory($category);
    }
}
