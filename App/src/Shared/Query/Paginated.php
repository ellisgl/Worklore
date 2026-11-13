<?php

declare(strict_types=1);

namespace App\Shared\Query;

/**
 * One page of a list, with what a client needs to fetch the rest. Serialized
 * as {"items": [...], "page": 1, "per_page": 50, "total": 123}.
 *
 * @template T
 */
final readonly class Paginated
{
    public int $page;
    public int $perPage;

    /**
     * @param list<T> $items
     * @param int     $total Items across all pages
     */
    public function __construct(
        public array $items,
        Page $page,
        public int $total,
    ) {
        $this->page = $page->number;
        $this->perPage = $page->size;
    }
}
