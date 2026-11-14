<?php

declare(strict_types=1);

namespace App\Shared\EventSourcing;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

use function implode;
use function sprintf;

#[AsCommand(name: 'app:projections:rebuild', description: 'Empty the read tables and replay every event into them')]
final readonly class RebuildProjectionsCommand
{
    public function __construct(
        private Connection $db,
        private EventStore $events,
        private Projections $projections,
    ) {
    }

    /**
     * @throws Exception
     * @throws Throwable
     */
    public function __invoke(SymfonyStyle $io): int
    {
        $count = $this->db->transactional(function (Connection $db): int {
            $tables = $this->projections->tables();
            if ([] !== $tables) {
                $db->executeStatement('TRUNCATE '.implode(', ', $tables));
            }

            $count = 0;
            foreach ($this->events->all() as $event) {
                $this->projections->project($event);
                ++$count;
            }

            return $count;
        });

        $io->success(sprintf('Replayed %d event(s) into the projections.', $count));

        return Command::SUCCESS;
    }
}
