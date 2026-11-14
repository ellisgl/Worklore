<?php

declare(strict_types=1);

namespace App\Purge\Listener;

use App\Journal\Media\MediaStorage;
use App\Journal\MediaId;
use App\Purge\Application\MediaPurged;
use League\Flysystem\FilesystemException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Deletes purged journal entries' files once the purge has committed. If it
 * fails, app:media:prune deletes them later (nothing refers to them).
 */
#[AsMessageHandler(bus: 'event.bus')]
final readonly class DeletePurgedMedia
{
    public function __construct(private MediaStorage $storage)
    {
    }

    /**
     * @throws FilesystemException
     */
    public function __invoke(MediaPurged $event): void
    {
        foreach ($event->mediaIds as $id) {
            $this->storage->delete($event->ownerId, MediaId::fromString($id));
        }
    }
}
