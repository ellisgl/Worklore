<?php

declare(strict_types=1);

namespace App\Journal\Listener;

use App\Journal\Media\MediaStorage;
use App\Shared\UserDeleted;
use League\Flysystem\FilesystemException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * A deleted user's files go with them.
 */
#[AsMessageHandler(bus: 'event.bus')]
final readonly class DeleteUsersMedia
{
    public function __construct(private MediaStorage $storage)
    {
    }

    /**
     * @throws FilesystemException
     */
    public function __invoke(UserDeleted $event): void
    {
        $this->storage->deleteAllOf($event->userId);
    }
}
