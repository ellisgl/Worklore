<?php

declare(strict_types=1);

namespace App\Taxonomy\Application;

use App\Shared\Doctrine\OwnerReferences;
use App\Taxonomy\CategoryRepository;
use Doctrine\ORM\Exception\ORMException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class UnarchiveTagHandler
{
    public function __construct(
        private CategoryRepository $categories,
        private OwnerReferences $owners,
    ) {
    }

    /**
     * @throws ORMException
     * @throws TagNotFound
     */
    public function __invoke(UnarchiveTag $command): void
    {
        $tag = $this->categories->findTagByIdForOwner($command->tagId, $this->owners->get($command->ownerId))
            ?? throw new TagNotFound();

        $tag->unarchive();
    }
}
