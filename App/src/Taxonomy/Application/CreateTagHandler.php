<?php

declare(strict_types=1);

namespace App\Taxonomy\Application;

use App\Shared\EventSourcing\ConcurrentModification;
use App\Shared\InvalidValueException;
use App\Taxonomy\CategoryRepository;
use App\Taxonomy\Query\TaxonomyNames;
use App\Taxonomy\Tag;
use App\Taxonomy\TagRepository;
use DateTimeZone;
use Doctrine\DBAL\Exception;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

/**
 * The category must be the owner's; tag names are unique within it, ignoring case.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class CreateTagHandler
{
    public function __construct(
        private TagRepository $tags,
        private CategoryRepository $categories,
        private TaxonomyNames $names,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws CategoryNotFound
     * @throws ConcurrentModification
     * @throws Exception
     * @throws ExceptionInterface
     * @throws InvalidValueException  if the name is blank or too long
     * @throws TagNameTaken
     */
    public function __invoke(CreateTag $command): void
    {
        $category = $this->categories->findForOwner($command->categoryId, $command->ownerId)
            ?? throw new CategoryNotFound();

        if ($this->names->tagNameTaken($category->id(), $command->name)) {
            throw new TagNameTaken($command->name);
        }

        $this->tags->save(Tag::create($command->tagId, $category->id(), $command->ownerId, $command->name, $this->clock->now()->setTimezone(new DateTimeZone('UTC'))));
    }
}
