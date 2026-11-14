<?php

declare(strict_types=1);

namespace App\Export\Application;

use App\Journal\Event\JournalEntryMediaAttached;
use App\Journal\Event\JournalEntryMediaRemoved;
use App\Journal\Media\MediaRules;
use App\Journal\Media\MediaStorage;
use App\Journal\Media\StorageQuotaExceeded;
use App\Journal\MediaId;
use App\Shared\EventSourcing\DomainEvent;
use App\Shared\InvalidValueException;
use App\Shared\UserId;
use League\Flysystem\FilesystemException;
use RuntimeException;
use Symfony\Component\Mime\MimeTypeGuesserInterface;
use Throwable;

use function array_column;
use function array_sum;
use function count;
use function fclose;
use function filesize;
use function fopen;
use function hash_file;
use function is_resource;
use function sprintf;
use function unlink;

/**
 * The media side of one import: follows the replayed events to learn which
 * files are still attached at the end, then stores each from the archive
 * under its new id. Every file must match what its event says (size,
 * SHA-256, and type as detected from the content) and the media rules, and
 * together they must fit in the owner's quota.
 */
final class MediaImport
{
    /** @var array<string, array{exported: string, media: MediaId, size: int, sha256: string, mediaType: string}> by new media id */
    private array $attached = [];

    public function __construct(
        private readonly MediaStorage $storage,
        private readonly MediaRules $rules,
        private readonly MimeTypeGuesserInterface $types,
    ) {
    }

    /**
     * @param DomainEvent $exported as in the export (old ids)
     * @param DomainEvent $imported the same with its new ids
     */
    public function follow(DomainEvent $exported, DomainEvent $imported): void
    {
        if ($exported instanceof JournalEntryMediaAttached && $imported instanceof JournalEntryMediaAttached) {
            $this->attached[$imported->mediaId->toRfc4122()] = [
                'exported' => $exported->mediaId->toRfc4122(),
                'media' => $imported->mediaId,
                'size' => $imported->size,
                'sha256' => $imported->sha256,
                'mediaType' => $imported->mediaType,
            ];
        } elseif ($imported instanceof JournalEntryMediaRemoved) {
            unset($this->attached[$imported->mediaId->toRfc4122()]);
        }
    }

    /**
     * Stores the files still attached. If anything fails, the files stored
     * so far are deleted again.
     *
     * @return int files stored
     *
     * @throws FilesystemException
     * @throws InvalidValueException if a file is missing or doesn't match
     * @throws StorageQuotaExceeded
     * @throws Throwable
     */
    public function store(UserId $ownerId, int $quota, ?ImportedFiles $files): int
    {
        if ([] === $this->attached) {
            return 0;
        }
        if (null === $files) {
            throw new InvalidValueException('This export has media files. Import the archive (GET /api/me/export/archive) instead of the JSON.');
        }
        $total = array_sum(array_column($this->attached, 'size'));
        if ($total > $quota) {
            throw new StorageQuotaExceeded($total, 0, $quota, 'the export\'s files are');
        }

        $stored = [];
        try {
            foreach ($this->attached as $file) {
                $this->storeOne($ownerId, $file, $files);
                $stored[] = $file['media'];
            }
        } catch (Throwable $e) {
            foreach ($stored as $mediaId) {
                $this->storage->delete($ownerId, $mediaId);
            }
            throw $e;
        }

        return count($stored);
    }

    /**
     * @param array{exported: string, media: MediaId, size: int, sha256: string, mediaType: string} $file
     *
     * @throws FilesystemException
     * @throws InvalidValueException
     */
    private function storeOne(UserId $ownerId, array $file, ImportedFiles $files): void
    {
        $name = 'media/'.$file['exported'];
        $path = $files->extract($file['exported'], $this->rules->maxFileSize)
            ?? throw new InvalidValueException(sprintf('The archive is missing %s.', $name));
        try {
            if (filesize($path) !== $file['size'] || hash_file('sha256', $path) !== $file['sha256']) {
                throw new InvalidValueException(sprintf('%s in the archive doesn\'t match its size or checksum.', $name));
            }
            $detected = $this->types->guessMimeType($path) ?? 'application/octet-stream';
            if ($detected !== $file['mediaType']) {
                throw new InvalidValueException(sprintf('%s in the archive is %s, not the %s its record says.', $name, $detected, $file['mediaType']));
            }
            $this->rules->check($file['size'], $detected);

            $stream = fopen($path, 'r');
            if (!is_resource($stream)) {
                throw new RuntimeException(sprintf('Cannot read %s.', $name));
            }
            try {
                $this->storage->write($ownerId, $file['media'], $stream);
            } finally {
                fclose($stream);
            }
        } finally {
            unlink($path);
        }
    }
}
