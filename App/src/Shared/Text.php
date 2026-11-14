<?php

declare(strict_types=1);

namespace App\Shared;

use function trim;

/**
 * Optional free text (overviews, descriptions): stored trimmed, and blank
 * means none.
 */
final class Text
{
    public static function optional(?string $text): ?string
    {
        return null === $text ? null : (trim($text) ?: null);
    }
}
