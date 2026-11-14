<?php

declare(strict_types=1);

namespace App\Export;

use App\Journal\Media\MediaStorage;
use App\Journal\MediaId;
use App\Shared\UserId;
use League\Flysystem\FilesystemException;
use Symfony\Component\Serializer\SerializerInterface;
use ZipStream\CompressionMethod;
use ZipStream\Exception\OverflowException;
use ZipStream\ZipStream;

use function fclose;
use function is_string;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * The complete export as a zip, written as it is read (nothing is buffered):
 *
 *     export.json     the DataExport, as GET /api/me/export gives it
 *     media/{id}      each attached file's bytes, by media id
 *
 * POST /api/me/import takes it back as is.
 */
final readonly class ArchiveExporter
{
    public const string DOCUMENT = 'export.json';
    public const string MEDIA_DIRECTORY = 'media/';

    public function __construct(
        private SerializerInterface $serializer,
        private MediaStorage $storage,
    ) {
    }

    /**
     * @param resource $output
     *
     * @throws FilesystemException
     * @throws OverflowException
     */
    public function write(DataExport $export, UserId $ownerId, $output): void
    {
        $zip = new ZipStream(outputStream: $output, sendHttpHeaders: false, defaultEnableZeroHeader: true);
        $zip->addFile(self::DOCUMENT, $this->serializer->serialize($export, 'json', [
            'json_encode_options' => JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ]));

        foreach ($export->media as $media) {
            $id = $media['id'] ?? null;
            if (!is_string($id)) {
                continue;
            }
            $stream = $this->storage->read($ownerId, MediaId::fromString($id));
            try {
                // Media is mostly compressed already (JPEG, MP4, ...): store it as is
                $zip->addFileFromStream(self::MEDIA_DIRECTORY.$id, $stream, compressionMethod: CompressionMethod::STORE);
            } finally {
                fclose($stream);
            }
        }

        $zip->finish();
    }
}
