<?php

declare(strict_types=1);

namespace App\Taxonomy\Application;

use App\Shared\EventSourcing\ConcurrentModification;
use App\Taxonomy\TagRepository;
use Doctrine\DBAL\Exception;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class UnarchiveTagHandler
{
    public function __construct(
        private TagRepository $tags,
    ) {
    }

    /**
     * @throws ConcurrentModification
     * @throws Exception
     * @throws ExceptionInterface
     * @throws TagNotFound
     */
    public function __invoke(UnarchiveTag $command): void
    {
        $tag = $this->tags->findForOwner($command->tagId, $command->ownerId)
            ?? throw new TagNotFound();

        $tag->unarchive();
        $this->tags->save($tag);
    }
}
