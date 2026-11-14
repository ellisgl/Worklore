<?php

declare(strict_types=1);

namespace App\Company;

use App\Shared\EventSourcing\AggregateStore;
use App\Shared\EventSourcing\ConcurrentModification;
use App\Shared\UserId;
use Doctrine\DBAL\Exception;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

/**
 * Positions, loaded from and saved to their event streams.
 */
final readonly class PositionRepository
{
    public function __construct(private AggregateStore $store)
    {
    }

    /**
     * The position, if it exists and belongs to the owner; null otherwise, so an
     * endpoint answers 404 instead of revealing someone else's record.
     * Archived ones are returned too.
     *
     * @throws Exception
     */
    public function findForOwner(PositionId $id, UserId $ownerId): ?Position
    {
        $history = $this->store->history(CompanyEvents::POSITION, $id);
        if ([] === $history) {
            return null;
        }
        $position = Position::fromHistory($history);

        return $position->ownerId()->equals($ownerId) ? $position : null;
    }

    /**
     * @throws ConcurrentModification
     * @throws Exception
     * @throws ExceptionInterface
     */
    public function save(Position $position): void
    {
        $this->store->save(CompanyEvents::POSITION, $position);
    }
}
