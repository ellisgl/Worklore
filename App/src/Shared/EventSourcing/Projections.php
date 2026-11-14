<?php

declare(strict_types=1);

namespace App\Shared\EventSourcing;

use Doctrine\DBAL\Exception;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Hands each stored event to every projector.
 */
final readonly class Projections
{
    /**
     * @param iterable<Projector> $projectors
     */
    public function __construct(#[AutowireIterator(Projector::class)] private iterable $projectors)
    {
    }

    /**
     * @throws Exception
     */
    public function project(StoredEvent $event): void
    {
        foreach ($this->projectors as $projector) {
            $projector->project($event);
        }
    }

    /**
     * @param list<string> $ids
     *
     * @throws Exception
     */
    public function forget(string $streamType, array $ids): void
    {
        if ([] === $ids) {
            return;
        }
        foreach ($this->projectors as $projector) {
            $projector->forget($streamType, $ids);
        }
    }

    /**
     * @return list<string> every projection table
     */
    public function tables(): array
    {
        $tables = [];
        foreach ($this->projectors as $projector) {
            foreach ($projector->tables() as $table) {
                $tables[] = $table;
            }
        }

        return $tables;
    }
}
