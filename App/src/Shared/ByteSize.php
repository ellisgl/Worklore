<?php

declare(strict_types=1);

namespace App\Shared;

use function number_format;
use function preg_match;
use function rtrim;
use function sprintf;
use function strtoupper;

/**
 * Sizes as people write them: 512K, 25M, 1G (powers of 1024), or a plain
 * number of bytes.
 */
final class ByteSize
{
    private const array UNITS = ['K' => 1024, 'M' => 1024 ** 2, 'G' => 1024 ** 3, 'T' => 1024 ** 4];

    /**
     * @throws InvalidValueException if $size isn't a size
     */
    public static function parse(string $size): int
    {
        if (1 !== preg_match('/^\s*(\d+)\s*([KMGT]?)B?\s*$/i', $size, $match)) {
            throw new InvalidValueException(sprintf('"%s" is not a size such as 500M or 2G.', $size));
        }
        $unit = strtoupper($match[2]);

        return (int) $match[1] * ('' === $unit ? 1 : self::UNITS[$unit]);
    }

    /**
     * For people: "1.5 GB", "25 MB", "300 bytes".
     */
    public static function format(int $bytes): string
    {
        foreach (['T' => 'TB', 'G' => 'GB', 'M' => 'MB', 'K' => 'KB'] as $unit => $label) {
            if ($bytes >= self::UNITS[$unit]) {
                return rtrim(rtrim(number_format($bytes / self::UNITS[$unit], 1, '.', ''), '0'), '.').' '.$label;
            }
        }

        return 1 === $bytes ? '1 byte' : $bytes.' bytes';
    }
}
