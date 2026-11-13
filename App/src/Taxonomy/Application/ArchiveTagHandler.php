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
final readonly class ArchiveTagHandler
{
    public function __construct(
        private CategoryRepository $categories,
        private OwnerReferences $owners,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws ORMException
     * @throws TagNotFound
     */
    public function __invoke(ArchiveTag $command): void
    {
        $tag = $this->categories->findTagByIdForOwner($command->tagId, $this->owners->get($command->ownerId))
            ?? throw new TagNotFound();

        $tag->archive($this->clock->now()->setTimezone(new DateTimeZone('UTC')));
    }
}
