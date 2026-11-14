<?php

declare(strict_types=1);

namespace App\Company\Application;

use App\Company\PositionRepository;
use App\Shared\EventSourcing\ConcurrentModification;
use App\Shared\InvalidValueException;
use Doctrine\DBAL\Exception;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

/**
 * Every change or none: the rules are checked before anything is saved.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class EditPositionHandler
{
    public function __construct(
        private PositionRepository $positions,
    ) {
    }

    /**
     * @throws ConcurrentModification
     * @throws Exception
     * @throws ExceptionInterface
     * @throws InvalidValueException  if the title is blank or too long, or the new start is after the end
     * @throws PositionNotFound
     */
    public function __invoke(EditPosition $command): void
    {
        $position = $this->positions->findForOwner($command->positionId, $command->ownerId)
            ?? throw new PositionNotFound();

        if (null !== $command->title) {
            $position->retitle($command->title);
        }
        if (null !== $command->overview) {
            $position->describe($command->overview);
        }
        if (null !== $command->startDate) {
            $position->moveStartDate($command->startDate);
        }

        $this->positions->save($position);
    }
}
