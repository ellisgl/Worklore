<?php

declare(strict_types=1);

namespace App\Company\Application;

use App\Company\PositionRepository;
use App\Shared\EventSourcing\ConcurrentModification;
use App\Shared\InvalidValueException;
use Doctrine\DBAL\Exception;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class EndPositionHandler
{
    public function __construct(
        private PositionRepository $positions,
    ) {
    }

    /**
     * @throws ConcurrentModification
     * @throws Exception
     * @throws ExceptionInterface
     * @throws InvalidValueException  if the end is before the start, or the reason is too long
     * @throws PositionNotFound
     */
    public function __invoke(EndPosition $command): void
    {
        $position = $this->positions->findForOwner($command->positionId, $command->ownerId)
            ?? throw new PositionNotFound();

        $position->end($command->endDate, $command->reasonLeft);
        $this->positions->save($position);
    }
}
