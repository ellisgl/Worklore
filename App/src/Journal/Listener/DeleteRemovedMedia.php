<?php

declare(strict_types=1);

namespace App\Journal\Listener;

use App\Journal\Event\JournalEntryMediaRemoved;
use App\Journal\Media\MediaStorage;
use App\Shared\UserId;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use League\Flysystem\FilesystemException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

use function is_string;

/**
 * Deletes a removed file's bytes once the removal has committed. If this
 * fails, the file is orphaned; app:media:prune deletes it later.
 */
#[AsMessageHandler(bus: 'event.bus')]
final readonly class DeleteRemovedMedia
{
    public function __construct(
        private Connection $db,
        private MediaStorage $storage,
    ) {
    }

    /**
     * @throws Exception
     * @throws FilesystemException
     */
    public function __invoke(JournalEntryMediaRemoved $event): void
    {
        $owner = $this->db->fetchOne('SELECT user_id FROM journal_entry WHERE id = :id', ['id' => $event->entryId->toRfc4122()]);
        if (is_string($owner)) {
            $this->storage->delete(UserId::fromString($owner), $event->mediaId);
        }
    }
}
