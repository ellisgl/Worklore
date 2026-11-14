<?php

declare(strict_types=1);

namespace App\Export\Application;

use App\Shared\InvalidValueException;

/**
 * The media files that came with an export (see ExportArchive).
 */
interface ImportedFiles
{
    /**
     * Copies the file for this (exported) media id to a new temporary file
     * and returns its path; the caller deletes it. Null if there is no such
     * file.
     *
     * @throws InvalidValueException if it holds more than $maxBytes
     */
    public function extract(string $mediaId, int $maxBytes): ?string;
}
