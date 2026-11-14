<?php

declare(strict_types=1);

namespace App\Export\Application;

use App\Journal\Media\MediaRules;
use App\Journal\Media\MediaStorage;
use App\Journal\Media\StorageQuotaExceeded;
use App\Shared\EntityId;
use App\Shared\EventSourcing\ConcurrentModification;
use App\Shared\EventSourcing\DomainEvent;
use App\Shared\EventSourcing\EventSerializer;
use App\Shared\EventSourcing\EventStore;
use App\Shared\EventSourcing\InnerId;
use App\Shared\EventSourcing\Projections;
use App\Shared\InvalidValueException;
use App\Shared\StorageQuotas;
use App\Shared\UserId;
use Closure;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use League\Flysystem\FilesystemException;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\MimeTypeGuesserInterface;
use Symfony\Component\Uid\Uuid;
use Throwable;
use UnexpectedValueException;

use function count;
use function rtrim;
use function sprintf;
use function str_starts_with;

/**
 * Replays an export's events into an empty account, then projects them, so
 * the account ends up with the same records and the same history.
 *
 * Every record gets a new id (the originals may still exist, e.g. when an
 * export is imported on the same server), as does everything inside one
 * (InnerId, e.g. media), and every owner id becomes the importing user.
 * Events keep their original recorded_at. They aren't published on
 * event.bus: they are history, not news. Media files still attached at the
 * end are stored from the archive (MediaImport).
 *
 * Nothing is trusted. Each event must decode, start or continue its own
 * stream in order, and refer only to records in the same export; anything
 * else is a 422 and nothing is imported (one transaction, see command.bus).
 */
#[AsMessageHandler(bus: 'command.bus')]
#[WithMonologChannel('domain')]
final readonly class ImportDataHandler
{
    public function __construct(
        private Connection $db,
        private EventStore $store,
        private EventSerializer $serializer,
        private Projections $projections,
        private StorageQuotas $quotas,
        private MediaStorage $mediaStorage,
        private MediaRules $mediaRules,
        private MimeTypeGuesserInterface $mimeTypes,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @throws AccountNotEmpty
     * @throws ConcurrentModification
     * @throws Exception
     * @throws FilesystemException
     * @throws InvalidValueException
     * @throws StorageQuotaExceeded
     * @throws Throwable
     */
    public function __invoke(ImportData $command): ImportResult
    {
        $owner = ['owner' => $command->ownerId->toRfc4122()];
        if (false !== $this->db->fetchOne('SELECT 1 FROM event_store WHERE user_id = :owner LIMIT 1', $owner)) {
            throw new AccountNotEmpty();
        }

        $newIds = self::newStreamIds($command->events);
        $innerIds = [];
        $map = static function (EntityId $id) use ($newIds, &$innerIds, $command): EntityId {
            if ($id instanceof UserId) {
                return $command->ownerId;
            }
            if ($id instanceof InnerId) {
                return $id::fromString($innerIds[$id->toRfc4122()] ??= Uuid::v7()->toRfc4122());
            }

            return $id::fromString($newIds[$id->toRfc4122()] ?? throw new InvalidValueException('refers to a record that is not in the export'));
        };

        $media = new MediaImport($this->mediaStorage, $this->mediaRules, $this->mimeTypes);
        foreach ($command->events as $i => $exported) {
            [$original, $event] = $this->event($i, $exported, $map);
            $media->follow($original, $event);
            $stored = $this->store->import($exported->streamType, Uuid::fromString($newIds[$exported->streamId->toRfc4122()]), $command->ownerId, $exported->version, $event, $exported->recordedAt);
            $this->projections->project($stored);
        }
        $files = $media->store($command->ownerId, $this->quotas->limitFor($command->ownerId), $command->files);

        $result = new ImportResult(count($newIds), count($command->events), $files);
        $this->logger->info('Imported {events} events into {records} records, with {files} files', ['owner_id' => $owner['owner'], 'events' => $result->events, 'records' => $result->records, 'files' => $files]);

        return $result;
    }

    /**
     * Checks every stream runs 1, 2, 3, ... in export order under one type,
     * and gives each a new id.
     *
     * @param list<ExportedEvent> $events
     *
     * @return array<string, string> old stream id => new one
     *
     * @throws InvalidValueException
     */
    private static function newStreamIds(array $events): array
    {
        $newIds = [];
        $types = [];
        $versions = [];
        foreach ($events as $i => $event) {
            $id = $event->streamId->toRfc4122();
            if ($event->version !== ($versions[$id] ?? 0) + 1) {
                throw ExportDocument::invalid($i, sprintf('is version %d of its record, out of order', $event->version));
            }
            if (($types[$id] ?? $event->streamType) !== $event->streamType || !str_starts_with($event->type, $event->streamType.'.')) {
                throw ExportDocument::invalid($i, 'does not belong to its record\'s type');
            }
            $versions[$id] = $event->version;
            $types[$id] = $event->streamType;
            $newIds[$id] ??= Uuid::v7()->toRfc4122();
        }

        return $newIds;
    }

    /**
     * The exported event, decoded, and a copy with its ids remapped. Only
     * the first event of a record names its owner (the one that created it).
     *
     * @param Closure(EntityId): EntityId $map
     *
     * @return array{DomainEvent, DomainEvent} as exported, as imported
     *
     * @throws InvalidValueException
     */
    private function event(int $i, ExportedEvent $exported, Closure $map): array
    {
        try {
            $event = $this->serializer->event($exported->type, $exported->data);
        } catch (UnexpectedValueException $e) {
            throw ExportDocument::invalid($i, 'cannot be read: '.rtrim($e->getMessage(), '.'));
        }

        $namesOwner = false;
        try {
            $imported = $this->serializer->withIds($event, static function (EntityId $id) use ($map, &$namesOwner): EntityId {
                $namesOwner = $namesOwner || $id instanceof UserId;

                return $map($id);
            });
        } catch (InvalidValueException $e) {
            throw ExportDocument::invalid($i, $e->getMessage());
        }

        if ($namesOwner !== (1 === $exported->version)) {
            throw ExportDocument::invalid($i, 1 === $exported->version ? 'does not create its record' : 'creates a record that already exists');
        }

        return [$event, $imported];
    }
}
