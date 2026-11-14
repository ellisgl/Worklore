<?php

declare(strict_types=1);

namespace App\Company\Application;

use App\Company\PositionRepository;
use App\Shared\EventSourcing\ConcurrentModification;
use DateTimeZone;
use Doctrine\DBAL\Exception;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class ArchivePositionHandler
{
    public function __construct(
        private PositionRepository $positions,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws ConcurrentModification
     * @throws Exception
     * @throws ExceptionInterface
     * @throws PositionNotFound
     */
    public function __invoke(ArchivePosition $command): void
    {
        $position = $this->positions->findForOwner($command->positionId, $command->ownerId)
            ?? throw new PositionNotFound();

        $position->archive($this->clock->now()->setTimezone(new DateTimeZone('UTC')));
        $this->positions->save($position);
    }
}
