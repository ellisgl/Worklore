<?php

declare(strict_types=1);

namespace App\Taxonomy\Application;

use App\Shared\EventSourcing\ConcurrentModification;
use App\Taxonomy\TagRepository;
use DateTimeZone;
use Doctrine\DBAL\Exception;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class ArchiveTagHandler
{
    public function __construct(
        private TagRepository $tags,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws ConcurrentModification
     * @throws Exception
     * @throws ExceptionInterface
     * @throws TagNotFound
     */
    public function __invoke(ArchiveTag $command): void
    {
        $tag = $this->tags->findForOwner($command->tagId, $command->ownerId)
            ?? throw new TagNotFound();

        $tag->archive($this->clock->now()->setTimezone(new DateTimeZone('UTC')));
        $this->tags->save($tag);
    }
}
