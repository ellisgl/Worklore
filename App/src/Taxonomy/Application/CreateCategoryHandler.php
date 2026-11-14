<?php

declare(strict_types=1);

namespace App\Taxonomy\Application;

use App\Shared\EventSourcing\ConcurrentModification;
use App\Shared\InvalidValueException;
use App\Taxonomy\Category;
use App\Taxonomy\CategoryRepository;
use App\Taxonomy\Query\TaxonomyNames;
use DateTimeZone;
use Doctrine\DBAL\Exception;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

/**
 * Names are unique per owner, ignoring case.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class CreateCategoryHandler
{
    public function __construct(
        private CategoryRepository $categories,
        private TaxonomyNames $names,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws CategoryNameTaken
     * @throws ConcurrentModification
     * @throws Exception
     * @throws ExceptionInterface
     * @throws InvalidValueException  if the name is blank or too long
     */
    public function __invoke(CreateCategory $command): void
    {
        if ($this->names->categoryNameTaken($command->ownerId, $command->name)) {
            throw new CategoryNameTaken($command->name);
        }

        $this->categories->save(Category::create($command->categoryId, $command->ownerId, $command->name, $command->description, $this->clock->now()->setTimezone(new DateTimeZone('UTC'))));
    }
}
