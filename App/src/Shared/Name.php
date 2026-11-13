<?php

declare(strict_types=1);

namespace App\Shared;

use Stringable;

/**
 * A short, required, single-line label: the name or title of a record.
 *
 * Always trimmed, never blank, at most MAX_LENGTH characters (the columns are
 * VARCHAR(255), which counts characters, as mb_strlen() does). Stored via the
 * "name" Doctrine type.
 */
final readonly class Name implements Stringable
{
    public const int MAX_LENGTH = 255;

    private function __construct(public string $value)
    {
    }

    /**
     * @param string $label what the name is, for error messages ("Company name")
     *
     * @throws InvalidValueException if blank or longer than MAX_LENGTH
     */
    public static function of(string $value, string $label = 'Name'): self
    {
        $value = trim($value);
        if ('' === $value) {
            throw new InvalidValueException(\sprintf('%s cannot be blank', $label));
        }
        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw new InvalidValueException(\sprintf('%s cannot be longer than %d characters', $label, self::MAX_LENGTH));
        }

        return new self($value);
    }

    /**
     * The form names are compared in for uniqueness: "Acme" and " ACME "
     * have the same key. Works on raw input, so lookups needn't build a Name.
     */
    public static function keyOf(string $value): string
    {
        return mb_strtolower(trim($value));
    }

    public function key(): string
    {
        return self::keyOf($this->value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
