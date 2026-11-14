<?php

declare(strict_types=1);

namespace App\Journal\Command;

use App\Journal\Media\MediaStorage;
use App\Journal\Query\MediaQueries;
use Doctrine\DBAL\Exception;
use League\Flysystem\FilesystemException;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;

use function array_keys;
use function count;
use function explode;
use function sprintf;

/**
 * Stored files no journal entry refers to are orphans: an upload whose
 * transaction failed after the file was written, or a delete that failed
 * after the change committed. Files younger than the grace period are left
 * alone, as their upload may still be committing.
 */
#[AsCommand(name: 'app:media:prune', description: 'Delete stored media files that no journal entry refers to')]
final readonly class PruneMediaCommand
{
    private const int BATCH = 500;

    public function __construct(
        private MediaStorage $storage,
        private MediaQueries $media,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws Exception
     * @throws FilesystemException
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Only list what would be deleted')] bool $dryRun = false,
        #[Option(description: 'Leave files younger than this many minutes')] int $graceMinutes = 60,
    ): int {
        $before = $this->clock->now()->getTimestamp() - $graceMinutes * 60;
        $orphans = 0;
        $batch = [];
        foreach ($this->storage->all() as $file) {
            [$owner, $media] = explode('/', $file['path'], 2) + [1 => ''];
            if (!Uuid::isValid($owner) || !Uuid::isValid($media)) {
                $io->warning(sprintf('Skipped %s: not a media file path.', $file['path']));
                continue;
            }
            if (null !== $file['lastModified'] && $file['lastModified'] > $before) {
                continue;
            }
            $batch[$media] = $file['path'];
            if (count($batch) >= self::BATCH) {
                $orphans += $this->prune($io, $batch, $dryRun);
                $batch = [];
            }
        }
        $orphans += $this->prune($io, $batch, $dryRun);

        $io->success(sprintf($dryRun ? 'Found %d orphaned file(s); nothing deleted (--dry-run).' : 'Deleted %d orphaned file(s).', $orphans));

        return Command::SUCCESS;
    }

    /**
     * @param array<string, string> $batch path by media id
     *
     * @throws Exception
     * @throws FilesystemException
     */
    private function prune(SymfonyStyle $io, array $batch, bool $dryRun): int
    {
        $existing = $this->media->existing(array_keys($batch));
        $orphans = 0;
        foreach ($batch as $mediaId => $path) {
            if (isset($existing[$mediaId])) {
                continue;
            }
            ++$orphans;
            $io->writeln(($dryRun ? 'Would delete ' : 'Deleting ').$path, SymfonyStyle::VERBOSITY_VERBOSE);
            if (!$dryRun) {
                $this->storage->deletePath($path);
            }
        }

        return $orphans;
    }
}
