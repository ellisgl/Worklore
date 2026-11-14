<?php

declare(strict_types=1);

namespace App\Taxonomy\Application;

use App\Shared\EventSourcing\ConcurrentModification;
use App\Taxonomy\CategoryRepository;
use DateTimeZone;
use Doctrine\DBAL\Exception;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class ArchiveCategoryHandler
{
    public function __construct(
        private CategoryRepository $categories,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws CategoryNotFound
     * @throws ConcurrentModification
     * @throws Exception
     * @throws ExceptionInterface
     */
    public function __invoke(ArchiveCategory $command): void
    {
        $category = $this->categories->findForOwner($command->categoryId, $command->ownerId)
            ?? throw new CategoryNotFound();

        $category->archive($this->clock->now()->setTimezone(new DateTimeZone('UTC')));
        $this->categories->save($category);
    }
}
