<?php

declare(strict_types=1);

namespace App\Company\Application;

use App\Company\PositionRepository;
use App\Shared\EventSourcing\ConcurrentModification;
use Doctrine\DBAL\Exception;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class ReopenPositionHandler
{
    public function __construct(
        private PositionRepository $positions,
    ) {
    }

    /**
     * @throws ConcurrentModification
     * @throws Exception
     * @throws ExceptionInterface
     * @throws PositionNotFound
     */
    public function __invoke(ReopenPosition $command): void
    {
        $position = $this->positions->findForOwner($command->positionId, $command->ownerId)
            ?? throw new PositionNotFound();

        $position->reopen();
        $this->positions->save($position);
    }
}
