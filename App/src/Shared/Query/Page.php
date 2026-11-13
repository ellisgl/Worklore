<?php

declare(strict_types=1);

namespace App\Shared\Query;

use App\Shared\InvalidValueException;

use function sprintf;

/**
 * Which slice of a list to read: page $number (from 1) of $size items.
 */
final readonly class Page
{
    public const int DEFAULT_SIZE = 50;
    public const int MAX_SIZE = 100;

    /**
     * @throws InvalidValueException if the number is below 1 or the size is outside 1..MAX_SIZE
     */
    public function __construct(
        public int $number = 1,
        public int $size = self::DEFAULT_SIZE,
    ) {
        if ($number < 1) {
            throw new InvalidValueException('Page number must be at least 1');
        }
        if ($size < 1 || $size > self::MAX_SIZE) {
            throw new InvalidValueException(sprintf('Page size must be between 1 and %d', self::MAX_SIZE));
        }
    }

    /** How many items come before this page. */
    public function offset(): int
    {
        return ($this->number - 1) * $this->size;
    }
}
