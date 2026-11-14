<?php

declare(strict_types=1);

namespace App\Journal;

use App\Shared\InvalidValueException;

use function mb_strlen;
use function mb_substr;
use function preg_match;
use function preg_replace;
use function strrpos;
use function substr;
use function trim;

/**
 * A file attached to a journal entry, as the entry knows it: what it is
 * called, its type (detected from its content), its size and its SHA-256.
 * The bytes themselves are in media storage (see MediaStorage).
 */
final readonly class Media
{
    public const int MAX_FILENAME_LENGTH = 255;

    public string $filename;

    /**
     * @param string $filename the name it was uploaded with; tidied, see filename()
     *
     * @throws InvalidValueException if the size or checksum is impossible
     */
    public function __construct(
        public MediaId $id,
        string $filename,
        public string $mediaType,
        public int $size,
        public string $sha256,
    ) {
        if ($size < 1) {
            throw new InvalidValueException('A file cannot be empty.');
        }
        if (1 !== preg_match('/^[0-9a-f]{64}$/', $sha256)) {
            throw new InvalidValueException('Not a SHA-256 checksum.');
        }
        $this->filename = self::filename($filename);
    }

    /**
     * The last path segment, without control characters, at most 255
     * characters (keeping the extension); "file" if nothing is left.
     */
    public static function filename(string $name): string
    {
        $name = (string) preg_replace('~^.*[/\\\\]~s', '', $name);
        $name = trim((string) preg_replace('/[\p{Cc}\p{Cf}]+/u', '', $name));
        if ('' === $name || '.' === $name || '..' === $name) {
            return 'file';
        }
        if (mb_strlen($name) > self::MAX_FILENAME_LENGTH) {
            $dot = strrpos($name, '.');
            $extension = false === $dot ? '' : substr($name, $dot);
            $extension = mb_strlen($extension) > 20 ? '' : $extension;
            $name = mb_substr($name, 0, self::MAX_FILENAME_LENGTH - mb_strlen($extension)).$extension;
        }

        return $name;
    }
}
