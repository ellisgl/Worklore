<?php

declare(strict_types=1);

namespace App\Shared;

use InvalidArgumentException;
use Stringable;
use Symfony\Component\Uid\Uuid;

/**
 * Base for typed entity ids (CompanyId, TagId, ...). Each wraps a UUIDv7,
 * but they don't mix: a TagId can't be passed where a CompanyId is expected,
 * and equals() is false across types even for the same UUID.
 *
 * Stored in native UUID columns via a matching EntityIdType subclass.
 */
abstract readonly class EntityId implements Stringable
{
    final protected function __construct(public Uuid $uuid)
    {
    }

    public static function generate(): static
    {
        return new static(Uuid::v7());
    }

    /**
     * @throws InvalidArgumentException if $id isn't a valid UUID
     */
    public static function fromString(string $id): static
    {
        return new static(Uuid::fromString($id));
    }

    public function equals(?self $other): bool
    {
        return $other instanceof static
            && $other::class === static::class   // not a subclass either
            && $this->uuid->equals($other->uuid);
    }

    public function toRfc4122(): string
    {
        return $this->uuid->toRfc4122();
    }

    public function __toString(): string
    {
        return $this->toRfc4122();
    }
}
