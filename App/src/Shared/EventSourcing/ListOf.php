<?php

declare(strict_types=1);

namespace App\Shared\EventSourcing;

use Attribute;

/**
 * Tells EventSerializer what an event's array parameter holds, e.g.
 * #[ListOf(TagId::class)] list<TagId> $tagIds.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class ListOf
{
    /**
     * @param class-string|'string'|'int' $type
     */
    public function __construct(public string $type)
    {
    }
}
