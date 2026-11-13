<?php

declare(strict_types=1);

namespace App\Taxonomy\Application;

use App\Shared\Doctrine\OwnerReferences;
use App\Taxonomy\CategoryRepository;
use DateTimeZone;
use Doctrine\ORM\Exception\ORMException;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class ArchiveCategoryHandler
{
    public function __construct(
        private CategoryRepository $categories,
        private OwnerReferences $owners,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws CategoryNotFound
     * @throws ORMException
     */
    public function __invoke(ArchiveCategory $command): void
    {
        $category = $this->categories->findOneByIdForOwner($command->categoryId, $this->owners->get($command->ownerId))
            ?? throw new CategoryNotFound();

        $category->archive($this->clock->now()->setTimezone(new DateTimeZone('UTC')));
    }
}
