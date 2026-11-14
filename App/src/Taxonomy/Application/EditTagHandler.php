<?php

declare(strict_types=1);

namespace App\Taxonomy\Application;

use App\Shared\EventSourcing\ConcurrentModification;
use App\Shared\InvalidValueException;
use App\Taxonomy\CategoryRepository;
use App\Taxonomy\Query\TaxonomyNames;
use App\Taxonomy\TagRepository;
use Doctrine\DBAL\Exception;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

/**
 * The name must be free in the category the tag ends up in, whether it's renamed, moved, or both.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class EditTagHandler
{
    public function __construct(
        private TagRepository $tags,
        private CategoryRepository $categories,
        private TaxonomyNames $names,
    ) {
    }

    /**
     * @throws CategoryNotFound       if moving to a category that isn't the owner's
     * @throws ConcurrentModification
     * @throws Exception
     * @throws ExceptionInterface
     * @throws InvalidValueException  if the name is blank or too long
     * @throws TagNameTaken
     * @throws TagNotFound
     */
    public function __invoke(EditTag $command): void
    {
        $tag = $this->tags->findForOwner($command->tagId, $command->ownerId)
            ?? throw new TagNotFound();

        $categoryId = null === $command->categoryId
            ? $tag->categoryId
            : ($this->categories->findForOwner($command->categoryId, $command->ownerId) ?? throw new CategoryNotFound())->id();
        $name = $command->name ?? $tag->name->value;

        if ($this->names->tagNameTaken($categoryId, $name, except: $tag->id())) {
            throw new TagNameTaken($name);
        }

        $tag->rename($name);
        $tag->moveTo($categoryId);
        $this->tags->save($tag);
    }
}
