<?php

declare(strict_types=1);

namespace App\Export\Application;

use App\Export\ArchiveExporter;
use App\Shared\InvalidValueException;
use JsonException;
use RuntimeException;
use ZipArchive;

use function fclose;
use function file_get_contents;
use function fopen;
use function is_array;
use function is_resource;
use function json_decode;
use function stream_copy_to_stream;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

use const JSON_THROW_ON_ERROR;

/**
 * A zip from GET /api/me/export/archive, opened for import. Sizes are
 * checked as bytes are read, not taken from the zip's own (forgeable)
 * headers, so a zip bomb stops at the limit.
 */
final class ExportArchive implements ImportedFiles
{
    /** export.json bigger than this is refused */
    private const int MAX_DOCUMENT_BYTES = 256 * 1024 * 1024;

    private function __construct(private readonly ZipArchive $zip)
    {
    }

    public function __destruct()
    {
        $this->zip->close();
    }

    /**
     * @throws InvalidValueException if it isn't a zip
     */
    public static function open(string $path): self
    {
        $zip = new ZipArchive();
        if (true !== $zip->open($path, ZipArchive::RDONLY)) {
            throw new InvalidValueException('The file is not a zip archive.');
        }

        return new self($zip);
    }

    /**
     * The decoded export.json.
     *
     * @return array<array-key, mixed>
     *
     * @throws InvalidValueException if it is missing, too big or not JSON
     */
    public function document(): array
    {
        $path = $this->extractEntry(ArchiveExporter::DOCUMENT, self::MAX_DOCUMENT_BYTES)
            ?? throw new InvalidValueException('The archive has no '.ArchiveExporter::DOCUMENT.'.');
        try {
            $document = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidValueException(ArchiveExporter::DOCUMENT.' in the archive is not valid JSON.');
        } finally {
            unlink($path);
        }
        if (!is_array($document)) {
            throw new InvalidValueException(ArchiveExporter::DOCUMENT.' in the archive is not a JSON object.');
        }

        return $document;
    }

    public function extract(string $mediaId, int $maxBytes): ?string
    {
        return $this->extractEntry(ArchiveExporter::MEDIA_DIRECTORY.$mediaId, $maxBytes);
    }

    /**
     * @throws InvalidValueException if the entry holds more than $maxBytes
     */
    private function extractEntry(string $name, int $maxBytes): ?string
    {
        if (false === $this->zip->locateName($name)) {
            return null;
        }
        $in = $this->zip->getStream($name);
        $path = tempnam(sys_get_temp_dir(), 'import');
        $out = false === $path ? false : fopen($path, 'wb');
        if (!is_resource($in) || false === $path || !is_resource($out)) {
            throw new RuntimeException('Cannot extract '.$name.' from the archive.');
        }

        $copied = stream_copy_to_stream($in, $out, $maxBytes + 1);
        fclose($in);
        fclose($out);
        if (false === $copied || $copied > $maxBytes) {
            unlink($path);
            throw new InvalidValueException($name.' in the archive is too large.');
        }

        return $path;
    }
}
