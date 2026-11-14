<?php

declare(strict_types=1);

namespace App\Journal\Application;

use App\Journal\JournalEntryRepository;
use App\Journal\Media;
use App\Journal\Media\MediaRules;
use App\Journal\Media\MediaStorage;
use App\Journal\Media\StorageQuotaExceeded;
use App\Journal\Query\MediaQueries;
use App\Shared\EventSourcing\ConcurrentModification;
use App\Shared\InvalidValueException;
use App\Shared\StorageQuotas;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use League\Flysystem\FilesystemException;
use Psr\Clock\ClockInterface;
use RuntimeException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Throwable;

use function fclose;
use function fopen;
use function is_resource;

/**
 * Checks the owner's quota, stores the file, then records it on the entry.
 * If recording fails, the stored file is deleted again.
 *
 * Uploads by the same owner take turns (a transaction-scoped advisory lock),
 * so two at once can't both fit in the space left for one.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class AttachMediaHandler
{
    public function __construct(
        private JournalEntryRepository $entries,
        private MediaRules $rules,
        private MediaStorage $storage,
        private MediaQueries $media,
        private StorageQuotas $quotas,
        private Connection $db,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws ConcurrentModification
     * @throws Exception
     * @throws ExceptionInterface
     * @throws FilesystemException
     * @throws InvalidValueException  if the file breaks MediaRules
     * @throws JournalEntryNotFound
     * @throws StorageQuotaExceeded
     * @throws Throwable
     */
    public function __invoke(AttachMedia $command): void
    {
        $this->rules->check($command->size, $command->mediaType);
        $media = new Media($command->mediaId, $command->filename, $command->mediaType, $command->size, $command->sha256);

        $entry = $this->entries->findForOwner($command->entryId, $command->ownerId)
            ?? throw new JournalEntryNotFound();

        $this->db->executeStatement('SELECT pg_advisory_xact_lock(hashtextextended(:owner, 0))', ['owner' => 'media:'.$command->ownerId->toRfc4122()]);
        $used = $this->media->usedBy($command->ownerId);
        $limit = $this->quotas->limitFor($command->ownerId);
        if ($used + $media->size > $limit) {
            throw new StorageQuotaExceeded($media->size, $used, $limit);
        }

        $file = fopen($command->path, 'r');
        if (!is_resource($file)) {
            throw new RuntimeException('The uploaded file can\'t be read.');
        }
        try {
            $this->storage->write($command->ownerId, $media->id, $file);
        } finally {
            fclose($file);
        }

        try {
            $entry->attach($media, $this->clock->now()->setTimezone(new DateTimeZone('UTC')));
            $this->entries->save($entry);
        } catch (Throwable $e) {
            $this->storage->delete($command->ownerId, $media->id);
            throw $e;
        }
    }
}
