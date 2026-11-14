<?php

declare(strict_types=1);

namespace App\Journal\Media;

use App\Journal\MediaId;
use App\Shared\UserId;
use Generator;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\StorageAttributes;

/**
 * Where attached files' bytes live: media.storage (local disk or S3, see
 * config/packages/flysystem.yaml), one object per file at "{owner id}/{media id}",
 * so all of a user's files can go at once.
 */
final readonly class MediaStorage
{
    public function __construct(
        // Named autowiring: $mediaStorage is the media.storage Flysystem storage.
        private FilesystemOperator $mediaStorage,
    ) {
    }

    public static function path(UserId $ownerId, MediaId $mediaId): string
    {
        return $ownerId->toRfc4122().'/'.$mediaId->toRfc4122();
    }

    /**
     * @param resource $contents
     *
     * @throws FilesystemException
     */
    public function write(UserId $ownerId, MediaId $mediaId, $contents): void
    {
        $this->mediaStorage->writeStream(self::path($ownerId, $mediaId), $contents);
    }

    /**
     * @return resource
     *
     * @throws FilesystemException
     */
    public function read(UserId $ownerId, MediaId $mediaId)
    {
        return $this->mediaStorage->readStream(self::path($ownerId, $mediaId));
    }

    /**
     * Deleting a file that isn't there does nothing.
     *
     * @throws FilesystemException
     */
    public function delete(UserId $ownerId, MediaId $mediaId): void
    {
        $this->mediaStorage->delete(self::path($ownerId, $mediaId));
    }

    /**
     * A path as all() lists it.
     *
     * @throws FilesystemException
     */
    public function deletePath(string $path): void
    {
        $this->mediaStorage->delete($path);
    }

    /**
     * @throws FilesystemException
     */
    public function deleteAllOf(UserId $ownerId): void
    {
        $this->mediaStorage->deleteDirectory($ownerId->toRfc4122());
    }

    /**
     * Every stored file, in no particular order.
     *
     * @return Generator<array{path: string, lastModified: int|null}>
     *
     * @throws FilesystemException
     */
    public function all(): Generator
    {
        /** @var StorageAttributes $item */
        foreach ($this->mediaStorage->listContents('', true) as $item) {
            if ($item->isFile()) {
                yield ['path' => $item->path(), 'lastModified' => $item->lastModified()];
            }
        }
    }
}
